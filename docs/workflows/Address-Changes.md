# Address Changes Workflow

**Status: specified, not yet implemented.** A CIPC-facing workflow for updating a company's registered or postal address, to be built as `AddressChangesDefinition`/`Guard`/`Service` under `includes/Workflows/AddressChanges/`.

**Type identifier (proposed)**: `address_changes`. **Subject**: `subject_type = 'company'`.

## Input

The address type being changed (registered office vs. postal address), the new address, and proof of the new address (e.g. a utility bill or lease, per CIPC's evidentiary requirements).

## Validation

New address is a complete, well-formed South African physical address (street, suburb, city, province, postal code); proof-of-address document is attached.

## Preconditions

The company must have an existing, completed registration on file.

## Business Rules

A registered office address change (unlike a postal address change) typically requires CIPC filing and cannot be a purely internal record update — the workflow definition should distinguish the two via `context`, with only the registered-office path requiring the `DocumentsVerified`/`Processing` (CIPC filing) stages, while a postal-address-only change could be a much shorter lifecycle.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (proof of address) → `DocumentsVerified` → `Processing` (CIPC filing, registered-office changes only) → `Completed` → `Archived`; `Cancelled` from any non-terminal status.

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact once the new address is confirmed and reflected (both internally and, where applicable, with CIPC).

## Rollback Behaviour

Single-step rollback; unavailable once a CIPC filing (for registered-office changes) has actually been submitted, mirroring the same caution described in `Director-Changes.md`.

## Completion Criteria

`Completed`: the new address is reflected in internal records and, for registered-office changes, confirmed by CIPC.

## Audit Logging

Standard engine logging; `context` should capture the address type (registered/postal) and both old and new address values for every transition, since "what was the address before this change" is a natural compliance question this workflow's own audit trail should answer without needing to consult a separate address-history table.
