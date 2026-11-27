# Phase 4: reference homepage, responsive layout and visual QA

Status: implementation and local verification complete on branch `feat/phase-4-homepage`. Ready for commit/push and draft PR review before Phase 5 (`templates/search`).

## Delivered

- **Self-hosted typography & asset budget (`web/themes/custom/nusry/assets/fonts/`)**: Added Latin WOFF2 subsets and OFL-1.1 licenses for `Source Sans 3` (`400`, `600`, `700`, `800`) and `Source Serif 4` (`400`, `400 italic`), totaling `102.7 KB` on disk (within the `160 KB` initial fonts budget). Vite production bundle outputs `2.19 KB` JS gzip and `5.55 KB` CSS gzip (within `150 KB` / `60 KB` theme budgets).
- **Original documentary-style WebP assets ([asset-register.md](asset-register.md))**: Generated and compressed original WebP images (`hero-clinic.webp`, `hero-clinic-mobile.webp` at `23.8 KB`, `panorama-relief.webp`, `news-featured.webp`, `feature-medical-care.webp`, `feature-work-focus.webp`, `help-{fundraise,event,donor}.webp`, and `thumb-{1..4}.webp`) with zero MSF trademarks or emblems.
- **Shared header, urgent banner, mega-menu, drawer, search modal & footer ([page.html.twig](../web/themes/custom/nusry/templates/layout/page.html.twig), [main.js](../web/themes/custom/nusry/src/main.js))**:
  - Top fictional-demo disclosure bar + dismissible yellow urgent banner (`version`-persisted via `localStorage`).
  - Expanded header with utility row (`Work With Us`, `Search` modal trigger), primary mega-menu dropdowns (`Our Work`, `About Us`, `Latest`, `Get Involved`), and rectangular `#EE0000` `DONATE` CTA, transitioning smoothly (`180ms`, `prefers-reduced-motion` compliant, zero CLS) to a compact sticky bar via `IntersectionObserver`.
  - Accessible mobile drawer + search dialog with focus trapping, `Escape` closing, and focus restoration; persistent visible `DONATE` CTA on `375px` and `768px`.
  - Grouped `#EFF0EB` footer with demo-only disclosure box, newsletter signup, social links, and white `How we use funds` (`82%` / `12%` / `6%`) strip.
- **Full 7-section reference homepage & preview routes ([HomepageController.php](../web/modules/custom/nusry_core/src/Controller/HomepageController.php), [nusry-homepage.html.twig](../web/modules/custom/nusry_core/templates/nusry-homepage.html.twig))**:
  - Updated `system.site` front page to `/home`, reading active campaign presets, `StorySelector` published stories (`1` featured + `4` compact supporting), `EditorialSettings` facts/allocation/alert, and `main` menu links directly from Drupal.
  - Added `/donate` (validated query preview) and `/search` (published-only keyword preview) routes ahead of Phases 5–6.

## Local verification & visual QA rounds

Environment: Windows x64, PHP 8.3.37, SQLite, Node 24, Playwright Chromium (`.tools/browsers`).

- **Round 1 findings fixed**:
  1. Hidden duplicate `nusry_title` page-title block on `<front>` (`block.block.nusry_title.yml`) and removed duplicate `<h1>` tags on `/donate` and `/search` so every route has exactly one `<h1>`.
  2. Tightened `@media (max-width: 480px)` header spacing so `375px` renders the wordmark, `DONATE`, and `Menu` on one row with zero horizontal overflow.
  3. Darkened red foreground text on `#F3F4F0` active tab and `#EFF0EB` footer sub-brand to `#C60000` (`> 5.1:1` contrast) to resolve `axe` WCAG 2.1 AA contrast failures.
- **Round 2 findings fixed**:
  1. Tuned hero `object-position` (`54% center`) at `1024px–1279px` so the clinician's eyes remain centered between the hero copy and donation card.
  2. Added distinct accessible SVG icons for social channels and verified eager decode of all below-the-fold lazy images in Playwright before capturing full-page screenshots.
- **Final checks**:
  - `npm run build`, `npm run check:assets`, `npm run lint`, `npm test` pass.
  - `vendor/bin/phpcs`, `vendor/bin/phpunit` (4 tests, 56 assertions), and `drush php:script scripts/verify-config.php` pass.
  - `npm run test:e2e` passes **11 / 11 tests**: `375px`, `768px`, `1024px`, `1440px` homepage + `/welcome` shell checks, zero horizontal overflow, zero `axe` WCAG 2.1 A/AA violations, all images loaded, keyboard skip link, mega-menu, drawer, search modal, roving-focus stats tabs, hero `$ Other` validation, newsletter validation, draft exclusion on `/search`, trusted hosts, and private-file protection.
  - Inspected screenshots: [1440px](screenshots/phase-4-homepage-1440.png), [1024px](screenshots/phase-4-homepage-1024.png), [768px](screenshots/phase-4-homepage-768.png), [375px](screenshots/phase-4-homepage-375.png), plus first-screen captures.

## Scope and limitations

- Local runtime checks used SQLite (`NUSRY_LOCAL_SQLITE=1`); MariaDB, Redis, and DDEV verification run in CI.
- Individual listing/article/country/issue/event/giving templates and Search API facets belong to Phase 5 (`templates/search`), and interactive mock checkout/portal/confirmed opt-in belong to Phase 6 (`donation/forms/portal`).
- Branch `feat/phase-4-homepage` has not yet been committed, pushed, or opened as a draft PR.
