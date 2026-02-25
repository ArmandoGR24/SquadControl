import http from 'k6/http';
import { check, group, sleep } from 'k6';

http.setResponseCallback(
  http.expectedStatuses(
    { min: 200, max: 299 },
    302,
    403,
    422
  )
);

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;
const RATE = Number(__ENV.RATE || 200);
const WRITE_RATE = Number(__ENV.WRITE_RATE || 50);
const DURATION = __ENV.DURATION || '10m';
const ENABLE_WRITES = String(__ENV.ENABLE_WRITES || 'false').toLowerCase() === 'true';
const ENABLE_DELETES = String(__ENV.ENABLE_DELETES || 'false').toLowerCase() === 'true';

export const options = {
  scenarios: {
    read_endpoints: {
      executor: 'constant-arrival-rate',
      rate: RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: Number(__ENV.VUS || 200),
      maxVUs: Number(__ENV.MAX_VUS || 400),
    },
    write_endpoints: {
      executor: 'constant-arrival-rate',
      rate: WRITE_RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: Number(__ENV.WRITE_VUS || 50),
      maxVUs: Number(__ENV.WRITE_MAX_VUS || 150),
      startTime: '5s',
      exec: 'writeFlow',
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
  if (typeof html !== 'string' || html.length === 0) {
    return null;
  }

  const match = html.match(/name="csrf-token" content="([^"]+)"/i);
  return match ? match[1] : null;
}

function decodeHtmlEntities(value) {
  return value
    .replace(/&quot;/g, '"')
    .replace(/&#34;/g, '"')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>');
}

function extractInertiaPage(html) {
  const match = html.match(/data-page="([^"]+)"/i);
  if (!match) {
    return null;
  }

  try {
    const decoded = decodeHtmlEntities(match[1]);
    return JSON.parse(decoded);
  } catch (_) {
    return null;
  }
}

function login() {
  if (!EMAIL || !PASSWORD) {
    throw new Error('EMAIL and PASSWORD env vars are required');
  }

  const loginPage = http.get(`${BASE_URL}/login`);

  if (loginPage.status === 0) {
    throw new Error(`Cannot connect to ${BASE_URL}. Start the Laravel server before running k6.`);
  }

  const token = extractCsrf(loginPage.body);
  if (!token) {
    throw new Error(`Unable to extract CSRF token from /login (status ${loginPage.status}).`);
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
    'login ok': (r) => [200, 204, 302].includes(r.status),
  });

  csrfToken = token;
  loggedIn = true;
}

function ensureLoggedIn() {
  if (!loggedIn) {
    login();
  }
}

function commonHeaders() {
  return {
    'Accept': 'application/json, text/plain, */*',
    'X-CSRF-TOKEN': csrfToken || '',
    'X-Requested-With': 'XMLHttpRequest',
  };
}

export default function () {
  ensureLoggedIn();

  group('dashboard', () => {
    const res = http.get(`${BASE_URL}/dashboard`);
    check(res, { 'dashboard ok': (r) => [200, 302].includes(r.status) });
  });

  group('mis-tareas', () => {
    const res = http.get(`${BASE_URL}/mis-tareas`);
    check(res, { 'mis-tareas ok': (r) => [200, 302].includes(r.status) });
  });

  group('checkin', () => {
    const res = http.get(`${BASE_URL}/checkin`);
    check(res, { 'checkin ok': (r) => [200, 302].includes(r.status) });
  });

  group('checkin-historial', () => {
    const res = http.get(`${BASE_URL}/checkin/historial`);
    check(res, { 'historial ok': (r) => [200, 302].includes(r.status) });
  });

  group('tareas', () => {
    const res = http.get(`${BASE_URL}/tareas`);
    check(res, { 'tareas ok': (r) => [200, 302].includes(r.status) });
  });

  group('usuarios', () => {
    const res = http.get(`${BASE_URL}/usuarios`);
    check(res, { 'usuarios ok': (r) => [200, 302].includes(r.status) });
  });

  sleep(0.1);
}

export function writeFlow() {
  if (!ENABLE_WRITES) {
    sleep(1);
    return;
  }

  ensureLoggedIn();

  const tareasPage = http.get(`${BASE_URL}/tareas`);
  const tareasData = extractInertiaPage(tareasPage.body);
  const leaders = tareasData?.props?.lideres || [];
  const tasks = tareasData?.props?.tareas || [];

  const leaderId = leaders.length > 0 ? leaders[0].id : null;
  const taskId = tasks.length > 0 ? tasks[0].id : null;

  group('checkin-entrada', () => {
    const res = http.post(
      `${BASE_URL}/checkin/entrada`,
      JSON.stringify({ latitude: 0, longitude: 0 }),
      {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      }
    );

    check(res, {
      'checkin entrada ok': (r) => [200, 302, 422].includes(r.status),
    });
  });

  group('checkin-salida', () => {
    const res = http.post(
      `${BASE_URL}/checkin/salida`,
      JSON.stringify({ latitude: 0, longitude: 0 }),
      {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      }
    );

    check(res, {
      'checkin salida ok': (r) => [200, 302, 422].includes(r.status),
    });
  });

  if (leaderId) {
    group('tareas-store', () => {
      const payload = {
        name: `Stress Task ${Date.now()}-${__VU}`,
        instructions: 'Tarea creada por prueba de estres',
        status: 'Pendiente',
        leader_ids: [leaderId],
        status_comment: 'Auto creado por k6',
      };

      const res = http.post(`${BASE_URL}/tareas`, JSON.stringify(payload), {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      });

      check(res, {
        'tareas store ok': (r) => [200, 302, 422].includes(r.status),
      });
    });
  }

  if (taskId && leaderId) {
    group('tareas-update', () => {
      const payload = {
        name: `Stress Update ${Date.now()}-${__VU}`,
        instructions: 'Actualizacion por prueba de estres',
        status: 'En progreso',
        leader_ids: [leaderId],
        status_comment: 'Actualizado por k6',
      };

      const res = http.put(`${BASE_URL}/tareas/${taskId}`, JSON.stringify(payload), {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      });

      check(res, {
        'tareas update ok': (r) => [200, 302, 422].includes(r.status),
      });
    });

    group('tareas-estado', () => {
      const payload = {
        status: 'En progreso',
        status_comment: 'Cambio de estado por k6',
      };

      const res = http.patch(`${BASE_URL}/tareas/${taskId}/estado`, JSON.stringify(payload), {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      });

      check(res, {
        'tareas estado ok': (r) => [200, 302, 422, 403].includes(r.status),
      });
    });
  }

  const usuariosPage = http.get(`${BASE_URL}/usuarios`);
  const usuariosData = extractInertiaPage(usuariosPage.body);
  const usuarios = usuariosData?.props?.usuarios || [];
  const targetUser = usuarios.length > 0 ? usuarios[0] : null;

  group('usuarios-store', () => {
    const unique = `${Date.now()}-${__VU}`;
    const payload = {
      name: `Stress User ${unique}`,
      email: `stress_${unique}@example.com`,
      password: 'Password123! ',
      password_confirmation: 'Password123! ',
      role: 'Empleado',
      status: 'Activo',
    };

    const res = http.post(`${BASE_URL}/usuarios`, JSON.stringify(payload), {
      headers: {
        ...commonHeaders(),
        'Content-Type': 'application/json',
      },
    });

    check(res, {
      'usuarios store ok': (r) => [200, 302, 422].includes(r.status),
    });
  });

  if (targetUser) {
    group('usuarios-update', () => {
      const payload = {
        name: `${targetUser.nombre} (k6)`,
        email: targetUser.email,
        role: targetUser.rol || 'Empleado',
        status: targetUser.estado || 'Activo',
      };

      const res = http.put(`${BASE_URL}/usuarios/${targetUser.id}`, JSON.stringify(payload), {
        headers: {
          ...commonHeaders(),
          'Content-Type': 'application/json',
        },
      });

      check(res, {
        'usuarios update ok': (r) => [200, 302, 422].includes(r.status),
      });
    });

    if (ENABLE_DELETES) {
      group('usuarios-destroy', () => {
        const res = http.del(`${BASE_URL}/usuarios/${targetUser.id}`, null, {
          headers: {
            ...commonHeaders(),
            'Content-Type': 'application/json',
          },
        });

        check(res, {
          'usuarios destroy ok': (r) => [200, 302, 422].includes(r.status),
        });
      });
    }
  }

  sleep(0.2);
}
