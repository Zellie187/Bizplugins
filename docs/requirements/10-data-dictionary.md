# BizHub Platform
# Data Dictionary

**Document ID:** BIZHUB-SRS-010  
**Version:** 1.0.0  
**Status:** Approved  
**Standard:** IEEE 29148

---

# 1. Purpose

This document defines the logical business entities and common data attributes used throughout the BizHub platform. It provides a consistent vocabulary for development, testing, reporting, and future database design.

---

# Client

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | BIGINT | Yes | Unique client identifier |
| wp_user_id | BIGINT | Yes | Associated WordPress user |
| first_name | VARCHAR(100) | Yes | Client first name |
| last_name | VARCHAR(100) | Yes | Client surname |
| email | VARCHAR(255) | Yes | Primary email address |
| mobile_number | VARCHAR(25) | No | Mobile telephone number |
| created_at | DATETIME | Yes | Record creation date |
| updated_at | DATETIME | Yes | Last modification date |

---

# Company

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| company_id | BIGINT | Yes | Company identifier |
| registration_number | VARCHAR(50) | Yes | Official registration number |
| company_name | VARCHAR(255) | Yes | Registered company name |
| status | VARCHAR(50) | Yes | Current status |
| incorporation_date | DATE | No | Registration date |
| created_at | DATETIME | Yes | Record creation date |

---

# Director

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| director_id | BIGINT | Yes | Director identifier |
| company_id | BIGINT | Yes | Related company |
| first_name | VARCHAR(100) | Yes | Director first name |
| last_name | VARCHAR(100) | Yes | Director surname |
| id_number | VARCHAR(30) | Yes | National identification number |
| appointment_date | DATE | Yes | Appointment date |
| status | VARCHAR(25) | Yes | Active or inactive |

---

# Service

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| service_id | BIGINT | Yes | Service identifier |
| product_id | BIGINT | Yes | WooCommerce product |
| service_name | VARCHAR(255) | Yes | Business service name |
| category | VARCHAR(100) | Yes | Service category |
| active | BOOLEAN | Yes | Availability flag |

---

# Application

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| application_id | BIGINT | Yes | Application identifier |
| client_id | BIGINT | Yes | Requesting client |
| company_id | BIGINT | No | Related company |
| service_id | BIGINT | Yes | Requested service |
| workflow_status | VARCHAR(50) | Yes | Current workflow state |
| submitted_at | DATETIME | Yes | Submission timestamp |

---

# Document

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| document_id | BIGINT | Yes | Document identifier |
| application_id | BIGINT | Yes | Parent application |
| filename | VARCHAR(255) | Yes | Stored filename |
| original_filename | VARCHAR(255) | Yes | Uploaded filename |
| mime_type | VARCHAR(100) | Yes | MIME type |
| file_size | BIGINT | Yes | File size in bytes |
| uploaded_at | DATETIME | Yes | Upload timestamp |

---

# Workflow

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| workflow_id | BIGINT | Yes | Workflow identifier |
| application_id | BIGINT | Yes | Related application |
| current_state | VARCHAR(50) | Yes | Current workflow stage |
| assigned_user | BIGINT | No | Assigned staff member |
| updated_at | DATETIME | Yes | Last update |

---

# Audit Log

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| audit_id | BIGINT | Yes | Audit identifier |
| user_id | BIGINT | Yes | User performing action |
| action | VARCHAR(255) | Yes | Action performed |
| entity | VARCHAR(100) | Yes | Affected entity |
| entity_id | BIGINT | Yes | Entity identifier |
| ip_address | VARCHAR(45) | Yes | Client IP address |
| created_at | DATETIME | Yes | Event timestamp |

---

# Version History

| Version | Date | Description |
|----------|------|-------------|
|1.0.0|2026-07-13|Initial Data Dictionary|