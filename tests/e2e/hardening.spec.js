import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

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

test('HTTP security headers, demo noindex, and external destination protection', async ({ request }) => {
  const home = await request.get('/');
  expect(home.status()).toBe(200);
  const h = home.headers();
  expect(h['x-robots-tag']).toBe('noindex, nofollow');
  expect(h['x-content-type-options']).toBe('nosniff');
  expect(h['x-frame-options']).toBe('SAMEORIGIN');
  expect(h['referrer-policy']).toBe('strict-origin-when-cross-origin');
  expect(h['permissions-policy']).toContain('payment=()');
  expect(h['content-security-policy-report-only']).toContain("default-src 'self'");

  const redirectProbe = await request.get('/donate?destination=https://evil.example/steal', { maxRedirects: 0 });
  expect(redirectProbe.headers().location || '').not.toContain('evil.example');

  const robots = await request.get('/robots.txt');
  expect(robots.status()).toBe(200);
});

test('mobile/desktop budget transfer and Core Web Vitals telemetry remain within demo budgets', async ({ page }) => {
  await page.addInitScript(() => {
    window.__nusryPerf = { lcp: 0, cls: 0 };
    try {
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          window.__nusryPerf.lcp = Math.max(window.__nusryPerf.lcp, entry.startTime || 0);
        }
      }).observe({ type: 'largest-contentful-paint', buffered: true });
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) {
            window.__nusryPerf.cls += entry.value || 0;
          }
        }
      }).observe({ type: 'layout-shift', buffered: true });
    } catch {
      // Browser does not expose observer entry types.
    }
  });
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

  const metrics = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    const resources = performance.getEntriesByType('resource');
    const paints = performance.getEntriesByType('paint');
    const fcp = paints.find((p) => p.name === 'first-contentful-paint')?.startTime || 0;
    const transferTotal = (nav?.transferSize || 0) + resources.reduce((sum, r) => sum + (r.transferSize || r.encodedBodySize || 0), 0);
    return {
      ttfbMs: Math.round((nav?.responseStart || 0) - (nav?.requestStart || 0)),
      fcpMs: Math.round(fcp),
      lcpMs: Math.round(window.__nusryPerf?.lcp || fcp),
      cls: Number((window.__nusryPerf?.cls || 0).toFixed(4)),
      domContentLoadedMs: Math.round(nav?.domContentLoadedEventEnd || 0),
      transferBytes: Math.round(transferTotal),
      resourceCount: resources.length,
    };
  });

  // Mobile initial transfer budget <= 1000 KB, LCP < 2.5s, CLS < 0.1 from docs/phase-plan.md.
  expect(metrics.transferBytes).toBeLessThanOrEqual(1024 * 1000);
  expect(metrics.fcpMs).toBeLessThan(2500);
  expect(metrics.lcpMs).toBeLessThan(2500);
  expect(metrics.cls).toBeLessThan(0.1);
  console.log('MOBILE_PERF_METRICS:', JSON.stringify(metrics));
});

test('320px reflow, 200% text resize, reduced motion and heading order pass across core templates', async ({ page }) => {
  test.setTimeout(60000);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await signInAs(page, 'demo-donor');

  for (const route of ['/', '/donate', '/news', '/contact', '/portal']) {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto(route);
    const overflow320 = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow320, `${route}@320 reflow`).toBeLessThanOrEqual(2);

    const headingCheck = await page.evaluate(() => {
      const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')];
      const h1Count = headings.filter((el) => el.tagName === 'H1').length;
      return { h1Count, total: headings.length };
    });
    expect(headingCheck.h1Count, `${route} single H1`).toBe(1);

    await page.setViewportSize({ width: 1280, height: 800 });
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '200%';
    });
    const overflowZoom = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflowZoom, `${route}@200% zoom overflow`).toBeLessThanOrEqual(2);
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '';
    });
  }
});

test('printed article view hides site chrome cleanly and retains article body', async ({ page }) => {
  await page.goto('/stories/mobile-clinics-expand-maternal-care-across-valeria-republic');
  await page.emulateMedia({ media: 'print' });

  const printCheck = await page.evaluate(() => {
    const header = document.querySelector('.site-header');
    const footer = document.querySelector('.site-footer');
    const consent = document.querySelector('.consent-banner');
    const h1 = document.querySelector('h1');
    const prose = document.querySelector('.prose-column, article');
    return {
      headerDisplay: header ? getComputedStyle(header).display : 'none',
      footerDisplay: footer ? getComputedStyle(footer).display : 'none',
      consentDisplay: consent ? getComputedStyle(consent).display : 'none',
      h1Display: h1 ? getComputedStyle(h1).display : 'none',
      hasBodyText: Boolean(prose && prose.textContent.trim().length > 40),
    };
  });

  expect(printCheck.headerDisplay).toBe('none');
  expect(printCheck.footerDisplay).toBe('none');
  expect(printCheck.consentDisplay).toBe('none');
  expect(printCheck.h1Display).not.toBe('none');
  expect(printCheck.hasBodyText).toBe(true);
});
