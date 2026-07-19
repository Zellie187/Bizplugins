<?php

declare(strict_types=1);

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-core.php.
 *
 * BizUpKeep Core currently has no services of its own to bind: it
 * ships as the platform's bootstrap/orchestration layer, with business
 * logic delivered by modules built on top of it (e.g. BizUpKeep
 * Workflow). This file exists so that future Core-owned services have
 * a contribution point ready without needing changes to
 * bizupkeep-core.php - mirrors the pattern BizUpKeep Workflow uses in
 * includes/Container/definitions.php.
 */
return [
];
