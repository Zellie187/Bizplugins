<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

use DateTimeImmutable;

/**
 * How often a recurring transaction template comes due.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum RecurringFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';

    /**
     * The next due date after $from, per this frequency.
     */
    public function nextDate(DateTimeImmutable $from): DateTimeImmutable
    {
        return $from->modify(match ($this) {
            self::Weekly => '+1 week',
            self::Monthly => '+1 month',
            self::Quarterly => '+3 months',
            self::Annually => '+1 year',
        });
    }

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Annually => 'Annually',
        };
    }
}
