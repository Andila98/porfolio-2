<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestIpTest extends TestCase
{
    private const PROXIES = '172.16.0.0/12,10.0.0.0/8';

    private function request(string $remote, string $xff = ''): Request
    {
        $server = ['REMOTE_ADDR' => $remote];
        if ($xff !== '') {
            $server['HTTP_X_FORWARDED_FOR'] = $xff;
        }
        return new Request('GET', '/', [], [], $server);
    }

    public function testUntrustedPeerCannotSpoofForwardedFor(): void
    {
        self::assertSame('203.0.113.9', $this->request('203.0.113.9', '1.2.3.4')->ip(self::PROXIES));
        self::assertSame('203.0.113.9', $this->request('203.0.113.9', '1.2.3.4')->ip(''));
    }

    public function testTrustedProxyUsesRightMostUntrustedHop(): void
    {
        // Client forged "6.6.6.6"; Traefik appended the real client 41.90.1.2.
        self::assertSame('41.90.1.2', $this->request('172.18.0.5', '6.6.6.6, 41.90.1.2')->ip(self::PROXIES));
        // Extra internal hop between Traefik and the app.
        self::assertSame('41.90.1.2', $this->request('172.18.0.5', '41.90.1.2, 10.0.0.7')->ip(self::PROXIES));
    }

    public function testTrustedProxyWithoutHeaderFallsBackToPeer(): void
    {
        self::assertSame('172.18.0.5', $this->request('172.18.0.5')->ip(self::PROXIES));
        self::assertSame('172.18.0.5', $this->request('172.18.0.5', 'garbage')->ip(self::PROXIES));
    }

    public function testCidrMatchingIpv4AndIpv6(): void
    {
        self::assertTrue(Request::ipInRanges('172.31.255.255', ['172.16.0.0/12']));
        self::assertFalse(Request::ipInRanges('172.32.0.1', ['172.16.0.0/12']));
        self::assertTrue(Request::ipInRanges('10.1.2.3', ['10.1.2.3']));
        self::assertTrue(Request::ipInRanges('fd00::1', ['fd00::/8']));
        self::assertFalse(Request::ipInRanges('2001:db8::1', ['fd00::/8', '10.0.0.0/8']));
        self::assertFalse(Request::ipInRanges('not-an-ip', ['0.0.0.0/0']));
    }
}
