<?php

declare(strict_types=1);

namespace BizHub\Applications\DTO;

use BizHub\Applications\Entities\ApplicationStatus;
use DateTimeImmutable;

/**
 * Lightweight Data Transfer Object representing an application summary.
 *
 * @package BizHub\Applications\DTO
 */
final readonly class ApplicationSummary
{
    public function __construct(
        public string $uuid,
        public string $type,
        public ApplicationStatus $status,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Export the DTO as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
