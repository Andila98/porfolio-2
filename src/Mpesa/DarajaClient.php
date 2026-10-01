<?php

declare(strict_types=1);

namespace App\Mpesa;

interface DarajaClient
{
    /**
     * Sends an STK Push prompt to the customer's phone.
     *
     * @return array{merchant_request_id: string, checkout_request_id: string, raw: string}
     * @throws DarajaException when Safaricom rejects the request
     */
    public function stkPush(string $phone, int $amount, string $callbackUrl): array;

    /**
     * Asks Safaricom for the outcome of an STK Push.
     *
     * @return array{result_code: ?int, result_desc: string, raw: string} result_code null = still processing
     */
    public function stkQuery(string $checkoutRequestId): array;

    /**
     * Callback payloads that are due now. Only the fake client produces these
     * (so the full callback path runs locally); the real client returns [].
     *
     * @return list<array<string, mixed>>
     */
    public function dueSimulatedCallbacks(): array;
}
