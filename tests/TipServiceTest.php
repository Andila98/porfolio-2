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

    private function service(?DarajaClient $client = null): TipService
    {
        return new TipService(new Ledger($this->db), $client ?? new FakeDarajaClient($this->cache, 0));
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
        $tips = $this->service();
        $tipId = $tips->start('0712345678', 250, 'https://example.com/cb');
        $tips->status($tipId);
        $checkout = (new Ledger($this->db))->latest($tipId)['checkout_request_id'];

        $payload = ['Body' => ['stkCallback' => ['CheckoutRequestID' => $checkout, 'ResultCode' => 1032, 'ResultDesc' => 'late']]];
        self::assertTrue($tips->handleCallback($payload));

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
        self::assertFalse($this->service()->handleCallback(['Body' => ['stkCallback' => ['CheckoutRequestID' => 'nope', 'ResultCode' => 0]]]));
        self::assertFalse($this->service()->handleCallback([]));
    }

    public function testReconcileSettlesTipsWithoutCallback(): void
    {
        $client = new FakeDarajaClient($this->cache, 0);
        $tips = $this->service($client);
        $tipId = $tips->start('0712345678', 100, 'x');
        // Age the pending tip past the reconcile threshold (only INSERTs are allowed, so shift the clock instead).
        $this->db->exec("SET time_zone = '+10:00'");

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

    public function testCallbackTokenDependsOnSecret(): void
    {
        self::assertSame(40, strlen(TipService::callbackToken()));
        self::assertSame(TipService::callbackToken(), TipService::callbackToken());
    }
}
