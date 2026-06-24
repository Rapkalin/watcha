# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-06-24

### Added
- Transactional e-mails (Symfony Mailer): a central `App\Service\Mail\Mailer` that logs transport
  failures instead of breaking the triggering action, a shared e-mail layout
  (`templates/emails/`), and a configurable default sender via `MAILER_FROM`. E-mails sent:
  e-mail address verification on sign-up, "account pending approval" to maintainers/admins,
  "account approved" to the user, and a per-owner digest of new CVE/update alerts.
- E-mail address verification on registration via `symfonycasts/verify-email-bundle` (signed,
  expiring links; `User.emailVerified`), with the verification route `/register/verify`.
- Idempotent `app:admin:bootstrap` console command that creates the default admin from
  `ADMIN_EMAIL`/`ADMIN_PASSWORD` only when no approved admin exists. Wired into the deploy script
  (after migrations) and `make fixtures-admin`, so admin creation uses the same mechanism
  everywhere.
- Bot/spam protection on the authentication forms: login throttling (`login_throttling`), a
  honeypot field on the registration form, and per-IP rate limiting of sign-ups
  (`config/packages/rate_limiter.yaml`).
- Per-owner e-mail digest of new alerts, sent at the end of `app:sites:scan` (batch only, never
  during a web request). `SiteAlert.notifiedAt` tracks delivery and a reopened alert is notified
  again.

### Changed
- Login is gated by admin approval only; e-mail verification is informational and no longer blocks
  login. An admin can approve an account whose e-mail is not yet verified, and the admin user list
  shows each account's e-mail verification status.
- Maintainers/admins are notified of a new account at registration time (the e-mail states whether
  the address has been confirmed).
- `make fixtures-admin` now uses `app:admin:bootstrap` (driven by `ADMIN_EMAIL`/`ADMIN_PASSWORD`)
  instead of hard-coded credentials.

### Fixed
- Development Docker PHP image upgraded from PHP 8.3 to 8.4 to match the project's `>=8.4`
  requirement (the application failed to boot in the container otherwise).

## [1.0.0] Project init (MVP) — 2026-06-22

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

[1.1.0]: https://github.com/Rapkalin/watcha/releases/tag/1.1.0
[1.0.0]: https://github.com/Rapkalin/watcha/releases/tag/1.0.0
