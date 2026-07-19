<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Applications;

use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\Entities\ApplicationStatus;
use BizHub\Applications\Exceptions\ApplicationNotFoundException;
use BizHub\Applications\Repositories\ApplicationRepository;
use BizHub\Applications\Services\ApplicationService;
use BizHub\Applications\Services\ApplicationWorkflowService;
use BizHub\Framework\Support\Uuid;
use BizHub\Tests\Mocks\InMemoryDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApplicationWorkflowServiceTest extends TestCase
{
    private ApplicationService $service;
    private ApplicationWorkflowService $workflow;
    private ApplicationRepository $repository;

    protected function setUp(): void
    {
        $db = new InMemoryDatabase();
        $this->repository = new ApplicationRepository($db);
        $this->service = new ApplicationService($this->repository);
        $this->workflow = new ApplicationWorkflowService($this->repository);
    }

    public function test_full_workflow_lifecycle(): void
    {
        $uuid = Uuid::generate();
        $this->service->createApplication(new ApplicationData($uuid, 7, 'company_registration'));

        $this->workflow->addStep($uuid, 'Collect documents', 1);
        $this->workflow->addStep($uuid, 'Submit to CIPC', 2);

        $application = $this->service->getApplication($uuid);
        $this->assertCount(2, $application->getSteps());

        $firstStep = $application->getSteps()[0];
        $this->workflow->completeStep($uuid, $firstStep->getUuid());

        $afterComplete = $this->service->getApplication($uuid);
        $this->assertTrue($afterComplete->getSteps()[0]->isCompleted());
        $this->assertFalse($afterComplete->allStepsCompleted());

        $this->workflow->addComment($uuid, 99, 'Please upload your ID document.');
        $this->assertCount(1, $this->service->getApplication($uuid)->getComments());

        $submitted = $this->workflow->submit($uuid);
        $this->assertSame(ApplicationStatus::SUBMITTED, $submitted->getStatus());

        $this->workflow->startReview($uuid);
        $approved = $this->workflow->approve($uuid);
        $this->assertSame(ApplicationStatus::APPROVED, $approved->getStatus());
    }

    public function test_cannot_resubmit_a_submitted_application(): void
    {
        $uuid = Uuid::generate();
        $this->service->createApplication(new ApplicationData($uuid, 7, 'company_registration'));
        $this->workflow->submit($uuid);

        $this->expectException(InvalidArgumentException::class);

        $this->workflow->submit($uuid);
    }

    public function test_cannot_transition_a_final_state_application(): void
    {
        $uuid = Uuid::generate();
        $this->service->createApplication(new ApplicationData($uuid, 7, 'company_registration'));
        $this->workflow->submit($uuid);
        $this->workflow->approve($uuid);

        $this->expectException(InvalidArgumentException::class);

        $this->workflow->reject($uuid);
    }

    public function test_get_missing_application_throws(): void
    {
        $this->expectException(ApplicationNotFoundException::class);

        $this->service->getApplication(Uuid::generate());
    }

    public function test_delete_removes_application(): void
    {
        $uuid = Uuid::generate();
        $this->service->createApplication(new ApplicationData($uuid, 7, 'company_registration'));

        $this->service->deleteApplication($uuid);

        $this->assertNull($this->repository->findByUuid($uuid));
    }
}
