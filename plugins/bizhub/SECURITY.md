# Security Policy

## Supported Versions

BizHub is under active development. Security fixes are applied to the
latest released version only; there is no long-term support branch yet.

| Version | Supported |
| ------- | --------- |
| 0.2.x   | ✅        |
| < 0.2   | ❌        |

## Reporting a Vulnerability

If you discover a security vulnerability in BizHub, please report it
privately rather than opening a public issue.

- Contact the maintainers via the Author URI listed in the `bizhub.php`
  plugin header (https://bizupkeep.co.za), with a subject or message
  starting with `[SECURITY]`.
- Include a description of the vulnerability, steps to reproduce, and
  the potential impact.
- Do not disclose the issue publicly until a fix has been released.

We aim to acknowledge reports within 5 business days and to release a
fix or mitigation as soon as reasonably possible, depending on severity.

## Scope

This policy covers the BizHub plugin's own code (`includes/`). It does
not cover vulnerabilities in WordPress core, WooCommerce, Forminator,
or other third-party plugins BizHub integrates with — please report
those to their respective maintainers.

## Security Practices

BizHub follows these practices to reduce risk:

- All database access is routed through `BizHub\Framework\Database`,
  using prepared statements (`$wpdb->prepare()`) rather than raw SQL
  concatenation.
- All capability checks go through `BizHub\Security\Authorization`
  rather than calling WordPress capability functions directly.
- Passwords and secrets are handled via `BizHub\Security\Encryption`
  (`Hasher` for one-way hashing, `Encryptor` for reversible encryption
  using a per-installation key derived from `wp_salt()`).
- Form submissions and admin actions are nonce-verified and input is
  sanitized before use.
- Static analysis (PHPStan) and coding standards checks (PHPCS with
  WordPress security sniffs) run as part of the development workflow.
