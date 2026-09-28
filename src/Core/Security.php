<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    private static ?string $nonce = null;

    /** Per-request nonce for the one inline script (theme bootstrap). */
    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public static function startSession(bool $https): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('pf_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function apply(Response $response, bool $https): Response
    {
        $nonce = self::nonce();
        $defaults = [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "object-src 'none'",
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        if ($https) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($defaults as $name => $value) {
            $response->headers[$name] ??= $value;
        }
        return $response;
    }

    /** Stable, non-reversible identifier for an IP or phone (privacy in logs). */
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) Env::get('APP_SECRET', 'dev-secret'));
    }
}
