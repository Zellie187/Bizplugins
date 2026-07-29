<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A company's own customer - the party a client invoices, entirely
 * distinct from BizHub's own Client/Company entities. A customer is
 * never a WP user and never logs into any portal in this system.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class Customer
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $name,
        public string $email,
        public string $phone,
        public string $addressLine1,
        public string $addressLine2,
        public string $suburb,
        public string $city,
        public string $province,
        public string $postalCode,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Customer uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Customer companyUuid cannot be empty.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Customer name cannot be empty.');
        }
    }
}
