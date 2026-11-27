# Manual QA Checklist & Bug Report Template

Run manual verification alongside CI automated suites before any milestone sign-off. Include failure paths, authenticated isolation checks, consent toggles, and print-stylesheet article checks — never only happy-path screenshots.

## 1. Manual QA Checklist

### Public & Editorial Shell
- [ ] Homepage (`/`) loads with persistent fictional demo banner, single `<h1>`, urgent alert dismissible across reload via `localStorage`, sticky header compaction, mega-menu keyboard open/Escape/focus restore.
- [ ] Mobile drawer (`375px` and `320px`) opens, traps focus, closes cleanly on Escape/backdrop, and keeps visible top-right `Donate` CTA.
- [ ] Search modal opens, focuses input, filters `/search?q=...` safely with XSS escaping, and Search API excludes drafts/archived content and `nusry_contact_submissions` / `nusry_newsletter_subscriptions`.
- [ ] Story article (`/stories/mobile-clinics-expand-maternal-care-across-valeria-republic`) Print Preview (`@media print` / browser Print dialog) hides header/nav/footer/consent banner/share/donate prompts while preserving readable headline, body, and printed link URLs.

### Donations, Failure Paths & Receipts (`/donate`, `/donate/receipt/{ref}`)
- [ ] Invalid amount (`< $1.00`, `> $100,000`), unsupported currency, or missing tribute honoree shows inline accessible validation error.
- [ ] **Failure path**: Clicking **Simulate Payment Failure** displays a non-charged failure notice and keeps form values for retry; retrying **Complete Demo Donation** succeeds cleanly.
- [ ] **Cancellation path**: Clicking **Cancel Demo Checkout** preserves form state with cancellation notice without requiring field validation.
- [ ] Sample receipt renders `"Demo complete—no charge made."` and `"Sample receipt—not valid for tax purposes."`, allows `.txt` download with `Cache-Control: private, no-store`, returns `403` in a separate incognito session, and rejects `?status=completed` forgery on unknown references.

### Newsletter, Contact & Donor Portal
- [ ] Footer/homepage/standalone (`/newsletter`) double opt-in creates pending state + demo mail-catcher link -> clicking link confirms once -> refreshing same confirmation link rejects token reuse -> unsubscribe withdraws consent.
- [ ] Optional SMS opt-in checkbox acts independently and requires a valid phone number only when checked.
- [ ] `/contact` blocks invalid/short messages and honeypot triggers, stores valid inquiry outside public Search/Views, and logs zero PII.
- [ ] Anonymous `/portal` returns `403`; signed-in `demo-donor` sees only own gifts, can adjust recurring plan (`/portal/recurring/{ref}/edit`) and simulated payment method (`/portal/payment-method` with zero card fields), and receives `403` on `/donor-services`.
- [ ] Signed-in `editor` / `contributor` receive `403` on `/portal` and `/donor-services`; `demo-donor-services` can filter `/donor-services` and export formula-safe CSV (`/donor-services/export`).

### Consent & Accessibility
- [ ] Before clicking **Accept demo analytics**, `window.dataLayer` stays empty; after accept, `{ event, demo: true }` events fire without PII; clicking **Cookie & Analytics Preferences** -> **Decline / Withdraw** halts further tracking.
- [ ] Keyboard Tab order, focus indicators, `320px` reflow, `200%` text scaling, and `prefers-reduced-motion: reduce` work cleanly without horizontal overflow.

---

## 2. Bug Report Template

Copy this template into [.github/ISSUE_TEMPLATE/bug_report.md](../.github/ISSUE_TEMPLATE/bug_report.md) when logging defects:

```markdown
- **Route / URL**: (e.g. `/donate`, `/portal/gifts/DEMO-SEED-ONCE-001/receipt`)
- **Build / Commit SHA / Environment**: (e.g. `main @ 37be5f6`, local SQLite vs CI MariaDB/Redis)
- **Viewport / Browser / Device**: (e.g. `375x812`, Chromium 1255 / Firefox / Safari iOS 18)
- **Severity**: Critical | High | Medium | Low
- **Steps to Reproduce**:
  1.
  2.
  3.
- **Expected Result**:
- **Actual Result**:
- **Accessibility Impact**: (e.g. missing focus trap, contrast failure, screen-reader announcement missing, 320px overflow)
- **Evidence (Screenshot / Playwright Trace)**:
- **Privacy-Safe Logs**: (Drupal watchdog ID or browser console error; NEVER include emails, tokens, passwords, or PII)
```
