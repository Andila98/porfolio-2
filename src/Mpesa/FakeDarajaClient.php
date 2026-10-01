<?php

declare(strict_types=1);

namespace App\Mpesa;

/**
 * Offline stand-in for Daraja (MPESA_ENV=fake). An STK Push "succeeds" and a
 * matching callback becomes due a few seconds later, so the real callback
 * handler runs end to end on a laptop with no credentials.
 *
 * Test amounts: 13 → customer cancels, 14 → payment fails, anything else → paid.
 */
final class FakeDarajaClient implements DarajaClient
{
    private const DELAY_SECONDS = 5;

    public function __construct(private readonly string $cacheDir, private readonly int $delay = self::DELAY_SECONDS)
    {
    }

    public function stkPush(string $phone, int $amount, string $callbackUrl): array
    {
        $checkoutId = 'ws_CO_FAKE_' . strtoupper(bin2hex(random_bytes(6)));
        $merchantId = 'FAKE-' . strtoupper(bin2hex(random_bytes(4)));

        [$code, $desc] = match ($amount) {
            13 => [1032, 'Request cancelled by user'],
            14 => [1, 'The balance is insufficient for the transaction.'],
            default => [0, 'The service request is processed successfully.'],
        };
        $callback = ['Body' => ['stkCallback' => [
            'MerchantRequestID' => $merchantId,
            'CheckoutRequestID' => $checkoutId,
            'ResultCode' => $code,
            'ResultDesc' => $desc,
        ]]];
        if ($code === 0) {
            $callback['Body']['stkCallback']['CallbackMetadata'] = ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'FAKE' . strtoupper(bin2hex(random_bytes(3)))],
                ['Name' => 'TransactionDate', 'Value' => (int) date('YmdHis')],
                ['Name' => 'PhoneNumber', 'Value' => (int) $phone],
            ]];
        }
        $this->store($checkoutId, ['due_at' => time() + $this->delay, 'payload' => $callback]);

        return [
            'merchant_request_id' => $merchantId,
            'checkout_request_id' => $checkoutId,
            'raw' => (string) json_encode(['ResponseCode' => '0', 'CheckoutRequestID' => $checkoutId, 'fake' => true]),
        ];
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        $entry = $this->load($checkoutRequestId);
        if ($entry === null || $entry['due_at'] > time()) {
            return ['result_code' => null, 'result_desc' => 'The transaction is being processed', 'raw' => '{}'];
        }
        $cb = $entry['payload']['Body']['stkCallback'];
        return ['result_code' => (int) $cb['ResultCode'], 'result_desc' => (string) $cb['ResultDesc'], 'raw' => (string) json_encode($cb)];
    }

    public function dueSimulatedCallbacks(): array
    {
        $due = [];
        foreach (glob($this->cacheDir . '/fake-stk-*.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true);
            if (is_array($entry) && $entry['due_at'] <= time()) {
                $due[] = $entry['payload'];
                @unlink($file);
            }
        }
        return $due;
    }

    /** @param array<string, mixed> $entry */
    private function store(string $checkoutId, array $entry): void
    {
        file_put_contents($this->file($checkoutId), json_encode($entry), LOCK_EX);
    }

    /** @return array<string, mixed>|null */
    private function load(string $checkoutId): ?array
    {
        $file = $this->file($checkoutId);
        $entry = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($entry) ? $entry : null;
    }

    private function file(string $checkoutId): string
    {
        return $this->cacheDir . '/fake-stk-' . preg_replace('/[^A-Za-z0-9_]/', '', $checkoutId) . '.json';
    }
}
