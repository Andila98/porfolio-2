<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', static fn () => null);
        $this->router->get('/projects/{slug}', static fn () => null);
        $this->router->post('/api/tips', static fn () => null);
    }

    public function testMatchesParams(): void
    {
        $match = $this->router->match('GET', '/projects/ilado-fms');
        self::assertSame(200, $match['status']);
        self::assertSame(['slug' => 'ilado-fms'], $match['params']);
    }

    public function testParamDoesNotCrossSegments(): void
    {
        self::assertSame(404, $this->router->match('GET', '/projects/a/b')['status']);
    }

    public function testMethodNotAllowed(): void
    {
        $match = $this->router->match('GET', '/api/tips');
        self::assertSame(405, $match['status']);
        self::assertSame(['POST'], $match['allowed']);
    }

    public function testHeadIsServedByGet(): void
    {
        self::assertSame(200, $this->router->match('HEAD', '/')['status']);
    }

    public function testNotFound(): void
    {
        self::assertSame(404, $this->router->match('GET', '/nope')['status']);
    }

    public function testRegexCharactersInPatternsAreLiteral(): void
    {
        $this->router->get('/sitemap.xml', static fn () => null);
        self::assertSame(200, $this->router->match('GET', '/sitemap.xml')['status']);
        self::assertSame(404, $this->router->match('GET', '/sitemapxxml')['status']);
    }
}
