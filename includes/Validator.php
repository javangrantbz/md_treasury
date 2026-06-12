<?php
declare(strict_types=1);

final class Validator
{
    /**
     * Validates a UUID (v4).
     */
    public static function isUuid(?string $uuid): bool
    {
        if ($uuid === null) return false;
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Validates a positive currency amount with 2-decimal precision.
     */
    public static function isCurrency(mixed $amount): bool
    {
        if (!is_numeric($amount)) return false;
        $amount = (float) $amount;
        if ($amount < 0) return false;
        
        // Check precision (at most 2 decimal places)
        return preg_match('/^\d+(\.\d{1,2})?$/', (string) $amount) === 1;
    }

    /**
     * Validates a YYYY-MM-DD date.
     */
    public static function isDate(?string $date): bool
    {
        if ($date === null) return false;
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Sanitizes a string input.
     */
    public static function sanitize(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validates and returns an integer ID.
     */
    public static function toInt(mixed $value): ?int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }
        return null;
    }
}
