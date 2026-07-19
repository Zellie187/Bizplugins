<?php

declare(strict_types=1);

namespace BizHub\Applications\Repositories;

use BizHub\Applications\Contracts\ApplicationRepositoryInterface;
use BizHub\Applications\DTO\ApplicationSummary;
use BizHub\Applications\Entities\Application;
use BizHub\Applications\Entities\ApplicationComment;
use BizHub\Applications\Entities\ApplicationStatus;
use BizHub\Applications\Entities\ApplicationStep;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Persists Application aggregates, including their steps and comments,
 * using the framework database abstraction.
 *
 * @package BizHub\Applications\Repositories
 */
final class ApplicationRepository implements ApplicationRepositoryInterface
{
    private const TABLE = 'bizhub_applications';
    private const STEPS_TABLE = 'bizhub_application_steps';
    private const COMMENTS_TABLE = 'bizhub_application_comments';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Application
    {
        $row = $this->database->findOne(self::TABLE, ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Application
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(int $clientId): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['client_id' => $clientId],
            ['created_at' => 'DESC']
        );

        return array_map(
            fn (array $row): Application => $this->hydrate($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findSummariesByClientId(int $clientId): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['client_id' => $clientId],
            ['created_at' => 'DESC']
        );

        return array_map(
            fn (array $row): ApplicationSummary => new ApplicationSummary(
                $row['uuid'],
                $row['type'],
                ApplicationStatus::from($row['status']),
                new DateTimeImmutable((string) $row['created_at'])
            ),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(Application $application): Application
    {
        $data = $this->dehydrate($application);

        if ($this->database->exists(self::TABLE, ['uuid' => $application->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $application->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        foreach ($application->getSteps() as $step) {
            $this->saveStep($step);
        }

        foreach ($application->getComments() as $comment) {
            $this->saveComment($comment);
        }

        return $application;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Application $application): void
    {
        $this->database->delete(self::STEPS_TABLE, ['application_uuid' => $application->getUuid()]);
        $this->database->delete(self::COMMENTS_TABLE, ['application_uuid' => $application->getUuid()]);
        $this->database->delete(self::TABLE, ['uuid' => $application->getUuid()]);
    }

    /**
     * Persist a single workflow step.
     */
    private function saveStep(ApplicationStep $step): void
    {
        $data = $step->toArray();
        unset($data['completed_at']);
        $data['completed'] = $step->isCompleted() ? 1 : 0;
        $data['completed_at'] = $step->getCompletedAt()?->format('Y-m-d H:i:s');

        if ($this->database->exists(self::STEPS_TABLE, ['uuid' => $step->getUuid()])) {
            $this->database->update(self::STEPS_TABLE, $data, ['uuid' => $step->getUuid()]);
        } else {
            $this->database->insert(self::STEPS_TABLE, $data);
        }
    }

    /**
     * Persist a single comment.
     */
    private function saveComment(ApplicationComment $comment): void
    {
        if (! $this->database->exists(self::COMMENTS_TABLE, ['uuid' => $comment->uuid])) {
            $data = $comment->toArray();
            $data['created_at'] = $comment->createdAt->format('Y-m-d H:i:s');

            $this->database->insert(self::COMMENTS_TABLE, $data);
        }
    }

    /**
     * Hydrate a database row into an Application aggregate, including
     * its steps and comments.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Application
    {
        $application = new Application(
            $row['uuid'],
            (int) $row['client_id'],
            $row['type'],
            ApplicationStatus::from($row['status']),
            $row['company_uuid'] ?? null,
            new DateTimeImmutable((string) $row['created_at']),
            empty($row['updated_at']) ? null : new DateTimeImmutable((string) $row['updated_at']),
            empty($row['submitted_at']) ? null : new DateTimeImmutable((string) $row['submitted_at'])
        );

        $stepRows = $this->database->findAll(
            self::STEPS_TABLE,
            ['application_uuid' => $row['uuid']],
            ['step_order' => 'ASC']
        );

        foreach ($stepRows as $stepRow) {
            $application->addStep(new ApplicationStep(
                $stepRow['uuid'],
                $stepRow['application_uuid'],
                $stepRow['name'],
                (int) $stepRow['step_order'],
                (bool) $stepRow['completed'],
                empty($stepRow['completed_at']) ? null : new DateTimeImmutable((string) $stepRow['completed_at'])
            ));
        }

        $commentRows = $this->database->findAll(
            self::COMMENTS_TABLE,
            ['application_uuid' => $row['uuid']],
            ['created_at' => 'ASC']
        );

        foreach ($commentRows as $commentRow) {
            $application->addComment(new ApplicationComment(
                $commentRow['uuid'],
                $commentRow['application_uuid'],
                (int) $commentRow['author_id'],
                $commentRow['message'],
                new DateTimeImmutable((string) $commentRow['created_at'])
            ));
        }

        return $application;
    }

    /**
     * Convert an Application aggregate into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Application $application): array
    {
        return [
            'uuid' => $application->getUuid(),
            'client_id' => $application->getClientId(),
            'type' => $application->getType(),
            'company_uuid' => $application->getCompanyUuid(),
            'status' => $application->getStatus()->value,
            'created_at' => $application->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $application->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'submitted_at' => $application->getSubmittedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
