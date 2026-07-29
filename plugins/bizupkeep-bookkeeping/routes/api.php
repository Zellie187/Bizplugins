<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('bizhub') || bizhub() === null) {
    return;
}

/*
 * No routes registered yet - the client portal (astra-child theme) and
 * the admin screens consume this plugin's services directly via
 * bizhub()->container(), following the same pattern documented
 * throughout astra-child/functions.php. Add register_rest_route()
 * calls here if/when an external API consumer (e.g. a future mobile
 * app) needs one.
 */
