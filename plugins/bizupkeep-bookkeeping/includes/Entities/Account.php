<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One line of a company's chart of accounts.
 *
 * Immutable - AccountService::createCustomAccount() and the default
 * chart-of-accounts seed both build a fresh instance; the only mutation
 * a v1 account ever needs (deactivation) is expressed as a wither
 * (deactivate()) returning a new instance, which AccountRepository::save()
 * then persists.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class Account
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $code,
        public string $name,
        public AccountType $type,
        public NormalBalance $normalBalance,
        public bool $isSystem,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Account uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Account companyUuid cannot be empty.');
        }

        if ($this->code === '') {
            throw new InvalidArgumentException('Account code cannot be empty.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Account name cannot be empty.');
        }
    }

    public function deactivate(): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->code,
            $this->name,
            $this->type,
            $this->normalBalance,
            $this->isSystem,
            false,
            $this->createdAt,
            new DateTimeImmutable()
        );
    }
}
