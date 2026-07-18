<?php

declare(strict_types=1);

namespace BizHub\Documents\Services;

use BizHub\Documents\Entities\Document;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Security\Authorization\Support\Capabilities;

/**
 * Determines whether a user may view, download, or delete a document.
 *
 * @package BizHub\Documents\Services
 */
final class DocumentSecurityService
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    /**
     * Determine whether a user may view a document.
     */
    public function canView(int $userId, Document $document): bool
    {
        return $this->authorization->can($userId, Capabilities::DOCUMENT_VIEW, [
            'owner_type' => $document->getOwnerType(),
            'owner_uuid' => $document->getOwnerUuid(),
        ]);
    }

    /**
     * Determine whether a user may download a document.
     */
    public function canDownload(int $userId, Document $document): bool
    {
        return $this->authorization->can($userId, Capabilities::DOCUMENT_DOWNLOAD, [
            'owner_type' => $document->getOwnerType(),
            'owner_uuid' => $document->getOwnerUuid(),
        ]);
    }

    /**
     * Determine whether a user may delete a document.
     */
    public function canDelete(int $userId, Document $document): bool
    {
        return $this->authorization->can($userId, Capabilities::DOCUMENT_DELETE, [
            'owner_type' => $document->getOwnerType(),
            'owner_uuid' => $document->getOwnerUuid(),
        ]);
    }

    /**
     * Determine whether a user may upload a document for a given owner.
     */
    public function canUpload(int $userId, string $ownerType, string $ownerUuid): bool
    {
        return $this->authorization->can($userId, Capabilities::DOCUMENT_UPLOAD, [
            'owner_type' => $ownerType,
            'owner_uuid' => $ownerUuid,
        ]);
    }
}
