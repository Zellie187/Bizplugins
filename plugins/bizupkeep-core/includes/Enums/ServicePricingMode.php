<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Enums;

/**
 * How a Service's price is determined. Purely descriptive metadata for
 * downstream consumers (e.g. BizUpKeep Bookkeeping's revenue
 * automation) - never used for behaviour branching in this plugin.
 *
 * @package BizUpKeep\Core\Enums
 */
enum ServicePricingMode: string
{
    case Fixed = 'fixed';
    case Quoted = 'quoted';
}
