<?php

declare(strict_types=1);

namespace BizHub\Documents\Repositories;

use BizHub\Documents\Contracts\DocumentRepositoryInterface;
use BizHub\Documents\Entities\Document;
use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Entities\DocumentVersion;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Persists Document aggregates, including their version history, using
 * the framework database abstraction.
 *
 * @package BizHub\Documents\Repositories
 */
final class DocumentRepository implements DocumentRepositoryInterface
{
    private const TABLE = 'bizhub_documents';
    private const VERSIONS_TABLE = 'bizhub_document_versions';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Document
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByOwner(string $ownerType, string $ownerUuid): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['owner_type' => $ownerType, 'owner_uuid' => $ownerUuid],
            ['created_at' => 'DESC']
        );

        return array_map(
            fn (array $row): Document => $this->hydrate($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(Document $document): Document
    {
        $data = $this->dehydrate($document);

        if ($this->database->exists(self::TABLE, ['uuid' => $document->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $document->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        foreach ($document->getVersions() as $version) {
            if (! $this->database->exists(self::VERSIONS_TABLE, ['uuid' => $version->uuid])) {
                $versionData = $version->toArray();
                $versionData['uploaded_at'] = $version->uploadedAt->format('Y-m-d H:i:s');

                $this->database->insert(self::VERSIONS_TABLE, $versionData);
            }
        }

        return $document;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Document $document): void
    {
        $this->database->delete(self::VERSIONS_TABLE, ['document_uuid' => $document->getUuid()]);
        $this->database->delete(self::TABLE, ['uuid' => $document->getUuid()]);
    }

    /**
     * Hydrate a database row into a Document aggregate, including its
     * version history.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Document
    {
        $document = new Document(
            $row['uuid'],
            $row['owner_type'],
            $row['owner_uuid'],
            $row['name'],
            DocumentCategory::from($row['category']),
            new DateTimeImmutable((string) $row['created_at']),
            empty($row['updated_at']) ? null : new DateTimeImmutable((string) $row['updated_at'])
        );

        $versionRows = $this->database->findAll(
            self::VERSIONS_TABLE,
            ['document_uuid' => $row['uuid']],
            ['version_number' => 'ASC']
        );

        foreach ($versionRows as $versionRow) {
            $document->addVersion(new DocumentVersion(
                $versionRow['uuid'],
                $versionRow['document_uuid'],
                (int) $versionRow['version_number'],
                $versionRow['file_path'],
                (int) $versionRow['file_size'],
                $versionRow['mime_type'],
                (int) $versionRow['uploaded_by'],
                new DateTimeImmutable((string) $versionRow['uploaded_at'])
            ));
        }

        return $document;
    }

    /**
     * Convert a Document aggregate into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Document $document): array
    {
        return [
            'uuid' => $document->getUuid(),
            'owner_type' => $document->getOwnerType(),
            'owner_uuid' => $document->getOwnerUuid(),
            'name' => $document->getName(),
            'category' => $document->getCategory()->value,
            'created_at' => $document->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
