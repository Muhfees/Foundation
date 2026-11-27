# Text wireframes

Desktop homepage is grounded in the 12 supplied references. Every mobile layout and inner template below is **derived, not in reference**. All pages share skip link, demo disclosure, navigation, visible donation action, footer, consent controls and semantic landmarks.

## Homepage — desktop

```text
[Skip to content] [Clear fictional-demo disclosure]
[URGENT label | original demo campaign notice | learn | dismiss]
[NUSRY FOUNDATION      work/about/latest/involve  search  DONATE]
-----------------------------------------------------------------
| FULL-BLEED LICENSED HERO PHOTO                                 |
| Original headline,             | White campaign panel          |
| original short story summary   | Demo notice + campaign title  |
| [Learn] [Support our work]      | [25] [100] [250] [1000]        |
|                               | [Other] disclaimer             |
| Photo credit + illustrative image label                        |
-----------------------------------------------------------------
| Mission heading and body      | [Reach] [Use of funds]          |
| [What we do]                  | 3 statistics, demo label       |
|                               | report source + donation CTA   |
-----------------------------------------------------------------
| PANORAMIC PHOTO -> fades into dark news background              |
| Latest stories                                      [See all]  |
| Featured photo + overlaid story | compact story 1 + thumbnail   |
|                                 | compact story 2 + thumbnail   |
|                                 | compact story 3 + thumbnail   |
|                                 | compact story 4 + thumbnail   |
----------------[ Overlapping social links strip ]---------------
| Newsletter heading / name + surname + email / optional SMS      |
| purpose consent, privacy link / [Subscribe]                     |
-----------------------------------------------------------------
| Centered principles / red rule / five statements / outline CTA |
-----------------------------------------------------------------
| Large image + credit            | Medical care text + link     |
| Work-in-focus text + link       | Large image + credit          |
-----------------------------------------------------------------
| How you can help / intro                                      |
| Fundraise card | Event card | Monthly donor card               |
| [Get involved]                                               |
-----------------------------------------------------------------
| Identity/demo disclaimer | grouped links | signup + socials    |
| Funds summary + source | programme % | fundraising % | admin %  |
-----------------------------------------------------------------
```

## Homepage — mobile (derived)

```text
[Demo] [optional alert]
[Wordmark   DONATE   Menu]
[Hero photo + original headline, summary, CTAs, credit]
[White campaign panel, 2-column amounts, Other]
[Mission]
[Stats: two accessible tabs, values, source, CTA]
[Panorama -> dark news]
[Featured story]
[4 compact story rows]
[Wrapped social links]
[Newsletter stacked labelled fields + consent]
[Principles]
[Medical-care image, then text]
[Work-in-focus image, then text]
[3 help cards stacked]
[Footer groups + signup + fund allocation]
```

## Landing, country, medical issue and giving pages (derived)

```text
[Breadcrumbs]
[H1 + summary + image + credit]
[Country: sourced demo key facts / issue: reviewed date / giving: CTA]
[Flexible rich text, image/text, quote, timeline or FAQ components]
[Relevant stories / downloads]
[Demo donation CTA]
```

Country directory defaults to a searchable list with region filter. Map is a progressive enhancement with the same accessible content; keyboard navigation does not depend on geographic pins. Giving pages explain a method and lead to the mock/platform adapter, not financial promises.

## Article and magazine story (derived)

```text
[Breadcrumbs | News/Story | date | estimated reading time]
[H1, standfirst, byline, demo label]
[Hero photo + credit]
[Narrow prose column, subheadings, quote/media, share links]
[3 related published stories]
[Donation prompt]
```

Prose width ~65 characters. Sharing has explicit named destinations and no social tracking widgets. Print styles remove menus, sticky controls and donation overlays, retain article title/date/credit and URLs where useful.

## Listings, search and calendar (derived)

```text
[H1 + intro]
[Search text] [type] [country/topic] [sort relevant/recent] [Apply]
[Active filters + Clear] [result count announced after change]
[Results: thumbnail, type/date, title, escaped highlighted excerpt]
[Pagination]
```

News, campaigns and events share a listing shell. Event filters add virtual/in-person, location and date. Calendar has an equivalent list view. Query parameters preserve filters across navigation; empty results offer clear/reset. Loading, unavailable search index and validation errors each have distinct messages. Search overlay includes labelled query field, common questions, submit and close; full results live on a normal route.

## Finance, reports and legal/help (derived)

```text
[H1 + summary + demo statement]
[Allocation chart + equivalent labelled table + report year/source]
[Download list: title, period, file type/size, accessible HTML link]
[FAQ / accountability explanation / contact action]
```

FAQ/scam warnings, contact, accessibility, terms and privacy use the landing shell. No false certification seals or tax registration claims. Careers uses an obvious example job-board destination; no fake live vacancies. Custom 404 includes home/search links and a donation link without hijacking the error recovery action.

## Donation and portal (derived)

```text
Donation:
[Demo only — no payment collected]
[One-time | Monthly] [Currency] [Preset amounts] [Other input]
[Optional tribute] [Optional employer match mock]
[Demo method: card / wallet / PayPal icons, no card input]
[Review amount/frequency] [Complete demo]
-> [Thank you: explicitly simulated, sample reference, next steps]

Portal:
[Demo account login] -> [Overview | Gift history | Recurring gifts]
[Gift list + sample receipt downloads]
[Change recurring amount / frequency] -> [Review and confirm]
[Update payment method -> simulated provider screen]
[Sign out]
```

Invalid amounts, cancelled checkout, expired login, rate limits, duplicate submission and failed mock completion need explicit states. Donation state resides server-side; a query string cannot forge a completed gift. Portal pages are private and scoped to the authenticated demo donor.

## Component library (derived)

/styleguide groups typography/tokens, navigation, content components and interactive controls. Show all required button/form/card states, tab and accordion keyboard behaviour, alert dismissal, search dialog, pagination, breadcrumbs, stat tiles, modal/toast, consent banner and sensitivity disclosure. Include light/dark surfaces, long text, empty content and reduced-motion variants.
