# Design decision record

Target: a responsive Drupal nonprofit demo that helps visitors understand fictional medical humanitarian work and try a donation journey. Audiences: prospective donors, supporters, readers, recruits and content editors. The visual contract is proposed, not yet approved.

## Evidence and boundaries

| Reference | Confidence | Keep | Change | Do not copy |
|---|---|---|---|---|
| 1 | observed | Alert, large header, photo hero + donation panel | Original identity, campaign and photo | MSF mark, headline, clinical photograph, real matching claim |
| 2 | observed | Compact sticky header, hero outline CTA | Accessible compact navigation and demo action | Source branding, source credit and story |
| 3 | observed | Mission/statistics split and tab treatment | Synthetic metrics, original mission, accessible tabs | Source mission text and operational totals |
| 4 | observed | Source note + panel CTA | Demo report citation and values | Real report attribution as Nusry evidence |
| 5 | observed | Panorama fading into dark news | Licensed illustrative panorama | Refugee photograph from reference |
| 6 | observed | One featured story + four compact stories | Original fictional articles and credited placeholders | Source titles, dates and images |
| 7 | observed | Overlapping social strip + newsletter | Original signup copy, visible labels, consent/SMS state | Exact slogan and implied real social accounts |
| 8 | observed | Centered principles and red divider | Original ethical-principle wording | Verbatim source copy |
| 9 | observed | Large image left, prose right | Original medical-care example | Source photograph, copy and credit |
| 10 | observed | Alternate image/text alignment | Original crisis-response example | Source photograph and story |
| 11 | observed | Three unboxed help cards | Fictional fundraising/event/monthly paths | Actual participant images and programme claims |
| 12 | observed | Grouped footer + fund allocation strip | Demo disclosure, synthetic allocation, no awards | Tax ID, Nobel/ratings marks, logo, exact disclaimer |
| User brief and answers | provided | Drupal stack, phase gates, donor focus | Fictional Nusry Foundation mission | Any implication of affiliation |
| Mobile/inner pages and states | inferred | Desktop rhythm applied consistently | Derive accessible layouts and interactions | Claims that mobile reference screenshots exist |

Final stance: a photography-led, square-edged editorial website with red actions, charcoal news, spacious white sections, bold sans headings and serif prose. Reference composition governs the homepage; originality, accessibility and performance govern the implementation details. Two self-hosted font families and restrained motion keep the design coherent and light.

## Risks and unknowns

- Screenshot scale is unknown; proportional matching is more reliable than treating all observed pixels as CSS pixels.
- Real photographs can imply a real programme: use illustrative credits and demo labelling, never fabricated patient testimony.
- A bright red works on white at an estimated 4.53:1 but reduced opacity or a different shade could fail; verify actual states.
- Real trust badges cannot be transferred to a fictional foundation. Omit rather than replace with fake accreditation.
- Hosting, repository, payment/email provider and production identity are unselected. No live integrations are assumed.
- Runtime prerequisites need verification before Phase 2. No application performance or accessibility score has been measured.

## Quality gate

- [x] All 12 references inspected, inventoried and assigned keep/change/do-not-copy boundaries.
- [x] Pixel sampling report and reproducible script exist.
- [x] Numerical estimates, source facts and original choices are distinguished.
- [x] DESIGN.md uses the nine required sections and one consistent direction.
- [x] Design spec records layout conflicts and accessibility changes.
- [x] Inner/mobile layouts are explicitly derived.
- [x] Content model, components, wireframes and implementation handoff exist.
- [ ] User approves design and says `next` before implementation.
- [ ] Target repository/access supplied; Phase 1 PR created and reviewed.
- [ ] Later visual QA, browser, accessibility and performance gates passed.
