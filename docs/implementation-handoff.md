# Implementation handoff

Do not start Phase 2 until the user approves the design and says `next`.

Read [README](../README.md), [DESIGN](../DESIGN.md), [design spec](design-spec.md), [architecture](architecture.md), [content model](content-model.md), [wireframes](wireframes.md) and [phase plan](phase-plan.md). Token source: [design-tokens.json](design-tokens.json). Reopen the source screenshots before UI work.

Confirmed stack: Drupal 11 + PHP 8.3, Twig, Vite, vanilla JS, MariaDB and Redis. Paragraphs is the constrained page-builder model. Check official version-specific docs and dependency compatibility before implementation. Do not switch to Next.js without explicit instruction.

Preserve reference homepage composition: in-hero donation panel, mission/stats split, panorama + dark featured news (1+4 stories), social/newsletter before principles, alternating feature rows, unboxed help cards and grouped footer. Palette #EE0000/#231F20/white with pale panels and yellow alert. Use Source Sans 3 + Source Serif 4, square corners, strong whitespace. Typography, photo assets and new Nusry branding are original approximations, not copied source assets.

No MSF logos/photos/text, real charity registrations, fake awards or unlabelled impact claims. Use licensed local placeholder assets with license/source/credit records and demo labels. No raw card inputs or real payment collection. Mock states must work, not be dead-end placeholders.

First implementation artifact (Phase 2) should prove reproducible local Drupal boot, compiled theme assets, config workflow and CI checks, with no dependency on live providers. Phase 4's first screen should prove header resizing, hero proportions, donation panel, mission/stats, keyboard operation and mobile stacking before filling the entire homepage.

Validate at 375/768/1024/1440px. Inner/mobile layouts are derived. Visible Donate on mobile, named modal/drawer, focus restoration, labelled fields, accessible tabs, no horizontal overflow and reduced-motion support are required. Save final screenshots and comparison notes after up to 3 comparison/fix rounds per screen.

Outstanding: repository/authenticated PR access, Docker/DDEV runtime availability and user approval. No servers were installed by Phase 1. Use the [tooling fallback plan](tooling.md) if MCP connections remain unavailable.
