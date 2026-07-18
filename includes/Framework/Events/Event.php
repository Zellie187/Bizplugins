<?php

declare(strict_types=1);

namespace BizHub\Framework\Events;

use DateTimeImmutable;

/**
 * Base class for all framework and module events.
 *
 * @package BizHub\Framework\Events
 */
abstract class Event
{
    private readonly DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
    }

    /**
     * Return the event's name, used for dispatcher registration.
     */
    public function name(): string
    {
        return static::class;
    }

    /**
     * Return when the event occurred.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
