# BizHub Platform
# Git Workflow

**Document ID:** BIZHUB-PROJ-002  
**Version:** 1.0.0

---

# Branch Strategy

## Main

Production-ready releases.

---

## Develop

Integration branch.

---

## Feature

Naming:

```
feature/<feature-name>
```

---

## Release

Naming:

```
release/x.y.z
```

---

## Hotfix

Naming:

```
hotfix/x.y.z
```

---

# Commit Message Format

```
type(scope): summary
```

Examples:

```
feat(core): add service registry

fix(api): validate permissions

docs(srs): update functional requirements

refactor(workflow): simplify transitions
```

---

# Pull Requests

Every pull request shall include:

- Description
- Linked issue
- Testing evidence
- Documentation updates

---

# Merge Policy

- No direct commits to main
- Code review required
- Automated checks must pass

---

# Version History

| Version | Date | Description |
|----------|------|-------------|
|1.0.0|2026-07-13|Initial Git Workflow|