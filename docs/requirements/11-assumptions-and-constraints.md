# BizHub Platform
# Assumptions and Constraints

**Document ID:** BIZHUB-SRS-011  
**Version:** 1.0.0  
**Status:** Approved

---

# 1. Purpose

This document records assumptions and constraints affecting the design, implementation, deployment, and maintenance of the BizHub platform.

---

# 2. Assumptions

## A-001

WordPress remains the primary application framework.

---

## A-002

WooCommerce provides all commerce functionality.

---

## A-003

PHP 8.2 or later is available in all supported environments.

---

## A-004

HTTPS is enforced in production.

---

## A-005

Reliable SMTP services are available.

---

## A-006

Scheduled WordPress Cron jobs execute successfully.

---

## A-007

GitHub is the authoritative source repository.

---

## A-008

All development follows Semantic Versioning.

---

# 3. Constraints

## C-001

WordPress core shall never be modified.

---

## C-002

Third-party plugins shall be replaceable without affecting core business logic.

---

## C-003

All custom functionality shall reside within BizHub plugins.

---

## C-004

All source code shall conform to WordPress Coding Standards.

---

## C-005

Every public endpoint shall be authenticated where applicable.

---

## C-006

All SQL shall use prepared statements.

---

## C-007

Sensitive configuration shall not be committed to source control.

---

## C-008

Production deployments shall use tagged Git releases.

---

# Version History

| Version | Date | Description |
|----------|------|-------------|
|1.0.0|2026-07-13|Initial Assumptions and Constraints|