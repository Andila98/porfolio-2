<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $server = [],
        public readonly array $files = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
            (string) file_get_contents('php://input'),
        );
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $this->json()[$key] ?? $this->query[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->jsonBody === null) {
            $decoded = json_decode($this->rawBody, true);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonBody;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept'), 'application/json')
            || str_contains((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json');
    }

    /**
     * The client IP used for rate limits and logs. X-Forwarded-For is only
     * believed when the direct peer is a trusted proxy (TRUSTED_PROXIES:
     * comma-separated IPs or CIDRs), and then the right-most hop that is not
     * itself a trusted proxy wins, since anything left of it is client-supplied.
     */
    public function ip(?string $trustedProxies = null): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = array_values(array_filter(array_map('trim', explode(',', $trustedProxies ?? (string) Env::get('TRUSTED_PROXIES', '')))));
        if ($trusted === [] || !self::ipInRanges($remote, $trusted)) {
            return $remote;
        }
        $hops = array_reverse(array_filter(array_map('trim', explode(',', $this->header('X-Forwarded-For')))));
        foreach ($hops as $hop) {
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                break; // garbage in the chain: stop at the last good hop
            }
            if (!self::ipInRanges($hop, $trusted)) {
                return $hop;
            }
            $remote = $hop;
        }
        return $remote;
    }

    /** @param list<string> $ranges IPs or CIDRs, IPv4 or IPv6 */
    public static function ipInRanges(string $ip, array $ranges): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        foreach ($ranges as $range) {
            [$subnet, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
            $net = @inet_pton($subnet);
            if ($net === false || strlen($net) !== strlen($packed)) {
                continue;
            }
            $bits = $bits === null ? strlen($net) * 8 : (int) $bits;
            $bytes = intdiv($bits, 8);
            if (strncmp($packed, $net, $bytes) !== 0) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($packed[$bytes]) & $mask) === (ord($net[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
    }

    public function isHttps(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || $this->header('X-Forwarded-Proto') === 'https';
    }
}
