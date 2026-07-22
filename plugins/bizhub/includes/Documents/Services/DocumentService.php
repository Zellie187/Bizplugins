<?php

declare(strict_types=1);

namespace BizHub\Documents\Services;

use BizHub\Documents\Contracts\DocumentRepositoryInterface;
use BizHub\Documents\Entities\Document;
use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Entities\DocumentVersion;
use BizHub\Documents\Exceptions\DocumentNotFoundException;
use BizHub\Framework\Support\Uuid;

/**
 * Implements the business operations for Document management.
 *
 * @package BizHub\Documents\Services
 */
final class DocumentService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocumentStorageService $storage
    ) {
    }

    /**
     * Upload a new document for an owner.
     */
    public function uploadDocument(
        string $ownerType,
        string $ownerUuid,
        string $name,
        DocumentCategory $category,
        string $temporaryPath,
        string $originalFilename,
        int $uploadedBy
    ): Document {
        $document = new Document(
            Uuid::generate(),
            $ownerType,
            $ownerUuid,
            $name,
            $category
        );

        $this->attachVersion($document, $temporaryPath, $originalFilename, $uploadedBy);

        return $this->documents->save($document);
    }

    /**
     * Upload a new version of an existing document.
     */
    public function addVersion(
        string $documentUuid,
        string $temporaryPath,
        string $originalFilename,
        int $uploadedBy
    ): Document {
        $document = $this->getDocument($documentUuid);

        $this->attachVersion($document, $temporaryPath, $originalFilename, $uploadedBy);

        return $this->documents->save($document);
    }

    /**
     * Retrieve a document.
     */
    public function getDocument(string $uuid): Document
    {
        return $this->documents->findByUuid($uuid)
            ?? throw DocumentNotFoundException::withUuid($uuid);
    }

    /**
     * Retrieve every document belonging to an owner.
     *
     * @return Document[]
     */
    public function getDocumentsForOwner(string $ownerType, string $ownerUuid): array
    {
        return $this->documents->findByOwner($ownerType, $ownerUuid);
    }

    /**
     * Delete a document and all of its stored file versions.
     */
    public function deleteDocument(string $uuid): void
    {
        $document = $this->getDocument($uuid);

        foreach ($document->getVersions() as $version) {
            $this->storage->delete($version->filePath);
        }

        $this->documents->delete($document);
    }

    /**
     * Delete a single stored version of a document - e.g. to correct
     * one bad upload without losing the rest of the document's
     * history - leaving the document and its remaining versions
     * intact. Use deleteDocument() instead if this is the only
     * version.
     *
     * @throws DocumentNotFoundException If the document does not exist.
     * @throws \InvalidArgumentException If the version does not belong to this
     *                                    document, or it is the only one left.
     */
    public function deleteVersion(string $documentUuid, string $versionUuid): void
    {
        $document = $this->getDocument($documentUuid);

        $target = null;

        foreach ($document->getVersions() as $version) {
            if ($version->uuid === $versionUuid) {
                $target = $version;

                break;
            }
        }

        // Validates the version belongs to this document and is not the
        // last one remaining, before any file/row is actually removed.
        $document->removeVersion($versionUuid);

        if ($target !== null) {
            $this->storage->delete($target->filePath);
        }

        $this->documents->deleteVersion($versionUuid);
    }

    /**
     * Store a file and attach it to a document as a new version.
     */
    private function attachVersion(
        Document $document,
        string $temporaryPath,
        string $originalFilename,
        int $uploadedBy
    ): void {
        $stored = $this->storage->store(
            $temporaryPath,
            $document->getOwnerType(),
            $document->getOwnerUuid(),
            $originalFilename
        );

        $document->addVersion(new DocumentVersion(
            Uuid::generate(),
            $document->getUuid(),
            $document->nextVersionNumber(),
            $stored['path'],
            $stored['size'],
            $stored['mime_type'],
            $uploadedBy
        ));
    }
}
