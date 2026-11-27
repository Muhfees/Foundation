# Delivery phases and acceptance criteria

Each phase starts with model/effort and tool recommendations, ends with deliverables, run/test commands, evidence and limitations, then stops for the user's `next`. Model names are recommendations from the user's plan; select the latest available model in the picker. This document does not claim to have changed the active model or established the user's subscription.

| Phase | Model / effort recommendation | Tools / dependencies | Exit criteria |
|---|---|---|---|
| 1: planning | Astra / high | Local files/images, official docs; GitHub if connected | Architecture, content model, sampled design spec, tokens, wireframes; user design approval; PR pending repo |
| 2: setup | Sol / high | Docker/DDEV, Composer, Node, GitHub; Context7 or official docs | One-command setup, Drupal boot, theme/Vite skeleton, config sync, CI checks and PR |
| 3: editorial | Sol / high | Local Drupal/DB, version-specific module docs | Types/Paragraphs/media, synthetic seeds, role tests, revision/schedule workflow, PR |
| 4: homepage | Sol / high; Terra for focused visual fixes | Browser controls or Playwright; image/font sources | All reference homepage sections, menus/footer, responsive/a11y behaviours, 4-width comparisons, PR |
| 5: templates/search | Sol / high | Search API docs, local DB, Playwright | All listing/article/country/issue/event/giving/legal routes, search/filter empty/error states, PR |
| 6: donation/forms/portal | Astra / high or Sol / high | Local mock adapters/mail catcher, browser tests | End-to-end demo donation, confirmed newsletter, protected contact/portal, receipts, PR |
| 7: hardening | Astra / high review, Sol / high fixes | DevTools or traces/Lighthouse, axe, k6; Sentry only if configured | Measured budgets, accessibility/SEO/security review, load/restore evidence and limits, PR |
| 8: handoff | Sol / high | CI, Playwright, docs, test environment | Complete automated/manual matrix, editor guide/runbook/launch checklist, final PR; deployment only on explicit instruction |

## Performance budget and measurement plan

Proposed homepage budgets before consent: JS <=150KB gzip, CSS <=60KB gzip, initial fonts <=160KB transfer, mobile hero <=250KB, initial mobile transfer <=1000KB. A later CI script reads built assets/manifest and measures sizes; browser network checks validate actual loading. Subset only permitted font glyphs and preserve needed languages. Use art-directed srcset/sizes with width/height; preload hero/critical fonts only, lazy-load below fold. Critical CSS strategy must be tested against CSP and cache behaviour, not blindly duplicate all CSS inline.

Targets: Lighthouse mobile Performance >=90, Accessibility/Best Practices/SEO >=95; LCP <2.5s, INP <200ms, CLS <0.1. Run repeatable mobile-throttled lab checks and report median + outliers/environment. Lighthouse does not establish field INP: instrument consent-aware real-user metrics on a future deployment and use representative scripted interactions for local diagnostics. No targets are claimed achieved in planning. CDN HTTP/2 or HTTP/3, Brotli and cache behaviour require hosting validation.

Public demo previews intentionally use noindex. Treat discoverability checks separately from production SEO readiness; enable indexing only for an authorized real public launch. Generate titles/descriptions/canonicals/OG, Organization/NewsArticle/Event/BreadcrumbList/DonateAction schema where accurate, sitemap.xml, robots.txt and news RSS. Synthetic entities must not masquerade as a registered charity.

## Test coverage by risk

- PHP unit/kernel/functional: amount parsing, schedule boundaries, gateway validation, idempotency, permissions, revision moderation, search index access, consent tokens and record ownership.
- E2E: homepage load, keyboard mega menu, drawer/search focus handling, filters/sorts, newsletter pending->confirmed, contact validation, mock one-time/monthly/tribute donation, cancellation/retry, portal login/logout/own receipts, cookie denial/acceptance/withdrawal.
- Security: cross-user record/receipt denial, private-cache headers, CSRF, rate limiting, output escaping, unsafe redirects, expired/reused opt-in tokens, export formula escaping and no PII in analytics/logs.
- Accessibility: axe + keyboard/manual screen-reader checks; heading order, focus visibility/restoration, live messages, form errors, image descriptions, 200% text resize and reflow at 320 CSS px, reduced motion, tab/accordion semantics. Automated axe alone cannot certify WCAG.
- Visual: Playwright at 375/768/1024/1440px; reference comparison at closest proportional desktop width, up to 3 rounds per screen; save screenshots/differences. Establish baselines after human review.
- Browsers: Chromium, Firefox and WebKit automated; actual latest Chrome/Edge/Safari and iOS Safari/Android Chrome manual when available. WebKit emulation is not a claim of testing real iOS hardware.
- Load: controlled test-only baseline and 50x surge from architecture plan; report edge vs origin and mock vs real provider limitations.
- Operations: backup restoration rehearsal, rollback test, uptime alert simulation and dependency audit before launch.

## Documentation and asset deliverables

README/setup commands; architecture; content model; design system/styleguide; source/license register; editor guide (page/story/alert/campaign publishing); performance report; manual QA checklist; bug-report template; deployment/rollback/backup/incident runbook; data-retention and access/deletion procedure; launch checklist. These remain future deliverables unless present in the Phase 1 summary.

The bug template will capture route, build/environment, viewport/browser/device, reproduction steps, expected/actual result, severity, screenshot/trace, privacy-safe logs and accessibility impact. Manual QA will include donation failure paths and printed articles, not only happy-path screenshots.

## Release gates

No live credentials or card data in source, chat or logs. No production database MCP. Demo mode cannot silently become live. Selecting real providers, enabling real messages/payments, deploying, destructive database operations, deleting branches or force-pushing requires explicit authorization. Keep phases in separate PRs; absent GitHub access is a recorded handoff limitation, not a passed PR gate.
