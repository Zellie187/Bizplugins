# BizHub Platform
## System Context Diagram

**Document ID:** BIZHUB-ARCH-014  
**Version:** 1.0.0  
**Status:** Approved

---

# 1. Purpose

This document defines the external systems, users, and integrations that interact with the BizHub platform.

---

# 2. Context Diagram

```
                     +----------------------+
                     |      Clients         |
                     +----------+-----------+
                                |
                                |
                    HTTPS / Browser / Mobile
                                |
                                ▼
                     +----------------------+
                     |      BizHub          |
                     |      Platform        |
                     +----------+-----------+
                                |
        ------------------------------------------------------
        |            |            |            |             |
        ▼            ▼            ▼            ▼             ▼
 WooCommerce     WordPress      Email      Payment      Government
                 Core           Services    Gateway        APIs
        |                         |            |             |
        ▼                         ▼            ▼             ▼
   Orders                     Notifications  Payments     CIPC / SARS
```

---

# 3. Primary Actors

## Client

Capabilities:

- Register
- Purchase services
- Upload documents
- Track applications
- Download completed documents
- Receive notifications

---

## Staff

Capabilities:

- Review applications
- Process workflows
- Upload documents
- Communicate with clients
- Approve requests

---

## Manager

Capabilities:

- Assign work
- Monitor KPIs
- Review reports
- Configure services

---

## Administrator

Capabilities:

- Manage users
- Configure platform
- Manage plugins
- View audit logs
- Perform maintenance

---

# 4. External Systems

| System | Purpose |
|----------|---------|
| WordPress | CMS |
| WooCommerce | Commerce |
| SMTP Provider | Email |
| Payment Gateway | Payments |
| CIPC | Company Registration |
| SARS | Tax Services |
| Cloud Storage | Documents |

---

# 5. External Interfaces

Supported interfaces:

- REST API
- HTTPS
- Email
- Webhooks
- Scheduled Jobs

---

# Version History

| Version | Date | Description |
|----------|------|-------------|
|1.0.0|2026-07-13|Initial System Context|