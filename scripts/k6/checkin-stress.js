import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;

export const options = {
  scenarios: {
    stress: {
      executor: 'constant-arrival-rate',
      rate: Number(__ENV.RATE || 50),
      timeUnit: '1s',
      duration: __ENV.DURATION || '1m',
      preAllocatedVUs: Number(__ENV.VUS || 50),
      maxVUs: Number(__ENV.MAX_VUS || 200),
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<1500'],
  },
};

let loggedIn = false;
let csrfToken = null;

function extractCsrf(html) {
  const match = html.match(/name="csrf-token" content="([^"]+)"/i);
  return match ? match[1] : null;
}

function login() {
  if (!EMAIL || !PASSWORD) {
    throw new Error('EMAIL and PASSWORD env vars are required');
  }

  const loginPage = http.get(`${BASE_URL}/login`);
  const token = extractCsrf(loginPage.body);
  if (!token) {
    throw new Error('Unable to extract CSRF token from /login');
  }

  const payload = `email=${encodeURIComponent(EMAIL)}&password=${encodeURIComponent(PASSWORD)}&_token=${encodeURIComponent(token)}`;

  const res = http.post(`${BASE_URL}/login`, payload, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Accept': 'text/html,application/xhtml+xml',
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest',
    },
    redirects: 0,
  });

  check(res, {
    'login ok': (r) => r.status === 204 || r.status === 200 || r.status === 302,
  });

  csrfToken = token;
  loggedIn = true;
}

export default function () {
  if (!loggedIn) {
    login();
  }

  const res = http.post(
    `${BASE_URL}/checkin/entrada`,
    JSON.stringify({ latitude: 0, longitude: 0 }),
    {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
      },
    }
  );

  check(res, {
    'checkin accepted': (r) => [200, 302, 422].includes(r.status),
  });

  sleep(0.1);
}
