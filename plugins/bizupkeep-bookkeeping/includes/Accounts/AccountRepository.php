<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Accounts;

use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the accounts table.
 *
 * @package BizHub\Bookkeeping\Accounts
 */
final class AccountRepository implements AccountRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_accounts';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByUuid(string $uuid): ?Account
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByCode(string $companyUuid, string $code): ?Account
    {
        $row = $this->database->findOne(self::TABLE, ['company_uuid' => $companyUuid, 'code' => $code]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findAllForCompany(string $companyUuid, ?AccountType $type = null, bool $onlyActive = true): array
    {
        $criteria = ['company_uuid' => $companyUuid];

        if ($type !== null) {
            $criteria['account_type'] = $type->value;
        }

        if ($onlyActive) {
            $criteria['is_active'] = 1;
        }

        $rows = $this->database->findAll(self::TABLE, $criteria, ['code' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function existsAnyForCompany(string $companyUuid): bool
    {
        return $this->database->exists(self::TABLE, ['company_uuid' => $companyUuid]);
    }

    public function insertMany(array $accounts): void
    {
        foreach ($accounts as $account) {
            $this->database->insert(self::TABLE, $this->dehydrate($account));
        }
    }

    public function save(Account $account): Account
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $account->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($account), ['uuid' => $account->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($account));
        }

        return $account;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(Account $account): array
    {
        return [
            'uuid' => $account->uuid,
            'company_uuid' => $account->companyUuid,
            'code' => $account->code,
            'name' => $account->name,
            'account_type' => $account->type->value,
            'normal_balance' => $account->normalBalance->value,
            'is_system' => $account->isSystem ? 1 : 0,
            'is_active' => $account->isActive ? 1 : 0,
            'created_at' => $account->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $account->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Account
    {
        return new Account(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            type: AccountType::from((string) $row['account_type']),
            normalBalance: NormalBalance::from((string) $row['normal_balance']),
            isSystem: (bool) $row['is_system'],
            isActive: (bool) $row['is_active'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }
}
