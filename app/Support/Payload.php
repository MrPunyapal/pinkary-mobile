<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Narrows values read out of untyped API payloads.
 *
 * Every endpoint response arrives as mixed, so reading a field off it yields
 * mixed. Casting mixed directly is what PHPStan rejects at level 9, and at
 * runtime it silently yields "Array" or throws on objects without __toString.
 * These helpers keep the surrounding cast semantics for scalars and fall back
 * to the default for anything that is not representable.
 */
final class Payload
{
    public static function string(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_bool($value) || is_float($value)) {
            return (int) $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if ($value === null) {
            return $default;
        }

        return is_string($value) && is_numeric($value) ? (float) $value : $default;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        return is_scalar($value) ? (bool) $value : $default;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value, array $default = []): array
    {
        return is_array($value) ? $value : $default;
    }
}
