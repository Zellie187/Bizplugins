<?php

declare(strict_types=1);

namespace BizHub\Applications\DTO;

use BizHub\Applications\Entities\ApplicationStatus;

/**
 * Data Transfer Object representing an Application.
 *
 * @package BizHub\Applications\DTO
 */
final readonly class ApplicationData
{
    public function __construct(
        public string $uuid,
        public int $clientId,
        public string $type,
        public ?string $companyUuid = null,
        public ApplicationStatus $status = ApplicationStatus::DRAFT,
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
            'client_id' => $this->clientId,
            'type' => $this->type,
            'company_uuid' => $this->companyUuid,
            'status' => $this->status->value,
        ];
    }
}
