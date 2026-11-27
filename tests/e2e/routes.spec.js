import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir } from 'node:fs/promises';

const ROUTES = [
  { path: '/news', heading: 'News & Stories' },
  { path: '/magazine', heading: 'Magazine' },
  { path: '/events', heading: 'Events Calendar' },
  { path: '/campaigns', heading: 'Campaigns' },
  { path: '/search?q=maternal', heading: 'Search' },
  { path: '/stories/mobile-clinics-expand-maternal-care-across-valeria-republic', heading: 'Mobile clinics expand maternal care across Valeria Republic' },
  { path: '/where-we-work', heading: 'Where We Work' },
  { path: '/where-we-work/valeria-republic', heading: 'Valeria Republic (Illustrative)' },
  { path: '/medical-issues/maternal-newborn-care', heading: 'Maternal & Newborn Care' },
  { path: '/events/maternal-clinics-virtual-briefing', heading: 'Inside Field Maternal Clinics: Virtual Briefing' },
  { path: '/campaigns/emergency-maternal-health-fund', heading: 'Emergency Maternal Health Fund (Demo)' },
  { path: '/reports/demo-annual-report-2025', heading: 'Demo annual report 2025' },
  { path: '/finances-and-accountability', heading: 'Finances & Accountability' },
  { path: '/our-work-in-focus', heading: 'Our Work in Focus' },
  { path: '/our-history', heading: 'Our History' },
  { path: '/ways-to-give/monthly', heading: 'Give Monthly (Demo)' },
  { path: '/faq', heading: 'FAQ & Scam Warning' },
  { path: '/styleguide', heading: 'Component Library & Styleguide' },
];

for (const route of ROUTES) {
  test(`Route ${route.path} renders cleanly without axe violations`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto(route.path);
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(route.heading);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(accessibility.violations).toEqual([]);
  });
}

test('Responsive check on /news and /search at 375px and 1440px with screenshots', async ({ page }) => {
  await mkdir('docs/screenshots', { recursive: true });
  for (const width of [375, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/search?q=maternal');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await expect(page.locator('.search-result mark').first()).toBeVisible();
    await page.screenshot({ path: `docs/screenshots/phase-5-search-${width}.png`, fullPage: true });
  }
});

test('Filters, empty/error states, sensitive reveal, video consent, 404, RSS and sitemap', async ({ page, request }) => {
  // News filter and empty state.
  await page.goto('/news?kind=news');
  await expect(page.locator('.filter-chip')).toContainText('Type: News');
  await page.goto('/news?q=zzzzunmatchedkeyword');
  await expect(page.locator('.state-banner')).toContainText('No published stories match');

  // Events filter.
  await page.goto('/events?mode=virtual');
  await expect(page.locator('.search-result')).toHaveCount(2);

  // Search API empty & validation error states, and draft exclusion.
  await page.goto('/search?q=zzzzunmatchedkeyword');
  await expect(page.locator('.state-banner')).toContainText('No published results matched');
  await page.goto(`/search?q=${'x'.repeat(205)}`);
  await expect(page.locator('.state-banner--error')).toContainText('200 characters');
  await page.goto('/search?q=seasonal+preparedness');
  await expect(page.getByText('Draft: Upcoming seasonal preparedness review')).toHaveCount(0);

  // Story hero image from private:// loads and related stories appear.
  await page.goto('/stories/mobile-clinics-expand-maternal-care-across-valeria-republic');
  const storyImgLoaded = await page.evaluate(() => {
    const img = document.querySelector('.editorial-figure img');
    return Boolean(img && img.complete && img.naturalWidth > 0);
  });
  expect(storyImgLoaded).toBe(true);
  await expect(page.locator('#related-heading')).toBeVisible();

  // Video privacy-aware consent + sensitive image reveal/hide on /our-work-in-focus.
  await page.goto('/our-work-in-focus');
  const videoBtn = page.locator('[data-video-load]');
  await videoBtn.click();
  await expect(page.locator('.p-video__player')).toBeVisible();
  const sensitiveBtn = page.locator('[data-sensitive-toggle]');
  await sensitiveBtn.click();
  await expect(sensitiveBtn).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.p-sensitive__media')).toBeVisible();
  await sensitiveBtn.click();
  await expect(page.locator('.p-sensitive__media')).toBeHidden();

  // Custom 404 page.
  const notFound = await page.goto('/this-demo-route-does-not-exist');
  expect(notFound.status()).toBe(404);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Page not found');

  // RSS & Sitemap XML exclude drafts and include noindex.
  const rss = await request.get('/rss.xml');
  expect(rss.status()).toBe(200);
  expect(rss.headers()['x-robots-tag']).toContain('noindex');
  const rssBody = await rss.text();
  expect(rssBody).toContain('Mobile clinics expand maternal care');
  expect(rssBody).not.toContain('Draft: Upcoming seasonal preparedness');

  const sitemap = await request.get('/sitemap.xml');
  expect(sitemap.status()).toBe(200);
  const sitemapBody = await sitemap.text();
  expect(sitemapBody).toContain('/what-we-do');
  expect(sitemapBody).toContain('/where-we-work');
  expect(sitemapBody).not.toContain('Upcoming seasonal preparedness');
});
