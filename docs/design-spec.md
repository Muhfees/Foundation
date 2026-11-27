# Reference-based design specification

Status: Phase 1 proposal, awaiting approval. All measurements are screenshot estimates, not extracted original CSS. All 12 PNGs in [Reference](../Reference/) were visually inspected. They are approximately 1881–1890px wide and 907–913px tall and show successive desktop scroll positions, not twelve separate pages. Original browser zoom/device pixel ratio is unknown.

## Evidence and sampling

[reference-colours.json](reference-colours.json) records each filename, image dimensions and its eight most frequent colours on an 8px sampling grid. [sample-reference.ps1](../scripts/sample-reference.ps1) uses .NET System.Drawing to read pixels without modifying the screenshots. A grid histogram can include photographic pixels and antialiasing; repeated flat colours across several images are stronger UI evidence. Layout/type sizes below are visual estimates, while pixel colour readings are exact for the sampled screenshot pixels but remain estimates of original design tokens.

## Top-to-bottom reference analysis

| Image | Purpose and observed composition | Proposed components / estimates |
|---|---|---|
| 1 | Yellow urgent strip above a large white header; logo left, utilities above right-aligned navigation; full-width photo hero with large white headline left and white donation panel right | Alert ~50px high; expanded header ~150px excluding alert; outer gutters ~60px at 1886px. Hero text region ~63%, donation ~29%, panel padding ~60px. Two-column presets plus full-width Other. Original identity/copy/photo replace all source assets |
| 2 | Same hero after scroll with compact pinned header, visible secondary outline CTA and photo credit | Compact header ~104px; hero headline ~88px/91px at source width. Donation panel remains part of hero; screenshot does not prove panel sticky behaviour. No sticky donation panel assumed |
| 3 | Two columns: mission headline and serif body on left, light-grey tabbed impact panel right | Main container ~1400px, ~240px left edge at source width; column ratio near 1:1 with gap ~64px. Two tabs, red rule/notch and three vertical icon/value rows |
| 4 | Lower continuation of image 3, with source note and red donation button inside stats panel | Not a second section. Source line below values; panel padding ~48px; secondary outline mission CTA left |
| 5 | Panoramic photograph transitions into charcoal news background, then section title and “See more” | Full-bleed divider photo with dark fade at lower edge, not an additional hero or carousel; dark news wrapper |
| 6 | Featured story left (~60%) with image, overlaid title/date and red side rule; four compact stories stacked right (~37%), each with text and right thumbnail | One featured + four supporting articles, not four identical cards. Thumbnails roughly 1.2:1; featured area ~1.28:1 in screenshot; consistent list gaps and link targets |
| 7 | White social panel overlaps the end of dark news. Centered newsletter appears below, between broad pale rules | Social icons/text in a horizontal strip ~950px wide; newsletter ~970px wide; three input fields in one row; red CTA beneath. Labels need accessible persistent equivalents |
| 8 | Centered principles, large two-line heading, short red divider, five bold statements and outline CTA | Wide white space, text-led block; source heading ~88px, statements ~38px. Six requested ethical concepts can fit in five statements by grouping independence/impartiality/neutrality |
| 9 | Large photo left (~60%), narrower vertically centered editorial text right (~35%) | Image ~833x650 at source scale (~1.28:1); heading, red divider, serif paragraph, bold text CTA; credit bottom of photo |
| 10 | Alternating feature with text left and photo right | Same visual language as image 9, reverse desktop arrangement; image roughly 1.28:1; document order remains sensible on small screens |
| 11 | Narrower help section with left-aligned heading, divider, introduction, three image-first cards and outline CTA below | Container ~1110px; equal columns with ~34px gaps; images ~1.6:1. Cards have no floating box, border radius or shadow |
| 12 | Broad pale footer: identity/disclaimer, grouped navigation columns, newsletter/social group. White strip below explains use of funds with three percentages and recognition marks | Footer gutters ~60px. Multi-column desktop; lower fund strip with compact bars. Recognition marks and real tax IDs are omitted from fictional brand; no invented accreditation |

## Estimated design tokens

| Role | Value | Basis |
|---|---|---|
| Primary action | #EE0000 | Sampled in 1–4, 6–12 |
| Ink / dark news | #231F20 | Sampled repeatedly in 3–8, 11–12 |
| Page / inverse text | #FFFFFF | All references |
| Statistics background | #F3F4F0 | 3–4, also 9/12 |
| Footer / separator | #EFF0EB | 7 and 12 |
| Urgent background | #FFE75A | 1 |
| Hover red | #C60000 | Original proposed interaction choice |
| Focus | #005FCC | Original proposed accessibility choice |
| Success / warning / error | #176B43 / #805800 / #B42318 | Original functional choices, not observed |

Calculated solid-colour contrast ratios: white on #EE0000 4.53:1; #231F20 on white 16.30:1; #231F20 on urgent yellow 13.06:1; focus blue on white 5.98:1. These pass normal-text contrast for the listed pairings, but do not establish whole-site compliance. Red/white is close to the threshold: no opacity reduction for active button labels. Photos need a tested overlay or opaque text backing; a global palette pass cannot guarantee readable text on every crop.

Typography proposals, not claimed identification of source fonts:

- Source Sans 3: headings 800, navigation 700, UI 600/700; system fallback Arial/sans-serif. [Official font source](https://github.com/adobe-fonts/source-sans).
- Source Serif 4: editorial prose 400, optional italic captions; fallback Georgia/serif. [Official font source](https://github.com/adobe-fonts/source-serif).
- Self-host WOFF2, include license files and use font-display: swap. Preload only the critical face(s); tune fallback metrics after measurement. Source Sans 3 intentionally approximates the screenshot's condensed CTA lettering without a third family.

| Type role | Source screenshot estimate (~1886px wide) | Proposed 1440px | Proposed 375px |
|---|---|---|---|
| Hero H1 | 88/91px, heavy | 64/68px | 38/41px |
| Main section H2 | 56/60px | 44/49px | 30/35px |
| Principles display | 88/92px | 64/70px | 36/40px |
| Feature/card heading | 36–56px | 28–42px | 24–30px |
| Long summary/prose | 28/46px | 21/32px | 18/28px |
| Navigation/body UI | 20–22px | 17–18px | 16–18px |
| Caption/meta | 16–18px | 14–16px | 14–16px |

Spacing scale: 4/8/12/16/24/32/48/64/96/128px. Container desktop width ~74vw, max 1400px; help content ~60vw, max 1120px. At <=1024px use width calc(100% - 48px), at <=767px calc(100% - 40px). Reference section gaps vary ~96–140px; start with 96px desktop and 56px mobile. Grid gaps 32–64px desktop, 24–32px mobile.

Corners: 0px for buttons/cards/panels, up to 2px for fields if rendering needs it. Secondary borders 2–3px; primary buttons solid red with bold uppercase white text; tertiary links bold with a small chevron. Default button height 56px, minimum target 44px. Social strip shadow estimate: 0 12px 36px rgba(35,31,32,.08); avoid shadows elsewhere. Icons: simple consistent line icons (roughly 2px stroke); social brands only for actual configured destinations and with proper names. Original Nusry wordmark uses typography, no borrowed pictogram.

Image ratios: hero uses cover in a content-sized region with focal point and separate mobile crop; feature images ~5:4; help cards ~8:5; featured story ~5:4; compact thumbs ~6:5; panoramic divider ~4:1 before gradient. Aspect ratios are estimates and may flex to fit content without clipping essential subjects.

## Responsive behaviour — derived, not in reference

Only desktop scroll behaviour is observed. Breakpoints 768/1024/1280px are proposed implementation thresholds and must be tested at 375, 768, 1024 and 1440px.

- 1440px: full navigation, hero story + donation columns, mission + stats, featured news + list, alternating feature rows, 3 help cards and multi-column footer.
- 1024px: use menu drawer to avoid crowding; hero story and donation panel stack, mission/stats may remain two columns only if readable; news/features can use two columns. Actual fit tests override assumed breakpoints.
- 768px: drawer, single-column hero and mission/stats; feature rows stack, news list under feature; help cards can use two columns with the third on next row; footer 2 columns. No compressed preset buttons.
- 375px: persistent visible Donate beside logo/menu; full-screen drawer; everything stacks; donation presets remain 2 columns with full-width Other; fields stack; social row wraps; help cards single column; footer groups stack/optionally accessible disclosure. Tabs wrap labels without horizontal page overflow.
- Mobile ordering: hero content -> campaign panel -> mission -> stats. All children retain logical reading order. Sticky header accounts for safe-area insets and in-page anchor offsets; focus must not be obscured. Demo disclosure remains visible but does not impersonate the urgent-alert slot.

## Interaction contract

Dismissible alert remembers the dismissed alert revision, so a new urgent message reappears; no dismissibility is visible in the reference, so this is derived from the brief. Reserve space to avoid late banner shifts. Shrink header visually inside a stable flow container; validate scroll/focus with reduced motion.

Mega menu: button toggle, aria-expanded, visible focus, Escape close, logical Tab sequence, no hover-only access; do not misuse application-menu roles for simple site navigation. Search: named modal, initial focus in labelled input, Escape and close button, focus restoration, common-question links. Submit to a normal search results route that works without JS; highlight escaped text only.

Stats: two labelled tabs, aria-selected, connected tab panels, keyboard arrows/Home/End. Final figures are accessible from first render; counters cannot delay comprehension. Donation presets deep-link validated amount/frequency/campaign/source to mock flow. Campaign visibility uses server time, editor timezone and cache expiry. Newsletter has visible labels, inline errors, honeypot, rate limiting, optional separate SMS consent and a pending-confirmation state.

Sensitive imagery: no graphic assets are needed for the demo. The reusable component initially displays a warning and safe placeholder; fetch/reveal sensitive media only on explicit action, provide re-hide, and avoid revealing disturbing details in its initial accessible name. Validate the later implementation with assistive technology.

## Conflicts and deliberate changes

1. Brief lists hero then donation block; reference integrates donation into the desktop hero. Use the reference composition, stack on mobile.
2. Brief asks for a four-card news grid; reference has five stories (one featured + four compact) plus a full-width introductory photo. Follow the reference.
3. Brief places newsletter after help cards; reference places social/newsletter after news and before principles, and repeats signup in the footer. Follow the reference and keep both forms consistent.
4. Brief asks for sans body text; reference uses serif for long editorial prose and sans for UI. Use two font families matching this distinction.
5. Brief mentions a red urgent label; reference shows dark text on a white label within yellow. Preserve that treatment with original alert copy; add an accessible dismiss button from the brief.
6. Brief requests awards marks; reference contains real accreditations. Omit them for this fictional organization rather than suggesting real recognition. Replace the identity/tax area with explicit demo status.
7. Reference includes small credit/footer text and placeholder-style form labels. Enforce minimum readable type, persistent labels, visible focus, semantic controls and >=44px primary interaction targets. These are planned accessibility additions; screenshots alone cannot prove the source site's accessibility behaviour.
8. Main typefaces and exact photo compositions differ deliberately because assets are original and licensed. No attempt is made to reproduce real stories or people.

## Unknowns and assumptions

- Approved for planning: fictional international medical humanitarian mission, Drupal, Nusry Foundation, red/charcoal/white.
- Inferred: USD demo amounts, synthetic programme totals, English-first content, original typographic wordmark, no genuine matching sponsor. These can be changed before implementation.
- Unobserved: mobile layouts, dropdowns, hover/focus states, search UI, donation screens, portal, inner pages and animation timing. All are derived.
- Still needed before later phases: target GitHub repository/access; usable Docker/DDEV runtime; hosting/donation/email provider choices if moving beyond a local demo. Codex plan was not supplied; it is not a blocker to drafting docs.
- Approval question: accept the reference-first structure and the listed accessibility/original-brand changes? Reply `next` to approve and start Phase 2, or give corrections.

## Visual QA contract for implementation

For each key screen capture 375/768/1024/1440px Playwright screenshots. Compare against closest desktop reference after normalizing displayed width; reference viewports are ~1886px, so absolute raw-pixel diffs at 1440px are not meaningful. Compare container proportions, heading wraps, vertical rhythm, panel sizing, photo/text relationships and component presence; mask intentionally replaced text/assets in automated comparisons where appropriate. Mobile comparisons use this derived spec, never claim supplied mobile evidence.

Save final shots to docs/screenshots/{screen}-{width}.png, plus a side-by-side comparison and notes. Fix concrete differences for up to 3 rounds per screen, then report remaining gaps and reasons. No browser QA or screenshots exist in Phase 1 because no screens have been built.
