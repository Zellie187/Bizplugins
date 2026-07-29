<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * What produced a journal entry - used for display and audit
 * filtering, never for behaviour branching (a posted entry behaves
 * identically regardless of source).
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum JournalSource: string
{
    case CaptureIncome = 'capture_income';
    case CaptureExpense = 'capture_expense';
    case Manual = 'manual';
    case Reversal = 'reversal';
    case OpeningBalance = 'opening_balance';
    case InvoiceIssued = 'invoice_issued';
    case InvoicePaymentReceived = 'invoice_payment_received';

    public function label(): string
    {
        return match ($this) {
            self::CaptureIncome => 'Income capture',
            self::CaptureExpense => 'Expense capture',
            self::Manual => 'Manual journal entry',
            self::Reversal => 'Reversal',
            self::OpeningBalance => 'Opening balance',
            self::InvoiceIssued => 'Invoice issued',
            self::InvoicePaymentReceived => 'Invoice payment received',
        };
    }
}
