<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Integration\Repositories;

use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkflowRepositoryTest extends TestCase
{
    private InMemoryDatabase $database;

    private WorkflowRepository $repository;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->repository = new WorkflowRepository($this->database);
    }

    public function test_a_saved_workflow_instance_round_trips_through_find(): void
    {
        $workflow = WorkflowInstance::start(
            Uuid::generate(),
            'company_registration',
            'company',
            Uuid::generate(),
            WorkflowStatus::Created,
            42,
            ['source' => 'client_portal']
        );

        $this->repository->save($workflow);

        $found = $this->repository->find($workflow->getUuid());

        $this->assertNotNull($found);
        $this->assertSame($workflow->getUuid(), $found->getUuid());
        $this->assertSame('company_registration', $found->getWorkflowType());
        $this->assertSame(WorkflowStatus::Created, $found->getStatus());
        $this->assertSame(42, $found->getCreatedBy());
        $this->assertSame(['source' => 'client_portal'], $found->getMetadata());
    }

    public function test_find_returns_null_for_an_unknown_uuid(): void
    {
        $this->assertNull($this->repository->find(Uuid::generate()));
    }

    public function test_recorded_transitions_are_returned_by_history_in_order(): void
    {
        $workflowUuid = Uuid::generate();

        $first = new Transition(
            Uuid::generate(),
            $workflowUuid,
            null,
            WorkflowStatus::Created,
            'create',
            1,
            '',
            [],
            new DateTimeImmutable('-2 minutes')
        );

        $second = new Transition(
            Uuid::generate(),
            $workflowUuid,
            WorkflowStatus::Created,
            WorkflowStatus::PendingDocuments,
            'request_documents',
            1,
            '',
            [],
            new DateTimeImmutable('-1 minute')
        );

        $this->repository->recordTransition($first);
        $this->repository->recordTransition($second);

        $history = $this->repository->history($workflowUuid);

        $this->assertCount(2, $history);
        $this->assertSame('create', $history[0]->action);
        $this->assertSame('request_documents', $history[1]->action);
        $this->assertSame(WorkflowStatus::PendingDocuments, $history[1]->to);
    }

    public function test_find_hydrates_history_alongside_the_instance(): void
    {
        $workflow = WorkflowInstance::start(
            Uuid::generate(),
            'company_registration',
            'company',
            Uuid::generate(),
            WorkflowStatus::Created,
            1
        );

        $this->repository->save($workflow);

        $transition = new Transition(
            Uuid::generate(),
            $workflow->getUuid(),
            null,
            WorkflowStatus::Created,
            'create',
            1,
            '',
            [],
            new DateTimeImmutable()
        );

        $this->repository->recordTransition($transition);

        $found = $this->repository->find($workflow->getUuid());

        $this->assertCount(1, $found->getHistory());
    }

    public function test_summaries_are_ordered_by_most_recently_updated_first(): void
    {
        $older = WorkflowInstance::start(Uuid::generate(), 'company_registration', 'company', Uuid::generate(), WorkflowStatus::Created, 1);
        $newer = WorkflowInstance::start(Uuid::generate(), 'company_registration', 'company', Uuid::generate(), WorkflowStatus::Created, 1);

        $this->database->seed('bizhub_workflow_instances', [
            [
                'uuid' => $older->getUuid(),
                'workflow_type' => 'company_registration',
                'subject_type' => 'company',
                'subject_uuid' => $older->getSubjectUuid(),
                'status' => 'created',
                'metadata' => '[]',
                'created_by' => 1,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'completed_at' => null,
            ],
            [
                'uuid' => $newer->getUuid(),
                'workflow_type' => 'company_registration',
                'subject_type' => 'company',
                'subject_uuid' => $newer->getSubjectUuid(),
                'status' => 'created',
                'metadata' => '[]',
                'created_by' => 1,
                'created_at' => '2026-01-02 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
                'completed_at' => null,
            ],
        ]);

        $summaries = $this->repository->summaries('company_registration');

        $this->assertCount(2, $summaries);
        $this->assertSame($newer->getUuid(), $summaries[0]->uuid);
        $this->assertSame($older->getUuid(), $summaries[1]->uuid);
    }
}
