<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a single historical event in a company's lifecycle.
 *
 * History entries are immutable once created; they form an append-only
 * audit trail of significant changes (status transitions, updates).
 *
 * @package BizHub\Companies\Entities
 */
final readonly class CompanyHistory
{
    /**
     * @param string            $uuid
     * @param string            $companyUuid
     * @param string            $description
     * @param CompanyStatus|null $previousStatus
     * @param CompanyStatus|null $newStatus
     * @param DateTimeImmutable $occurredAt
     */
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $description,
        public ?CompanyStatus $previousStatus = null,
        public ?CompanyStatus $newStatus = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('History entry UUID cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('History entry must be associated with a company.');
        }

        if (trim($this->description) === '') {
            throw new InvalidArgumentException('History entry description cannot be empty.');
        }
    }

    /**
     * Create a history entry describing a status transition.
     */
    public static function statusChanged(
        string $uuid,
        string $companyUuid,
        CompanyStatus $previousStatus,
        CompanyStatus $newStatus
    ): self {
        return new self(
            $uuid,
            $companyUuid,
            sprintf(
                'Status changed from "%s" to "%s".',
                $previousStatus->label(),
                $newStatus->label()
            ),
            $previousStatus,
            $newStatus
        );
    }

    /**
     * Export entity as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'company_uuid' => $this->companyUuid,
            'description' => $this->description,
            'previous_status' => $this->previousStatus?->value,
            'new_status' => $this->newStatus?->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
