<?php

declare(strict_types=1);

namespace BizHub\Workflow\Tests\Unit\Enums;

use BizHub\Workflow\Enums\WorkflowStatus;
use PHPUnit\Framework\TestCase;

final class WorkflowStatusTest extends TestCase
{
    public function test_terminal_statuses_are_reported_correctly(): void
    {
        $this->assertTrue(WorkflowStatus::Archived->isTerminal());
        $this->assertTrue(WorkflowStatus::Cancelled->isTerminal());
        $this->assertTrue(WorkflowStatus::Rejected->isTerminal());

        $this->assertFalse(WorkflowStatus::Created->isTerminal());
        $this->assertFalse(WorkflowStatus::Processing->isTerminal());
        $this->assertFalse(WorkflowStatus::Completed->isTerminal());
    }

    public function test_only_completed_and_archived_are_successful(): void
    {
        $this->assertTrue(WorkflowStatus::Completed->isSuccessful());
        $this->assertTrue(WorkflowStatus::Archived->isSuccessful());

        $this->assertFalse(WorkflowStatus::Cancelled->isSuccessful());
        $this->assertFalse(WorkflowStatus::Rejected->isSuccessful());
        $this->assertFalse(WorkflowStatus::Created->isSuccessful());
    }

    public function test_every_status_has_a_label(): void
    {
        foreach (WorkflowStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }
}
