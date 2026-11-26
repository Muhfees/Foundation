# Architecture proposal

Phase 1 only. Choices below are proposals for the confirmed Drupal stack; no infrastructure is provisioned.

## System diagram

```text
Anonymous visitor
  -> CDN + WAF (TLS, cache public pages/assets, bot controls)
    -> Managed Drupal origin: PHP 8.3 + Drupal 11
       -> Twig theme + Vite-built CSS / small vanilla JavaScript
       -> MariaDB (content, revisions, users, private submissions)
       -> Redis (Drupal cache bins, never a substitute for access control)
       -> Media storage + responsive image derivatives
       -> Search API database index -> later Solr adapter if required
       -> DonationGateway -> mock now / hosted donation subdomain later
       -> NewsletterGateway -> local mail catcher now / provider later
       -> private receipt storage, authenticated portal responses

Editor -> staff authentication -> draft -> review -> published revisions
Cron/queue -> scheduling, search indexing, mail, retention, cache invalidation
Consent manager -> permits analytics loader -> GTM -> approved events only

Pull request -> GitHub Actions -> immutable build artifact
                            -> dev -> test -> approved live release
```

## Decisions and reasons

- Server-rendered Drupal/Twig keeps content, editorial access rules and rendering together. Vanilla JS supplies menus, search interaction and donation mock state; Alpine is not needed initially.
- Paragraphs is the single editorial composition model. Avoid mixing unrestricted Layout Builder layouts with Paragraphs: constrained components protect accessibility and visual consistency.
- PHP 8.3 follows the brief. Verify its compatibility and security support again at installation and before launch. Drupal's current table supports it for listed Drupal 11 releases. Pin a supported stable Drupal 11 release and all dependencies in Composer lock, rather than using development branches. [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements).
- Plan MariaDB 10.11 as the local baseline, subject to the selected host's supported versions. Drupal 11 requires MariaDB 10.6+ or MySQL 8.0+. [Database requirements](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements).
- Current Paragraphs and Search API project pages advertise Drupal 11 support. Exact module versions and their transitive dependencies are a Phase 2 Composer resolution gate. [Paragraphs](https://www.drupal.org/project/paragraphs), [Search API](https://www.drupal.org/project/search_api).
- DDEV with Docker is the proposed local environment, including MariaDB, Redis and a local email catcher. Confirm Docker/WSL2 availability before creating its configuration. Hosting is a managed Drupal pattern, not a purchased vendor contract.

## Planned folder structure

Only README, DESIGN, docs and the sampling script exist at Phase 1. This is the proposed implementation tree:

```text
Nusry Foundation/
  Reference/                         original screenshots; not deployed
  README.md
  DESIGN.md
  docs/                              architecture, design, guides, QA
    screenshots/                     later visual evidence at 4 widths
  scripts/                           setup, checks, sampling, budgets
  .ddev/                             local services and commands
  .github/workflows/                 lint, tests, build, gated deploy
  composer.json / composer.lock      Drupal and PHP dependencies
  package.json / package-lock.json   Vite and browser test dependencies
  config/sync/                       exported Drupal configuration
  config/environments/               environment-specific config guidance
  web/                               only public document root
    modules/custom/
      nusry_core/                    validation, demo labels, schedules
      nusry_donations/               mock + hosted gateway interface
      nusry_forms/                   consent, opt-in, rate limiting
      nusry_portal/                  synthetic donor service
    themes/custom/nusry/
      templates/                     page, node, paragraph, form, views
      components/                    shared Twig partials
      src/styles/                    tokens, layout, components, print
      src/scripts/                   progressive enhancement
      assets/fonts/                  self-hosted licensed fonts
      dist/                          generated Vite assets and manifest
    sites/default/                   environment settings, not secrets
  tests/
    php/                             unit, kernel, functional tests
    e2e/                             Playwright + axe + screenshots
    load/                            k6 scripts
  private/                           ignored; non-public exports/receipts
```

## Drupal module strategy

Core: Media/Media Library, Responsive Image, Views, Taxonomy, CKEditor, Workflows, Content Moderation, language/config/content translation readiness. Contributed candidates: Paragraphs + Entity Reference Revisions, Pathauto + Token, Redirect, Metatag, Simple XML Sitemap, Search API, Redis and scheduling support compatible with moderated revisions. Evaluate maintained staff 2FA/SSO and audit logging modules before selection. Search facets use Views exposed filters initially. Do not install an abandoned or unverified module simply to satisfy this list.

Export reusable site structure through config sync; do not put real submissions, user passwords or environment secrets in config. Separate demo content seeding from schema/config deployment. Scheduling must transition the approved revision, not publish unreviewed edits; test cron, timezone and cache expiration at schedule boundaries.

## Integration boundaries

DonationGateway accepts validated amount in integer minor units, currency, frequency, campaign, tribute and an allowlist of attribution parameters. Returns a local mock route or an allowlisted hosted checkout URL. Never allow an arbitrary return URL or put donor PII in query strings. Mock completion stores a synthetic record and announces “Demo complete—no charge made.” Real success later requires provider verification and idempotent webhook processing; return URLs alone do not establish payment success. Browser analytics are best-effort, consent-gated and separate from payment accounting.

NewsletterGateway accepts an explicit purpose-specific consent record. Local adapter sends confirmation to the mail catcher; a single-use, expiring token enables double opt-in. SMS consent is independent and optional; a demo provider is used until an SMS service is selected. Contact submissions and exports stay in restricted storage. Portal uses a server-side session, CSRF protection and server-enforced ownership; payment method changes are simulated/provider redirects, never raw card storage.

## Cache and traffic model

Cache anonymous public GET pages at the edge; use Drupal cache tags/contexts and invalidation for content changes. Cache lifetime must end at alert/campaign schedule boundaries. Exclude authenticated, preview, portal, token, form POST, receipt, thank-you and personalized endpoints from shared caches. Do not cache CSRF tokens into shared HTML. Use private/no-store responses where sensitive state is present. Media derivatives use immutable URLs where possible.

Redis accelerates cache work; anonymous CDN hits keep surge traffic away from PHP/database. Donation checkout later runs on the hosted provider. Local mock load testing measures only our mock, not provider capacity. Proposed test baseline is 10 requests/sec and surge 500 requests/sec (50x), explicitly an assumption pending real traffic data. Test warmed and cold caches, ramp-up and a 10-minute plateau against a controlled test environment. Record p95 latency, error rate, origin hit rate and resources; never run against a public service without authorization.

## Operations and deployment

PR checks: PHP lint/coding standards, static analysis, unit/kernel/functional tests, JS/CSS lint, Vite build, size budgets, Playwright, axe and visual review. Promote the same artifact through dev/test/live. Config import and database updates are a documented deployment step, preceded by a recoverable backup. Rollback may need database restore if updates are not reversible; reverting code alone is not a universal recovery plan.

Monitor homepage, search and donation entry/checkout availability; record redacted errors and queue/cron failures. Document downtime escalation, daily database/media backups, restore rehearsals and quarterly dependency review. Credentials live in environment/host secret storage only. Production deployment always requires the user's explicit confirmation.

## Security, privacy and analytics design

TLS, security headers and an initially report-only CSP precede a tested enforced CSP. Frame destinations are allowlisted; HTML is filtered, output escaped, forms rate limited and CSRF protected. Staff uses least-privilege access and 2FA/SSO. Donor-services has no editorial publishing or configuration rights. Private exports are authorized, audited, short lived and protected against spreadsheet formula injection.

Default consent denies nonessential analytics; accepting enables tags and withdrawing disables future collection. Proposed events: donate_click, donate_start, donate_complete, newsletter_signup, search, outbound_click. Mock events carry demo=true and never reach a production container. No names, email addresses, tribute messages or raw sensitive search terms in events. Newsletter_signup fires on confirmed opt-in. Consent UI is a technical control, not a claim of legal compliance.

All demo data is synthetic. Proposed retention for later review: unconfirmed subscriptions 7 days, contact submissions 90 days, application logs 30 days; confirmed subscribers until withdrawal, with minimal suppression records where justified. Provide authenticated identity-verification steps for access/deletion requests and backup-expiry handling. Policies, tax language and jurisdiction remain placeholders until a real entity exists.
