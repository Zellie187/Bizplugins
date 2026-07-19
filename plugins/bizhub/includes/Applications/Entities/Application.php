<?php

declare(strict_types=1);

namespace BizHub\Applications\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a client's application for a BizHub service
 * (e.g. company registration, annual return, compliance review).
 *
 * @package BizHub\Applications\Entities
 */
final class Application
{
    /**
     * @var ApplicationStep[]
     */
    private array $steps = [];

    /**
     * @var ApplicationComment[]
     */
    private array $comments = [];

    public function __construct(
        private readonly string $uuid,
        private readonly int $clientId,
        private readonly string $type,
        private ApplicationStatus $status = ApplicationStatus::DRAFT,
        private readonly ?string $companyUuid = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $submittedAt = null
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Application UUID cannot be empty.');
        }

        if ($this->clientId <= 0) {
            throw new InvalidArgumentException('Invalid client ID.');
        }

        if (trim($this->type) === '') {
            throw new InvalidArgumentException('Application type cannot be empty.');
        }
    }

    /**
     * Get UUID.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get client ID.
     */
    public function getClientId(): int
    {
        return $this->clientId;
    }

    /**
     * Get application type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the UUID of the company this application relates to, if any.
     */
    public function getCompanyUuid(): ?string
    {
        return $this->companyUuid;
    }

    /**
     * Get status.
     */
    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    /**
     * Transition the application to a new status.
     */
    public function setStatus(ApplicationStatus $status): void
    {
        if ($this->status->isFinal()) {
            throw new InvalidArgumentException(
                sprintf('Application is already in a final state ("%s").', $this->status->label())
            );
        }

        $this->status = $status;
        $this->touch();
    }

    /**
     * Mark the application as submitted.
     */
    public function submit(): void
    {
        if (! $this->status->isEditable()) {
            throw new InvalidArgumentException('Only draft applications can be submitted.');
        }

        $this->status = ApplicationStatus::SUBMITTED;
        $this->submittedAt = new DateTimeImmutable();
        $this->touch();
    }

    /**
     * Add a step to the workflow.
     */
    public function addStep(ApplicationStep $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Get workflow steps, ordered.
     *
     * @return ApplicationStep[]
     */
    public function getSteps(): array
    {
        $steps = $this->steps;

        usort($steps, static fn (ApplicationStep $a, ApplicationStep $b): int => $a->getOrder() <=> $b->getOrder());

        return $steps;
    }

    /**
     * Determine whether every workflow step is complete.
     */
    public function allStepsCompleted(): bool
    {
        if ($this->steps === []) {
            return false;
        }

        foreach ($this->steps as $step) {
            if (! $step->isCompleted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add a comment.
     */
    public function addComment(ApplicationComment $comment): void
    {
        $this->comments[] = $comment;
    }

    /**
     * Get comments.
     *
     * @return ApplicationComment[]
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last update timestamp.
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get submission timestamp.
     */
    public function getSubmittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /**
     * Update modification timestamp.
     */
    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Export entity as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'client_id' => $this->clientId,
            'type' => $this->type,
            'company_uuid' => $this->companyUuid,
            'status' => $this->status->value,
            'steps' => array_map(
                static fn (ApplicationStep $step): array => $step->toArray(),
                $this->getSteps()
            ),
            'comments' => array_map(
                static fn (ApplicationComment $comment): array => $comment->toArray(),
                $this->comments
            ),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
            'submitted_at' => $this->submittedAt?->format(DATE_ATOM),
        ];
    }
}
