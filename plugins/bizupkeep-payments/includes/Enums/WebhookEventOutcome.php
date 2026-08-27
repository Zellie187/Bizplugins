<?php

declare(strict_types=1);

namespace BizHub\Payments\Enums;

/**
 * A gateway-agnostic classification of what a parsed webhook event
 * means, independent of each gateway's own event-type vocabulary
 * (Yoco's "payment.succeeded", SnapScan's "completed" status, etc.).
 *
 * @package BizHub\Payments\Enums
 */
enum WebhookEventOutcome
{
    case Succeeded;
    case Failed;
    case Pending;
}
