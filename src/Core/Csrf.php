<?php

declare(strict_types=1);

namespace App\Core;

/** Synchronizer-token CSRF protection stored in the PHP session. */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \LogicException('Session must be started before using CSRF tokens.');
        }
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function valid(Request $request): bool
    {
        $sent = $request->input('_csrf') ?: $request->header('X-CSRF-Token');
        $expected = $_SESSION[self::KEY] ?? '';
        return is_string($expected) && $expected !== '' && $sent !== '' && hash_equals($expected, $sent);
    }
}
