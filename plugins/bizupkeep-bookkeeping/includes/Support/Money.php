<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Support;

use Stringable;

/**
 * Immutable monetary value, stored as integer minor units (cents).
 *
 * No Money value object existed anywhere in the BizUpKeep codebase
 * before this plugin - prior money handling (e.g. Workflow's
 * quote_amount) was a raw float in a JSON blob, which cannot reliably
 * guarantee the debit=credit balance checks a double-entry ledger
 * depends on. This class exists specifically to close that gap.
 *
 * Single currency (ZAR) - this business only serves South African
 * clients, so no currency code is tracked.
 *
 * @package BizHub\Bookkeeping\Support
 */
final readonly class Money implements Stringable
{
    private function __construct(private int $minorUnits)
    {
    }

    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Build from a Rand amount, e.g. 1234.5 -> R 1,234.50.
     *
     * Rounds to 2 decimal places BEFORE scaling to cents, not after -
     * $rands * 100 alone can land a value like 1.005 on the wrong side
     * of a whole cent (100.49999999999999 due to float representation),
     * where a naive round($rands * 100) would silently truncate a cent.
     * PHP's round() already compensates for this at a given decimal
     * precision, so rounding to rands first and only then scaling
     * avoids the problem entirely.
     */
    public static function fromRands(float $rands): self
    {
        return new self((int) round(round($rands, 2) * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function toRands(): float
    {
        return $this->minorUnits / 100;
    }

    public function add(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function subtract(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    public function negate(): self
    {
        return new self(-$this->minorUnits);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function format(): string
    {
        return 'R ' . number_format($this->toRands(), 2);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
