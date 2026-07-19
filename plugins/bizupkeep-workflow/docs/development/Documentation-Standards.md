# Documentation Standards

## This documentation set's own ground rule

Every document under `docs/` must be true to the actual, current code. Where a feature is only designed and not yet built (the other 15 workflow types, the queue system, automation, most of the UI), the relevant document says so explicitly and near the top — never silently describing a plan as a fact. Three explicit states are used throughout this documentation set, and every document should make clear which one applies to what it describes:

- **Implemented** — exists in `includes/`, is exercised by at least one passing test, and is what actually runs today (e.g. the Company Registration workflow, the workflow engine, the REST API's four endpoints).
- **Specified** — a design document good enough to build from (Input/Validation/Preconditions/Business Rules/State Changes/Events/Notifications/Rollback/Completion Criteria/Audit Logging, per `Workflow-Standards.md`), but not yet implemented (the other 15 workflow types).
- **Planned** — a named future capability without a concrete design yet (the queue system, automation, the client portal UI).

## Docblocks explain *why*, not just *what*

Match the standard already set in the source: `WorkflowManager`'s class docblock explains its role as the engine's Facade and *why* every workflow type funnels through it; `Container/definitions.php`'s comment explains *why* `WorkflowManager` is bound the way it is, not merely that it is. A docblock that only restates a method's name and parameters in prose is not meeting this bar.

## Code examples must be copied from real source, never invented

Every code fence in this documentation set should be a verbatim (or lightly trimmed for length) excerpt from an actual file in this repository, with real class names, real method signatures, and real table/column names — not a plausible-looking approximation. If a document needs to illustrate a not-yet-built concept, it should describe the shape in prose and explicitly flag that no such code exists yet, rather than fabricating a code sample that looks implemented.

## Structure and headings

GitHub-flavored Markdown, `#` for the document title, `##` for major sections, `###` sparingly for sub-points. Tables are preferred over prose for anything enumerable (status lists, capability grants, endpoint tables). Mermaid diagrams (` ```mermaid ` fences) are used for state graphs and entity-relationship diagrams, since they render natively wherever this documentation is viewed.

## Cross-referencing

Documents reference each other by filename (e.g. "see `Audit.md`") rather than duplicating content — the audit log field list lives once, in `docs/security/Audit.md`, and every other document that touches logging points there instead of re-listing the fields.

## Keeping documentation honest over time

When a "specified" or "planned" item in this set is actually implemented, the owning document(s) must be updated in the same change that ships the code — moving it from "specified" to "implemented," adding real test references, and removing any "not yet built" language. Documentation drift (a doc claiming something works when it does not, or vice versa) is treated as a defect, not a formatting nitpick.
