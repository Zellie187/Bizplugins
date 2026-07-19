<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Workflows;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Logging\Logger;
use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\States\WorkflowStateMachine;
use BizHub\Workflow\Tests\Mocks\InMemoryDatabase;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationGuard;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;
use PHPUnit\Framework\TestCase;

final class CompanyRegistrationServiceTest extends TestCase
{
    private CompanyRegistrationService $service;

    private CompanyServiceInterface&\PHPUnit\Framework\MockObject\MockObject $companyService;

    protected function setUp(): void
    {
        $manager = new WorkflowManager(
            new WorkflowRepository(new InMemoryDatabase()),
            new WorkflowStateMachine(),
            new EventDispatcher(),
            new Logger(new LogManager())
        );
        $manager->registerDefinition(new CompanyRegistrationDefinition(), new CompanyRegistrationGuard());

        $this->companyService = $this->createMock(CompanyServiceInterface::class);

        $this->service = new CompanyRegistrationService($manager, $this->companyService);
    }

    public function test_start_creates_a_workflow_bound_to_an_existing_company(): void
    {
        $company = $this->makeCompany('company-uuid-1');

        $this->companyService->method('getCompany')
            ->with('company-uuid-1')
            ->willReturn($company);

        $workflow = $this->service->start('company-uuid-1', 3);

        $this->assertSame(CompanyRegistrationDefinition::TYPE, $workflow->getWorkflowType());
        $this->assertSame('company', $workflow->getSubjectType());
        $this->assertSame('company-uuid-1', $workflow->getSubjectUuid());
        $this->assertSame(3, $workflow->getCreatedBy());
    }

    public function test_start_propagates_company_not_found(): void
    {
        $this->companyService->method('getCompany')
            ->willThrowException(CompanyNotFoundException::withUuid('missing'));

        $this->expectException(CompanyNotFoundException::class);

        $this->service->start('missing', 3);
    }

    public function test_perform_action_rejects_an_action_that_is_not_a_company_registration_action(): void
    {
        $company = $this->makeCompany('company-uuid-2');
        $this->companyService->method('getCompany')->willReturn($company);

        $workflow = $this->service->start('company-uuid-2', 3);

        $this->expectException(ValidationException::class);

        $this->service->performAction($workflow->getUuid(), 'not_a_real_action', 3);
    }

    public function test_operating_on_an_unknown_workflow_uuid_throws_not_found(): void
    {
        $this->expectException(WorkflowNotFoundException::class);

        $this->service->find(Uuid::generate());
    }

    private function makeCompany(string $uuid): Company
    {
        return new Company(
            $uuid,
            1,
            'REG-001',
            'Test Co (Pty) Ltd',
            'Private Company',
            CompanyStatus::CREATED,
            new RegisteredAddress('1 Main Street', '', 'Suburb', 'Cape Town', 'Western Cape', '8001')
        );
    }
}
