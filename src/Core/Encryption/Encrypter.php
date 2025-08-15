<?php

namespace Core\Encryption;

use RuntimeException;

/**
 * Provides strong, authenticated encryption (AES-256-GCM) for data like cookies.
 */
class Encrypter
{
    protected string $key;
    protected string $cipher = 'AES-256-GCM';

    public function __construct(string $key)
    {
        if (static::supported($key, $this->cipher)) {
            $this->key = $key;
        } else {
            throw new RuntimeException('The only supported ciphers are AES-128-GCM and AES-256-GCM with the correct key lengths.');
        }
    }

    /**
     * Determine if the given key and cipher combination is supported.
     */
    public static function supported(string $key, string $cipher): bool
    {
        $length = mb_strlen($key, '8bit');
        return ($cipher === 'AES-128-GCM' && $length === 16) ||
               ($cipher === 'AES-256-GCM' && $length === 32);
    }

    /**
     * Encrypt the given value.
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            $this->cipher,
            $this->key,
            0,
            $iv,
            $tag,
        );

        if ($value === false) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        $json = json_encode([
            'iv' => base64_encode($iv),
            'value' => $value,
            'tag' => base64_encode($tag),
        ]);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not create JSON payload.');
        }

        return base64_encode($json);
    }

    /**
     * Decrypt the given value.
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = json_decode(base64_decode($payload), true);

        if (!$this->isValidPayload($payload)) {
            throw new RuntimeException('The payload is invalid.');
        }

        $decrypted = \openssl_decrypt(
            $payload['value'],
            $this->cipher,
            $this->key,
            0,
            base64_decode($payload['iv']),
            base64_decode($payload['tag']),
        );

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    protected function isValidPayload(mixed $payload): bool
    {
        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['tag']);
    }
}
