<?php
declare(strict_types=1);
namespace App;

final class Crypto
{
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, Config::key()));
    }
    public static function decrypt(?string $value): string
    {
        if (!$value) { return ''; }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) { throw new \RuntimeException('Invalid encrypted value'); }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), Config::key());
        if ($plain === false) { throw new \RuntimeException('Cannot decrypt profile. Check APP_KEY.'); }
        return $plain;
    }
    public static function lookup(?string $value): ?string { return $value ? hash_hmac('sha256', mb_strtolower(trim($value)), Config::key(), true) : null; }
}

