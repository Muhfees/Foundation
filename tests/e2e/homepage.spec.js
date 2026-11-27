import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir } from 'node:fs/promises';

for (const width of [375, 768, 1024, 1440]) {
  test(`Homepage layout, accessibility and screenshots at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto('/');
    expect(response.status()).toBe(200);
    expect(response.headers()['x-robots-tag']).toContain('noindex');

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Care begins with showing up.');
    await expect(page.getByText('Fictional demonstration. No real donations are collected.')).toBeVisible();
    await expect(page.locator('[data-urgent-banner]')).toBeVisible();

    const heroLoaded = await page.evaluate(() => {
      const img = document.querySelector('.hero__media img');
      return Boolean(img && img.complete && img.naturalWidth > 0);
    });
    expect(heroLoaded).toBe(true);

    // No horizontal overflow at any viewport.
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

    // Capture first screen before interaction.
    await mkdir('docs/screenshots', { recursive: true });
    await page.screenshot({ path: `docs/screenshots/phase-4-first-screen-${width}.png`, fullPage: false });

    // Keyboard skip link to main content.
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    // Verify all 7 homepage sections render.
    await expect(page.locator('.donate-panel')).toBeVisible();
    await expect(page.locator('.mission-stats')).toBeVisible();
    await expect(page.locator('.news-featured')).toBeVisible();
    await expect(page.locator('.news-compact')).toHaveCount(4);
    await expect(page.locator('.newsletter-band')).toBeVisible();
    await expect(page.locator('.principles-section')).toBeVisible();
    await expect(page.locator('.feature-row')).toHaveCount(2);
    await expect(page.locator('.help-card')).toHaveCount(3);
    await expect(page.locator('.funds-strip')).toBeVisible();

    // Scroll through page so lazy-loaded below-fold images decode and verify all images load.
    await page.evaluate(async () => {
      for (const img of document.querySelectorAll('img')) {
        img.scrollIntoView();
        await img.decode().catch(() => {});
      }
      window.scrollTo(0, 0);
    });
    const allImagesLoaded = await page.evaluate(() => [...document.querySelectorAll('img')].every((img) => img.complete && img.naturalWidth > 0));
    expect(allImagesLoaded).toBe(true);

    // Automated WCAG 2.1 AA check.
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.locator('#main-content').blur();
    await page.screenshot({ path: `docs/screenshots/phase-4-homepage-${width}.png`, fullPage: true });
  });
}

test('Interactive header, mega-menu, drawer, search, tabs, hero donate and newsletter', async ({ page }) => {
  // Desktop interactions at 1440px.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/');

  // Mega menu open + Escape close + focus restoration.
  const ourWorkBtn = page.getByRole('button', { name: 'Our Work' });
  await ourWorkBtn.click();
  await expect(ourWorkBtn).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#mega-our-work')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(ourWorkBtn).toHaveAttribute('aria-expanded', 'false');
  await expect(ourWorkBtn).toBeFocused();

  // Search modal open + input focused + Escape close + focus restoration.
  const searchBtn = page.locator('.site-header__utility [data-search-open]');
  await searchBtn.click();
  await expect(page.locator('#nusry-search-input')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('#nusry-search-modal')).toBeHidden();
  await expect(searchBtn).toBeFocused();

  // Header compacts on scroll without error.
  await page.evaluate(() => window.scrollTo(0, 420));
  await expect(page.locator('[data-site-header]')).toHaveClass(/is-compact/);
  await page.evaluate(() => window.scrollTo(0, 0));

  // Stats tabs roving focus with ArrowRight.
  const reachTab = page.getByRole('tab', { name: 'Our reach' });
  const fundsTab = page.getByRole('tab', { name: 'How we use funds' });
  await reachTab.focus();
  await page.keyboard.press('ArrowRight');
  await expect(fundsTab).toHaveAttribute('aria-selected', 'true');
  await expect(fundsTab).toBeFocused();
  await expect(page.locator('#panel-funds')).toBeVisible();

  // Hero $ Other toggle + simulated donation route.
  const otherBtn = page.locator('[data-other-toggle]');
  await otherBtn.click();
  await expect(page.locator('#hero-custom-dollars')).toBeFocused();
  await page.fill('#hero-custom-dollars', '50');
  await page.locator('[data-other-form] button[type="submit"]').click();
  await expect(page).toHaveURL(/\/donate\?.*amount=5000/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Simulated Donation Preview');

  // Newsletter inline validation & demo confirmation.
  await page.goto('/');
  const mainNewsletter = page.locator('.newsletter-band [data-newsletter-form]');
  await mainNewsletter.locator('button[type="submit"]').click();
  await expect(mainNewsletter.locator('[data-newsletter-status]')).toContainText('Please enter your first name');
  await mainNewsletter.locator('input[name="first_name"]').fill('Amira');
  await mainNewsletter.locator('input[name="last_name"]').fill('Nasser');
  await mainNewsletter.locator('input[name="email"]').fill('amira@example.test');
  await mainNewsletter.locator('input[name="consent"]').check();
  await mainNewsletter.locator('button[type="submit"]').click();
  await expect(mainNewsletter.locator('[data-newsletter-status]')).toContainText('Demo subscription recorded locally');

  // Mobile drawer + persistent visible Donate CTA at 375px.
  await page.setViewportSize({ width: 375, height: 812 });
  await expect(page.locator('.site-header__donate--mobile')).toBeVisible();
  const menuBtn = page.locator('[data-drawer-open]');
  await menuBtn.click();
  await expect(page.locator('#nusry-mobile-drawer')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#nusry-mobile-drawer')).toBeHidden();
  await expect(menuBtn).toBeFocused();

  // Search preview route excludes unpublished drafts.
  await page.goto('/search?q=Draft');
  await expect(page.getByText('Draft: Upcoming seasonal preparedness review')).toHaveCount(0);
});
