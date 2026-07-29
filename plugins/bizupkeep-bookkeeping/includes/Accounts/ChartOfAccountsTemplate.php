<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Accounts;

use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\Bookkeeping\Enums\NormalBalance;

/**
 * The default chart of accounts seeded for every new company - a basic
 * chart appropriate for a South African startup, covering the accounts
 * a young company typically needs before a dedicated accountant takes
 * over: bank/cash, VAT input/output, PAYE/UIF payable, share capital,
 * and a standard set of income/expense categories.
 *
 * Other modules reference the CODE_* constants rather than hardcoding
 * magic strings, so a code never drifts out of sync between the seed
 * data and the code that depends on a specific account existing (e.g.
 * PaymentMethod's offsetting-account lookup).
 *
 * @package BizHub\Bookkeeping\Accounts
 */
final class ChartOfAccountsTemplate
{
    public const CODE_BANK_ACCOUNT = '1000';
    public const CODE_CASH_ON_HAND = '1010';
    public const CODE_ACCOUNTS_RECEIVABLE = '1020';
    public const CODE_VAT_INPUT = '1030';
    public const CODE_FIXED_ASSETS = '1040';
    public const CODE_ACCUMULATED_DEPRECIATION = '1050';

    public const CODE_ACCOUNTS_PAYABLE = '2000';
    public const CODE_VAT_OUTPUT = '2010';
    public const CODE_PAYE_PAYABLE = '2020';
    public const CODE_UIF_PAYABLE = '2030';
    public const CODE_LOANS_PAYABLE = '2040';

    public const CODE_SHARE_CAPITAL = '3000';
    public const CODE_RETAINED_EARNINGS = '3010';
    public const CODE_DRAWINGS = '3020';

    public const CODE_SALES_REVENUE = '4000';
    public const CODE_OTHER_INCOME = '4010';

    public const CODE_COST_OF_SALES = '5000';
    public const CODE_SALARIES_WAGES = '5010';
    public const CODE_RENT = '5020';
    public const CODE_UTILITIES = '5030';
    public const CODE_BANK_CHARGES = '5040';
    public const CODE_PROFESSIONAL_FEES = '5050';
    public const CODE_MARKETING = '5060';
    public const CODE_OFFICE_SUPPLIES = '5070';
    public const CODE_TRAVEL_MOTOR = '5080';
    public const CODE_INSURANCE = '5090';
    public const CODE_DEPRECIATION = '5100';
    public const CODE_OTHER_EXPENSES = '5110';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * @return array<int,array{code:string,name:string,type:AccountType,normalBalance:NormalBalance}>
     */
    public static function defaultAccounts(): array
    {
        $asset = AccountType::Asset;
        $liability = AccountType::Liability;
        $equity = AccountType::Equity;
        $income = AccountType::Income;
        $expense = AccountType::Expense;
        $debit = NormalBalance::Debit;
        $credit = NormalBalance::Credit;

        return [
            self::row(self::CODE_BANK_ACCOUNT, 'Bank Account', $asset, $debit),
            self::row(self::CODE_CASH_ON_HAND, 'Cash on Hand', $asset, $debit),
            self::row(self::CODE_ACCOUNTS_RECEIVABLE, 'Accounts Receivable', $asset, $debit),
            self::row(self::CODE_VAT_INPUT, 'VAT Input', $asset, $debit),
            self::row(self::CODE_FIXED_ASSETS, 'Fixed Assets', $asset, $debit),
            self::row(self::CODE_ACCUMULATED_DEPRECIATION, 'Accumulated Depreciation', $asset, $credit),

            self::row(self::CODE_ACCOUNTS_PAYABLE, 'Accounts Payable', $liability, $credit),
            self::row(self::CODE_VAT_OUTPUT, 'VAT Output / Payable', $liability, $credit),
            self::row(self::CODE_PAYE_PAYABLE, 'PAYE Payable', $liability, $credit),
            self::row(self::CODE_UIF_PAYABLE, 'UIF Payable', $liability, $credit),
            self::row(self::CODE_LOANS_PAYABLE, 'Loans Payable', $liability, $credit),

            self::row(self::CODE_SHARE_CAPITAL, "Share Capital / Owner's Equity", $equity, $credit),
            self::row(self::CODE_RETAINED_EARNINGS, 'Retained Earnings', $equity, $credit),
            self::row(self::CODE_DRAWINGS, 'Drawings', $equity, $debit),

            self::row(self::CODE_SALES_REVENUE, 'Sales / Service Revenue', $income, $credit),
            self::row(self::CODE_OTHER_INCOME, 'Other Income', $income, $credit),

            self::row(self::CODE_COST_OF_SALES, 'Cost of Sales', $expense, $debit),
            self::row(self::CODE_SALARIES_WAGES, 'Salaries & Wages', $expense, $debit),
            self::row(self::CODE_RENT, 'Rent', $expense, $debit),
            self::row(self::CODE_UTILITIES, 'Utilities', $expense, $debit),
            self::row(self::CODE_BANK_CHARGES, 'Bank Charges', $expense, $debit),
            self::row(self::CODE_PROFESSIONAL_FEES, 'Professional Fees', $expense, $debit),
            self::row(self::CODE_MARKETING, 'Marketing', $expense, $debit),
            self::row(self::CODE_OFFICE_SUPPLIES, 'Office Supplies', $expense, $debit),
            self::row(self::CODE_TRAVEL_MOTOR, 'Travel & Motor', $expense, $debit),
            self::row(self::CODE_INSURANCE, 'Insurance', $expense, $debit),
            self::row(self::CODE_DEPRECIATION, 'Depreciation', $expense, $debit),
            self::row(self::CODE_OTHER_EXPENSES, 'Other Expenses', $expense, $debit),
        ];
    }

    /**
     * @return array{code:string,name:string,type:AccountType,normalBalance:NormalBalance}
     */
    private static function row(string $code, string $name, AccountType $type, NormalBalance $normalBalance): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type, 'normalBalance' => $normalBalance];
    }
}
