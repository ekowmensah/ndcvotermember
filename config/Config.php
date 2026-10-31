<?php
declare(strict_types=1);
namespace App;

final class Config
{
    private static array $values = [];
    public static function load(string $path): void
    {
        if (!is_file($path)) { return; }
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) { continue; }
            [$name, $value] = explode('=', $line, 2);
            self::$values[trim($name)] = trim(trim($value), "\"'");
        }
    }
    public static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        return $env !== false ? $env : (self::$values[$key] ?? $default);
    }
    public static function bool(string $key): bool { return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN); }
    public static function baseUrl(): string
    {
        $url = rtrim(trim(self::get('APP_URL')), '/');
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new \RuntimeException('Set APP_URL to the application root, for example http://localhost/ndcvotermember.');
        }
        return $url;
    }
    public static function basePath(): string
    {
        $path = trim((string)parse_url(self::baseUrl(), PHP_URL_PATH), '/');
        return $path === '' ? '/' : '/' . $path . '/';
    }
    public static function key(): string
    {
        $key = base64_decode(self::get('APP_KEY'), true);
        if ($key === false || strlen($key) !== 32) { throw new \RuntimeException('APP_KEY must be a base64-encoded 32-byte key. Run the installer.'); }
        return $key;
    }
}

