<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Workflow;

use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Logging\Logger;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Events\WorkflowCancelled;
use BizHub\Workflow\Events\WorkflowCompleted;
use BizHub\Workflow\Events\WorkflowCreated;
use BizHub\Workflow\Events\WorkflowRolledBack;
use BizHub\Workflow\Events\WorkflowTransitioned;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\States\WorkflowStateMachine;
use BizHub\Workflow\Tests\Mocks\InMemoryDatabase;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationGuard;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the workflow engine driving the Company
 * Registration workflow through its full happy-path lifecycle, plus
 * its rejection/cancellation/rollback paths - exercising every layer
 * except the WordPress-facing Controller and the real BizHub database
 * driver (Repository -> WorkflowManager -> events, against an
 * in-memory database).
 */
final class CompanyRegistrationWorkflowTest extends TestCase
{
    private WorkflowManager $manager;

    /** @var array<int,object> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        $database = new InMemoryDatabase();
        $repository = new WorkflowRepository($database);
        $events = new EventDispatcher();
        $logger = new Logger(new LogManager());

        foreach ([
            WorkflowCreated::class,
            WorkflowTransitioned::class,
            WorkflowCompleted::class,
            WorkflowCancelled::class,
            WorkflowRolledBack::class,
        ] as $eventClass) {
            $events->listen($eventClass, function ($event): void {
                $this->dispatchedEvents[] = $event;
            });
        }

        $this->manager = new WorkflowManager($repository, new WorkflowStateMachine(), $events, $logger);
        $this->manager->registerDefinition(new CompanyRegistrationDefinition(), new CompanyRegistrationGuard());
    }

    public function test_full_happy_path_reaches_archived(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-1',
            7
        ));

        $this->assertSame(WorkflowStatus::Created, $workflow->getStatus());
        $this->assertInstanceOf(WorkflowCreated::class, $this->dispatchedEvents[0]);

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
            7
        ));
        $this->assertSame(WorkflowStatus::PendingDocuments, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            7,
            '',
            ['documents_verified' => true]
        ));
        $this->assertSame(WorkflowStatus::DocumentsVerified, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_PAYMENT,
            7
        ));
        $this->assertSame(WorkflowStatus::AwaitingPayment, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            7,
            '',
            ['payment_reference' => 'PMT-1']
        ));
        $this->assertSame(WorkflowStatus::Processing, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_START_QUALITY_REVIEW,
            7
        ));
        $this->assertSame(WorkflowStatus::QualityReview, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_APPROVE,
            7,
            '',
            ['reviewed_by' => 'Jane Reviewer']
        ));
        $this->assertSame(WorkflowStatus::Completed, $workflow->getStatus());
        $this->assertNotNull($workflow->getCompletedAt());

        $completedEvents = array_filter($this->dispatchedEvents, static fn ($e) => $e instanceof WorkflowCompleted);
        $this->assertNotEmpty($completedEvents);

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_ARCHIVE,
            7
        ));
        $this->assertSame(WorkflowStatus::Archived, $workflow->getStatus());
        $this->assertTrue($workflow->isTerminal());

        $this->assertCount(7, $this->manager->historyFor($workflow->getUuid()));
    }

    public function test_verify_documents_is_rejected_without_precondition_context(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-2',
            7
        ));

        $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
            7
        ));

        $this->expectException(PreconditionFailedException::class);

        $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            7
        ));
    }

    public function test_an_arbitrary_transition_is_rejected(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-3',
            7
        ));

        $this->expectException(InvalidTransitionException::class);

        // Cannot jump straight from Created to Processing.
        $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            7
        ));
    }

    public function test_transitioning_an_unknown_workflow_throws_not_found(): void
    {
        $this->expectException(WorkflowNotFoundException::class);

        $this->manager->transition(new TransitionWorkflowCommand('does-not-exist', 'cancel', 7));
    }

    public function test_cancel_moves_a_workflow_to_a_terminal_cancelled_state_and_raises_cancelled_event(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-4',
            7
        ));

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CANCEL,
            7,
            'Client withdrew application'
        ));

        $this->assertSame(WorkflowStatus::Cancelled, $workflow->getStatus());
        $this->assertTrue($workflow->isTerminal());

        $cancelledEvents = array_filter($this->dispatchedEvents, static fn ($e) => $e instanceof WorkflowCancelled);
        $this->assertNotEmpty($cancelledEvents);

        // Once terminal, no further action is permitted.
        $this->expectException(InvalidTransitionException::class);
        $this->manager->transition(new TransitionWorkflowCommand($workflow->getUuid(), 'request_documents', 7));
    }

    public function test_rollback_returns_a_workflow_to_its_previous_status(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-5',
            7
        ));

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
            7
        ));
        $this->assertSame(WorkflowStatus::PendingDocuments, $workflow->getStatus());

        $workflow = $this->manager->rollback(new RollbackWorkflowCommand(
            $workflow->getUuid(),
            7,
            'Requested documents by mistake'
        ));

        $this->assertSame(WorkflowStatus::Created, $workflow->getStatus());

        $rolledBackEvents = array_filter($this->dispatchedEvents, static fn ($e) => $e instanceof WorkflowRolledBack);
        $this->assertNotEmpty($rolledBackEvents);
    }

    public function test_name_rejection_loop_returns_to_quality_review_with_new_names(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-7',
            7,
            ['proposed_names' => ['First Choice (Pty) Ltd']]
        ));

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
            7
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            7,
            '',
            ['documents_verified' => true]
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_PAYMENT,
            7
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            7,
            '',
            ['payment_reference' => 'PMT-7']
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_START_QUALITY_REVIEW,
            7
        ));
        $this->assertSame(WorkflowStatus::QualityReview, $workflow->getStatus());

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REJECT_NAME,
            1,
            'CIPC declined - name too similar to an existing company.'
        ));
        $this->assertSame(WorkflowStatus::NamesRejected, $workflow->getStatus());
        $this->assertFalse($workflow->isTerminal());

        // A rejected-names workflow can still be cancelled outright.
        $this->assertContains(
            CompanyRegistrationDefinition::ACTION_CANCEL,
            (new WorkflowStateMachine())->allowedActions(new CompanyRegistrationDefinition(), $workflow->getStatus())
        );

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES,
            7,
            'Client resubmitted new proposed names.',
            ['proposed_names' => ['Second Choice (Pty) Ltd', 'Third Choice (Pty) Ltd']]
        ));

        $this->assertSame(WorkflowStatus::QualityReview, $workflow->getStatus());
        $this->assertSame(
            ['Second Choice (Pty) Ltd', 'Third Choice (Pty) Ltd'],
            $workflow->getMetadata()['proposed_names']
        );

        // The application can now be approved normally, as if the
        // rejection loop never happened.
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_APPROVE,
            7,
            '',
            ['reviewed_by' => 'Jane Reviewer']
        ));
        $this->assertSame(WorkflowStatus::Completed, $workflow->getStatus());
    }

    public function test_resubmit_names_is_rejected_without_at_least_one_name(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-8',
            7
        ));

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
            7
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
            7,
            '',
            ['documents_verified' => true]
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REQUEST_PAYMENT,
            7
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
            7,
            '',
            ['payment_reference' => 'PMT-8']
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_START_QUALITY_REVIEW,
            7
        ));
        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_REJECT_NAME,
            1,
            'Name already taken.'
        ));

        $this->expectException(PreconditionFailedException::class);

        $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES,
            7,
            '',
            ['proposed_names' => []]
        ));
    }

    public function test_rollback_is_rejected_once_a_workflow_is_terminal(): void
    {
        $workflow = $this->manager->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            'company-uuid-6',
            7
        ));

        $workflow = $this->manager->transition(new TransitionWorkflowCommand(
            $workflow->getUuid(),
            CompanyRegistrationDefinition::ACTION_CANCEL,
            7,
            'no longer needed'
        ));

        $this->expectException(InvalidTransitionException::class);

        $this->manager->rollback(new RollbackWorkflowCommand($workflow->getUuid(), 7, 'try again'));
    }
}
