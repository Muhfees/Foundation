# Nusry Foundation

A fictional medical humanitarian nonprofit website demo. No affiliation with Doctors Without Borders / MSF. No real donations, patients, field operations, tax status or awards are represented.

## Status

Phase 1 planning is ready for review. No application, CMS, payment integration or deployment has been built. Implementation begins only after design approval and the user's `next`.

Confirmed direction: Drupal 11, Twig, Vite, MariaDB, Redis; original Nusry Foundation branding in red, charcoal and white. Fictional international emergency medical care, outbreak response and support for people affected by conflict and disasters. English first; USD is a provisional demo currency.

## Review the plan

- [Design specification and reference analysis](docs/design-spec.md)
- [Architecture and planned folder structure](docs/architecture.md)
- [Content model and permissions](docs/content-model.md)
- [Homepage and template wireframes](docs/wireframes.md)
- [Design direction](DESIGN.md), [decision record](docs/design-contract.md), and [implementation handoff](docs/implementation-handoff.md)
- [Design tokens](docs/design-tokens.json)
- [Tool availability and setup guidance](docs/tooling.md)
- [Phases and acceptance criteria](docs/phase-plan.md)
- [Phase 1 review checklist and PR handoff](docs/phase-1-summary.md)

## Run and check Phase 1

Open the Markdown documents in VS Code and use its Markdown preview. There is no web server to start yet.

Reproduce colour sampling on Windows, using built-in .NET System.Drawing:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/sample-reference.ps1
```

This reads the 12 PNG references and writes [reference-colours.json](docs/reference-colours.json). It does not alter the images. Colour readings are screenshot estimates, not original site CSS.

Phase 2 will provide the Docker/DDEV setup command after checking local prerequisites. Docker, PHP, Composer and `gh` were not found on the current shell's PATH; that is not proof they are absent from the computer.

## Project boundaries

Reference screenshots are private design evidence, never public website assets. All stories, metrics, campaigns, portal records and receipts must be labelled synthetic/demo. Use locally stored, licensed placeholder photographs with provenance and credits; never imply that pictured people received care from this fictional organization. Public previews will be noindex and visibly marked as demos. Real payments, email delivery and deployment require later configuration and explicit readiness approval.

No Git repository or GitHub remote is present in this folder. Phase 1 cannot have a pull request until the target repository and authenticated access are available; the draft scope is recorded in the phase summary.
