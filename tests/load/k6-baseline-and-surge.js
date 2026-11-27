/* global __ENV */
// Reference k6 script for controlled test-environment baseline (10 RPS) and 50x surge (500 RPS).
// NEVER run against third-party or live production sites without explicit authorization.
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8765';

export const options = {
  scenarios: {
    baseline: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      duration: '1m',
      preAllocatedVUs: 20,
      maxVUs: 50,
    },
    surge_50x: {
      executor: 'ramping-arrival-rate',
      startRate: 10,
      timeUnit: '1s',
      startTime: '1m',
      stages: [
        { target: 500, duration: '2m' },
        { target: 500, duration: '10m' },
        { target: 10, duration: '1m' },
      ],
      preAllocatedVUs: 100,
      maxVUs: 600,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
  },
};

export default function () {
  const routes = ['/', '/news', '/search?q=maternal', '/donate'];
  const path = routes[Math.floor(Math.random() * routes.length)];
  const res = http.get(`${BASE_URL}${path}`, { headers: { Host: '127.0.0.1' } });
  check(res, { 'status is 200': (r) => r.status === 200 });
  sleep(0.2);
}
