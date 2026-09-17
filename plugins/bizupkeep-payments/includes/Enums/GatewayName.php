<?php

declare(strict_types=1);

namespace BizHub\Payments\Enums;

/**
 * A supported payment gateway. Adding a third gateway later means
 * adding a case here plus a Gateways/<Name>/<Name>Gateway.php
 * implementation of PaymentGatewayInterface - nothing else in this
 * plugin (or in bizupkeep-bookkeeping's PaymentMethod::Online) needs
 * to change.
 *
 * @package BizHub\Payments\Enums
 */
enum GatewayName: string
{
    case Yoco = 'yoco';
    case SnapScan = 'snapscan';
}
