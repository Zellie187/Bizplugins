<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a document attached to a company record.
 *
 * This is a lightweight metadata record scoped to the Companies module.
 * Full document lifecycle management (versions, storage, security)
 * belongs to the Documents module.
 *
 * @package BizHub\Companies\Entities
 */
final class CompanyDocument
{
    /**
     * @param string            $uuid
     * @param string            $companyUuid
     * @param string            $name
     * @param string            $filePath
     * @param string            $mimeType
     * @param DateTimeImmutable $uploadedAt
     */
    public function __construct(
        private readonly string $uuid,
        private readonly string $companyUuid,
        private string $name,
        private readonly string $filePath,
        private readonly string $mimeType,
        private readonly DateTimeImmutable $uploadedAt = new DateTimeImmutable()
    ) {
        $this->validate();
    }

    /**
     * Validate the entity.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Document UUID cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Document must be associated with a company.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Document name cannot be empty.');
        }

        if (trim($this->filePath) === '') {
            throw new InvalidArgumentException('Document file path cannot be empty.');
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
     * Get the UUID of the company this document belongs to.
     */
    public function getCompanyUuid(): string
    {
        return $this->companyUuid;
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
    }

    /**
     * Get file path.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get MIME type.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get upload timestamp.
     */
    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
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
            'name' => $this->name,
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'uploaded_at' => $this->uploadedAt->format(DATE_ATOM),
        ];
    }
}
