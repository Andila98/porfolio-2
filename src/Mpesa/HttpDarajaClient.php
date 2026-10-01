<?php

declare(strict_types=1);

namespace App\Mpesa;

use App\Core\Env;

/** Safaricom Daraja API client (Lipa na M-Pesa Online / STK Push). */
final class HttpDarajaClient implements DarajaClient
{
    private const BASE_URLS = [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ];

    public function __construct(private readonly string $cacheDir)
    {
    }

    public function stkPush(string $phone, int $amount, string $callbackUrl): array
    {
        [$password, $timestamp] = $this->password();
        $isTill = Env::get('MPESA_TYPE', 'paybill') === 'till';
        $response = $this->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->shortcode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $isTill ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $isTill ? (string) Env::get('MPESA_TILL_NUMBER') : $this->shortcode(),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => substr((string) Env::get('MPESA_ACCOUNT_REF', 'BuyMeATea'), 0, 12),
            'TransactionDesc' => 'Buy me a tea',
        ]);

        $data = $response['data'];
        if (($data['ResponseCode'] ?? null) !== '0' || empty($data['CheckoutRequestID'])) {
            $message = $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'STK Push was rejected.';
            throw new DarajaException((string) $message);
        }
        return [
            'merchant_request_id' => (string) $data['MerchantRequestID'],
            'checkout_request_id' => (string) $data['CheckoutRequestID'],
            'raw' => $response['raw'],
        ];
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        [$password, $timestamp] = $this->password();
        $response = $this->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $this->shortcode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
        $data = $response['data'];
        // While the customer has not answered, Daraja returns an errorCode
        // (e.g. 500.001.1001 "The transaction is being processed").
        $code = isset($data['ResultCode']) && is_numeric($data['ResultCode']) ? (int) $data['ResultCode'] : null;
        return [
            'result_code' => $code,
            'result_desc' => (string) ($data['ResultDesc'] ?? $data['errorMessage'] ?? ''),
            'raw' => $response['raw'],
        ];
    }

    public function dueSimulatedCallbacks(): array
    {
        return [];
    }

    private function shortcode(): string
    {
        return (string) Env::get('MPESA_SHORTCODE');
    }

    /** @return array{0: string, 1: string} */
    private function password(): array
    {
        $timestamp = date('YmdHis');
        return [base64_encode($this->shortcode() . Env::get('MPESA_PASSKEY') . $timestamp), $timestamp];
    }

    private function baseUrl(): string
    {
        return self::BASE_URLS[Env::get('MPESA_ENV', 'sandbox')] ?? self::BASE_URLS['sandbox'];
    }

    /** OAuth access token, cached on disk until shortly before it expires. */
    private function token(): string
    {
        $cacheFile = $this->cacheDir . '/daraja-token-' . Env::get('MPESA_ENV', 'sandbox') . '.json';
        $cached = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return (string) $cached['token'];
        }

        $credentials = base64_encode(Env::get('MPESA_CONSUMER_KEY') . ':' . Env::get('MPESA_CONSUMER_SECRET'));
        $response = $this->request('GET', '/oauth/v1/generate?grant_type=client_credentials', null, 'Basic ' . $credentials);
        $token = $response['data']['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new DarajaException('Could not get a Daraja access token. Check the consumer key and secret.');
        }
        $expiresIn = (int) ($response['data']['expires_in'] ?? 3599);
        file_put_contents($cacheFile, json_encode(['token' => $token, 'expires_at' => time() + $expiresIn]), LOCK_EX);
        return $token;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: array<string, mixed>, raw: string}
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body, 'Bearer ' . $this->token());
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{data: array<string, mixed>, raw: string}
     */
    private function request(string $method, string $path, ?array $body, string $authorization): array
    {
        $ch = curl_init($this->baseUrl() . $path);
        $headers = ['Authorization: ' . $authorization, 'Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new DarajaException('Could not reach Safaricom: ' . $error);
        }
        $data = json_decode((string) $raw, true);
        return ['data' => is_array($data) ? $data : [], 'raw' => (string) $raw];
    }
}
