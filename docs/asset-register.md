# Asset and license register

All Phase 3 editorial media seeds and Phase 4 theme typography/visual assets are synthetic, locally hosted, and free of MSF trademarks, real patient imagery, or third-party unlicensed assets.

## Phase 4 theme fonts & homepage images

| Asset | Path | Format / Size | License | Provenance |
|---|---|---|---|---|
| Source Sans 3 (400/600/700/800 Latin) | `web/themes/custom/nusry/assets/fonts/source-sans-3-latin-*.woff2` | WOFF2 (~62 KB total) | SIL Open Font License 1.1 (`SOURCE-SANS-3-LICENSE.txt`) | Adobe `source-sans` via `@fontsource/source-sans-3` |
| Source Serif 4 (400/400i Latin) | `web/themes/custom/nusry/assets/fonts/source-serif-4-latin-*.woff2` | WOFF2 (~40 KB total) | SIL Open Font License 1.1 (`SOURCE-SERIF-4-LICENSE.txt`) | Adobe `source-serif` via `@fontsource/source-serif-4` |
| Hero clinic image (desktop & mobile) | `web/themes/custom/nusry/assets/images/hero-clinic.webp`, `hero-clinic-mobile.webp` | WebP (1600x900, 760x560) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and compressed to WebP; no real persons or MSF marks |
| Panorama relief craft | `web/themes/custom/nusry/assets/images/panorama-relief.webp` | WebP (1600x420) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and cropped/compressed to WebP |
| Featured news clinic interior | `web/themes/custom/nusry/assets/images/news-featured.webp` | WebP (960x768) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and cropped/compressed to WebP |
| Medical care feature | `web/themes/custom/nusry/assets/images/feature-medical-care.webp` | WebP (960x768) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and cropped/compressed to WebP |
| Work in focus feature | `web/themes/custom/nusry/assets/images/feature-work-focus.webp` | WebP (960x768) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and cropped/compressed to WebP |
| Help cards (fundraise, event, donor) | `web/themes/custom/nusry/assets/images/help-{fundraise,event,donor}.webp` | WebP (720x450) | Synthetic AI-generated demo asset | Generated via project `image-generator` tool and cropped/compressed to WebP |
| Story thumbnails 1–4 | `web/themes/custom/nusry/assets/images/thumb-{1..4}.webp` | WebP (360x300) | Synthetic AI-generated demo asset | Cropped from the synthetic images above via PHP GD |

## Phase 3 synthetic media seeds

Generated locally by [seed-demo.php](../scripts/seed-demo.php) into ignored `private://editorial/demo/`:

| Media name | File / URI | Type | Format | License | Provenance |
|---|---|---|---|---|---|
| Demo hero clinic scene | `private://editorial/demo/demo-hero-clinic.png` | Image | 1200x800 PNG | CC0-1.0 | Generated locally via PHP GD in `scripts/seed-demo.php` |
| Demo water station scene | `private://editorial/demo/demo-water-station.png` | Image | 1200x800 PNG | CC0-1.0 | Generated locally via PHP GD in `scripts/seed-demo.php` |
| Demo cold-chain logistics scene | `private://editorial/demo/demo-logistics.png` | Image | 1200x800 PNG | CC0-1.0 | Generated locally via PHP GD in `scripts/seed-demo.php` |
| Demo training workshop scene | `private://editorial/demo/demo-midwives.png` | Image | 1200x800 PNG | CC0-1.0 | Generated locally via PHP GD in `scripts/seed-demo.php` |
| Demo sensitive placeholder | `private://editorial/demo/demo-sensitive-placeholder.png` | Image | 1200x800 PNG (`field_sensitive = 1`) | CC0-1.0 | Generated locally via PHP GD in `scripts/seed-demo.php` |
| Demo annual report 2025 PDF | `private://editorial/demo/demo-annual-report-2025.pdf` | Document | Single-page PDF 1.4 | CC0-1.0 | Generated locally in `scripts/seed-demo.php` with accessible HTML summary |
| Financial transparency summary 2025 PDF | `private://editorial/demo/demo-financial-summary-2025.pdf` | Document | Single-page PDF 1.4 | CC0-1.0 | Generated locally in `scripts/seed-demo.php` with accessible HTML summary |
| Demo Medical Briefing Video | `https://example.test/videos/demo-briefing` | Remote video | Placeholder URL | CC0-1.0 | Placeholder URL with verified captions flag and full transcript |
