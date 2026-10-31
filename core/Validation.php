<?php
declare(strict_types=1);
namespace App;

final class Validation
{
    public static function text(array $data, string $name, int $max = 200, bool $required = true): ?string
    {
        $value = $data[$name] ?? '';
        if (!is_scalar($value)) { throw new \InvalidArgumentException('Invalid ' . label($name)); }
        $value = trim((string)$value);
        if (($required && $value === '') || mb_strlen($value) > $max) { throw new \InvalidArgumentException(label($name) . ' is required and must be at most ' . $max . ' characters.'); }
        return $value === '' ? null : $value;
    }
    public static function id(array $data, string $name): int
    {
        $value = filter_var($data[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$value) { throw new \InvalidArgumentException('Choose a valid ' . label($name)); }
        return $value;
    }
    public static function choice(array $data, string $name, array $values): string
    {
        $value = self::text($data, $name, 60);
        if (!in_array($value, $values, true)) { throw new \InvalidArgumentException('Choose a valid ' . label($name)); }
        return $value;
    }
    public static function date(array $data, string $name, bool $required = false): ?string
    {
        $value = self::text($data, $name, 10, $required);
        if ($value === null) { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value > date('Y-m-d')) { throw new \InvalidArgumentException(label($name) . ' must be a valid date, no later than today.'); }
        return $value;
    }
    public static function email(array $data, string $name, bool $required = true): ?string
    {
        $value = self::text($data, $name, 190, $required);
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new \InvalidArgumentException('Enter a valid email address.'); }
        return $value === null ? null : mb_strtolower($value);
    }
}
