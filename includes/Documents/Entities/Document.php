<?php

declare(strict_types=1);

namespace BizHub\Documents\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a document owned by a client, company, or application.
 *
 * Ownership is polymorphic: ownerType identifies the owning aggregate
 * ("client", "company", "application") and ownerUuid its identifier.
 *
 * @package BizHub\Documents\Entities
 */
final class Document
{
    /**
     * @var DocumentVersion[]
     */
    private array $versions = [];

    public function __construct(
        private readonly string $uuid,
        private readonly string $ownerType,
        private readonly string $ownerUuid,
        private string $name,
        private DocumentCategory $category,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Document UUID cannot be empty.');
        }

        if (trim($this->ownerType) === '') {
            throw new InvalidArgumentException('Document owner type cannot be empty.');
        }

        if ($this->ownerUuid === '') {
            throw new InvalidArgumentException('Document must be associated with an owner.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Document name cannot be empty.');
        }
    }

    /**
     * Get UUID.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get owner type (e.g. "client", "company", "application").
     */
    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    /**
     * Get the UUID of the owning aggregate.
     */
    public function getOwnerUuid(): string
    {
        return $this->ownerUuid;
    }

    /**
     * Get document name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Rename the document.
     */
    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Document name cannot be empty.');
        }

        $this->name = $name;
        $this->touch();
    }

    /**
     * Get category.
     */
    public function getCategory(): DocumentCategory
    {
        return $this->category;
    }

    /**
     * Update category.
     */
    public function setCategory(DocumentCategory $category): void
    {
        $this->category = $category;
        $this->touch();
    }

    /**
     * Add a new version, becoming the current version.
     */
    public function addVersion(DocumentVersion $version): void
    {
        $this->versions[] = $version;
        $this->touch();
    }

    /**
     * Get every stored version, most recent first.
     *
     * @return DocumentVersion[]
     */
    public function getVersions(): array
    {
        $versions = $this->versions;

        usort($versions, static fn (DocumentVersion $a, DocumentVersion $b): int => $b->versionNumber <=> $a->versionNumber);

        return $versions;
    }

    /**
     * Get the current (latest) version.
     */
    public function getCurrentVersion(): ?DocumentVersion
    {
        return $this->getVersions()[0] ?? null;
    }

    /**
     * Get the next version number for a new upload.
     */
    public function nextVersionNumber(): int
    {
        return count($this->versions) + 1;
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last update timestamp.
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update modification timestamp.
     */
    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
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
            'owner_type' => $this->ownerType,
            'owner_uuid' => $this->ownerUuid,
            'name' => $this->name,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'current_version' => $this->getCurrentVersion()?->toArray(),
            'version_count' => count($this->versions),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
