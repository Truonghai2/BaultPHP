<?php

namespace Tests\Unit\Http;

use Core\Http\TrustedProxyChecker;
use PHPUnit\Framework\TestCase;

class TrustedProxyCheckerTest extends TestCase
{
    public function test_trusts_all_when_wildcard_configured(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => '*']);

        $this->assertTrue($checker->isTrusted('192.168.1.1'));
        $this->assertTrue($checker->isTrusted('10.0.0.1'));
        $this->assertTrue($checker->isTrusted('8.8.8.8'));
    }

    public function test_trusts_specific_ip(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => ['192.168.1.1']]);

        $this->assertTrue($checker->isTrusted('192.168.1.1'));
        $this->assertFalse($checker->isTrusted('192.168.1.2'));
    }

    public function test_trusts_cidr_range(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => ['192.168.1.0/24']]);

        $this->assertTrue($checker->isTrusted('192.168.1.1'));
        $this->assertTrue($checker->isTrusted('192.168.1.100'));
        $this->assertTrue($checker->isTrusted('192.168.1.255'));
        $this->assertFalse($checker->isTrusted('192.168.2.1'));
    }

    public function test_trusts_private_networks_when_enabled(): void
    {
        $checker = new TrustedProxyChecker([
            'proxies' => [],
            'trust_private_networks' => true,
        ]);

        $this->assertTrue($checker->isTrusted('192.168.1.1'));
        $this->assertTrue($checker->isTrusted('10.0.0.1'));
        $this->assertTrue($checker->isTrusted('172.16.0.1'));
        $this->assertTrue($checker->isTrusted('127.0.0.1'));
        $this->assertFalse($checker->isTrusted('8.8.8.8'));
    }

    public function test_does_not_trust_private_networks_when_disabled(): void
    {
        $checker = new TrustedProxyChecker([
            'proxies' => [],
            'trust_private_networks' => false,
        ]);

        $this->assertFalse($checker->isTrusted('192.168.1.1'));
        $this->assertFalse($checker->isTrusted('10.0.0.1'));
    }

    public function test_parses_comma_separated_proxies(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => '192.168.1.1, 10.0.0.1']);

        $this->assertTrue($checker->isTrusted('192.168.1.1'));
        $this->assertTrue($checker->isTrusted('10.0.0.1'));
        $this->assertFalse($checker->isTrusted('172.16.0.1'));
    }

    public function test_handles_ipv6_addresses(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => ['::1', 'fe80::/10']]);

        $this->assertTrue($checker->isTrusted('::1'));
        $this->assertTrue($checker->isTrusted('fe80::1'));
        $this->assertFalse($checker->isTrusted('2001:db8::1'));
    }

    public function test_returns_false_for_empty_proxy_list(): void
    {
        $checker = new TrustedProxyChecker(['proxies' => []]);

        $this->assertFalse($checker->isTrusted('192.168.1.1'));
        $this->assertFalse($checker->isTrusted('10.0.0.1'));
    }
}
