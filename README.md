# Nusry Foundation

A fictional medical humanitarian nonprofit website demo. No affiliation with Doctors Without Borders / MSF. No real donations, patients, operations, tax status or awards are represented.

## Status

Phase 3 (editorial content model, moderation/scheduling workflow, synthetic seeds and role/workflow tests) is ready for review on branch `feat/phase-3-editorial`. Phase 2 is in [draft PR #2](https://github.com/Muhfees/Foundation/pull/2), with both [CI jobs passing](https://github.com/Muhfees/Foundation/actions/runs/45869936401). Phase 1 was approved by the user's `next`; [planning PR #1](https://github.com/Muhfees/Foundation/pull/1) remains a draft. The reference homepage, listing templates, search and donation flows belong to Phases 4–6.

## Local setup

Recommended stack: Docker with DDEV, Node 24 inside DDEV, MariaDB 10.11 and Redis 7.4. With Docker running and DDEV installed, from the repository root:

```sh
ddev setup
```

Visit https://nusry-foundation.ddev.site. Setup installs dependencies, builds the theme and initializes an empty database. It refuses to overwrite a nonempty unbootable database. On an existing Drupal site it preserves content/configuration; use the explicit update steps below to apply reviewed changes. Generated credentials live in ignored `.local/admin-password`; obtain a local login link using `ddev drush uli`. Never publish that link or password.

Docker/WSL/DDEV are absent on the current Windows machine; that path has not been executed locally. A portable SQLite preview is available for Windows x64 with Node.js 24 and the Microsoft Visual C++ 2015?2022 x64 runtime:

```powershell
powershell -File scripts/setup-local.ps1
powershell -File scripts/serve-local.ps1
```

Setup downloads pinned, SHA-256-verified PHP 8.3.37 and Composer 2.11.3 into ignored `.tools`, then installs dependencies. Visit http://127.0.0.1:8765. This loopback-only PHP development server and SQLite preview are for local testing; they do not exercise Redis or MariaDB. Keep the public document root at `web`, never the project root. No global PHP/Composer installation is changed. If PowerShell policy blocks scripts, follow your machine's approved script policy.

## Development and checks

```sh
npm run dev                 # Rebuild theme on source edits
npm run build
npm run lint
npm test
npm run check:assets
composer validate --strict
composer audit
vendor/bin/phpcs
vendor/bin/phpunit
npm run test:e2e             # Requires a running local server
```

To seed and verify the fictional editorial demo dataset locally (Windows SQLite preview):

```powershell
$env:NUSRY_ENV='local'; $env:NUSRY_LOCAL_SQLITE='1'
.tools/php/php.exe vendor/drush/drush/drush.php php:script scripts/seed-demo.php
.tools/php/php.exe vendor/drush/drush/drush.php php:script scripts/verify-editorial-schema.php
.tools/php/php.exe vendor/drush/drush/drush.php php:script scripts/verify-demo-seed.php
```

Before browser checks, install Chromium once: `npx playwright install chromium`. Tests default to port 8765; set `BASE_URL` to test another local origin. For DDEV commands, prefix PHP/Node commands with `ddev exec` or use `ddev composer` / `ddev drush`. On the portable Windows setup use `.tools/php/php.exe .tools/composer.phar` for Composer and `.tools/php/php.exe vendor/drush/drush/drush.php` for Drush, with `NUSRY_ENV=local` and `NUSRY_LOCAL_SQLITE=1`.

After pulling reviewed application/configuration updates on an existing DDEV site:

```sh
ddev composer install
ddev exec npm ci
ddev exec npm run build
ddev drush updatedb --yes
ddev drush config:import --yes
ddev drush cache:rebuild
```

Review configuration diffs and back up valuable local content before importing. Setup itself never imports over an existing site. Export intentional changes with `ddev drush config:export`. Do not commit generated credentials, local databases or uploaded files.

GitHub Actions installs from committed configuration against MariaDB, checks Redis with a cache round-trip, audits dependencies, builds assets and runs Chromium/axe checks at 375/768/1024/1440px. A second job exercises `ddev setup` using the [official DDEV action](https://github.com/ddev/github-action-setup-ddev). CI uploads browser evidence and built theme assets; it does not deploy. See [Phase 2 evidence and limitations](docs/phase-2-summary.md).

## Project guide

- [Design specification](docs/design-spec.md), [design tokens](docs/design-tokens.json), [design direction](DESIGN.md)
- [Architecture](docs/architecture.md), [content model](docs/content-model.md), [wireframes](docs/wireframes.md)
- [Design decisions](docs/design-contract.md), [handoff](docs/implementation-handoff.md), [delivery phases](docs/phase-plan.md)
- [Phase 1 review](docs/phase-1-summary.md), [tooling guidance](docs/tooling.md)

Reference screenshots remain private design evidence and are ignored by Git. Use original copy and licensed imagery with provenance. Public previews must remain visibly marked as demos and noindex. Real payments, email delivery and deployment require later configuration and explicit approval.
