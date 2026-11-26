# Phase 1 review and handoff

Status: planning deliverables prepared; design approval and GitHub PR remain pending. No app code, CMS installation or infrastructure deployment performed.

## Produced

- [Reference design specification](design-spec.md): all 12 desktop screenshots, sampled palette, estimated typography/spacing, responsive derivations, interactions, conflicts, accessibility additions and QA procedure.
- [Design tokens](design-tokens.json) and [pixel sampling report](reference-colours.json), with [reproducible sampling script](../scripts/sample-reference.ps1).
- [Architecture](architecture.md): stack, text diagram, proposed folders, integrations, caching, security/privacy and deployment approach.
- [Content model](content-model.md): content types, Paragraph library, media metadata, permissions, workflow and synthetic fixtures.
- [Wireframes](wireframes.md): homepage desktop/mobile and key inner/application templates.
- [Design direction](../DESIGN.md), [decision record](design-contract.md), [implementation handoff](implementation-handoff.md), [tooling guidance](tooling.md), [phase/quality plan](phase-plan.md), [README](../README.md).

## Run / review / test

Open Markdown previews in VS Code. No server command exists yet. To regenerate the palette report:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/sample-reference.ps1
```

Review the section order against references 1–12; inspect tokens and their provenance; verify all content claims are labelled demo. Check proposed permissions against editorial and donor-service responsibilities. Validate JSON parsing, reference count, Markdown local links and the nine DESIGN sections as document checks. Application, E2E, Lighthouse, axe, load and cross-browser tests are not applicable yet and have not run.

Document checks completed: 11 Markdown documents scanned, 12 reference images sampled and matched to files, both JSON reports parsed, all nine DESIGN sections present, and zero broken local Markdown links. The sampler completed successfully using Windows System.Drawing.

## Matches / differences

Proposed matches: desktop hero/donation proportions, shrinking header concept, mission/stat split, dark featured news, social/newsletter position, principles, alternating feature rows, help cards and footer grouping.

Deliberate differences: original branding/copy/photos/fonts; demo disclosures and synthetic metrics; no real tax IDs or awards; persistent form labels, keyboard/focus controls, readable mobile text and reduced-motion behaviour. Mobile and inner pages are derived. No rendered screen exists, so these are planned correspondences, not measured visual matches. Final screenshots start in the implementation phases.

## Pending dependencies

1. User design approval and `next` to start Phase 2, as required by the supplied brief.
2. Target GitHub repository and authenticated access. No .git directory or remote existed in this project; no PR was created.
3. Docker/DDEV readiness check. Docker/PHP/Composer/gh were not found on current PATH. Do not interpret the planning docs as proof the runtime is ready.
4. Codex plan is unspecified; model availability will be taken from the user's picker. No need to resolve this before approving the design.

## Prepared Phase 1 PR scope

Suggested title: `docs: define Nusry Foundation architecture and reference design`

Suggested description: Establishes the Drupal implementation plan and original fictional nonprofit identity before UI work. Adds the reference-based design specification, reproducible colour sampling, content/role model, template wireframes, design tokens and phased acceptance criteria. Records reference/brief conflicts, demo-only payment/content boundaries, and environment/tool dependencies. Validation: reference sampling and document consistency checks; no application tests because implementation has not started.

Ready to review locally. Do not mark the PR requirement satisfied until a real PR exists. Stop here and wait for the user's `next` or requested changes.
