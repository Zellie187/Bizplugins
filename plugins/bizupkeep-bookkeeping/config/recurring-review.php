<?php

declare(strict_types=1);

/**
 * Recurring-transaction review-ready email template, sent by
 * BizHub\Bookkeeping\Recurring\RecurringTransactionService::generateDueOccurrences()
 * whenever a company gets one or more new pending occurrences.
 * {placeholders} are substituted at dispatch time. Mirrors the shape of
 * config/reminders.php.
 *
 * @return array{subject:string,body:string}
 */
return [
    'subject' => 'Recurring transactions ready for review',
    'body' => '{count} recurring transaction(s) for {company_name} are ready for review. '
        . 'Confirm or skip each one before it is added to your books: {review_url}',
];
