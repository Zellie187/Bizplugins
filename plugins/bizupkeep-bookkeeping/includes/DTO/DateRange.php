<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An inclusive date range used to scope ledger queries and financial
 * statements.
 *
 * $from may be null, meaning "since inception" (used for Balance Sheet
 * point-in-time balances, where every historical posting matters, not
 * just a period's movements).
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class DateRange
{
    public function __construct(
        public ?DateTimeImmutable $from,
        public DateTimeImmutable $to
    ) {
        if ($this->from !== null && $this->from > $this->to) {
            throw new InvalidArgumentException('DateRange "from" cannot be after "to".');
        }
    }

    public static function between(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        return new self($from, $to);
    }

    /**
     * Every posting up to and including $to - used for Balance Sheet
     * point-in-time account balances.
     */
    public static function sinceInception(DateTimeImmutable $to): self
    {
        return new self(null, $to);
    }

    public static function monthToDate(?DateTimeImmutable $reference = null): self
    {
        $reference ??= new DateTimeImmutable();

        return new self(
            new DateTimeImmutable($reference->format('Y-m-01')),
            $reference
        );
    }

    /**
     * The fiscal year containing $asOf, running March 1 - end of
     * February (this business's default financial year for every
     * company, unless a future round adds a per-company override).
     */
    public static function fiscalYearToDate(DateTimeImmutable $asOf): self
    {
        $year = (int) $asOf->format('Y');
        $month = (int) $asOf->format('n');
        $startYear = $month >= 3 ? $year : $year - 1;

        return new self(new DateTimeImmutable(sprintf('%d-03-01', $startYear)), $asOf);
    }
}
