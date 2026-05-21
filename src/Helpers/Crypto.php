<?php

declare(strict_types=1);

namespace App\Helpers;

class Crypto
{
    private static function key(): string
    {
        $key = $_ENV['APP_KEY'] ?? 'cofre-digital-default-key-32char!!';
        return substr(hash('sha256', $key, true), 0, 32);
    }

    public static function encrypt(string $plaintext): string
    {
        $iv         = random_bytes(16);
        $ciphertext = \openssl_encrypt($plaintext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        $hmac       = hash_hmac('sha256', $ciphertext, self::key(), true);

        return base64_encode($iv . $hmac . $ciphertext);
    }

    public static function decrypt(string $encoded): string|false
    {
        $raw        = base64_decode($encoded);
        $iv         = substr($raw, 0, 16);
        $hmac       = substr($raw, 16, 32);
        $ciphertext = substr($raw, 48);

        $expected = hash_hmac('sha256', $ciphertext, self::key(), true);
        if (!hash_equals($expected, $hmac)) {
            return false; // Integridade comprometida
        }

        return \openssl_decrypt($ciphertext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
    }
}
