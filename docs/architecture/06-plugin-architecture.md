# BizHub Platform
## Plugin Architecture Specification

Document ID: BIZHUB-ARCH-006
Version: 1.0.0
Status: Approved

---

# 1. Purpose

Defines the standard structure for every BizHub plugin.

---

# 2. Standard Directory Structure

```
plugin-name/

assets/
    css/
    js/
    images/

config/

includes/

includes/Admin/

includes/API/

includes/Application/

includes/Domain/

includes/Infrastructure/

includes/Repositories/

includes/Services/

includes/Workflow/

languages/

templates/

tests/

vendor/

plugin-name.php

composer.json

readme.txt

CHANGELOG.md
```

---

# 3. Bootstrap File

Responsibilities

- Plugin metadata
- Autoload registration
- Service registration
- Activation hooks
- Deactivation hooks

---

# 4. Namespaces

Every class shall use:

```
BizHub\
```

Sub-namespaces:

```
Admin
API
Application
Domain
Infrastructure
Repositories
Services
Workflow
Portal
Notifications
Documents
```

---

# 5. Interfaces

All public services shall expose interfaces.

Concrete implementations shall remain internal.

---

# 6. Configuration

Configuration files shall reside inside:

```
config/
```

Configuration shall never be hardcoded.

---

# 7. Assets

CSS

```
assets/css
```

JavaScript

```
assets/js
```

Images

```
assets/images
```

---

# 8. Templates

Templates shall never contain business logic.

---

# 9. Testing

Every plugin shall include:

```
tests/
```

Containing:

- Unit Tests
- Integration Tests
- Fixtures

---

# 10. Documentation

Every plugin shall include:

- README
- CHANGELOG
- LICENSE
- Architecture Notes

---

# Version History

|Version|Date|Description|
|-------|----|-----------|
|1.0.0|2026-07-13|Initial Plugin Architecture|