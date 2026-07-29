<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;

/**
 * Emails an invoice PDF to a customer via wp_mail() directly, not
 * through BizHub's NotificationQueue/Notification system - that system
 * requires a recipientId resolving to a real WP user
 * (EmailNotification::send() calls get_userdata($id)->user_email) and
 * has no attachment support at all. A client's customer is an external
 * third party who will never be a WP user, so this bypasses that
 * abstraction entirely rather than forcing an external email address
 * through infrastructure that was never built for one.
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class InvoiceMailer
{
    public function sendInvoice(Customer $customer, Invoice $invoice, string $pdfBytes): void
    {
        $subject = sprintf('Invoice %s', $invoice->invoiceNumber);
        $body = sprintf(
            "Dear %s,\n\nPlease find attached invoice %s, due %s, for a total of %s.\n\nRegards",
            $customer->name,
            $invoice->invoiceNumber,
            $invoice->dueDate->format('Y-m-d'),
            $invoice->total->format()
        );

        $this->sendWithAttachment($customer->email, $subject, $body, $pdfBytes, $invoice->invoiceNumber . '.pdf');
    }

    private function sendWithAttachment(
        string $toEmail,
        string $subject,
        string $body,
        string $pdfBytes,
        string $filename
    ): void {
        // wp_tempnam() is deliberately not used here - it is only
        // defined in wp-admin/includes/file.php, which is never loaded
        // on the front-end, and this mailer is called from a
        // client-portal (front-end) form submission.
        $tempFile = tempnam(sys_get_temp_dir(), 'bizupkeep-invoice-');

        if ($tempFile === false) {
            return;
        }

        try {
            file_put_contents($tempFile, $pdfBytes);
            wp_mail($toEmail, $subject, $body, [], [$tempFile]);
        } finally {
            wp_delete_file($tempFile);
        }
    }
}
