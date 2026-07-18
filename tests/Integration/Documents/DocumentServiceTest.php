<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Documents;

use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Exceptions\DocumentNotFoundException;
use BizHub\Documents\Repositories\DocumentRepository;
use BizHub\Documents\Services\DocumentSecurityService;
use BizHub\Documents\Services\DocumentService;
use BizHub\Documents\Services\DocumentStorageService;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class DocumentServiceTest extends TestCase
{
    private DocumentService $service;
    private DocumentSecurityService $security;
    private DocumentRepository $repository;
    private string $storagePath;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/bizhub_doc_test_' . uniqid() . '/';

        if (! \defined('BIZHUB_STORAGE_PATH')) {
            define('BIZHUB_STORAGE_PATH', $this->storagePath);
        }

        $db = new InMemoryDatabase();
        $this->repository = new DocumentRepository($db);
        $this->service = new DocumentService($this->repository, new DocumentStorageService());

        $this->security = new DocumentSecurityService(new class implements AuthorizationServiceInterface {
            public function can(int $userId, string $capability, array $context = []): bool
            {
                return $userId === 1;
            }
            public function registerCapability(string $capability): void
            {
            }
            public function capabilities(): array
            {
                return [];
            }
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function makeUploadFile(string $content = 'fake content'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bizhub_doc_upload_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_upload_and_version(): void
    {
        $document = $this->service->uploadDocument(
            'company',
            'company-uuid-1',
            'ID Document',
            DocumentCategory::ID_DOCUMENT,
            $this->makeUploadFile(),
            'id.pdf',
            1
        );

        $this->assertNotNull($document->getCurrentVersion());
        $this->assertFileExists($document->getCurrentVersion()->filePath);
        $this->tempFiles[] = $document->getCurrentVersion()->filePath;

        $this->service->addVersion($document->getUuid(), $this->makeUploadFile('v2'), 'id-v2.pdf', 1);

        $fetched = $this->service->getDocument($document->getUuid());
        $this->assertCount(2, $fetched->getVersions());
        $this->assertSame(2, $fetched->getCurrentVersion()->versionNumber);
        $this->tempFiles[] = $fetched->getCurrentVersion()->filePath;
    }

    public function test_access_control(): void
    {
        $document = $this->service->uploadDocument(
            'company',
            'company-uuid-1',
            'ID Document',
            DocumentCategory::ID_DOCUMENT,
            $this->makeUploadFile(),
            'id.pdf',
            1
        );
        $this->tempFiles[] = $document->getCurrentVersion()->filePath;

        $this->assertTrue($this->security->canView(1, $document));
        $this->assertFalse($this->security->canDelete(2, $document));
        $this->assertTrue($this->security->canDelete(1, $document));
    }

    public function test_get_missing_document_throws(): void
    {
        $this->expectException(DocumentNotFoundException::class);

        $this->service->getDocument('nonexistent');
    }

    public function test_delete_removes_physical_files(): void
    {
        $document = $this->service->uploadDocument(
            'company',
            'company-uuid-1',
            'ID Document',
            DocumentCategory::ID_DOCUMENT,
            $this->makeUploadFile(),
            'id.pdf',
            1
        );

        $path = $document->getCurrentVersion()->filePath;
        $this->service->deleteDocument($document->getUuid());

        $this->assertFileDoesNotExist($path);
        $this->assertNull($this->repository->findByUuid($document->getUuid()));
    }
}
