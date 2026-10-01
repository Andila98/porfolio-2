<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    protected function tearDown(): void
    {
        Env::forget('APP_ENV');
        Env::forget('MPESA_ENV');
        Env::set('APP_SECRET', 'test-secret');
    }

    private function production(string $secret, string $mpesa): void
    {
        Env::set('APP_ENV', 'production');
        Env::set('APP_SECRET', $secret);
        Env::set('MPESA_ENV', $mpesa);
    }

    public function testLocalEnvironmentIsNotChecked(): void
    {
        Env::set('APP_SECRET', 'change-me');
        Env::set('MPESA_ENV', 'fake');
        Env::assertProductionConfig();
        $this->addToAssertionCount(1);
    }

    public function testProductionRejectsPlaceholderOrShortSecret(): void
    {
        foreach (['change-me', 'dev-secret', 'short-but-random'] as $secret) {
            $this->production($secret, 'sandbox');
            try {
                Env::assertProductionConfig();
                self::fail("Accepted weak secret {$secret}");
            } catch (RuntimeException $e) {
                self::assertStringContainsString('APP_SECRET', $e->getMessage());
            }
        }
    }

    public function testProductionRejectsFakeOrMissingMpesa(): void
    {
        foreach (['fake', ''] as $mpesa) {
            $this->production(str_repeat('a1', 20), $mpesa);
            try {
                Env::assertProductionConfig();
                self::fail('Accepted MPESA_ENV=' . $mpesa);
            } catch (RuntimeException $e) {
                self::assertStringContainsString('MPESA_ENV', $e->getMessage());
            }
        }
    }

    public function testProductionAcceptsGoodConfig(): void
    {
        foreach (['sandbox', 'production'] as $mpesa) {
            $this->production(bin2hex(random_bytes(32)), $mpesa);
            Env::assertProductionConfig();
        }
        $this->addToAssertionCount(1);
    }

    public function testSecretHasNoSilentFallbackInProduction(): void
    {
        Env::set('APP_ENV', 'production');
        Env::forget('APP_SECRET');
        $this->expectException(RuntimeException::class);
        Env::secret();
    }
}
