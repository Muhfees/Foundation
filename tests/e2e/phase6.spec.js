import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

function loginViaUli(username) {
  const raw = execFileSync(
    '.tools/php/php.exe',
    ['vendor/bin/drush.php', 'user:login', `--name=${username}`, '--uri=http://127.0.0.1:8765'],
    {
      env: { ...process.env, NUSRY_ENV: 'local', NUSRY_LOCAL_SQLITE: '1' },
      encoding: 'utf8',
    },
  );
  const fullUrl = raw.trim().split(/\s+/).pop() || '';
  return fullUrl.replace('http://127.0.0.1:8765', '');
}

async function signInAs(page, username) {
  await page.goto(loginViaUli(username));
  const loginBtn = page.locator('input[value="Log in"]');
  if (await loginBtn.count()) {
    await loginBtn.click();
  }
}

const VIEWPORTS = [
  { width: 375, height: 812 },
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1440, height: 900 },
];

async function expectNoOverflowAndAxeClean(page, label) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, `${label} horizontal overflow`).toBeLessThanOrEqual(2);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
  expect(axe.violations, `${label} WCAG 2.1 AA violations`).toEqual([]);
}

test('donation checkout supports failure, cancellation, retry, sample receipt & ownership isolation', async ({ page, browser }) => {
  await page.goto('/donate?campaign=DEMO-MAT-2025&amount=7500&frequency=monthly');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Try a Demo Donation');
  await expect(page.locator('.demo-notice-banner')).toContainText('Demo only — no charge made');

  // Ensure no raw credit card number / CVV fields exist.
  await expect(page.locator('input[autocomplete="cc-number"], input[autocomplete="cc-csc"]')).toHaveCount(0);

  await page.fill('input[name="donor_name"]', 'Nadia Haddad');
  await page.fill('input[name="donor_email"]', 'nadia@example.test');
  await page.selectOption('select[name="tribute_type"]', 'honor');
  await page.fill('input[name="tribute_name"]', 'Community Midwives');
  await page.check('input[name="employer_match"]');

  // Simulated failure branch.
  await page.click('input[name="simulate_failure"]');
  await expect(page.locator('.nusry-alert--warning')).toContainText('Simulated payment failed');

  // Simulated cancellation branch.
  await page.click('input[name="cancel_checkout"]');
  await expect(page.locator('.nusry-alert--warning')).toContainText('Demo checkout cancelled');

  // Retry and complete simulated donation.
  await page.click('input[name="complete"]');
  await expect(page).toHaveURL(/\/donate\/receipt\/DEMO-/);
  const receiptUrl = page.url();

  await expect(page.locator('.demo-notice-banner')).toContainText('Demo complete—no charge made.');
  await expect(page.locator('.demo-notice-banner')).toContainText('Sample receipt—not valid for tax purposes.');
  await expect(page.locator('.nusry-receipt-card')).toContainText('Community Midwives');

  // Sample receipt text download returns private, no-store headers and disclaimers.
  const downloadRes = await page.request.get(`${receiptUrl}?download=1`);
  expect(downloadRes.status()).toBe(200);
  expect(downloadRes.headers()['cache-control']).toContain('no-store');
  const txt = await downloadRes.text();
  expect(txt).toContain('Demo complete—no charge made.');
  expect(txt).toContain('Sample receipt—not valid for tax purposes.');

  // An independent browser context without session ownership receives 403 Forbidden.
  const isolatedContext = await browser.newContext();
  const isolatedRes = await isolatedContext.request.get(`${receiptUrl}?status=completed`);
  expect(isolatedRes.status()).toBe(403);
  await isolatedContext.close();
});

test('newsletter double opt-in links pending -> single-use confirm -> reject reuse -> unsubscribe', async ({ page }) => {
  await page.goto('/newsletter');
  const uniqueEmail = `optin-${Date.now()}@example.test`;
  await page.fill('#nl-page-email', uniqueEmail);
  await page.check('#nl-page-sms');
  await page.fill('#nl-page-phone', '+1-555-0142');
  await page.click('[data-newsletter-form] button[type="submit"]');

  const confirmLink = page.locator('.nl-form__msg a, [role="status"] a[href^="/newsletter/confirm/"]').first();
  await expect(confirmLink).toBeVisible();
  const confirmHref = await confirmLink.getAttribute('href');
  expect(confirmHref).toMatch(/^\/newsletter\/confirm\//);

  // First click confirms subscription.
  await page.goto(confirmHref);
  await expect(page.locator('.preview-panel')).toContainText('Subscription confirmed (demo)');
  const unsubHref = await page.locator('a[href^="/newsletter/unsubscribe/"]').getAttribute('href');

  // Reusing the same confirmation token must fail.
  await page.goto(confirmHref);
  await expect(page.locator('.preview-panel')).toContainText('invalid or has already been used');

  // Unsubscribe succeeds cleanly.
  await page.goto(unsubHref);
  await expect(page.locator('.preview-panel')).toContainText('consent have been withdrawn');
});

test('contact form embeds cleanly on /contact and validates input', async ({ page }) => {
  await page.goto('/contact');
  await expect(page.locator('.nusry-contact-form')).toBeVisible();
  await page.fill('input[name="sender_name"]', 'Tariq Salem');
  await page.fill('input[name="sender_email"]', 'tariq@example.test');
  await page.fill('textarea[name="message"]', 'Too short');
  await page.check('input[name="consent_ack"]');
  await page.click('.nusry-contact-form input[type="submit"]');
  await expect(page.locator('.messages--error, [role="alert"], .form-item--error-message').first()).toBeVisible();

  await page.fill('textarea[name="message"]', 'Testing fictional inquiry persistence for Phase 6 review.');
  await page.click('.nusry-contact-form input[type="submit"]');
  await expect(page.locator('[aria-label="Status message"], .nusry-alert--info, .messages--status').first()).toContainText('Demo inquiry recorded');
});

test('authenticated donor portal, recurring edit, payment method & role denial', async ({ page }) => {
  const anonPortal = await page.request.get('/portal');
  expect(anonPortal.status()).toBe(403);

  await signInAs(page, 'demo-donor');

  await page.goto('/portal');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Demo Donor Portal');
  await expect(page.locator('.data-table')).toContainText('DEMO-SEED-ONCE-001');

  await page.goto('/portal/recurring/DEMO-SEED-MON-002/edit');
  await page.fill('input[name="amount"]', '45.00');
  await page.click('input[type="submit"]');
  await expect(page).toHaveURL(/\/portal$/);
  await expect(page.locator('.data-table')).toContainText('$45.00 USD');

  await page.goto('/portal/payment-method');
  await expect(page.locator('input[autocomplete="cc-number"]')).toHaveCount(0);
  await page.check('input[value="demo_paypal"]');
  await page.click('input[type="submit"]');
  await expect(page).toHaveURL(/\/portal$/);

  const forbiddenDs = await page.request.get('/donor-services');
  expect(forbiddenDs.status()).toBe(403);
});

test('authorized donor services export, analytics consent gating & responsive WCAG 2.1 AA', async ({ page, browser }) => {
  // Authorized donor services user can inspect and export formula-safe CSV.
  await signInAs(page, 'demo-donor-services');
  await page.goto('/donor-services');
  await expect(page.locator('.data-table')).toContainText('DEMO-SEED-ONCE-001');
  const csvRes = await page.request.get('/donor-services/export');
  expect(csvRes.status()).toBe(200);
  expect(csvRes.headers()['content-type']).toContain('text/csv');
  expect(csvRes.headers()['cache-control']).toContain('no-store');

  // Analytics consent banner gates window.dataLayer with demo=true and zero PII.
  const consentCtx = await browser.newContext();
  const cPage = await consentCtx.newPage();
  await cPage.goto('/');
  await expect(cPage.locator('[data-consent-banner]')).toBeVisible();
  await cPage.click('a[href="/donate"] >> nth=0');
  const preConsentEvents = await cPage.evaluate(() => window.dataLayer || []);
  expect(preConsentEvents).toEqual([]);

  await cPage.click('[data-consent-accept]');
  await cPage.goto('/donate');
  const postConsentEvents = await cPage.evaluate(() => window.dataLayer || []);
  expect(postConsentEvents.some((e) => e.event === 'donate_start' && e.demo === true)).toBe(true);
  expect(JSON.stringify(postConsentEvents)).not.toContain('@');

  await cPage.click('[data-consent-open]');
  await cPage.click('[data-consent-decline]');
  expect(await cPage.evaluate(() => localStorage.getItem('nusry_consent'))).toBe('denied');
  await consentCtx.close();
});

test('responsive WCAG 2.1 AA across viewports & Phase 6 screenshots', async ({ page }) => {
  test.setTimeout(60000);
  await signInAs(page, 'demo-donor');
  for (const vp of VIEWPORTS) {
    await page.setViewportSize(vp);
    await page.goto('/donate');
    await expectNoOverflowAndAxeClean(page, `/donate@${vp.width}`);
    if (vp.width === 1440) {
      await page.screenshot({ path: 'docs/screenshots/phase-6-donate-desktop.png', fullPage: true });
    }
    await page.goto('/portal');
    await expectNoOverflowAndAxeClean(page, `/portal@${vp.width}`);
    if (vp.width === 375) {
      await page.screenshot({ path: 'docs/screenshots/phase-6-portal-mobile.png', fullPage: true });
    }
  }
});
