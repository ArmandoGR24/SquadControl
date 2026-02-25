<template>
  <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
      <div class="bg-white shadow-xl rounded-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-6 py-8">
          <h1 class="text-3xl font-bold text-white flex items-center gap-3">
            🔍 Debug FCM Token
          </h1>
          <p class="text-blue-100 mt-2">Diagnóstico de notificaciones push</p>
        </div>

        <!-- Estado de Notificaciones -->
        <div class="p-6 border-b">
          <h2 class="text-xl font-semibold mb-4">🔔 Permisos de Notificación</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <span class="font-medium">Estado:</span>
              <span 
                class="px-3 py-1 rounded-full text-sm font-semibold"
                :class="{
                  'bg-green-100 text-green-800': notificationPermission === 'granted',
                  'bg-yellow-100 text-yellow-800': notificationPermission === 'default',
                  'bg-red-100 text-red-800': notificationPermission === 'denied'
                }"
              >
                {{ notificationPermission }}
              </span>
            </div>
            
            <button
              v-if="notificationPermission !== 'granted'"
              @click="requestPermission"
              class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition"
            >
              Solicitar Permisos
            </button>
          </div>
        </div>

        <!-- Estado del Token -->
        <div class="p-6 border-b">
          <h2 class="text-xl font-semibold mb-4">🎫 Token FCM</h2>
          
          <!-- Token Generado -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Token Generado:</label>
            <div v-if="fcmToken" class="bg-green-50 border border-green-200 rounded-lg p-3">
              <div class="flex items-start justify-between gap-2">
                <code class="text-xs text-green-800 break-all flex-1">{{ fcmToken }}</code>
                <button 
                  @click="copyToken"
                  class="text-green-600 hover:text-green-800 flex-shrink-0"
                  title="Copiar"
                >
                  📋
                </button>
              </div>
            </div>
            <div v-else class="bg-gray-50 border border-gray-200 rounded-lg p-3">
              <p class="text-gray-500 text-sm">❌ No se ha generado token aún</p>
            </div>
          </div>

          <!-- Token en DB -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Token en Base de Datos:</label>
            <div v-if="dbToken" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <div class="flex items-start justify-between gap-2">
                <code class="text-xs text-blue-800 break-all flex-1">{{ dbToken }}</code>
                <span v-if="dbToken === fcmToken" class="text-green-600 text-xl" title="Coincide">✅</span>
                <span v-else class="text-red-600 text-xl" title="No coincide">⚠️</span>
              </div>
            </div>
            <div v-else class="bg-red-50 border border-red-200 rounded-lg p-3">
              <p class="text-red-600 text-sm">❌ No hay token guardado en la base de datos</p>
            </div>
          </div>

          <!-- Match Status -->
          <div v-if="fcmToken && dbToken" class="p-3 rounded-lg" :class="tokensMatch ? 'bg-green-50' : 'bg-yellow-50'">
            <p class="text-sm font-medium" :class="tokensMatch ? 'text-green-800' : 'text-yellow-800'">
              {{ tokensMatch ? '✅ Los tokens coinciden - Todo OK' : '⚠️ Los tokens NO coinciden - Intenta guardar de nuevo' }}
            </p>
          </div>
        </div>

        <!-- Usuario Autenticado -->
        <div class="p-6 border-b">
          <h2 class="text-xl font-semibold mb-4">👤 Usuario</h2>
          <div v-if="user" class="space-y-2">
            <div class="flex justify-between p-2 bg-gray-50 rounded">
              <span class="font-medium">ID:</span>
              <span>{{ user.id }}</span>
            </div>
            <div class="flex justify-between p-2 bg-gray-50 rounded">
              <span class="font-medium">Nombre:</span>
              <span>{{ user.name }}</span>
            </div>
            <div class="flex justify-between p-2 bg-gray-50 rounded">
              <span class="font-medium">Email:</span>
              <span>{{ user.email }}</span>
            </div>
          </div>
          <div v-else class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-red-600 text-sm">❌ No autenticado</p>
          </div>
        </div>

        <!-- Acciones -->
        <div class="p-6 space-y-3">
          <h2 class="text-xl font-semibold mb-4">🛠️ Acciones</h2>
          
          <button
            @click="generateToken"
            :disabled="loading"
            class="w-full bg-purple-500 text-white px-4 py-3 rounded-lg hover:bg-purple-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? '⏳ Generando...' : '🔄 Regenerar Token' }}
          </button>

          <button
            @click="saveTokenManually"
            :disabled="!fcmToken || loading"
            class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            💾 Guardar Token en DB
          </button>
          <button
            @click="sendTestNotificationToMyDevices"
            :disabled="!fcmToken || loading"
            class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Enviar Notificación de Prueba
          </button>

          <button
            @click="sendTestNotification"
            :disabled="!dbToken || loading"
            class="w-full bg-green-500 text-white px-4 py-3 rounded-lg hover:bg-green-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            🔔 Enviar Notificación de Prueba
          </button>

          <button
            @click="refresh"
            class="w-full bg-gray-500 text-white px-4 py-3 rounded-lg hover:bg-gray-600 transition"
          >
            🔄 Actualizar Estado
          </button>
        </div>

        <!-- Logs -->
        <div v-if="logs.length > 0" class="p-6 bg-gray-50">
          <h2 class="text-xl font-semibold mb-4">📝 Log</h2>
          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div 
              v-for="(log, index) in logs" 
              :key="index"
              class="text-xs p-2 rounded"
              :class="{
                'bg-green-100 text-green-800': log.type === 'success',
                'bg-red-100 text-red-800': log.type === 'error',
                'bg-blue-100 text-blue-800': log.type === 'info'
              }"
            >
              <span class="font-mono">{{ log.time }}</span> - {{ log.message }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { getToken } from 'firebase/messaging';
import { initializeFirebaseMessaging, getFirebaseMessaging } from '@/lib/firebase';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const notificationPermission = ref<NotificationPermission>(Notification.permission);
const fcmToken = ref<string | null>(null);
const dbToken = ref<string | null>(null);
const loading = ref(false);
const logs = ref<Array<{ time: string; message: string; type: 'success' | 'error' | 'info' }>>([]);

const tokensMatch = computed(() => {
  return fcmToken.value && dbToken.value && fcmToken.value === dbToken.value;
});

const getCsrfToken = () => {
  const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  if (metaToken) {
    return metaToken;
  }

  const xsrfCookie = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];

  return xsrfCookie ? decodeURIComponent(xsrfCookie) : null;
};

const parseResponseSafely = async (response: Response) => {
  const contentType = response.headers.get('content-type') || '';
  const text = await response.text();

  if (contentType.includes('application/json')) {
    try {
      return JSON.parse(text);
    } catch {
      return { message: 'Respuesta JSON inválida del servidor' };
    }
  }

  return { message: text.slice(0, 180) || 'Respuesta no JSON del servidor' };
};

const addLog = (message: string, type: 'success' | 'error' | 'info' = 'info') => {
  const time = new Date().toLocaleTimeString();
  logs.value.unshift({ time, message, type });
  console.log(`[${time}] ${message}`);
};

const requestPermission = async () => {
  try {
    const permission = await Notification.requestPermission();
    notificationPermission.value = permission;
    addLog(`Permiso: ${permission}`, permission === 'granted' ? 'success' : 'error');
    
    if (permission === 'granted') {
      await generateToken();
    }
  } catch (error: any) {
    addLog(`Error solicitando permisos: ${error.message}`, 'error');
  }
};

const generateToken = async () => {
  if (notificationPermission.value !== 'granted') {
    addLog('Necesitas dar permisos de notificación primero', 'error');
    return;
  }

  loading.value = true;
  try {
    addLog('Generando token FCM...', 'info');
    const result = await initializeFirebaseMessaging();
    
    if (result?.messaging && result?.token) {
      fcmToken.value = result.token;
      addLog('✅ Token generado correctamente', 'success');
      
      // Intentar guardar automáticamente
      await saveTokenManually();
    } else {
      addLog('❌ No se pudo inicializar Firebase Messaging', 'error');
    }
  } catch (error: any) {
    addLog(`❌ Error generando token: ${error.message}`, 'error');
  } finally {
    loading.value = false;
  }
};

const saveTokenManually = async (): Promise<boolean> => {
  if (!fcmToken.value) {
    addLog('No hay token para guardar', 'error');
    return false;
  }

  loading.value = true;
  try {
    addLog('Guardando token en backend...', 'info');
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      addLog('❌ CSRF token no disponible', 'error');
      return false;
    }
    
    const response = await fetch('/fcm/token', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({ token: fcmToken.value }),
    });

    const data = await parseResponseSafely(response);

    if (response.ok) {
      addLog('✅ Token guardado en base de datos', 'success');
      await fetchDbToken();
      return true;
    } else {
      addLog(`❌ Error guardando token: ${data.message || 'Error desconocido'}`, 'error');
      return false;
    }
  } catch (error: any) {
    addLog(`❌ Error en request: ${error.message}`, 'error');
    return false;
  } finally {
    loading.value = false;
  }
};

const sendTestNotificationToMyDevices = async () => {
  addLog('Preparando envío a todos tus dispositivos...', 'info');

  const saved = await saveTokenManually();
  if (!saved) {
    addLog('❌ No se pudo guardar el token antes del envío', 'error');
    return;
  }

  await sendTestNotification();
};

const fetchDbToken = async () => {
  try {
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      addLog('❌ CSRF token no disponible', 'error');
      return;
    }

    const response = await fetch('/fcm/my-tokens', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
    });

    const data = await parseResponseSafely(response);

    if (!response.ok) {
      addLog(`❌ Error obteniendo token de DB: ${data.message || 'Error desconocido'}`, 'error');
      return;
    }

    dbToken.value = data?.token || null;

    if (dbToken.value) {
      const count = typeof data?.count === 'number' ? data.count : 1;
      addLog(`✅ Token obtenido de DB (${count} dispositivo(s))`, 'success');
    } else {
      addLog('⚠️ No hay token en DB', 'info');
    }
  } catch (error: any) {
    addLog(`Error obteniendo token de DB: ${error.message}`, 'error');
  }
};

const sendTestNotification = async () => {
  loading.value = true;
  try {
    addLog('Enviando notificación de prueba...', 'info');
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      addLog('❌ CSRF token no disponible', 'error');
      return;
    }
    
    const response = await fetch('/fcm/test', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
    });

    const data = await parseResponseSafely(response);

    const successful = typeof data.successful === 'number' ? data.successful : null;
    const failed = typeof data.failed === 'number' ? data.failed : null;

    if (response.ok) {
      if (successful !== null && failed !== null) {
        addLog(`✅ Notificación enviada a ${successful} dispositivo(s). Fallidos: ${failed}`, successful > 0 ? 'success' : 'error');
      } else {
        addLog('✅ Notificación enviada! Revisa tu navegador', 'success');
      }
      return;
    }

    const firstFailureReason = Array.isArray(data?.failure_reasons) && data.failure_reasons.length > 0
      ? data.failure_reasons[0]?.error
      : null;

    const message = firstFailureReason
      ? `${data.message || 'Error desconocido'} | Motivo: ${firstFailureReason}`
      : (data.message || 'Error desconocido');

    addLog(`❌ Error: ${message}`, 'error');
  } catch (error: any) {
    addLog(`❌ Error enviando notificación: ${error.message}`, 'error');
  } finally {
    loading.value = false;
  }
};

const copyToken = () => {
  if (fcmToken.value) {
    navigator.clipboard.writeText(fcmToken.value);
    addLog('Token copiado al portapapeles', 'success');
  }
};

const refresh = () => {
  fetchDbToken();
  addLog('Estado actualizado', 'info');
};

onMounted(async () => {
  addLog('Iniciando debug FCM...', 'info');

  await fetchDbToken();
  
  // Intentar obtener el token actual de Firebase
  if (notificationPermission.value === 'granted') {
    try {
      const result = await initializeFirebaseMessaging();
      if (result?.messaging && result?.token) {
        fcmToken.value = result.token;
        addLog('Token FCM cargado', 'success');
      }
    } catch (error: any) {
      addLog(`Error cargando token: ${error.message}`, 'error');
    }
  }
  
  addLog('Debug listo', 'success');
});
</script>
