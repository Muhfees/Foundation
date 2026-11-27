import http from 'node:http';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8765';
const urlObj = new URL(BASE);
if (!['127.0.0.1', 'localhost'].includes(urlObj.hostname)) {
  throw new Error(`Refusing load benchmark against non-local host: ${urlObj.hostname}`);
}

function requestOnce(path) {
  return new Promise((resolve) => {
    const start = performance.now();
    const req = http.get(`${BASE}${path}`, (res) => {
      res.resume();
      res.on('end', () => {
        resolve({ status: res.statusCode || 0, ms: performance.now() - start });
      });
    });
    req.on('error', () => {
      resolve({ status: 0, ms: performance.now() - start });
    });
  });
}

function summarize(label, samples) {
  const sorted = samples.map((s) => s.ms).sort((a, b) => a - b);
  const errors = samples.filter((s) => s.status !== 200).length;
  const p50 = sorted[Math.floor(sorted.length * 0.5)] || 0;
  const p95 = sorted[Math.floor(sorted.length * 0.95)] || 0;
  return {
    label,
    requests: samples.length,
    errors,
    errorRate: Number((errors / samples.length).toFixed(4)),
    p50Ms: Number(p50.toFixed(1)),
    p95Ms: Number(p95.toFixed(1)),
  };
}

async function runStage(label, totalRequests, concurrency) {
  const routes = ['/', '/news', '/search?q=maternal', '/donate'];
  const samples = [];
  let idx = 0;
  async function worker() {
    while (idx < totalRequests) {
      const current = idx++;
      samples.push(await requestOnce(routes[current % routes.length]));
    }
  }
  await Promise.all(Array.from({ length: concurrency }, worker));
  return summarize(label, samples);
}

const baseline = await runStage('local-baseline-c2', 30, 2);
const burst = await runStage('local-burst-c8', 64, 8);
console.log(JSON.stringify({ environment: 'local-single-process-php-built-in-server', baseline, burst }, null, 2));
if (baseline.errors > 0 || burst.errors > 0) {
  process.exitCode = 1;
}
