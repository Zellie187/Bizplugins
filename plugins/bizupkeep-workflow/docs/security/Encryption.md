# Encryption

## This module stores no sensitive/encrypted fields of its own

Everything `bizhub_workflow_instances` and `bizhub_workflow_transitions` store is **business-process state**, not secrets: a workflow's current status, small JSON metadata/context blobs (e.g. `{"documents_verified": true}`, `{"payment_reference": "PMT-1"}`, `{"reviewed_by": "Jane Reviewer"}`), free-text reasons, and actor/timestamp bookkeeping (see `docs/database/Tables.md`). None of this is a password, an API key, a payment card number, or an identity document — it is a record of *that* a review happened or a payment reference was supplied, not the underlying sensitive artifact itself (the uploaded documents, the actual payment instrument) which, if they exist, are owned and stored by other BizHub modules (e.g. `BizHub\Documents`) with their own storage and access-control story.

## No bespoke encryption code in this plugin

There is no encryption/decryption logic anywhere in `includes/` — no `openssl_encrypt()`/`sodium_*` calls, no "encrypted column" abstraction, and no key-management code. `metadata` and `context` columns are plain `LONGTEXT` JSON, readable by anyone with direct database access (subject to normal database-level access controls) or with the `workflow.view` capability via the REST API.

## Deferring to BizHub

Where genuinely sensitive data exists elsewhere in the platform (documents, payment credentials, personal identification data held by other modules), encryption at rest and in transit is that owning module's/BizHub's responsibility, not this plugin's. This plugin's `context`/`metadata` fields are designed to hold *references* to such data (a payment reference string, a reviewer's name) rather than the sensitive data itself — workflow definitions and guards should be written with this boundary in mind: a `TransitionGuardInterface` implementation should validate that a reference exists, not carry the sensitive payload through the workflow engine.

## Guidance for future workflow types

Anyone designing a new workflow type's `context`/`metadata` shape (see `docs/development/Workflow-Standards.md`) should keep this boundary intact: pass identifiers/references through the workflow engine, and let the module that actually owns sensitive data (documents, payments, personal records) handle its own encryption and access control. If a future workflow type genuinely needs to carry sensitive data through its context, that is a signal encryption support needs to be designed and added to this plugin explicitly — it does not exist today, and no workflow type should assume it is protected.
