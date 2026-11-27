# Nusry Foundation design direction

Status: proposed for approval. Evidence: all twelve desktop screenshots in [Reference](Reference/). Numerical values are estimates or original choices, identified in the [design spec](docs/design-spec.md).

## 1. Visual Theme & Atmosphere

Urgent, dignified and editorial. Photography anchors the page. Strong headings, broad white space and rectangular red actions direct attention toward supporting the work. This is an explicitly fictional demonstration, with original copy and identity.

## 2. Color

Sampled screenshot estimates: action red #EE0000, ink #231F20, white #FFFFFF, statistics surface #F3F4F0, footer surface #EFF0EB, urgent yellow #FFE75A. Original supporting choices: hover red #C60000, focus blue #005FCC, success #176B43, warning #805800, error #B42318. Use visible labels/icons alongside status colours. Validate contrast for each actual pairing.

## 3. Typography

Proposed two-family system: Source Sans 3 for headings, navigation, UI and short card summaries; Source Serif 4 for editorial body text and long hero summaries. Both are free font candidates, to be licensed, downloaded and self-hosted in Phase 2/4. Use font-display: swap and system fallbacks. Main headings weight 800; interface 600–700; prose 400. At 1440px: hero 64/68px, section headings 44/49px, prose 21/32px; mobile 38/41px, 30/35px, 18/28px. Use fluid sizing within these limits. Condensed screenshot buttons are approximated with the sans family, avoiding a third font.

## 4. Spacing & Grid

4px base; scale 4, 8, 12, 16, 24, 32, 48, 64, 96, 128. Main desktop content width about 74vw up to 1400px; narrower help section about 60vw up to 1120px. Header horizontal gutters about 3.2vw, minimum 20px. Mobile content uses 20px gutters. Major sections 64–128px apart on desktop, 48–64px on mobile. No rounded card grid aesthetic.

## 5. Layout & Composition

Desktop: urgent banner; expanded/shrinking header; image hero with a right-side donation panel; mission/statistics split; panoramic photograph fading into dark featured news; overlapping social strip; newsletter; principles; two alternating feature rows; three help cards; footer and fund allocation strip. Tablet/mobile and inner pages are derived, not in reference. On mobile the donation panel follows the hero copy in document order.

## 6. Components

Rectangular primary/donate buttons, outlined secondary buttons, ghost links, image cards, tabs, stat tiles, accordions, alert, mega menu, mobile drawer, search dialog, input/error fields, pagination, breadcrumbs, tags, modal, toast, quotation, video/transcript, download list, timeline, country directory/map, sensitive-image warning and blur/reveal. Implement a styleguide with default, hover, focus, active, disabled, loading, empty, success and error states as appropriate. Primary actions at least 44px high; no icon-only action without an accessible name.

## 7. Motion & Interaction

Header size transition about 180ms without moving document content. Counters reveal final values immediately to assistive technology; animation is decorative, optional and disabled for reduced motion. Menus work with click, keyboard and touch; hover is supplemental. Dialogs trap focus and return it on close. Tabs use roving focus and arrow keys. No autoplay carousel or background video.

## 8. Voice & Brand

Name: Nusry Foundation. Working mission: bring timely medical care and practical support to people affected by conflict, outbreaks and disasters. This is fictional concept copy, not a claim of services. Use an original wordmark, not MSF's running-person mark, red-cross emblems or an imitation charity seal. Original example hero: “Care begins with showing up.” All mock figures carry a visible “Illustrative demo data” label. Donation CTA becomes “Try a demo donation” where transaction intent needs clarification.

## 9. Anti-patterns

Do not reuse MSF names, marks, photographs, stories, statistics or awards as Nusry assets. Do not rasterize page text, turn screenshots into the website, invent charitable registration numbers, publish fictional field claims as fact, add unsupported matching offers, add gradients/glass cards unrelated to the reference, use pill buttons everywhere, hide focus rings, require hover, preload every image, or load tracking before consent. No live card fields in the mock.
