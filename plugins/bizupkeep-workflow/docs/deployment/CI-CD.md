# CI/CD

## Status: no GitHub Actions workflow exists yet for this plugin

There is no `.github/workflows/` directory in this repository. Every quality check described here (`phpcs`, `phpstan`, `phpunit`) has been run manually/locally in this development session and verified clean/passing (32 tests, PHPStan level 6, clean PHPCS) — none of it runs automatically on push or pull request today.

## A model to copy: BizHub's own `php.yml`

The sibling BizHub plugin repository (`Bizhub_plugin`) already has a working CI setup at `.github/workflows/php.yml` that this plugin should mirror rather than reinvent:

```yaml
name: PHP CI
on:
  push:
    branches: [main, develop, "feature/**", "release/**", "hotfix/**"]
  pull_request:
    branches: [main, develop]
jobs:
  quality:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '${{ matrix.php-version }}', coverage: none, tools: composer }
      - run: composer validate --no-check-publish || true
      - run: composer install --prefer-dist --no-progress
      - run: find . -type f -name "*.php" -print0 | xargs -0 -n1 php -l
      - run: vendor/bin/phpcs
      - run: vendor/bin/phpstan analyse
      - run: vendor/bin/phpunit
```

BizHub also has `documentation.yml` and `release.yml` workflows; this plugin has an equivalent build/release script (see `Release-Checklist.md`) but no corresponding GitHub Actions automation for it yet either.

## What a CI workflow for this plugin should run, at minimum

Adapting the model above almost verbatim: `composer install` (this plugin's `composer.json` requires a path repository to `../Bizhub_plugin`, so CI would need that sibling checked out too, or the dependency published/vendored differently than the local path-repository setup used for development — this is a real setup detail to solve before CI can run `composer install` unattended), a PHP lint pass, then the three verified commands:

- `vendor/bin/phpcs` — clean today (PSR-12 + WordPress security sniffs, `phpcs.xml`).
- `vendor/bin/phpstan analyse` — clean today (level 6, `phpstan.neon`).
- `vendor/bin/phpunit` — 32 tests passing today (`phpunit.xml`).

## The `../Bizhub_plugin` path-repository dependency is the main CI blocker

`composer.json`'s `repositories` entry points at a relative path (`../Bizhub_plugin`) for `bizupkeep/bizhub-plugin: dev-main` — this works for local development where both plugin directories are checked out as siblings, but a CI runner needs an equivalent setup (checking out both repositories into that same relative layout, or switching to a proper package registry/VCS repository reference) before `composer install` can succeed unattended. This is the first thing to solve when actually adding a CI workflow, not an afterthought.
