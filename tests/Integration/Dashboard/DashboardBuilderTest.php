<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Dashboard;

use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\Repositories\ApplicationRepository;
use BizHub\Applications\Services\ApplicationService;
use BizHub\Applications\Services\ApplicationWorkflowService;
use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\NotificationData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Repositories\ClientRepository;
use BizHub\ClientPortal\Services\ClientService;
use BizHub\ClientPortal\Services\NotificationService;
use BizHub\Companies\DTO\AddressData;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Repositories\CompanyRepository;
use BizHub\Companies\Repositories\DirectorRepository;
use BizHub\Companies\Services\CompanyLookupService;
use BizHub\Companies\Services\CompanyService;
use BizHub\Dashboard\DashboardBuilder;
use BizHub\Dashboard\Widgets\ApplicationWidget;
use BizHub\Dashboard\Widgets\CompanyWidget;
use BizHub\Dashboard\Widgets\NotificationWidget;
use BizHub\Dashboard\Widgets\RecentDocumentsWidget;
use BizHub\Dashboard\Widgets\TaskWidget;
use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Repositories\DocumentRepository;
use BizHub\Documents\Services\DocumentService;
use BizHub\Documents\Services\DocumentStorageService;
use BizHub\Framework\Support\Uuid;
use BizHub\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class DashboardBuilderTest extends TestCase
{
    private const WP_USER_ID = 55;

    private DashboardBuilder $builder;
    private ApplicationWorkflowService $applicationWorkflow;
    private DocumentService $documentService;
    private string $applicationUuid;
    private string $clientUuid;
    private ?string $uploadedFilePath = null;

    protected function setUp(): void
    {
        if (! \defined('BIZHUB_STORAGE_PATH')) {
            define('BIZHUB_STORAGE_PATH', sys_get_temp_dir() . '/bizhub_dashboard_test/');
        }

        $db = new InMemoryDatabase();

        $directorRepository = new DirectorRepository($db);
        $companyRepository = new CompanyRepository($db, $directorRepository);
        $companyLookup = new CompanyLookupService($companyRepository);
        $companyService = new CompanyService($companyRepository);

        $applicationRepository = new ApplicationRepository($db);
        $applicationService = new ApplicationService($applicationRepository);
        $this->applicationWorkflow = new ApplicationWorkflowService($applicationRepository);

        $clientRepository = new ClientRepository($db);
        $clientService = new ClientService($clientRepository);
        $notificationService = new NotificationService($db);

        $documentRepository = new DocumentRepository($db);
        $this->documentService = new DocumentService($documentRepository, new DocumentStorageService());

        $this->clientUuid = Uuid::generate();
        $clientService->createClient(new ClientData(
            $this->clientUuid,
            self::WP_USER_ID,
            new ProfileData('Thandi', 'Zulu'),
            ClientStatus::ACTIVE
        ));

        $companyService->createCompany(new CompanyData(
            Uuid::generate(),
            self::WP_USER_ID,
            '2025/555555/07',
            'Zulu Consulting',
            'Private Company',
            CompanyStatus::ACTIVE,
            new AddressData('12 Loop St', '', 'CBD', 'Cape Town', 'Western Cape', '8001')
        ));

        $this->applicationUuid = Uuid::generate();
        $applicationService->createApplication(new ApplicationData($this->applicationUuid, self::WP_USER_ID, 'company_registration'));
        $this->applicationWorkflow->addStep($this->applicationUuid, 'Upload ID document', 1);

        $notificationService->send(new NotificationData($this->clientUuid, 'Welcome', 'Welcome to your BizHub portal'));

        $tmp = tempnam(sys_get_temp_dir(), 'bizhub_dash_test_');
        file_put_contents($tmp, 'fake content');
        $document = $this->documentService->uploadDocument(
            'client',
            $this->clientUuid,
            'Proof of ID',
            DocumentCategory::ID_DOCUMENT,
            $tmp,
            'id.pdf',
            self::WP_USER_ID
        );
        $this->uploadedFilePath = $document->getCurrentVersion()->filePath;
        unlink($tmp);

        $this->builder = new DashboardBuilder(
            $clientService,
            new CompanyWidget($companyLookup),
            new ApplicationWidget($applicationService),
            new TaskWidget($applicationService),
            new NotificationWidget($notificationService),
            new RecentDocumentsWidget($this->documentService)
        );
    }

    protected function tearDown(): void
    {
        if ($this->uploadedFilePath !== null && is_file($this->uploadedFilePath)) {
            unlink($this->uploadedFilePath);
        }
    }

    public function test_dashboard_aggregates_all_widgets(): void
    {
        $dashboard = $this->builder->buildForWpUser(self::WP_USER_ID);

        $this->assertSame(self::WP_USER_ID, $dashboard['client']['wp_user_id']);
        $this->assertSame(1, $dashboard['widgets']['companies']['total']);
        $this->assertSame(1, $dashboard['widgets']['applications']['total']);
        $this->assertSame(1, $dashboard['widgets']['tasks']['total']);
        $this->assertSame('Upload ID document', $dashboard['widgets']['tasks']['items'][0]['step_name']);
        $this->assertSame(1, $dashboard['widgets']['notifications']['unread_count']);
        $this->assertSame(1, $dashboard['widgets']['recent_documents']['total']);
    }

    public function test_completed_steps_are_excluded_from_tasks(): void
    {
        $this->applicationWorkflow->completeStep(
            $this->applicationUuid,
            $this->applicationWorkflow->submit($this->applicationUuid)->getSteps()[0]->getUuid()
        );

        $dashboard = $this->builder->buildForWpUser(self::WP_USER_ID);

        $this->assertSame(0, $dashboard['widgets']['tasks']['total']);
    }
}
