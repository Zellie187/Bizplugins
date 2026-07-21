<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Workflow;

use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Logging\Logger;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\States\WorkflowStateMachine;
use BizHub\Workflow\Tests\Mocks\InMemoryDatabase;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnGuard;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the workflow engine driving the Annual Return
 * workflow through its full lifecycle - exercising the "staff must
 * quote before the client can pay" precondition
 * (AnnualReturnGuard::guardRequestPayment()) against the real
 * WorkflowManager, not just the isolated Guard unit test.
 */
final class AnnualReturnWorkflowTest extends TestCase
{
    private WorkflowManager $manager;

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();
        $repository = new WorkflowRepository($database);
        $events = new EventDispatcher();
        $logger = new Logger(new LogManager());

        $this->manager = new WorkflowManager($repository, new WorkflowStateMachine(), $events, $logger);
        $this->manager->registerDefinition(new AnnualReturnDefinition(), new AnnualReturnGuard());
    }

    public function test_request_payment_is_rejected_without_a_quote_amount(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            AnnualReturnDefinition::TYPE,
            'company',
            'company-uuid-ar-1',
            7,
            ['financial_year' => 2026]
        ));

        $this->assertSame(WorkflowStatus::Created, $workflow->getStatus());

        $this->expectException(PreconditionFailedException::class);

        $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_REQUEST_PAYMENT,
            7
        ));
    }

    public function test_full_lifecycle_with_a_staff_quote_reaches_completed(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            AnnualReturnDefinition::TYPE,
            'company',
            'company-uuid-ar-2',
            7,
            ['financial_year' => 2026]
        ));

        // Staff check CIPC and send a quote - the workflow spec's
        // "staff to check annual returns on CIPC site >> send quote to
        // client" step.
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_REQUEST_PAYMENT,
            1,
            'Quote sent by Jane Staff.',
            ['quote_amount' => 850.00, 'quote_notes' => 'Covers 2 years outstanding.']
        ));

        $this->assertSame(WorkflowStatus::AwaitingPayment, $workflow->getStatus());
        $this->assertSame(850.00, $workflow->getMetadata()['quote_amount']);
        $this->assertSame('Covers 2 years outstanding.', $workflow->getMetadata()['quote_notes']);

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT,
            7,
            '',
            ['payment_reference' => 'ORDER-42']
        ));
        $this->assertSame(WorkflowStatus::Processing, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_START_QUALITY_REVIEW,
            1
        ));
        $this->assertSame(WorkflowStatus::QualityReview, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            AnnualReturnDefinition::ACTION_APPROVE,
            1,
            '',
            ['reviewed_by' => 'Jane Staff']
        ));
        $this->assertSame(WorkflowStatus::Completed, $workflow->getStatus());
    }
}
