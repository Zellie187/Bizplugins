<?php

declare(strict_types=1);

namespace BizHub\Applications\Services;

use BizHub\Applications\Contracts\ApplicationRepositoryInterface;
use BizHub\Applications\Entities\Application;
use BizHub\Applications\Entities\ApplicationComment;
use BizHub\Applications\Entities\ApplicationStatus;
use BizHub\Applications\Entities\ApplicationStep;
use BizHub\Applications\Exceptions\ApplicationNotFoundException;
use BizHub\Framework\Support\Uuid;
use InvalidArgumentException;

/**
 * Manages an application's workflow: steps, submission, review, and comments.
 *
 * Kept separate from ApplicationService so that basic CRUD operations
 * are not coupled to workflow state-transition rules.
 *
 * @package BizHub\Applications\Services
 */
final class ApplicationWorkflowService
{
    public function __construct(
        private readonly ApplicationRepositoryInterface $applications
    ) {
    }

    /**
     * Add a workflow step to an application.
     */
    public function addStep(string $applicationUuid, string $name, int $order): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->addStep(new ApplicationStep(
            Uuid::generate(),
            $applicationUuid,
            $name,
            $order
        ));

        return $this->applications->save($application);
    }

    /**
     * Mark a workflow step as complete.
     */
    public function completeStep(string $applicationUuid, string $stepUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        foreach ($application->getSteps() as $step) {
            if ($step->getUuid() === $stepUuid) {
                $step->complete();
            }
        }

        return $this->applications->save($application);
    }

    /**
     * Submit an application for review.
     */
    public function submit(string $applicationUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->submit();

        return $this->applications->save($application);
    }

    /**
     * Move a submitted application into review.
     */
    public function startReview(string $applicationUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        if ($application->getStatus() !== ApplicationStatus::SUBMITTED) {
            throw new InvalidArgumentException('Only submitted applications can enter review.');
        }

        $application->setStatus(ApplicationStatus::IN_REVIEW);

        return $this->applications->save($application);
    }

    /**
     * Approve an application.
     */
    public function approve(string $applicationUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->setStatus(ApplicationStatus::APPROVED);

        return $this->applications->save($application);
    }

    /**
     * Reject an application.
     */
    public function reject(string $applicationUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->setStatus(ApplicationStatus::REJECTED);

        return $this->applications->save($application);
    }

    /**
     * Cancel an application.
     */
    public function cancel(string $applicationUuid): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->setStatus(ApplicationStatus::CANCELLED);

        return $this->applications->save($application);
    }

    /**
     * Add a comment to an application.
     */
    public function addComment(string $applicationUuid, int $authorId, string $message): Application
    {
        $application = $this->getApplication($applicationUuid);

        $application->addComment(new ApplicationComment(
            Uuid::generate(),
            $applicationUuid,
            $authorId,
            $message
        ));

        return $this->applications->save($application);
    }

    /**
     * Retrieve an application or throw if it does not exist.
     */
    private function getApplication(string $uuid): Application
    {
        return $this->applications->findByUuid($uuid)
            ?? throw ApplicationNotFoundException::withUuid($uuid);
    }
}
