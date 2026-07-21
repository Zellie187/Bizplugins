<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Workflows;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Logging\Logger;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\States\WorkflowStateMachine;
use BizHub\Workflow\Tests\Mocks\InMemoryDatabase;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnGuard;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnService;
use PHPUnit\Framework\TestCase;

final class AnnualReturnServiceTest extends TestCase
{
    private AnnualReturnService $service;

    private WorkflowRepository $repository;

    private CompanyServiceInterface&\PHPUnit\Framework\MockObject\MockObject $companyService;

    protected function setUp(): void
    {
        $this->repository = new WorkflowRepository(new InMemoryDatabase());

        $manager = new WorkflowManager(
            $this->repository,
            new WorkflowStateMachine(),
            new EventDispatcher(),
            new Logger(new LogManager())
        );
        $manager->registerDefinition(new AnnualReturnDefinition(), new AnnualReturnGuard());

        $this->companyService = $this->createMock(CompanyServiceInterface::class);
        $this->companyService->method('getCompany')->willReturn($this->makeCompany('company-uuid-1'));

        $this->service = new AnnualReturnService($manager, $this->repository, $this->companyService);
    }

    public function test_start_covers_multiple_financial_years_in_one_workflow(): void
    {
        $filings = [
            ['financial_year' => 2024, 'turnover' => 150000.0],
            ['financial_year' => 2025, 'turnover' => 180000.0],
            ['financial_year' => 2026, 'turnover' => 200000.0],
        ];

        $workflow = $this->service->start('company-uuid-1', 7, $filings);

        $this->assertSame($filings, $workflow->getMetadata()['filings']);
    }

    public function test_start_rejects_an_empty_filings_list(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->start('company-uuid-1', 7, []);
    }

    public function test_start_rejects_a_year_already_filed_in_an_earlier_application(): void
    {
        $this->service->start('company-uuid-1', 7, [['financial_year' => 2025, 'turnover' => 100000.0]]);

        $this->expectException(ValidationException::class);

        $this->service->start('company-uuid-1', 7, [
            ['financial_year' => 2025, 'turnover' => 120000.0],
            ['financial_year' => 2026, 'turnover' => 130000.0],
        ]);
    }

    public function test_start_allows_a_year_whose_only_prior_application_was_cancelled(): void
    {
        $first = $this->service->start('company-uuid-1', 7, [['financial_year' => 2025, 'turnover' => 100000.0]]);
        $this->service->performAction($first->getUuid(), AnnualReturnDefinition::ACTION_CANCEL, 7, 'client withdrew');

        $second = $this->service->start('company-uuid-1', 7, [['financial_year' => 2025, 'turnover' => 110000.0]]);

        $this->assertSame(2025, $second->getMetadata()['filings'][0]['financial_year']);
    }

    public function test_start_detects_a_duplicate_year_against_the_old_single_financial_year_shape(): void
    {
        // Simulate a workflow created before multi-year filings existed
        // (a flat `financial_year` int, not a `filings` list) directly
        // via the manager's TransitionWorkflowCommand-free create path,
        // to prove alreadyFiledYears() still reads it correctly.
        $old = $this->service->start('company-uuid-1', 7, [['financial_year' => 2023, 'turnover' => 0.0]]);
        $workflow = $this->repository->find($old->getUuid());
        $workflow->mergeMetadata(['financial_year' => 2023, 'filings' => null]);
        $this->repository->save($workflow);

        $this->expectException(ValidationException::class);

        $this->service->start('company-uuid-1', 7, [['financial_year' => 2023, 'turnover' => 90000.0]]);
    }

    public function test_revise_quote_updates_the_amount_after_a_quote_was_already_sent(): void
    {
        $workflow = $this->service->start('company-uuid-1', 7, [['financial_year' => 2025, 'turnover' => 100000.0]]);

        $workflow = $this->service->performAction(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_REQUEST_PAYMENT,
            1,
            'Quote sent.',
            ['quote_amount' => 650.0]
        );
        $this->assertSame(650.0, $workflow->getMetadata()['quote_amount']);

        $workflow = $this->service->performAction(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_REVISE_QUOTE,
            1,
            'Quote revised.',
            ['quote_amount' => 720.0]
        );

        $this->assertSame(720.0, $workflow->getMetadata()['quote_amount']);
        $this->assertSame(\BizHub\Workflow\Enums\WorkflowStatus::AwaitingPayment, $workflow->getStatus());
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
