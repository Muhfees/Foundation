# Phase 5: editorial templates, listings, Search API and utility routes

Status: implementation and local verification complete on branch `feat/phase-5-templates-search`.

## Delivered

- **Search API database backend & editorial index ([configure-search.php](../scripts/configure-search.php), [ContentSearchService.php](../web/modules/custom/nusry_core/src/Service/ContentSearchService.php))**:
  - Installed `drupal/search_api` (`1.43.0`) and enabled `search_api` + `search_api_db` with `database` server and `editorial_content` index (`entity_status`, `content_access`, `html_filter`, `ignorecase`, `tokenizer`).
  - Configured keyword, content type, illustrative country, medical issue, and relevance/recent sorting with pagination, active filter chips, escaped `<mark>` excerpts, empty-result state, `>200`-char validation errors, and unavailable-index fallback.
- **Listing, calendar, 404, styleguide, RSS & sitemap routes ([ListingController.php](../web/modules/custom/nusry_core/src/Controller/ListingController.php), [UtilityController.php](../web/modules/custom/nusry_core/src/Controller/UtilityController.php))**:
  - `/news`, `/magazine`, `/events` (format, virtual/in-person, timeframe), `/campaigns` (status), `/search`, `/not-found` (`404` handler), `/styleguide`, `/rss.xml` (with `views.view.frontpage` disabled so the custom published-only RSS feed serves cleanly), `/sitemap.xml`, and `NoindexResponseSubscriber` enforcing `X-Robots-Tag: noindex, nofollow`.
- **Node & Paragraph Twig templates + Print styles ([nusry.theme](../web/themes/custom/nusry/nusry.theme), [pages.css](../web/themes/custom/nusry/src/styles/pages.css), [print.css](../web/themes/custom/nusry/src/styles/print.css))**:
  - Custom templates for all 7 node bundles (`story`, `country`, `medical_issue`, `event`, `campaign`, `report`, `flexible_page`) and the Paragraphs library (`hero`, `rich_text`, `image_text`, `quote`, `faq`, `timeline`, `downloads`, `video` click-to-load consent, `sensitive_image` reveal/re-hide, `country_directory` live filter, `card_grid`).
  - Breadcrumbs block (`nusry_breadcrumbs`) aligned inside `.site-main--inner`, accessible fund allocation table on `/finances-and-accountability`, and clean print styles.

## Local verification

Environment: Windows x64, PHP 8.3.37, SQLite, Node 24, Playwright Chromium (`.tools/browsers`).

- `composer validate --strict` & `composer audit` pass (0 advisories).
- `vendor/bin/phpcs`, `drush php:script scripts/verify-config.php`, `npm run lint`, `npm test`, `npm run check:assets` (`2,589 B` JS / `6,840 B` CSS gzip) pass.
- `vendor/bin/phpunit` passes **6 tests, 73 assertions** (including `SearchAndRoutesTest` verifying Search API access, HTML escaping, unavailable-index fallback, and RSS/sitemap draft exclusion).
- `npm run test:e2e` passes **31 / 31 tests** (`homepage.spec.js`, `routes.spec.js`, `shell.spec.js`) across all listing, node, utility, 404, RSS, sitemap, interactive, and axe WCAG 2.1 A/AA checks.
- Inspected screenshots: [search 375px](screenshots/phase-5-search-375.png) and [search 1440px](screenshots/phase-5-search-1440.png).

## Scope and limitations

- Local runtime checks used SQLite (`NUSRY_LOCAL_SQLITE=1`); MariaDB, Redis, and DDEV verification run in CI.
- Interactive mock donation checkout, double opt-in newsletter confirmation, contact form protection, and donor portal belong to Phase 6 (`donation/forms/portal`).
