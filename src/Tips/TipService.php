<?php

declare(strict_types=1);

namespace App\Tips;

use App\Core\Env;
use App\Core\Security;
use App\Mpesa\DarajaClient;
use App\Mpesa\DarajaException;

/**
 * Buy Me a Tea: validates the request, records every step in the append-only
 * ledger, talks to Daraja, and settles each tip exactly once.
 */
final class TipService
{
    public const MIN_AMOUNT = 10;
    public const MAX_AMOUNT = 10000;
    public const PRESETS = [50, 100, 250];

    /** A tip with no SENT row after this long never reached Safaricom. */
    public const STUCK_REQUEST_SECONDS = 120;

    /**
     * STK Query result codes that are a final outcome. Anything else (for
     * example 4999, "still under processing") leaves the tip pending, so a
     * reconcile can never lock in FAILED before the real callback arrives.
     * 0 paid · 1 insufficient balance · 1032 cancelled by user ·
     * 1037 phone unreachable · 1019 transaction expired · 2001 wrong PIN
     */
    public const FINAL_QUERY_CODES = [0, 1, 1032, 1037, 1019, 2001];

    public function __construct(
        private readonly Ledger $ledger,
        private readonly DarajaClient $daraja,
        /** MPESA_ENV, stamped on every ledger row: only "production" is real money. */
        private readonly string $environment = 'fake',
    ) {
    }

    public static function validateAmount(string|int $raw): int
    {
        $raw = trim((string) $raw);
        if (!ctype_digit($raw)) {
            throw new TipException('Enter a whole amount in KES.');
        }
        $amount = (int) $raw;
        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT) {
            throw new TipException(sprintf('Amount must be between KES %d and KES %s.', self::MIN_AMOUNT, number_format(self::MAX_AMOUNT)));
        }
        return $amount;
    }

    /** Starts an STK Push and returns the public tip id used for polling. */
    public function start(string $rawPhone, string|int $rawAmount, string $callbackUrl): string
    {
        $phone = PhoneNumber::normalize($rawPhone);
        if ($phone === null) {
            throw new TipException('Enter a Safaricom number like 0712 345 678.');
        }
        $amount = self::validateAmount($rawAmount);

        $tipId = bin2hex(random_bytes(16));
        $base = [
            'tip_id' => $tipId,
            'amount' => $amount,
            'phone_hash' => Security::hash($phone),
            'phone_last3' => PhoneNumber::last3($phone),
            'source' => 'app',
            'environment' => $this->environment,
        ];

        // Written before the network call, so even a crash mid-request leaves a record.
        $this->ledger->append($base + ['event' => 'REQUESTED']);

        try {
            $result = $this->daraja->stkPush($phone, $amount, $callbackUrl);
        } catch (DarajaException $e) {
            $this->ledger->append($base + [
                'event' => 'FAILED',
                'result_desc' => mb_substr($e->getMessage(), 0, 255),
                'dedupe_key' => $tipId . ':final',
            ]);
            throw new TipException('M-Pesa could not send the payment prompt. Please try again in a moment.', 0, $e);
        }

        $this->ledger->append($base + [
            'event' => 'SENT',
            'merchant_request_id' => $result['merchant_request_id'],
            'checkout_request_id' => $result['checkout_request_id'],
            'raw_payload' => $result['raw'],
        ]);
        return $tipId;
    }

    /**
     * Handles a Daraja STK callback. Unknown checkouts are ignored; a second
     * callback for an already-settled tip is recorded as DUPLICATE_CALLBACK
     * and never counted twice.
     *
     * @param array<string, mixed> $payload
     * @return 'settled'|'duplicate'|'unknown'
     */
    public function handleCallback(array $payload, string $source = 'callback'): string
    {
        $cb = $payload['Body']['stkCallback'] ?? null;
        if (!is_array($cb) || empty($cb['CheckoutRequestID'])) {
            return 'unknown';
        }
        $checkoutId = (string) $cb['CheckoutRequestID'];
        $previous = $this->ledger->findByCheckout($checkoutId);
        if ($previous === null) {
            return 'unknown';
        }

        $code = (int) ($cb['ResultCode'] ?? -1);
        $meta = [];
        foreach ($cb['CallbackMetadata']['Item'] ?? [] as $item) {
            if (isset($item['Name'])) {
                $meta[$item['Name']] = $item['Value'] ?? null;
            }
        }

        $row = [
            'tip_id' => $previous['tip_id'],
            'amount' => $previous['amount'],
            'phone_hash' => $previous['phone_hash'],
            'phone_last3' => $previous['phone_last3'],
            'merchant_request_id' => $cb['MerchantRequestID'] ?? $previous['merchant_request_id'],
            'checkout_request_id' => $checkoutId,
            'mpesa_receipt' => isset($meta['MpesaReceiptNumber']) ? (string) $meta['MpesaReceiptNumber'] : null,
            'result_code' => $code,
            'result_desc' => mb_substr((string) ($cb['ResultDesc'] ?? ''), 0, 255),
            'source' => $source,
            'environment' => $previous['environment'],
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ];

        $settled = $this->ledger->append($row + [
            'event' => self::eventFor($code),
            'dedupe_key' => $checkoutId . ':final',
        ]);
        if ($settled) {
            return 'settled';
        }
        if ($source === 'callback') {
            $this->ledger->append($row + ['event' => 'DUPLICATE_CALLBACK']);
        }
        return 'duplicate';
    }

    /**
     * Current public status of a tip.
     *
     * @return array{status: string, amount: int, receipt: ?string, message: string}|null
     */
    public function status(string $tipId): ?array
    {
        // In fake mode this is where simulated callbacks get delivered.
        foreach ($this->daraja->dueSimulatedCallbacks() as $payload) {
            $this->handleCallback($payload);
        }
        $events = $this->ledger->events($tipId);
        if ($events === []) {
            return null;
        }
        // A settled tip's status is its final event; later DUPLICATE_CALLBACK rows never change it.
        $current = end($events);
        foreach ($events as $row) {
            if (in_array($row['event'], Ledger::FINAL_EVENTS, true)) {
                $current = $row;
                break;
            }
        }
        $event = $current['event'];
        // Crashed between REQUESTED and SENT: Safaricom never got the request.
        if ($event === 'REQUESTED' && time() - (int) strtotime((string) $current['created_at']) > self::STUCK_REQUEST_SECONDS) {
            $event = 'FAILED';
        }
        [$status, $message] = match ($event) {
            'COMPLETED' => ['completed', 'Asante! Your tea has been received.'],
            'CANCELLED' => ['cancelled', 'The payment was cancelled on your phone.'],
            'FAILED' => ['failed', 'The payment did not go through.'],
            default => ['pending', 'Check your phone and enter your M-Pesa PIN.'],
        };
        return [
            'status' => $status,
            'amount' => (int) $current['amount'],
            'receipt' => $event === 'COMPLETED' ? $this->ledger->receipt($tipId) : null,
            'message' => $message,
        ];
    }

    /** Settles tips that never got a callback by asking Daraja directly. Returns how many were settled. */
    public function reconcile(int $olderThanSeconds = 300): int
    {
        $settled = 0;
        foreach ($this->ledger->pendingOlderThan($olderThanSeconds) as $pending) {
            $result = $this->daraja->stkQuery((string) $pending['checkout_request_id']);
            if (!in_array($result['result_code'], self::FINAL_QUERY_CODES, true)) {
                continue; // still processing or unknown: try again next run
            }
            $outcome = $this->handleCallback(['Body' => ['stkCallback' => [
                'MerchantRequestID' => $pending['merchant_request_id'],
                'CheckoutRequestID' => $pending['checkout_request_id'],
                'ResultCode' => $result['result_code'],
                'ResultDesc' => $result['result_desc'],
            ]]], 'reconcile');
            if ($outcome === 'settled') {
                $settled++;
            }
        }
        return $settled;
    }

    /** Secret path segment that authenticates Daraja callbacks. */
    public static function callbackToken(): string
    {
        return substr(hash_hmac('sha256', 'mpesa-callback', Env::secret()), 0, 40);
    }

    public static function eventFor(int $resultCode): string
    {
        return match ($resultCode) {
            0 => 'COMPLETED',
            1032 => 'CANCELLED',
            default => 'FAILED',
        };
    }
}
