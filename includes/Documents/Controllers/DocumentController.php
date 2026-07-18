<?php

declare(strict_types=1);

namespace BizHub\Documents\Controllers;

use BizHub\Documents\Exceptions\DocumentAccessDeniedException;
use BizHub\Documents\Services\DocumentSecurityService;
use BizHub\Documents\Services\DocumentService;

/**
 * Handles document view and deletion operations.
 *
 * Transport-agnostic: route registration belongs to the API/Admin layers.
 * File upload handling (extracting a temp path from a request) is left
 * to the caller, which should invoke DocumentService::uploadDocument()
 * directly with the resolved temporary path.
 *
 * @package BizHub\Documents\Controllers
 */
final class DocumentController
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentSecurityService $security
    ) {
    }

    /**
     * Retrieve a single document, enforcing view access.
     *
     * @return array<string,mixed>
     */
    public function show(string $uuid, int $userId): array
    {
        $document = $this->documents->getDocument($uuid);

        if (! $this->security->canView($userId, $document)) {
            throw DocumentAccessDeniedException::forUser($uuid, $userId);
        }

        return $document->toArray();
    }

    /**
     * List documents belonging to an owner.
     *
     * @return array<int,array<string,mixed>>
     */
    public function indexForOwner(string $ownerType, string $ownerUuid): array
    {
        return array_map(
            static fn ($document): array => $document->toArray(),
            $this->documents->getDocumentsForOwner($ownerType, $ownerUuid)
        );
    }

    /**
     * Delete a document, enforcing delete access.
     */
    public function delete(string $uuid, int $userId): void
    {
        $document = $this->documents->getDocument($uuid);

        if (! $this->security->canDelete($userId, $document)) {
            throw DocumentAccessDeniedException::forUser($uuid, $userId);
        }

        $this->documents->deleteDocument($uuid);
    }
}
