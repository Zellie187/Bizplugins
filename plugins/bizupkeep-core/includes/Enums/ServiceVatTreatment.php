<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Enums;

/**
 * How VAT applies to a Service's price, as a simple classification -
 * deliberately not a calculator. Consumers that need to actually split
 * a VAT-inclusive amount (e.g. BizUpKeep Bookkeeping) use their own
 * Vat\VatCalculator once they know a Service is Inclusive; this plugin
 * has no VAT math of its own.
 *
 * @package BizUpKeep\Core\Enums
 */
enum ServiceVatTreatment: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
    case None = 'none';
}
