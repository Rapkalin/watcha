# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [INIT] Project init (MVP)

### Added
- GitHub Actions pipeline (`.github/workflows/ci.yml` + reusable `deploy.yml`) modeled after the
  pipeline: quality (cs-fixer, phpstan, lint, `composer audit`), test (PHPUnit + MySQL 8),
  secret detection (gitleaks), build (production artifact), and SSH+rsync deployment by environment
  (preprod on `main` push, production on tag push with manual approval). “In-place”
  deployment via the versioned script `infra/deploy/release.sh` (app in `website/`, secrets in
  `shared/.env`, `old_website/` snapshot as hard links for rollback). Daily CVE check (cron).
- Scripts Composer `cs-check`, `cs-fix`, `phpstan`.
- “Synchronize” button by technology (or all) from the Vulnerabilities page
  (reserved for maintainers/admins, CSRF-protected).
- Manual editing of a site's technology/version (site record). The manually entered value is
  stored separately from the auto-detected value: both are displayed side by side, the scan
  continues to update the auto-detected value without overwriting the manually entered one, and the actual version (manual ?? auto)
  enables CVE matching for Symfony/Laravel (versions not publicly exposed). Alerts
  are recalculated upon saving.
- Initial Watcha dashboard (Symfony 7.4, PHP 8.3, MySQL 8).
- Authentication with form login, self-registration and a manual approval workflow.
- Three role levels: `basic` (dashboard access), `maintainer` (view users + approve new accounts),
  `admin` (full user management).
- Site monitoring: add a URL, automatic technology/version detection (WordPress, Drupal,
  Symfony, Laravel) and on-demand re-scan. Symfony is detected via AssetMapper/Symfony UX
  fingerprints (works in production); WordPress version via meta generator, `?ver=` assets and
  the `/feed/` generator; Drupal via `X-Generator`, `drupalSettings` and `data-drupal-*`.
- Advisory synchronisation from OSV.dev (Packagist ecosystem) and WPScan (optional API token).
- Alert engine: raises CVE alerts when a detected version matches an advisory constraint and
  update alerts when a newer stable release exists; resolves alerts automatically when fixed.
- Console commands `app:cve:sync`, `app:sites:scan` and `app:user:create`.
- SCSS design system inspired by shadcn/ui (no Bootstrap/Tailwind).
- Docker development stack, GitHub Actions CI/CD (quality, tests, security, secret scanning,
  build, deploy) and 1&1 deployment guide.

[INIT]: https://github.com/Rapkalin/watcha/commits/main
