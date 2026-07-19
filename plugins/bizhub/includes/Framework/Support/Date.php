<?php

declare(strict_types=1);

namespace BizHub\Framework\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Date helper utilities.
 *
 * @package BizHub\Framework\Support
 */
final class Date
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Return the current date and time.
     */
    public static function now(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $timezone);
    }

    /**
     * Parse a date string into a DateTimeImmutable instance.
     */
    public static function parse(string $value, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return new DateTimeImmutable($value, $timezone);
    }

    /**
     * Format a date using the given format string.
     */
    public static function format(DateTimeImmutable $date, string $format = 'Y-m-d'): string
    {
        return $date->format($format);
    }

    /**
     * Determine whether a date is in the past.
     */
    public static function isPast(DateTimeImmutable $date): bool
    {
        return $date < self::now();
    }

    /**
     * Determine whether a date is in the future.
     */
    public static function isFuture(DateTimeImmutable $date): bool
    {
        return $date > self::now();
    }

    /**
     * Return the number of whole days between two dates.
     */
    public static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->format('%r%a');
    }
}
