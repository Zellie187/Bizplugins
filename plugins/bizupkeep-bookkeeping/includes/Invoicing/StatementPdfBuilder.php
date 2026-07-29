<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a customer's statement of account (every invoice in a date
 * range, plus a running balance) as a PDF via Dompdf.
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class StatementPdfBuilder
{
    public function __construct(
        private readonly CompanyServiceInterface $companies,
        private readonly CompanySettingsRepositoryInterface $companySettings
    ) {
    }

    /**
     * @param Invoice[] $invoices
     */
    public function build(string $companyUuid, Customer $customer, DateRange $range, array $invoices): string
    {
        $company = $this->companies->getCompany($companyUuid);
        $vatNumber = $this->companySettings->findByCompanyUuid($companyUuid)?->vatNumber;

        return $this->renderPdf($this->renderHtml($company, $vatNumber, $customer, $range, $invoices));
    }

    /**
     * @param Invoice[] $invoices
     */
    private function renderHtml(
        Company $company,
        ?string $vatNumber,
        Customer $customer,
        DateRange $range,
        array $invoices
    ): string {
        $rows = '';
        $balance = Money::zero();

        foreach ($invoices as $invoice) {
            if ($invoice->status === InvoiceStatus::Void) {
                continue;
            }

            $balance = $invoice->status === InvoiceStatus::Paid ? $balance : $balance->add($invoice->total);

            $rows .= '<tr>'
                . '<td>' . esc_html($invoice->invoiceDate->format('Y-m-d')) . '</td>'
                . '<td>' . esc_html($invoice->invoiceNumber) . '</td>'
                . '<td>' . esc_html($invoice->status->label()) . '</td>'
                . '<td class="num">' . esc_html($invoice->total->format()) . '</td>'
                . '</tr>';
        }

        $vatNumberLine = $vatNumber !== null
            ? '<p>VAT No: ' . esc_html($vatNumber) . '</p>'
            : '';

        return '<html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<div class="header">'
            . '<div class="from">'
            . '<h2>' . esc_html($company->getCompanyName()) . '</h2>'
            . '<p>Reg No: ' . esc_html($company->getRegistrationNumber()) . '</p>'
            . $vatNumberLine
            . '</div>'
            . '<div class="statement-meta">'
            . '<h1>STATEMENT OF ACCOUNT</h1>'
            . '<p>' . esc_html($range->from?->format('Y-m-d') ?? 'Inception') . ' to '
            . esc_html($range->to->format('Y-m-d')) . '</p>'
            . '</div>'
            . '</div>'
            . '<div class="bill-to">'
            . '<h3>Customer</h3>'
            . '<p>' . esc_html($customer->name) . '</p>'
            . '<p>' . esc_html($customer->email) . '</p>'
            . '</div>'
            . '<table class="lines">'
            . '<thead><tr><th>Date</th><th>Invoice</th><th>Status</th><th class="num">Amount</th></tr></thead>'
            . '<tbody>' . $rows
            . '<tr class="total"><td colspan="3" class="label">Balance Due (unpaid invoices)</td>'
            . '<td class="num">' . esc_html($balance->format()) . '</td></tr>'
            . '</tbody></table>'
            . '</body></html>';
    }

    private function css(): string
    {
        return 'body { font-family: sans-serif; font-size: 12px; color: #222; }'
            . '.header { display: flex; justify-content: space-between; margin-bottom: 20px; }'
            . '.statement-meta { text-align: right; }'
            . 'h1 { margin: 0; font-size: 22px; }'
            . 'h2 { margin: 0 0 5px; }'
            . 'p { margin: 2px 0; }'
            . 'table.lines { width: 100%; border-collapse: collapse; margin-top: 20px; }'
            . 'table.lines th, table.lines td { border-bottom: 1px solid #ccc; padding: 6px; text-align: left; }'
            . 'table.lines .num { text-align: right; }'
            . 'table.lines .label { text-align: right; font-weight: bold; }'
            . 'table.lines .total td { border-top: 2px solid #222; font-weight: bold; }';
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
