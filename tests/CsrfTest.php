<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Csrf;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testTokenIsStableWithinSession(): void
    {
        self::assertSame(Csrf::token(), Csrf::token());
        self::assertSame(64, strlen(Csrf::token()));
    }

    public function testAcceptsFormFieldAndHeader(): void
    {
        $token = Csrf::token();
        self::assertTrue(Csrf::valid(new Request('POST', '/contact', [], ['_csrf' => $token])));
        self::assertTrue(Csrf::valid(new Request('POST', '/api/tips', [], [], ['HTTP_X_CSRF_TOKEN' => $token])));
    }

    public function testRejectsMissingOrWrongToken(): void
    {
        Csrf::token();
        self::assertFalse(Csrf::valid(new Request('POST', '/contact')));
        self::assertFalse(Csrf::valid(new Request('POST', '/contact', [], ['_csrf' => 'forged'])));
    }
}
