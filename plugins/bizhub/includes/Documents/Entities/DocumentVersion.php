<?php

declare(strict_types=1);

namespace BizHub\Documents\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a single stored version of a document.
 *
 * @package BizHub\Documents\Entities
 */
final readonly class DocumentVersion
{
    public function __construct(
        public string $uuid,
        public string $documentUuid,
        public int $versionNumber,
        public string $filePath,
        public int $fileSize,
        public string $mimeType,
        public int $uploadedBy,
        public DateTimeImmutable $uploadedAt = new DateTimeImmutable(),
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Document version UUID cannot be empty.');
        }

        if ($this->documentUuid === '') {
            throw new InvalidArgumentException('Document version must be associated with a document.');
        }

        if ($this->versionNumber < 1) {
            throw new InvalidArgumentException('Version number must be at least 1.');
        }

        if (trim($this->filePath) === '') {
            throw new InvalidArgumentException('Document version file path cannot be empty.');
        }
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
            'document_uuid' => $this->documentUuid,
            'version_number' => $this->versionNumber,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'uploaded_by' => $this->uploadedBy,
            'uploaded_at' => $this->uploadedAt->format(DATE_ATOM),
        ];
    }
}
