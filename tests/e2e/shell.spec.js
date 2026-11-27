import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir } from 'node:fs/promises';

for (const width of [375, 768, 1024, 1440]) {
  test(`Drupal shell at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto('/welcome');
    expect(response.status()).toBe(200);
    expect(response.headers()['x-robots-tag']).toContain('noindex');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Care, dignity and solidarity');
    await expect(page.getByText('Fictional demonstration. No real donations are collected.')).toBeVisible();
    await expect(page.locator('.wordmark__sub')).toHaveCSS('color', 'rgb(238, 0, 0)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(accessibility.violations).toEqual([]);
    await page.locator('#main-content').blur();
    await mkdir('docs/screenshots', { recursive: true });
    await page.screenshot({ path: `docs/screenshots/phase-2-shell-${width}.png`, fullPage: true });
  });
}
test('unknown hosts are rejected', async ({ request }) => {
  const response = await request.get('/', { headers: { Host: 'untrusted.example' } });
  expect(response.status()).toBe(400);
});
test('private project files are outside the document root', async ({ request }) => {
  for (const path of ['/.local/admin-password', '/composer.json', '/.git/config']) {
    const response = await request.get(path);
    expect(response.status()).toBe(404);
  }
});
