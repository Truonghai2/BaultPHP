<?php

namespace Core\Auth;

class JWT
{
    public static function encode(array $payload, string $secret, string $alg = 'HS256'): string
    {
        $header = ['typ' => 'JWT', 'alg' => $alg];

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload))
        ];

        $signature = self::sign(implode('.', $segments), $secret, $alg);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token, string $secret, array $allowedAlgs = ['HS256']): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode(self::base64UrlDecode($header64), true);
        $payload = json_decode(self::base64UrlDecode($payload64), true);
        $signature = self::base64UrlDecode($signature64);

        $alg = $header['alg'] ?? 'HS256';

        if (!in_array($alg, $allowedAlgs)) {
            return null;
        }

        $valid = self::sign("$header64.$payload64", $secret, $alg) === $signature;
        $expired = isset($payload['exp']) && $payload['exp'] < time();

        return ($valid && !$expired) ? $payload : null;
    }

    protected static function sign(string $input, string $key, string $alg): string
    {
        return hash_hmac(strtolower(str_replace('HS', 'sha', $alg)), $input, $key, true);
    }

    protected static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    protected static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
