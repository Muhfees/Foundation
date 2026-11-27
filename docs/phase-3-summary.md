# Phase 3: editorial model, workflow, seeds and role tests

Status: implementation and local verification complete on branch `feat/phase-3-editorial`. Ready for commit/push and draft PR review before moving to Phase 4 (`homepage`).

## Delivered

- **Contrib editorial stack & exported config (`config/sync/`)**: Enabled `paragraphs`, `entity_reference_revisions`, `media`, `media_library`, `focal_point`, `crop`, `pathauto`, `token`, `redirect`, `metatag`, `scheduler`, `scheduler_content_moderation_integration`, `content_moderation`, `workflows`, `content_translation`, `language`, `ckeditor5`, `taxonomy`, and `menu_link_content`.
- **7 content types & constrained 19-type Paragraphs library**: `flexible_page`, `story`, `country`, `medical_issue`, `event`, `campaign`, `report` with shared summary, demo flag, metatag SEO, social image, translation readiness, Pathauto patterns, and 15 top-level components + 4 leaf item types (`statistic_item`, `faq_item`, `timeline_item`, `principle_item`), enforcing max 2-level nesting.
- **3 media bundles & focal-point image styles**: `image` (with contextual alt-or-decorative flag, sensitivity flag, caption, credit, license, provenance, source URL, focal point, and optional mobile crop), `document` (accessible text equivalent + document date), `remote_video` (transcript + verified captions flag); plus `hero_desktop` (1440x720), `hero_mobile` (750x900), and `card_landscape` (720x480) focal-point styles.
- **Reusable editorial configuration & custom services (`nusry_core`)**: Typed config schema (`nusry_core.schema.yml`) and defaults (`nusry_core.settings.yml`) for urgent alert windows, illustrative organization facts (`6` regions, `24,800` consultations, `3,600` admissions), fund allocation (`82%` / `12%` / `6%` summing to `100%`), social links, giving destinations, and mock provider flags; plus `ReadingTimeCalculator`, `StorySelector`, `EditorialSettings`, `EditorialConstraint`, and `MediaAltOrDecorativeConstraint`.
- **Editorial workflow, scheduling & least-privilege roles**: 5-state `editorial` workflow (`draft`, `review`, `scheduled`, `published`, `archived`), `scheduler` integration for moderated revisions, `auto_redirect: true` on alias changes, and least-privilege `contributor`, `editor`, `donor_services`, `demo_donor`, and `administrator` roles.
- **Idempotent synthetic seeder & asset register**: [seed-demo.php](../scripts/seed-demo.php) generates local PNG/PDF placeholders into `private://editorial/demo/` (documented in [asset-register.md](asset-register.md)), `example.test` synthetic staff/donor accounts, taxonomy terms, 8 published stories, 1 draft story, 1 scheduled story, 4 illustrative countries, 4 medical issues, 3 events, 2 campaigns, 2 sample reports, flexible pages, and `main` / `donate` / `footer` menu links.
- **CI & automated tests**: Added [phpunit.xml.dist](../phpunit.xml.dist), [EditorialHelpersTest.php](../web/modules/custom/nusry_core/tests/src/Unit/EditorialHelpersTest.php), [EditorialWorkflowAndRolesTest.php](../tests/php/Integration/EditorialWorkflowAndRolesTest.php), [verify-editorial-schema.php](../scripts/verify-editorial-schema.php), and [verify-demo-seed.php](../scripts/verify-demo-seed.php), wired into [validate.yml](../.github/workflows/validate.yml).

## Local verification

Environment: Windows x64, PHP 8.3.37, SQLite, Node 24, Playwright Chromium (`.tools/browsers`).

- `composer validate --strict` and `composer audit` pass (no advisories found).
- `php -l` passes on all custom scripts and `settings.php`.
- `vendor/bin/phpcs` passes with 0 errors and 0 warnings.
- `vendor/bin/phpunit` passes: 4 tests, 56 assertions (unit reading-time/allocation/alert tests + integration tests covering role boundaries, pending draft revisions, Scheduler publish/unpublish transitions, alias-rename 301 redirects, `StorySelector` deduplication, and editorial/media validation constraints).
- `drush php:script scripts/verify-config.php`, `scripts/verify-editorial-schema.php`, and `scripts/verify-demo-seed.php` pass; `scripts/seed-demo.php` verified idempotent across consecutive runs.
- `npm run lint`, `npm test`, `npm run check:assets` (`389` B JS / `782` B CSS gzip), and all 6 Playwright/axe E2E checks (`tests/e2e/shell.spec.js`) pass against `http://127.0.0.1:8765`.

## Scope and limitations

- Local verification used SQLite (`NUSRY_LOCAL_SQLITE=1`); MariaDB and Redis runtime checks and DDEV setup run in CI on push/PR.
- Branch `feat/phase-3-editorial` has not yet been committed, pushed, or opened as a draft PR on GitHub.
- Reference homepage layout, theme templates, search views and interactive donation/form flows remain Phases 4–6.
