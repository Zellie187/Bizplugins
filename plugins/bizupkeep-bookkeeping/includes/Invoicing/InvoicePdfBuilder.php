<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders one invoice as a PDF document via Dompdf - the ecosystem's
 * first real PDF-generation dependency (bizhub's own ExportPdf class
 * documents itself as a "print-styled HTML" stand-in for exactly this
 * requirement, since nothing else needed true PDF output before now).
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class InvoicePdfBuilder
{
    public function __construct(
        private readonly CompanyServiceInterface $companies,
        private readonly CompanySettingsRepositoryInterface $companySettings
    ) {
    }

    public function build(string $companyUuid, Customer $customer, Invoice $invoice): string
    {
        $company = $this->companies->getCompany($companyUuid);
        $vatNumber = $this->companySettings->findByCompanyUuid($companyUuid)?->vatNumber;

        return $this->renderPdf($this->renderHtml($company, $vatNumber, $customer, $invoice));
    }

    private function renderHtml(Company $company, ?string $vatNumber, Customer $customer, Invoice $invoice): string
    {
        $rows = '';

        foreach ($invoice->lines as $line) {
            $rows .= '<tr>'
                . '<td>' . esc_html($line->description) . '</td>'
                . '<td class="num">' . esc_html((string) $line->quantity) . '</td>'
                . '<td class="num">' . esc_html($line->unitPrice->format()) . '</td>'
                . '<td class="num">' . esc_html($line->lineTotal->format()) . '</td>'
                . '</tr>';
        }

        $vatRow = '';

        if ($invoice->includesVat) {
            $vatRow = '<tr><td colspan="3" class="label">Subtotal (excl. VAT)</td>'
                . '<td class="num">' . esc_html($invoice->subtotal->format()) . '</td></tr>'
                . '<tr><td colspan="3" class="label">VAT</td>'
                . '<td class="num">' . esc_html($invoice->vat->format()) . '</td></tr>';
        }

        $vatNumberLine = $vatNumber !== null
            ? '<p>VAT No: ' . esc_html($vatNumber) . '</p>'
            : '';

        $notes = $invoice->notes !== ''
            ? '<p class="notes">' . esc_html($invoice->notes) . '</p>'
            : '';

        return '<html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<div class="header">'
            . '<div class="from">'
            . '<h2>' . esc_html($company->getCompanyName()) . '</h2>'
            . '<p>Reg No: ' . esc_html($company->getRegistrationNumber()) . '</p>'
            . $vatNumberLine
            . '<p>' . esc_html($company->getRegisteredAddress()->getFormattedAddress()) . '</p>'
            . '</div>'
            . '<div class="invoice-meta">'
            . '<h1>INVOICE</h1>'
            . '<p><strong>' . esc_html($invoice->invoiceNumber) . '</strong></p>'
            . '<p>Date: ' . esc_html($invoice->invoiceDate->format('Y-m-d')) . '</p>'
            . '<p>Due: ' . esc_html($invoice->dueDate->format('Y-m-d')) . '</p>'
            . '</div>'
            . '</div>'
            . '<div class="bill-to">'
            . '<h3>Bill To</h3>'
            . '<p>' . esc_html($customer->name) . '</p>'
            . '<p>' . esc_html($customer->email) . '</p>'
            . '<p>' . esc_html(trim($customer->addressLine1 . ', ' . $customer->addressLine2)) . '</p>'
            . '<p>' . esc_html(trim($customer->suburb . ', ' . $customer->city)) . '</p>'
            . '<p>' . esc_html(trim($customer->province . ' ' . $customer->postalCode)) . '</p>'
            . '</div>'
            . '<table class="lines">'
            . '<thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit Price</th>'
            . '<th class="num">Amount</th></tr></thead>'
            . '<tbody>' . $rows . $vatRow
            . '<tr class="total"><td colspan="3" class="label">Total</td>'
            . '<td class="num">' . esc_html($invoice->total->format()) . '</td></tr>'
            . '</tbody></table>'
            . $notes
            . '</body></html>';
    }

    private function css(): string
    {
        return 'body { font-family: sans-serif; font-size: 12px; color: #222; }'
            . '.header { display: flex; justify-content: space-between; margin-bottom: 20px; }'
            . '.invoice-meta { text-align: right; }'
            . 'h1 { margin: 0; font-size: 24px; }'
            . 'h2 { margin: 0 0 5px; }'
            . 'p { margin: 2px 0; }'
            . 'table.lines { width: 100%; border-collapse: collapse; margin-top: 20px; }'
            . 'table.lines th, table.lines td { border-bottom: 1px solid #ccc; padding: 6px; text-align: left; }'
            . 'table.lines .num { text-align: right; }'
            . 'table.lines .label { text-align: right; font-weight: bold; }'
            . 'table.lines .total td { border-top: 2px solid #222; font-weight: bold; }'
            . '.notes { margin-top: 20px; color: #555; }';
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
