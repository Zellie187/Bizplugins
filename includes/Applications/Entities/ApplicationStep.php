<?php

declare(strict_types=1);

namespace BizHub\Applications\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a single step within an application's workflow.
 *
 * @package BizHub\Applications\Entities
 */
final class ApplicationStep
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $applicationUuid,
        private readonly string $name,
        private readonly int $order,
        private bool $completed = false,
        private ?DateTimeImmutable $completedAt = null
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Step UUID cannot be empty.');
        }

        if ($this->applicationUuid === '') {
            throw new InvalidArgumentException('Step must be associated with an application.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Step name cannot be empty.');
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
     * Get the UUID of the application this step belongs to.
     */
    public function getApplicationUuid(): string
    {
        return $this->applicationUuid;
    }

    /**
     * Get step name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get step order.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Determine whether the step is complete.
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Mark the step as complete.
     */
    public function complete(): void
    {
        $this->completed = true;
        $this->completedAt = new DateTimeImmutable();
    }

    /**
     * Get completion timestamp.
     */
    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
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
            'application_uuid' => $this->applicationUuid,
            'name' => $this->name,
            'step_order' => $this->order,
            'completed' => $this->completed,
            'completed_at' => $this->completedAt?->format(DATE_ATOM),
        ];
    }
}
