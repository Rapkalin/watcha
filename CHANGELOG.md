# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] — 2026-06-28

### Added
- "Version inexistante" badge on the site detail page and the site list when a manually entered
  version has been checked and is not a published release of the technology (persisted as
  `Site.manualVersionExists`).

### Fixed
- Advisory synchronisation aborting on a duplicate advisory id: OSV can return the same id more than
  once in a single run and, as nothing is flushed until the end, the duplicate was persisted twice
  and violated the unique `(source, external_id)` index — leaving the advisory table empty and no
  CVEs for any site. Occurrences are now de-duplicated within a run (`AdvisorySynchronizer`).
- The latest stable version is now recorded as soon as the technology is known, even when the
  entered version is missing or does not exist (previously the lookup was skipped for an invalid
  version, leaving "dernière version connue" empty).

## [1.3.0] — 2026-06-26

### Added
- Page-availability checker: a "Scanner les pages" button reads the site's `/sitemap.xml` (following
  a sitemap index one level deep) and reports the HTTP status of every listed page (which return
  200 and which fail, with the error code). Checks run concurrently through the SSRF-safe HTTP
  client, are bounded (200 pages, 10 sub-sitemaps), and are stored as history (`PageScan` /
  `PageResult`) on a dedicated page per site.

### Changed
- Scans are now driven by the technology and version the owner enters by hand. The "Scanner (CVE)"
  button stays disabled until both are set; clicking it looks up matching CVEs and the latest stable
  release for that version. Creating a site now redirects to the technology/version form.

### Removed
- Automatic technology/version detection by URL scanning (the `App\Service\Detection` layer and its
  detectors). It could not read the version of Symfony/Laravel sites anyway; the manual entry it was
  paired with is now the single source of truth.

## [1.2.0] — 2026-06-26

### Added
- Manual version validation: when a version is set by hand, scanning (or saving the override) now
  checks it against the technology's published releases. An unknown version is reported and CVE
  matching is skipped rather than run against a version that does not exist; a valid version
  unlocks CVE matching as before. Releases are looked up on Packagist (Symfony/Laravel/Drupal) and
  wordpress.org (WordPress), cached for an hour; an unreachable source is treated as "unverified"
  so a real version is never wrongly rejected (`LatestVersionResolver::versionExists`).

### Fixed
- **Security (SSRF):** the site scanner now refuses to connect to private, loopback and link-local
  addresses — re-checked on every redirect — via a dedicated `NoPrivateNetworkHttpClient`
  (`app.scanner_http_client`) used by `SiteScanner` and `WordPressDetector`. URLs are also
  restricted to the `http`/`https` schemes. Previously any approved user could make the server
  fetch internal hosts (e.g. cloud metadata at `169.254.169.254`) by registering a crafted URL.

### Changed
- The scanner/feed `User-Agent` (`APP_HTTP_USER_AGENT`) defaults to a browser-like value so fewer
  sites/WAFs refuse the scan; switch it back to an identifying `watcha/...` string if preferred.

## [1.1.0] — 2026-06-24

### Added
- Transactional e-mails (Symfony Mailer): a central `App\Service\Mail\Mailer` that logs transport
  failures instead of breaking the triggering action, a shared e-mail layout
  (`templates/emails/`), and a configurable default sender via `MAILER_FROM`. E-mails sent:
  e-mail address verification on sign-up, "account pending approval" to maintainers/admins,
  "account approved" to the user, and a per-owner digest of new CVE/update alerts.
- E-mail address verification on registration via `symfonycasts/verify-email-bundle` (signed,
  expiring links; `User.emailVerified`), with the verification route `/register/verify`.
- Forgot-password flow via `symfonycasts/reset-password-bundle`: request page, confirmation page
  and tokenised reset link e-mailed to the user (`/reset-password`), plus a "forgot password?" link
  on the login page. The request page never reveals whether an account exists.
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

[1.4.0]: https://github.com/Rapkalin/watcha/releases/tag/1.4.0
[1.3.0]: https://github.com/Rapkalin/watcha/releases/tag/1.3.0
[1.2.0]: https://github.com/Rapkalin/watcha/releases/tag/1.2.0
[1.1.0]: https://github.com/Rapkalin/watcha/releases/tag/1.1.0
[1.0.0]: https://github.com/Rapkalin/watcha/releases/tag/1.0.0
