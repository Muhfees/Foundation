# Content and editorial model

All seeded content is fictional and labelled. No imported reference-site articles or metrics. English is the initial language; entity references, aliases and translation settings are designed for later translation.

## Content types

| Type | Required core fields | Relationships / behaviour |
|---|---|---|
| Flexible page | Title, summary, components, demo flag, SEO | Homepage, landing pages, giving options, careers, legal, contact, donor FAQ |
| Story | Title, type (news/story), summary, body/components, date, author, hero media | Countries, medical issues, campaigns, curated related stories; computed reading time |
| Country | Name, ISO code, summary, hero, components | Geographic taxonomy, sourced illustrative facts, related stories; do not imply actual operations |
| Medical issue | Title, intro, hero, components | Related countries and stories, reviewed information date |
| Event | Title, start/end with timezone, format, summary | Venue/address or virtual URL, accessibility details, booking destination, campaign |
| Campaign | Title, summary, hero, start/end, status | Donation campaign ID, preset amounts, frequency defaults, verified/demo matching text |
| Report | Title, reporting period, summary, document media | Report category, publication date, accessible HTML summary, financial-data reference |

Flexible pages cover the 14 requested page families without creating a content type for every URL. News/events/campaign listings, search, styleguide, 404, donation flow and portal are Views or application routes. Magazine is a filtered story collection; individual issues can reference report media. History uses a timeline paragraph; the fictional US office and careers pages explain demo status and use placeholder contact/job-board links.

## Shared fields, media and taxonomies

Common fields: title, language, revision log, moderation state, summary, alias, canonical/SEO metadata, social image, translation links, demo flag. Public pages expose current published revisions only; draft previews require authorization and are excluded from search, feeds and caches.

Media bundles: image, document/PDF, remote video. Images require contextual alt text (or explicit decorative status), credit, source URL, license/provenance, focal point, width/height, optional separate mobile crop, and sensitivity flag. Stock photography receives “Illustrative photograph” captions. Documents require title, type, size, date and accessible text equivalent. Videos require captions and transcript; privacy-aware embeds load after applicable consent/user action.

Taxonomies: regions, medical issues (or references to issue nodes), story categories, event formats, campaign themes, report categories and giving methods. Use a single canonical issue entity; taxonomy tags must not create duplicate medical issue records.

Reusable configuration/entities: site identity and navigation; urgent alert (text/link, active window, revision/version for dismissals); organization facts and sourced statistics; fund allocation dataset (period/source and values summing to 100); social links; giving destinations; provider configuration references. No private keys in exported configuration.

## Paragraph library

| Component | Fields and validation |
|---|---|
| Hero | Heading, summary, desktop/mobile image, credit, two CTA links; one H1 per page |
| Rich text | Restricted formats, heading hierarchy, meaningful link text |
| Image + text | Media, heading, body, link, alignment; DOM order stays readable |
| Statistics / tab group | Labels, numeric values, units, period, source/report reference, demo notice |
| Card grid | Curated entity references OR query criteria, count, display variant |
| Quote | Quote text, attribution and source; no fabricated real-person endorsement |
| Video | Media reference, transcript, caption status, consent placeholder |
| FAQ / accordion | Question/answer items; headings and accessible disclosure buttons |
| Donation block | Active campaign reference, amount presets, currency, frequency options |
| Newsletter | Intro, purpose/consent text version, optional SMS, privacy link |
| Downloads | Document references, accessible alternatives |
| Timeline | Date, title, description, optional media |
| Country directory/map | Published country query, region filters; list is the primary accessible mode |
| Principles | Title, original principle statements, CTA |
| Sensitive image | Warning text, media, explicit reveal/re-hide controls; no graphic image seeded |

Cap nesting at two levels and constrain each bundle's allowed children. Editors choose components, not arbitrary CSS or scripts. Automatic story selections query only published content, use deterministic ordering and support a curated featured story with deduplication.

## Roles and workflow

| Role | Allowed | Explicitly excluded |
|---|---|---|
| Admin | Site configuration, users, permission management, all content | Routine use discouraged; separate staff accounts |
| Editor | Create/edit, review, publish/unpublish, schedule approved content, media | Modules, permissions, secrets, donor records |
| Contributor | Create drafts, edit own drafts, submit review, own approved media uploads | Publishing, exporting submissions, other users' drafts |
| Donor-services | Authorized synthetic donor lookup, receipt/restricted submission export, audit trail | Publishing, site config, raw payment details |
| Demo donor | Own synthetic gifts, receipts, simulated recurring changes | Other donors' records, staff functions |

Transitions: contributor draft -> review; editor review -> draft or published; editor published -> archived; edits to a live page create a nonpublic draft while the previous published revision remains live. Scheduled publishing acts on an approved revision with start/end timezone stored consistently. Revisions retain author/time/reason. Newsletter/contact data is not editorial content and must not be exposed through public Views, feeds, JSON or search indexes.

## Navigation and route plan

- Our Work: What We Do; Where We Work; Medical Issues; Our Work in Focus.
- About Us: Who We Are; Finances & Accountability; Our History; The US Office (fictional).
- Latest: News & Stories; Magazine; Events Calendar; Campaigns.
- Get Involved: Ways to Get Involved; Ways to Give; Fundraise; Student Chapters; Events; Campaigns.
- Donate menu: Demo Donation; Give Monthly (demo); More Ways to Give. Persistent Work With Us and Search.
- Giving routes: monthly, tribute, legacy, donor-advised fund, workplace, corporate and vehicle. Content is demonstrative, not tax advice.
- Utility: donor portal, contact, FAQ/scam warning, privacy, terms, accessibility, search, 404 and styleguide.

Mega-menu links have one-line descriptions. URL aliases follow stable readable patterns such as /stories/title, /where-we-work/country, /medical-issues/issue, /events/title and /ways-to-give/monthly. Renaming a published alias creates a redirect; preserve canonical URLs and prevent redirect loops.

## Demo dataset plan

Seed 8 original stories (enough for one featured + four supporting and inner-page related content), 4 clearly illustrative country pages, 4 medical issues, 3 events, 2 campaigns, 2 sample reports and all key flexible pages. Use synthetic authors and donor accounts with example.test emails. Suggested illustrative annual figures: 6 regions, 24,800 consultations and 3,600 admissions; allocation 82% programmes, 12% fundraising, 6% administration. These are invented examples, always visibly labelled, not borrowed MSF figures. Source links point to a generated “Demo annual report,” never a genuine MSF report.

No working real phone numbers, street addresses, charity IDs, testimonials or award badges. Demo receipts say “Sample receipt—not valid for tax purposes.” Demo matches never imply a real sponsor or financial commitment.
