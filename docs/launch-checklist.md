# Pre-Production Launch & Cutover Checklist

**Important**: This repository currently implements a **fictional demonstration** (`Nusry Foundation`). Never convert demo mode to live operation, disable `noindex`, or deploy externally without explicit stakeholder authorization and completed items below.

## 1. Demo Guardrails & Provider Replacement
- [ ] Obtain explicit written launch authorization before provisioning production hosting or DNS.
- [ ] Purge all synthetic demo entities (`demo-editor`, `demo-donor`, `DEMO-*` gifts, illustrative reports/countries, `"Fictional Demo"` notices) and seed real approved legal copy/org entity details.
- [ ] Replace `MockDonationGateway` with a hosted payment provider integration (`DonationGatewayInterface`) supporting verified cryptographic webhooks, server-side idempotency, and zero raw card storage (PCI SAQ-A boundary).
- [ ] Replace `MockNewsletterGateway` with an authenticated transactional email/marketing API and optional verified SMS provider (`NewsletterGatewayInterface`), removing local mail-catcher links from HTTP responses.
- [ ] Verify real charitable registration, privacy policy, terms of use, and jurisdiction-specific tax-receipt wording with legal counsel.

## 2. Security & Identity Hardening
- [ ] Configure TLS/HTTPS termination at CDN/WAF and set secure cookie flags (`SameSite=Lax`, `HttpOnly`, `Secure`).
- [ ] Enforce 2FA/SSO for all `editor`, `contributor`, `donor_services`, and `administrator` accounts; disable passwordless demo ULI patterns outside local dev.
- [ ] Review `Content-Security-Policy-Report-Only` violation telemetry in staging, remove any temporary `'unsafe-inline'` if eliminated, and promote header to enforced `Content-Security-Policy`.
- [ ] Confirm `hash_salt` and DB/API secrets live exclusively in host environment secret storage (never in Git or `config/sync`).

## 3. Infrastructure, Caching, Monitoring & Backups
- [ ] Provision MariaDB 10.11+, Redis 7+, CDN edge caching honoring `Cache-Control` / Drupal cache tags/contexts, and scheduled Drupal cron (`drush cron`).
- [ ] Remove `NoindexResponseSubscriber`'s global `X-Robots-Tag: noindex, nofollow` ONLY on public marketing pages after legal launch sign-off, while keeping `noindex, nofollow` and `private, no-store` on `/portal*`, `/donate/receipt/*`, `/donor-services*`, `/newsletter/confirm/*`, and `/user/*`.
- [ ] Run staging k6 baseline + `50x` surge test (`tests/load/k6-baseline-and-surge.js`) against CDN + PHP-FPM + Redis + MariaDB; verify p95 latency, `<1%` errors, and origin cache-hit ratio.
- [ ] Enable automated daily encrypted MariaDB + `private/` backups and verify non-prod restore drill (`RPO`/`RTO` verified).
- [ ] Configure Sentry/redacted log aggregation and external uptime alerts for `/`, `/search`, `/donate`, and cron health.
- [ ] Confirm data-retention cron cleanup (unconfirmed opt-in tokens 7d, contact submissions 90d, redacted logs 30d) and documented DSAR identity verification + backup-expiry deletion workflow.
