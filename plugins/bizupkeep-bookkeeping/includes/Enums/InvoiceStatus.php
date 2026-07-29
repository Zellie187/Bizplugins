<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Enums;

/**
 * Lifecycle of an invoice. Draft is editable and never posted to the
 * ledger; Sent is posted (Debit AR / Credit revenue) and immutable
 * like any other journal entry; Paid is posted a second time (Debit
 * Bank-Cash / Credit AR); Void only ever applies to a Draft, since a
 * Sent/Paid invoice is already a real, immutable ledger posting.
 *
 * @package BizHub\Bookkeeping\Enums
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }
}
