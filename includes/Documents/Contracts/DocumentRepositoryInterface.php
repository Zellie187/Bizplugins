<?php

declare(strict_types=1);

namespace BizHub\Documents\Contracts;

use BizHub\Documents\Entities\Document;

/**
 * Defines the persistence contract for Document entities.
 *
 * @package BizHub\Documents\Contracts
 */
interface DocumentRepositoryInterface
{
    /**
     * Find a document by UUID.
     */
    public function findByUuid(string $uuid): ?Document;

    /**
     * Retrieve every document belonging to an owner.
     *
     * @return Document[]
     */
    public function findByOwner(string $ownerType, string $ownerUuid): array;

    /**
     * Persist a document.
     */
    public function save(Document $document): Document;

    /**
     * Delete a document.
     */
    public function delete(Document $document): void;
}
