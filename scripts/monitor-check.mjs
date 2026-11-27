import http from 'node:http';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8765';
const TARGETS = [
  { path: '/', expectStatus: 200, contains: 'Care begins with showing up.' },
  { path: '/search?q=maternal', expectStatus: 200, contains: 'Maternal' },
  { path: '/donate', expectStatus: 200, contains: 'Demo only' },
  { path: '/rss.xml', expectStatus: 200, contains: '<rss' },
  { path: '/sitemap.xml', expectStatus: 200, contains: '<urlset' },
  { path: '/portal', expectStatus: 403, contains: '' },
];

function fetchCheck(path) {
  return new Promise((resolve) => {
    const start = performance.now();
    const req = http.get(`${BASE}${path}`, (res) => {
      let body = '';
      res.on('data', (chunk) => {
        body += chunk;
      });
      res.on('end', () => {
        resolve({
          path,
          status: res.statusCode || 0,
          ms: Math.round(performance.now() - start),
          body,
        });
      });
    });
    req.on('error', (err) => {
      resolve({ path, status: 0, ms: Math.round(performance.now() - start), body: err.message });
    });
  });
}

const results = [];
let failed = false;

for (const target of TARGETS) {
  const res = await fetchCheck(target.path);
  const ok = res.status === target.expectStatus && (!target.contains || res.body.includes(target.contains));
  if (!ok) {
    failed = true;
  }
  results.push({ path: res.path, status: res.status, expected: target.expectStatus, ok, ms: res.ms });
}

// Verify synthetic outage alert simulation triggers non-ok status on missing route.
const simulatedOutage = await fetchCheck('/this-uptime-alert-probe-should-404');
const alertTriggered = simulatedOutage.status !== 200;

console.log(JSON.stringify({ status: failed ? 'ALERT' : 'OK', checks: results, alertSimulationTriggered: alertTriggered }, null, 2));
if (failed || !alertTriggered) {
  process.exitCode = 1;
}
