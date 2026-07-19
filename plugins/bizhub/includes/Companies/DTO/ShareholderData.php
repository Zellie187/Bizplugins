<?php

declare(strict_types=1);

namespace BizHub\Companies\DTO;

/**
 * Data Transfer Object representing a company shareholder.
 *
 * @package BizHub\Companies\DTO
 */
final readonly class ShareholderData
{
    /**
     * @param string      $uuid
     * @param string      $fullName
     * @param string|null $idNumber
     * @param string|null $passportNumber
     * @param float       $sharesPercentage
     */
    public function __construct(
        public string $uuid,
        public string $fullName,
        public ?string $idNumber,
        public ?string $passportNumber,
        public float $sharesPercentage,
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
            'full_name' => $this->fullName,
            'id_number' => $this->idNumber,
            'passport_number' => $this->passportNumber,
            'shares_percentage' => $this->sharesPercentage,
        ];
    }
}
