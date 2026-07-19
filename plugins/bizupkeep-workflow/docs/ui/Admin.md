# Admin Screen

## Implemented: one screen, listing Company Registration workflows

`BizHub\Workflow\Admin\WorkflowAdminMenu` is the only UI this plugin ships. It registers a **submenu page** under BizHub's own top-level `bizhub` admin menu — not a second top-level menu entry:

```php
add_submenu_page(
    'bizhub',
    __('Workflows', 'bizupkeep-workflow'),
    __('Workflows', 'bizupkeep-workflow'),
    'manage_options',
    'bizupkeep-workflow',
    [$this, 'render']
);
```

Registered via `Plugin::boot()`'s `add_action('admin_menu', [new WorkflowAdminMenu(), 'register'])`.

## Access control

The page is gated by WordPress's native `manage_options` capability (checked both by `add_submenu_page()`'s own argument and again explicitly inside `render()` via `current_user_can('manage_options')`, calling `wp_die()` if it fails) — **not** by this plugin's own `workflow.*` capabilities. This is a deliberate choice distinct from the REST API's authorization model (see `docs/security/Permissions.md`): the admin screen is currently scoped to full site administrators only, regardless of who else holds `workflow.view`/`workflow.manage`.

## What it renders

A single `wp-list-table`-styled HTML table listing every Company Registration workflow instance, sourced from `WorkflowRepositoryInterface::summaries(CompanyRegistrationDefinition::TYPE)` (resolved from BizHub's container via `bizhub()?->container()->get(...)`), with columns: **UUID**, **Company** (the `subjectUuid`), **Status** (via `WorkflowStatus::label()`), **Created**, **Updated**. If no instances exist, a single "No Company Registration workflows yet" row is shown. All output is escaped with `esc_html()`.

## What it does not do

It is read-only — there are no action buttons, no way to transition or roll back a workflow from this screen, and no filtering/sorting/pagination beyond whatever `summaries()`'s default `limit = 50` provides. It only ever shows Company Registration workflows; there is no workflow-type selector, since Company Registration is the only workflow type registered with the engine today (see `docs/architecture/Module-Architecture.md`). It does not resolve `subjectUuid` into a human-readable company name — that would require depending on `BizHub\Companies`, which this admin screen deliberately does not do.

## Where this fits relative to the rest of `docs/ui/`

Every other document in this directory (`ClientPortal.md`, `Dashboard.md`, `WorkflowBoard.md`, `Forms.md`, `Notifications.md`, `Accessibility.md`) describes UI that does **not** exist yet — forward-looking design briefs only. This is the one document in `docs/ui/` describing something actually implemented and shipped in this plugin's `includes/Admin/` directory.
