<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Check if an IP address is from a trusted proxy.
 */
class TrustedProxyChecker
{
    private array $trustedProxies;
    private bool $trustPrivateNetworks;
    
    private const PRIVATE_NETWORKS = [
        '127.0.0.0/8',     // Loopback
        '10.0.0.0/8',      // Private network
        '172.16.0.0/12',   // Private network
        '192.168.0.0/16',  // Private network
        '169.254.0.0/16',  // Link-local
        'fc00::/7',        // IPv6 private
        'fe80::/10',       // IPv6 link-local
        '::1/128',         // IPv6 loopback
    ];

    public function __construct(array $config = [])
    {
        $this->trustedProxies = $this->parseProxies($config['proxies'] ?? null);
        $this->trustPrivateNetworks = $config['trust_private_networks'] ?? false;
    }

    /**
     * Check if the given IP is a trusted proxy.
     *
     * @param string $ip
     * @return bool
     */
    public function isTrusted(string $ip): bool
    {
        // Trust all
        if (in_array('*', $this->trustedProxies, true)) {
            return true;
        }

        // Check if IP is in trusted list
        foreach ($this->trustedProxies as $proxy) {
            if ($this->ipMatch($ip, $proxy)) {
                return true;
            }
        }

        // Check private networks if enabled
        if ($this->trustPrivateNetworks && $this->isPrivateNetwork($ip)) {
            return true;
        }

        return false;
    }

    /**
     * Check if IP is in a private network.
     *
     * @param string $ip
     * @return bool
     */
    private function isPrivateNetwork(string $ip): bool
    {
        foreach (self::PRIVATE_NETWORKS as $cidr) {
            if ($this->ipMatch($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP matches a CIDR or specific IP.
     *
     * @param string $ip
     * @param string $range IP or CIDR notation
     * @return bool
     */
    private function ipMatch(string $ip, string $range): bool
    {
        // Exact match
        if ($ip === $range) {
            return true;
        }

        // CIDR notation
        if (str_contains($range, '/')) {
            return $this->cidrMatch($ip, $range);
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range.
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        // IPv6
        if (str_contains($ip, ':')) {
            return $this->ipv6CidrMatch($ip, $subnet, (int) $mask);
        }

        // IPv4
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - (int) $mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IPv6 is within CIDR range.
     *
     * @param string $ip
     * @param string $subnet
     * @param int $mask
     * @return bool
     */
    private function ipv6CidrMatch(string $ip, string $subnet, int $mask): bool
    {
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        $bitsToCheck = $mask;
        
        for ($i = 0; $i < strlen($ipBinary); $i++) {
            if ($bitsToCheck <= 0) {
                break;
            }

            $bits = min(8, $bitsToCheck);
            $maskByte = (0xFF << (8 - $bits)) & 0xFF;

            if ((ord($ipBinary[$i]) & $maskByte) !== (ord($subnetBinary[$i]) & $maskByte)) {
                return false;
            }

            $bitsToCheck -= 8;
        }

        return true;
    }

    /**
     * Parse proxy configuration.
     *
     * @param mixed $proxies
     * @return array
     */
    private function parseProxies($proxies): array
    {
        if ($proxies === null || $proxies === '') {
            return [];
        }

        if ($proxies === '*' || $proxies === '**') {
            return [$proxies];
        }

        if (is_string($proxies)) {
            return array_map('trim', explode(',', $proxies));
        }

        if (is_array($proxies)) {
            return $proxies;
        }

        return [];
    }
}
