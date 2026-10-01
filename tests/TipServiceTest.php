<?php

declare(strict_types=1);

namespace Tests;

use App\Mpesa\DarajaClient;
use App\Mpesa\DarajaException;
use App\Mpesa\FakeDarajaClient;
use App\Tips\Ledger;
use App\Tips\TipException;
use App\Tips\TipService;
use PDOException;

final class TipServiceTest extends DatabaseTestCase
{
    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = sys_get_temp_dir() . '/pf-cache-' . bin2hex(random_bytes(4));
        mkdir($this->cache);
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            array_map('unlink', glob($this->cache . '/*') ?: []);
            @rmdir($this->cache);
        }
    }

    private function service(?DarajaClient $client = null, string $environment = 'fake'): TipService
    {
        return new TipService(new Ledger($this->db), $client ?? new FakeDarajaClient($this->cache, 0), $environment);
    }

    /**
     * Shifts the DB session clock by $seconds (rows cannot be UPDATEd to age them).
     * Positive: NOW() runs ahead, so existing rows look $seconds old to SQL.
     * Negative: rows inserted afterwards get timestamps $seconds in the past.
     */
    private function ageRows(int $seconds): void
    {
        $offset = (new \DateTime())->getOffset() + $seconds;
        $sign = $offset < 0 ? '-' : '+';
        $this->db->exec(sprintf("SET time_zone = '%s%02d:%02d'", $sign, intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60)));
    }

    public function testSuccessfulTipIsRecordedAndSettledOnce(): void
    {
        $tips = $this->service();
        $tipId = $tips->start('0712 345 678', '100', 'https://example.com/cb');

        $status = $tips->status($tipId); // delivers the simulated callback
        self::assertSame('completed', $status['status']);
        self::assertSame(100, $status['amount']);
        self::assertStringStartsWith('FAKE', (string) $status['receipt']);

        $events = array_column((new Ledger($this->db))->events($tipId), 'event');
        self::assertSame(['REQUESTED', 'SENT', 'COMPLETED'], $events);
    }

    public function testDuplicateCallbackIsLoggedButNotCountedTwice(): void
    {
        $tips = $this->service(null, 'production');
        $tipId = $tips->start('0712345678', 250, 'https://example.com/cb');
        $tips->status($tipId);
        $checkout = (new Ledger($this->db))->latest($tipId)['checkout_request_id'];

        $payload = ['Body' => ['stkCallback' => ['CheckoutRequestID' => $checkout, 'ResultCode' => 1032, 'ResultDesc' => 'late']]];
        self::assertSame('duplicate', $tips->handleCallback($payload));

        $events = array_column((new Ledger($this->db))->events($tipId), 'event');
        self::assertSame(['REQUESTED', 'SENT', 'COMPLETED', 'DUPLICATE_CALLBACK'], $events);
        self::assertSame('completed', $tips->status($tipId)['status']);
        self::assertSame([['month' => date('Y-m'), 'tips' => 1, 'total' => '250']], array_map(
            static fn (array $r): array => ['month' => $r['month'], 'tips' => (int) $r['tips'], 'total' => (string) $r['total']],
            (new Ledger($this->db))->monthlyTotals(),
        ));
    }

    public function testCancelledAndFailedResults(): void
    {
        $tips = $this->service();
        $cancelled = $tips->start('0712345678', 13, 'x');
        $failed = $tips->start('0712345678', 14, 'x');
        self::assertSame('cancelled', $tips->status($cancelled)['status']);
        self::assertSame('failed', $tips->status($failed)['status']);
    }

    public function testDarajaErrorRecordsFailure(): void
    {
        $broken = new class implements DarajaClient {
            public function stkPush(string $phone, int $amount, string $callbackUrl): array
            {
                throw new DarajaException('Invalid credentials');
            }
            public function stkQuery(string $checkoutRequestId): array
            {
                return ['result_code' => null, 'result_desc' => '', 'raw' => ''];
            }
            public function dueSimulatedCallbacks(): array
            {
                return [];
            }
        };
        try {
            $this->service($broken)->start('0712345678', 50, 'x');
            self::fail('Expected TipException');
        } catch (TipException) {
        }
        $events = $this->db->query('SELECT event, result_desc FROM tip_ledger ORDER BY id')->fetchAll();
        self::assertSame(['REQUESTED', 'FAILED'], array_column($events, 'event'));
        self::assertSame('Invalid credentials', $events[1]['result_desc']);
    }

    public function testValidationHappensBeforeAnythingIsWritten(): void
    {
        foreach ([['12345', 100], ['0712345678', 5], ['0712345678', 20000], ['0712345678', '10.5']] as [$phone, $amount]) {
            try {
                $this->service()->start($phone, $amount, 'x');
                self::fail('Expected TipException');
            } catch (TipException) {
            }
        }
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM tip_ledger')->fetchColumn());
    }

    public function testUnknownCheckoutIsIgnored(): void
    {
        self::assertSame('unknown', $this->service()->handleCallback(['Body' => ['stkCallback' => ['CheckoutRequestID' => 'nope', 'ResultCode' => 0]]]));
        self::assertSame('unknown', $this->service()->handleCallback([]));
    }

    public function testReconcileSettlesTipsWithoutCallback(): void
    {
        $client = new FakeDarajaClient($this->cache, 0);
        $tips = $this->service($client);
        $tipId = $tips->start('0712345678', 100, 'x');
        $this->ageRows(3600);

        self::assertSame(1, $tips->reconcile(60));
        $events = (new Ledger($this->db))->events($tipId);
        self::assertSame('COMPLETED', end($events)['event']);
        self::assertSame('reconcile', end($events)['source']);
        self::assertSame(0, $tips->reconcile(60));
    }

    public function testLedgerIsAppendOnly(): void
    {
        $tipId = $this->service()->start('0712345678', 100, 'x');
        try {
            $this->db->exec("UPDATE tip_ledger SET amount = 1 WHERE tip_id = '{$tipId}'");
            self::fail('UPDATE should be rejected');
        } catch (PDOException $e) {
            self::assertStringContainsString('append-only', $e->getMessage());
        }
        try {
            $this->db->exec("DELETE FROM tip_ledger WHERE tip_id = '{$tipId}'");
            self::fail('DELETE should be rejected');
        } catch (PDOException $e) {
            self::assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function testReceiptSurvivesReconcileBeforeLateCallback(): void
    {
        $stub = new StubDarajaClient();
        $tips = $this->service($stub, 'production');
        $tipId = $tips->start('0712345678', 100, 'x');
        $this->ageRows(3600);

        $stub->queryCode = 0; // STK Query: paid, but it carries no receipt
        self::assertSame(1, $tips->reconcile(60));
        self::assertNull($tips->status($tipId)['receipt']);

        // Safaricom's real callback arrives afterwards with the receipt.
        $late = ['Body' => ['stkCallback' => ['CheckoutRequestID' => 'ws_CO_STUB_1', 'ResultCode' => 0, 'ResultDesc' => 'ok',
            'CallbackMetadata' => ['Item' => [['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT123']]]]]];
        self::assertSame('duplicate', $tips->handleCallback($late));

        $status = $tips->status($tipId);
        self::assertSame('completed', $status['status']);
        self::assertSame('RCPT123', $status['receipt']);
        $admin = (new Ledger($this->db))->tips();
        self::assertSame('COMPLETED', $admin[0]['final_event']);
        self::assertSame('RCPT123', $admin[0]['receipt']);
    }

    public function testReconcileLeavesStillProcessingCodesPending(): void
    {
        $stub = new StubDarajaClient();
        $tips = $this->service($stub);
        $tipId = $tips->start('0712345678', 100, 'x');
        $this->ageRows(3600);

        foreach ([4999, 500, null] as $code) {
            $stub->queryCode = $code;
            self::assertSame(0, $tips->reconcile(60));
        }
        self::assertSame('pending', $tips->status($tipId)['status']);

        // The real success callback still settles it.
        $tips->handleCallback(['Body' => ['stkCallback' => ['CheckoutRequestID' => 'ws_CO_STUB_1', 'ResultCode' => 0, 'ResultDesc' => 'ok']]]);
        self::assertSame('completed', $tips->status($tipId)['status']);
    }

    public function testReconcileCountsOnlyRealSettles(): void
    {
        $stub = new StubDarajaClient();
        $tips = $this->service($stub);
        $tips->start('0712345678', 100, 'x');
        $tips->start('0712345678', 200, 'x');
        $this->ageRows(3600);
        // A callback settles the second tip between the pending query and the settle.
        $tips->handleCallback(['Body' => ['stkCallback' => ['CheckoutRequestID' => 'ws_CO_STUB_2', 'ResultCode' => 0, 'ResultDesc' => 'ok']]]);
        $stub->queryCode = 0;
        self::assertSame(1, $tips->reconcile(60));
    }

    public function testRequestStuckBeforeSentReadsAsFailed(): void
    {
        $ledger = new Ledger($this->db);
        $row = ['tip_id' => str_repeat('b', 32), 'event' => 'REQUESTED', 'amount' => 50, 'phone_hash' => str_repeat('0', 64),
            'phone_last3' => '678', 'source' => 'app', 'environment' => 'fake'];
        $ledger->append($row);
        self::assertSame('pending', $this->service()->status(str_repeat('b', 32))['status']);

        $this->ageRows(-(TipService::STUCK_REQUEST_SECONDS + 60));
        $ledger->append(['tip_id' => str_repeat('c', 32)] + $row);
        $this->db->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        self::assertSame('failed', $this->service()->status(str_repeat('c', 32))['status']);
    }

    public function testEnvironmentIsStampedAndOnlyProductionCounts(): void
    {
        foreach (['sandbox', 'production', 'fake'] as $env) {
            $tips = $this->service(null, $env);
            $tips->status($tips->start('0712345678', 100, 'x'));
        }
        $envs = $this->db->query("SELECT DISTINCT environment FROM tip_ledger WHERE event = 'COMPLETED' ORDER BY environment")->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['fake', 'production', 'sandbox'], $envs);

        $totals = (new Ledger($this->db))->monthlyTotals();
        self::assertCount(1, $totals);
        self::assertSame(1, (int) $totals[0]['tips']);
        self::assertSame(1, (int) (new Ledger($this->db))->monthlyTotals('sandbox')[0]['tips']);
    }

    public function testCallbackTokenDependsOnSecret(): void
    {
        self::assertSame(40, strlen(TipService::callbackToken()));
        self::assertSame(TipService::callbackToken(), TipService::callbackToken());
    }
}
