<?php

declare(strict_types=1);

namespace BizHub\Companies\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object representing a company director.
 *
 * This immutable DTO transports director data between the
 * repository, service and presentation layers.
 *
 * @package BizHub\Companies\DTO
 */
final readonly class DirectorData
{
    /**
     * Create a new Director DTO.
     *
     * @param string                 $uuid
     * @param string                 $firstName
     * @param string                 $lastName
     * @param string|null            $idNumber
     * @param string|null            $passportNumber
     * @param DateTimeImmutable      $appointmentDate
     * @param DateTimeImmutable|null $resignationDate
     * @param bool                   $active
     */
    public function __construct(
        public string $uuid,
        public string $firstName,
        public string $lastName,
        public ?string $idNumber,
        public ?string $passportNumber,
        public DateTimeImmutable $appointmentDate,
        public ?DateTimeImmutable $resignationDate = null,
        public bool $active = true,
    ) {
    }

    /**
     * Get the director's full name.
     */
    public function getFullName(): string
    {
        return trim(
            sprintf(
                '%s %s',
                $this->firstName,
                $this->lastName
            )
        );
    }

    /**
     * Determine whether the director has resigned.
     */
    public function hasResigned(): bool
    {
        return $this->resignationDate !== null;
    }

    /**
     * Export the DTO as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid'              => $this->uuid,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'full_name'         => $this->getFullName(),
            'id_number'         => $this->idNumber,
            'passport_number'   => $this->passportNumber,
            'appointment_date'  => $this->appointmentDate->format('Y-m-d'),
            'resignation_date'  => $this->resignationDate?->format('Y-m-d'),
            'active'            => $this->active,
        ];
    }
}
