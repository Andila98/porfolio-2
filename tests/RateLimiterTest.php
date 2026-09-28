<?php

declare(strict_types=1);

namespace Tests;

use App\Core\RateLimiter;

final class RateLimiterTest extends DatabaseTestCase
{
    public function testBlocksAfterLimitPerKey(): void
    {
        $limiter = new RateLimiter($this->db);
        self::assertTrue($limiter->attempt('contact:1.2.3.4', 2, 3600));
        self::assertTrue($limiter->attempt('contact:1.2.3.4', 2, 3600));
        self::assertFalse($limiter->attempt('contact:1.2.3.4', 2, 3600));
        self::assertTrue($limiter->attempt('contact:5.6.7.8', 2, 3600));
    }
}
