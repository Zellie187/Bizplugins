<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\JournalSource;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A posted, immutable double-entry journal entry header plus its lines.
 *
 * Posted entries are never edited in place - correcting a mistake posts
 * a linked reversal (see JournalRepository::findReversalOf()), so there
 * is deliberately no wither/setter on this entity at all.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class JournalEntry
{
    /**
     * @param JournalLine[] $lines
     */
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public DateTimeImmutable $entryDate,
        public string $description,
        public JournalSource $source,
        public ?string $reversedEntryUuid,
        public int $createdBy,
        public DateTimeImmutable $createdAt,
        public array $lines = []
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('JournalEntry uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('JournalEntry companyUuid cannot be empty.');
        }
    }

    /**
     * @param JournalLine[] $lines
     */
    public function withLines(array $lines): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->entryDate,
            $this->description,
            $this->source,
            $this->reversedEntryUuid,
            $this->createdBy,
            $this->createdAt,
            $lines
        );
    }

    public function isReversal(): bool
    {
        return $this->reversedEntryUuid !== null;
    }
}
