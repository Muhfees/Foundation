# Editor & Staff Publishing Guide (Demo)

This guide covers editorial workflows for the fictional **Nusry Foundation** Drupal 11 site. Contributors and editors work under least-privilege permissions (`config/sync/user.role.*.yml`) and cannot access donor records (`/donor-services`) or payment/module configuration.

## 1. Roles & Workflow States
- **`contributor`**: Creates and edits own content and media in `Draft`, then moves items to `Needs review` (`submit_review`). Cannot publish directly.
- **`editor`**: Reviews submitted drafts, edits across content types, manages URL aliases, and transitions content to `Approved for scheduling` (`approve_schedule`), `Published` (`publish`), or `Archived` (`archive`).
- **Moderation states**: `Draft` -> `Needs review` -> `Approved for scheduling` -> `Published` -> `Archived`. Scheduled transitions only publish the approved revision at the configured date/time.

## 2. Publishing Content Types
### Stories (`story`) & Field Dispatches
1. Sign in via `/user/login` with an `editor` or `contributor` account.
2. Open `/node/add/story`. Fill in Title, Summary (`field_summary`), Story kind (`field_story_kind`), Category, Country/Medical Issue tags, Reading time, and a Hero image media entity (`field_hero`).
3. Compose structured sections via constrained Paragraphs (`field_components`: rich text, quotes, video with transcript/captions, sensitive image with warning toggle, statistics, downloads, etc.).
4. Keep `field_demo` enabled so all preview copy stays visibly marked as fictional demonstration data.

### Flexible Pages (`flexible_page`)
- Used for `/what-we-do`, `/who-we-are`, `/ways-to-give/*`, `/contact`, and legal pages (`/privacy`, `/terms`, `/accessibility`, `/faq`).
- Pages without a Hero Paragraph render a clean editorial header with `<h1>` automatically; pages with a Hero Paragraph suppress the duplicate title block (`nusry.theme`).

### Urgent Alert Banner (`nusry_core.settings`)
1. Managed via editorial configuration (`config/sync/nusry_core.settings.yml` or authorized admin form).
2. Fields: `urgent_alert.enabled`, `urgent_alert.version` (bump e.g. `v2` when issuing a new notice so prior dismissals reset), `urgent_alert.text`, `urgent_alert.link_url`, and `urgent_alert.link_label`.

### Demo Campaigns (`campaign`)
- Set `field_campaign_id` (allowlisted ID used by `/donate?campaign=...`), `field_campaign_status` (`active` or closed), `field_frequency` (`monthly` or `once`), and integer minor-unit presets (`field_amounts`, e.g. `3500` for `$35`).
- Do **not** present synthetic campaigns as active public fundraising or a registered charity.

## 3. Media & Accessibility Rules
- **Images**: Required `alt` text (`field_media_image`), Credit, License/permission, Provenance notes, and Source URL. Mark graphic or distressing illustrations with `field_sensitive = 1` so visitors must opt to reveal them.
- **Documents (PDF)**: Provide an HTML accessible text summary (`field_accessible_text`) alongside the downloadable file.
- **Remote Video**: Verify captions (`field_captions_confirmed = 1`) and include a full transcript (`field_accessible_text`). Click-to-load consent gates third-party playback.

## 4. Guardrails
- Never enter real patient names, PII, real tax registration numbers, or real bank/payment instructions into any node, Paragraph, or configuration field.
