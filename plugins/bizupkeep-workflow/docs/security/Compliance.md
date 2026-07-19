# Compliance

This document describes how this plugin's audit trail and rollback support relate to South African business-compliance recordkeeping expectations (CIPC company registration, SARS tax processes) that BizUpKeep operates alongside. It makes no claim to any specific legal certification, accreditation, or formal compliance status — none has been sought or obtained for this plugin, and this document should not be read as legal advice or a compliance guarantee.

## What the audit trail supports

CIPC and SARS-facing business processes generally expect a business to be able to demonstrate *when* a compliance step occurred, *who* performed it, and *why* a status changed — particularly for anything resembling a company registration, a director/address change, or a tax registration. The durable `bizhub_workflow_transitions` table (see `Audit.md`) is designed to support exactly this kind of retrospective demonstration: every state change carries an actor, a timestamp, a reason, and supporting context, and the table is append-only, so a full, ordered history of any single Company Registration workflow — including any rollback — can always be reconstructed and exported for a compliance review or an audit request.

## What rollback supports

Compliance processes are rarely perfectly linear in practice — a document later found to be incomplete, a payment recorded against the wrong reference, a review approved prematurely. `WorkflowManager::rollback()` gives operators a structurally-safe way to correct a single erroneous step (reverting to the immediately-prior status) without silently editing or deleting the original transition record: the rollback itself becomes a new, equally-audited transition (`action = 'rollback'`), so the correction is visible in the history, not hidden.

## What this plugin does not claim

- It does not claim POPIA (Protection of Personal Information Act) compliance as a certified status — see `Encryption.md` for why this module stores process state rather than personal information directly, but any personal information handled elsewhere in the platform is out of this plugin's scope to certify.
- It does not claim to satisfy any specific CIPC or SARS technical integration requirement — this plugin's workflows model a business's *internal* process around registration/compliance activities; it is not itself a CIPC/SARS system of record or an accredited filing channel.
- It does not provide legal retention-period enforcement (e.g. automatically retaining records for a statutory number of years) — data is retained indefinitely by default (tables are preserved across deactivation and only dropped on explicit uninstall opt-in, see `docs/deployment/Rollback.md`), but no automated retention *policy* enforcement exists.

## Practical positioning

Treat this plugin's audit trail as a well-structured internal record that a business can use to *support* its own compliance recordkeeping obligations and to answer "what happened and when" — not as a substitute for legal or regulatory advice about what South African law specifically requires a business to retain or demonstrate.
