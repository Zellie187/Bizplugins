<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * How a bank statement CSV expresses each transaction's amount - a
 * single signed column (positive=inflow, negative=outflow, the most
 * common South African bank export format) or separate debit/credit
 * columns.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum ImportAmountStyle: string
{
    case Signed = 'signed';
    case DebitCredit = 'debit_credit';
}
