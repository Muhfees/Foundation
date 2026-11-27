# Phase 6 Summary — Donations, Forms, Portal & Consent (Fictional Demo)

## Scope Delivered
1. **Mock Donations & Receipts (`nusry_donations`)**
   - Interactive mock donation checkout at `/donate` (`DonationForm`) supporting query prefills (`?campaign=...&amount=...&frequency=...`), presets & custom integer minor-unit amounts (`100` to `10000000`), currency allowlist (`USD`, `EUR`, `GBP`), frequency (`once`, `monthly`), allowlisted campaigns, optional tributes (`honor`/`memory`), simulated employer match, and simulated payment methods (`demo_card`, `demo_wallet`, `demo_paypal`) with **zero card number or CVV inputs**.
   - Full handling for simulated completion, failure recovery/retry, checkout cancellation, and server-side idempotency (`DonationRepository::createIdempotent()`).
   - Server-verified sample receipts at `/donate/receipt/{reference}` (`ReceiptController`) with optional plain-text download (`?download=1`), enforcing strict session/user ownership (HTTP 403 for unowned visitors; query parameters cannot forge a completed gift), emitting `Cache-Control: private, no-store` and `X-Robots-Tag: noindex, nofollow` (`PrivateResponseHeaderSubscriber`), and clearly stating `"Demo complete—no charge made."` and `"Sample receipt—not valid for tax purposes."`.

2. **Double Opt-In Newsletter, Protected Contact & Donor Services Export (`nusry_forms`)**
   - Standalone `/newsletter` page plus wired homepage and footer forms backed by `POST /newsletter/subscribe`, `GET /newsletter/confirm/{token}`, and `GET /newsletter/unsubscribe/{token}` (`NewsletterController`, `MockNewsletterGateway`).
   - Double opt-in stores SHA-256 hashed single-use 24-hour confirmation tokens and separate unsubscribe tokens, supports optional independent SMS consent, enforces honeypot + `FloodInterface` rate limiting (5 attempts / 900s), captures non-PII demo confirmation links in `private://demo-mail/`, and never logs PII.
   - Protected `/contact` form (`ContactForm`) attached to the `/contact` `flexible_page` with honeypot + flood protection stored in `nusry_contact_submissions` (excluded from public Views, Search API, RSS, and sitemap).
   - Restricted Donor Services workspace (`/donor-services`, `/donor-services/export`) guarded by `access nusry donor services`, auditing authorized lookups without PII and neutralizing spreadsheet formula prefixes (`=`, `+`, `-`, `@`, `\t`, `\r`, and leading-whitespace formulas) via `CsvSafety::escapeFormula()`.

3. **Authenticated Demo Donor Portal (`nusry_portal`)**
   - `/portal`, `/portal/gifts/{reference}/receipt`, `/portal/recurring/{reference}/edit`, and `/portal/payment-method` protected by `access nusry portal`, `_user_is_logged_in: 'TRUE'`, strict record `uid` ownership checks (HTTP 403 on cross-user access), and `private, no-store` headers.
   - Added responsive `.table-scroll` wrapping for mobile tables and idempotent synthetic donor gifts (`DEMO-SEED-ONCE-001`, `DEMO-SEED-MON-002`) in `scripts/seed-demo.php`.

4. **Consent-Gated Analytics (`main.js`, `page.html.twig`, `pages.css`)**
   - Default-denied consent banner + persistent `"Cookie & Analytics Preferences"` footer control allowing accept/decline/withdrawal stored in `localStorage` (`nusry_consent`).
   - Only when consented (`granted`), `window.dataLayer` records `{ event, demo: true }` with zero PII (`donate_click`, `donate_start`, `donate_complete`, `newsletter_signup`, `search`, `outbound_click`).

## Verification Results
- **Asset Build & Budget:** `npm run build` + `npm run check:assets` passed (`3,387 B` gzipped JS, `7,335 B` gzipped CSS).
- **Linters & Unit Tests:** `npm run lint` (`eslint`), `npm test` (1/1 passed), `vendor/bin/phpcs` (`Drupal` & `DrupalPractice`, 0 errors / 0 warnings).
- **PHPUnit (`phpunit.xml.dist`):** `12 tests, 115 assertions` passed (including unit validation, CSV formula injection escaping, double opt-in token expiry/single-use/rate-limiting, cross-user 403 isolation, private response headers, and contact search exclusion).
- **Playwright E2E + axe-core (`npm run test:e2e`):** `37 / 37` tests passed across `375px`, `768px`, `1024px`, and `1440px` viewports, producing screenshots `docs/screenshots/phase-6-donate-desktop.png` and `docs/screenshots/phase-6-portal-mobile.png`.
