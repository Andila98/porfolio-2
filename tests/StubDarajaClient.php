<?php

declare(strict_types=1);

namespace Tests;

use App\Mpesa\DarajaClient;

/** Daraja double whose STK Query answer the test controls. */
final class StubDarajaClient implements DarajaClient
{
    public ?int $queryCode = null;
    private int $n = 0;

    public function stkPush(string $phone, int $amount, string $callbackUrl): array
    {
        $this->n++;
        return ['merchant_request_id' => 'M' . $this->n, 'checkout_request_id' => 'ws_CO_STUB_' . $this->n, 'raw' => '{}'];
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        return ['result_code' => $this->queryCode, 'result_desc' => 'stub ' . var_export($this->queryCode, true), 'raw' => '{}'];
    }

    public function dueSimulatedCallbacks(): array
    {
        return [];
    }
}
