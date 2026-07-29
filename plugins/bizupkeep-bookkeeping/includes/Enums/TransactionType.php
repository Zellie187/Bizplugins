<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * The two transaction types the client-facing capture form supports.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
