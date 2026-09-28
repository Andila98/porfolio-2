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

    public function ip(): string
    {
        // Behind Traefik the client IP arrives in X-Forwarded-For; the container is
        // not publicly reachable, so the first hop is trustworthy there.
        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded !== '' && Env::get('APP_ENV') === 'production') {
            return trim(explode(',', $forwarded)[0]);
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || $this->header('X-Forwarded-Proto') === 'https';
    }
}
