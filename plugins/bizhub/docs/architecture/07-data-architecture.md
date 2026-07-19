# BizHub Platform
## Data Architecture

Document ID: BIZHUB-ARCH-007
Version: 1.0.0
Status: Approved
Sprint: 000 – Software Requirements & Architecture

---

# 1. Purpose

This document defines the logical data architecture for the BizHub platform. It establishes data ownership, storage standards, naming conventions, relationships, and lifecycle management for all persistent data.

---

# 2. Data Architecture Principles

The platform shall adhere to the following principles:

- Single source of truth
- Data ownership by module
- Normalized relational storage
- Immutable audit records
- Secure storage of sensitive information
- Encryption where appropriate
- Referential integrity
- Minimal duplication

---

# 3. Data Categories

| Category | Description |
|----------|-------------|
| Master Data | Clients, Companies, Users |
| Transaction Data | Orders, Applications, Payments |
| Operational Data | Workflows, Tasks, Notifications |
| Documents | Uploaded files and generated documents |
| Audit Data | Security and activity logs |
| Configuration | System and module settings |

---

# 4. Primary Data Entities

The platform consists of the following primary entities:

- Client
- Company
- Service
- Application
- Order
- Payment
- Workflow
- Task
- Document
- Notification
- User
- Role
- Audit Log

---

# 5. Database Ownership

Each module owns:

- Its tables
- Its indexes
- Its migrations
- Its repositories

Modules shall not modify another module's schema.

---

# 6. Naming Standards

Custom tables shall use the following prefix:

```
wp_bizhub_
```

Examples:

```
wp_bizhub_clients
wp_bizhub_companies
wp_bizhub_documents
wp_bizhub_workflows
wp_bizhub_notifications
```

---

# 7. Primary Keys

All custom tables shall use:

- BIGINT UNSIGNED
- AUTO_INCREMENT
- Primary Key

---

# 8. Foreign Keys

Foreign keys shall be used where supported and shall enforce referential integrity.

---

# 9. Soft Deletes

Business records shall use soft deletion where legally permissible.

Required fields:

- deleted_at
- deleted_by

---

# 10. Audit Requirements

Every transactional entity shall maintain:

- created_at
- created_by
- updated_at
- updated_by

Sensitive records shall additionally record:

- approved_at
- approved_by

---

# 11. Document Storage

Documents shall store metadata only within the database.

Binary files shall remain in secure storage.

---

# 12. Data Retention

Retention policies shall comply with:

- POPIA
- Applicable South African legislation
- Business requirements

---

# Version History

| Version | Date | Description |
|----------|------|-------------|
|1.0.0|2026-07-13|Initial Data Architecture|