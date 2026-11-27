# Security, Operations, and Hardening Runbook (Demo Baseline)

This document records the Phase 7 security, backup/recovery, monitoring, performance budget, and operational limitations for the fictional **Nusry Foundation** Drupal 11 demonstration.

## 1. Security Headers & CSP Progression
`Drupal\nusry_core\EventSubscriber\NoindexResponseSubscriber` attaches baseline security and demo headers to main HTTP responses:
- `X-Robots-Tag: noindex, nofollow` (keeps public demo previews non-indexable until an authorized live launch)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'`

Private personalized routes (`PrivateResponseHeaderSubscriber`) additionally enforce `Cache-Control: private, no-store` and `Pragma: no-cache` on `/donate/receipt/{reference}`, `/portal*`, `/donor-services*`, `/newsletter/confirm/{token}`, and `/newsletter/unsubscribe/{token}`.

## 2. Role Separation & Data Protection
- `editor` and `contributor` cannot access `/portal` or `/donor-services` (verified HTTP `403`).
- `demo_donor` accesses only personal portal history and receipts; cross-user record IDs return HTTP `403`.
- `donor_services` holds `access nusry donor services` only, with read lookup audit logging (no PII logged) and formula-injection CSV sanitization via `CsvSafety::escapeFormula()`.
- Secrets never live in repository files or `config/sync`; database credentials and `hash_salt` resolve from environment variables or `.local/`.

## 3. Backup, Restore & Rollback Procedure
- **Rehearsal command (local/CI SQLite)**:
  ```powershell
  $env:NUSRY_ENV='local'; .tools/php/php.exe scripts/rehearse-backup-restore.php
  ```
- Snapshots write outside the public document root (`web/`) to `.local/backups/` using SQLite `VACUUM INTO`, verify SHA-256 copy integrity and `PRAGMA integrity_check`, and confirm core/donation table row counts on an isolated restore target.
- **Deployment / Rollback Steps**:
  1. Take a pre-deploy database and `private/` backup before `drush updatedb` or `drush config:import`.
  2. Roll back non-reversible schema updates by restoring the pre-deploy database snapshot alongside the prior immutable artifact (reverting code alone is insufficient if `hook_update_N` altered tables).

## 4. Monitoring & Availability Checks
```powershell
node scripts/monitor-check.mjs
```
Probes `/`, `/search?q=maternal`, `/donate`, `/rss.xml`, `/sitemap.xml`, protected `/portal` (`403`), and confirms non-200 outage detection behavior. External paging/Sentry alerting requires host-level configuration upon an authorized production deployment.

## 5. Dependency Audits & Data Retention
- Run `composer audit` and `npm audit --audit-level=high` quarterly and on every CI pull request (`.github/workflows/validate.yml`).
- Proposed production retention defaults (pending legal review if a real entity launches): unconfirmed newsletter tokens deleted after `7 days`; contact submissions reviewed/purged after `90 days`; redacted operational logs rotated after `30 days`; confirmed opt-in subscribers retained until consent withdrawal.
