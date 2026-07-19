# Accessibility

## What exists today

The only UI this plugin ships is `WorkflowAdminMenu`'s list table (see `Admin.md`), rendered as plain server-side HTML using WordPress's own `wp-list-table widefat fixed striped` CSS classes — the same table styling WordPress core's own admin list screens use. It contains no custom JavaScript, no custom CSS, and no interactive widgets: it is a single static `<table>` with a `<thead>`/`<tbody>`, populated via `esc_html()`-escaped output. Because it reuses WordPress core's own table markup and styling rather than introducing bespoke components, it inherits whatever baseline accessibility WordPress core's admin table styling already provides (semantic table structure, sufficient default contrast, no custom keyboard traps) without this plugin needing to do anything extra — but no accessibility-specific testing (screen reader pass, contrast audit, keyboard-navigation audit) has been performed on it.

## What a future, richer UI would need to address

Every other document in `docs/ui/` (`ClientPortal.md`, `Dashboard.md`, `WorkflowBoard.md`, `Forms.md`, `Notifications.md`) describes interfaces that do not exist yet. If and when they are built, they should meet at least WCAG 2.1 AA as a baseline, consistent with WordPress core's own admin accessibility standards:

- **`WorkflowBoard.md`'s drag-and-drop board** is the highest accessibility risk of the proposed surfaces — any implementation must provide a non-drag (keyboard- and screen-reader-operable) equivalent path to perform the same action (e.g. an accessible menu/button alternative to dragging a card between columns), not drag-and-drop as the only interaction model.
- **`Forms.md`'s action forms** must use properly associated `<label>` elements, visible focus states, and server-side validation errors (422/409 responses, see `docs/api/Examples.md`) surfaced in a way assistive technology can announce (e.g. `aria-live` regions or focus-management to the error), not color-only error indication.
- **`ClientPortal.md`'s status displays** should not rely on color alone to convey `WorkflowStatus` (e.g. red for cancelled, green for completed) — `WorkflowStatus::label()` already provides a text label for every status specifically so status is never communicated by color alone; any UI consuming it should always render that label alongside any color coding.
- **`Dashboard.md`'s aggregate views** (charts, counts) should always provide the underlying data in an accessible tabular form alongside any visual chart, not chart-only.

## No accessibility tooling or CI check exists yet

There is no automated accessibility linting (e.g. `axe-core`) wired into this plugin's test or CI setup (see `docs/deployment/CI-CD.md`) — accessibility compliance for any future UI work would need to be verified manually or by adding such tooling explicitly.
