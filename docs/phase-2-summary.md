# Phase 2: runnable Drupal foundation

Status: complete. Local checks and both jobs in [CI run 45869936401](https://github.com/Muhfees/Foundation/actions/runs/45869936401) passed. [Draft PR #2](https://github.com/Muhfees/Foundation/pull/2) is ready for review. Scope is the runtime and theme skeleton. Phase 3 has not started.

## Delivered

- Locked Drupal 11.4.14, PHP 8.3, Drush 13, Redis integration and Vite 8 with Node 24.
- DDEV configuration for MariaDB 10.11 and Redis 7.4, invoked with `ddev setup`.
- Portable Windows setup and preview scripts with official PHP/Composer downloads and SHA-256 checks.
- Installer restricted to explicit local/CI environments; generated credentials/salt outside the public root; existing Drupal databases preserved; nonempty unbootable databases rejected.
- Exported minimal Drupal configuration, Nusry theme regions/Twig shell and manifest-based hashed asset attachment.
- Original fictional welcome route, visible demo notice, noindex meta/header and trusted host configuration.
- CI for dependency audits, coding standards, asset budgets, fresh configuration installation, Redis read/write, browser checks and a separate DDEV setup job. No deployment job.

## Local verification

Environment: Windows x64, PHP 8.3.37, SQLite 3.54.1, Node 24.21.0, Chromium 159 through Playwright.

- Drupal reports `bootstrap: Successful`, `db-status: Connected`, and default theme `nusry`.
- Installer rerun preserves the existing site; active configuration matches the export.
- Composer strict validation and audit pass; no advisory was returned at the time of testing. npm installation reports no vulnerabilities.
- ESLint, Node asset-gate test and Drupal/DrupalPractice coding standards pass.
- Vite production build passes. Theme payload: 389 bytes JavaScript and 782 bytes CSS gzip, below 150 KB / 60 KB budgets. These totals exclude Drupal core assets and are not whole-page performance measurements.
- Six browser tests pass: four viewport runs plus trusted-host and private-file checks. Each viewport verifies HTTP 200, demo notice, noindex, loaded theme CSS, no horizontal overflow, keyboard skip-link focus and zero axe WCAG A/AA violations in the tested shell.
- Screenshots visually inspected at [375px](screenshots/phase-2-shell-375.png), [768px](screenshots/phase-2-shell-768.png), [1024px](screenshots/phase-2-shell-1024.png), [1440px](screenshots/phase-2-shell-1440.png).

## Visual scope and limitations

The shell establishes the sampled red/charcoal/pale-surface palette and readable responsive spacing. It intentionally contains only a wordmark, introductory content, demo disclosure and footer. Reference hero, donation panel, navigation, news cards, photographs, final fonts and other homepage sections belong to Phase 4. No pixel-level homepage parity is claimed. Mobile/desktop shell screenshots were reviewed in one completed visual round after runtime/asset integration failures were corrected.

Docker, WSL and DDEV are absent on this Windows machine. Local evidence uses SQLite and does not validate Redis/MariaDB. Remote Linux CI passed fresh MariaDB installation from committed configuration, safe installer rerun, Redis backend read/write, configuration comparison, six Chromium/axe tests, and the separate `ddev setup` job. The Docker path is therefore verified in CI; Docker was not installed or run on this Windows machine. The complete Windows setup command passed using the tools already downloaded; its download hashes were verified during tool installation. No fresh-machine Windows/Visual C++ installation test is claimed.

Automated axe checks do not certify WCAG compliance. Lighthouse, full assistive-technology checks, other browser engines and real devices remain later-phase work. No live payments, email, deployment or production migration was performed.

## Run

See [README](../README.md) for DDEV and Windows commands. Current local preview: http://127.0.0.1:8765. Keep the document root at `web`; the PHP built-in server is a loopback-only development preview.

[Phase 2 draft PR #2](https://github.com/Muhfees/Foundation/pull/2) is stacked on [Phase 1 draft PR #1](https://github.com/Muhfees/Foundation/pull/1); no branch is merged automatically. Stop at the phase boundary and wait for `next` before editorial/content-model implementation.
