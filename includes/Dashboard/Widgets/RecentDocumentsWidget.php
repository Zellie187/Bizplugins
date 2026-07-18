<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

use BizHub\Documents\Services\DocumentService;

/**
 * Dashboard widget summarizing a client's most recently uploaded documents.
 *
 * @package BizHub\Dashboard\Widgets
 */
final class RecentDocumentsWidget implements Widget
{
    private const OWNER_TYPE = 'client';

    public function __construct(
        private readonly DocumentService $documents
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return 'recent_documents';
    }

    /**
     * {@inheritDoc}
     */
    public function data(int $clientId, string $clientUuid): array
    {
        $documents = $this->documents->getDocumentsForOwner(self::OWNER_TYPE, $clientUuid);

        return [
            'total' => count($documents),
            'items' => array_map(
                static fn ($document): array => $document->toArray(),
                array_slice($documents, 0, 5)
            ),
        ];
    }
}
