<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type LastCheckin = {
  id: number;
  check_in_time: string;
  check_out_time: string | null;
} | null;

const { hasCheckedIn, lastCheckin } = defineProps<{
  hasCheckedIn: boolean;
  lastCheckin: LastCheckin;
}>();

const currentTime = ref(new Date());
const gpsLoading = ref(false);
const gpsError = ref<string | null>(null);

const checkInForm = useForm({
  latitude: null as number | null,
  longitude: null as number | null,
});

const checkOutForm = useForm({
  latitude: null as number | null,
  longitude: null as number | null,
});

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string>);

// Actualizar reloj cada segundo
let clockInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  clockInterval = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
});

const formattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
});

const formattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('es-MX', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const getLocation = (): Promise<{ latitude: number; longitude: number }> => {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocalización no soportada por el navegador'));
      return;
    }

    gpsLoading.value = true;
    gpsError.value = null;

    navigator.geolocation.getCurrentPosition(
      (position) => {
        gpsLoading.value = false;
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        });
      },
      (error) => {
        gpsLoading.value = false;
        let errorMsg = 'Error al obtener ubicación';
        if (error.code === error.PERMISSION_DENIED) {
          errorMsg = 'Permiso de ubicación denegado';
        } else if (error.code === error.POSITION_UNAVAILABLE) {
          errorMsg = 'Ubicación no disponible';
        } else if (error.code === error.TIMEOUT) {
          errorMsg = 'Tiempo de espera agotado';
        }
        reject(new Error(errorMsg));
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      }
    );
  });
};

const handleCheckIn = async () => {
  try {
    const location = await getLocation();
    checkInForm.latitude = location.latitude;
    checkInForm.longitude = location.longitude;
    checkInForm.post('/checkin/entrada', {
      preserveScroll: true,
    });
  } catch (error) {
    gpsError.value = error instanceof Error ? error.message : 'Error desconocido';
    // Enviar sin ubicación si falla
    checkInForm.post('/checkin/entrada', {
      preserveScroll: true,
    });
  }
};

const handleCheckOut = async () => {
  try {
    const location = await getLocation();
    checkOutForm.latitude = location.latitude;
    checkOutForm.longitude = location.longitude;
    checkOutForm.post('/checkin/salida', {
      preserveScroll: true,
    });
  } catch (error) {
    gpsError.value = error instanceof Error ? error.message : 'Error desconocido';
    // Enviar sin ubicación si falla
    checkOutForm.post('/checkin/salida', {
      preserveScroll: true,
    });
  }
};

const formatDateTime = (isoString: string) => {
  const date = new Date(isoString);
  return date.toLocaleString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
  <LeaderLayout title="Checkin">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-foreground">Reloj Checador</h1>
          <p class="text-sm text-muted-foreground">
            Registra tu entrada y salida diaria
          </p>
        </div>
        <Link
          href="/checkin/historial"
          class="h-9 rounded-md bg-muted px-4 text-sm font-medium text-foreground shadow hover:bg-muted/80 flex items-center"
        >
          Historial
        </Link>
      </div>

      <div class="grid gap-4">
        <!-- Reloj digital grande -->
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-8 text-center shadow-sm">
          <div class="text-6xl font-bold text-foreground md:text-8xl">
            {{ formattedTime }}
          </div>
          <div class="mt-3 text-base capitalize text-muted-foreground md:text-lg">
            {{ formattedDate }}
          </div>
        </div>

        <!-- Estado actual -->
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-foreground">Estado actual</h2>
          <div class="mt-4">
            <div
              v-if="hasCheckedIn && lastCheckin"
              class="rounded-lg bg-green-500/10 p-4 text-green-700 dark:text-green-400"
            >
              <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-full bg-green-500"></div>
                <span class="font-semibold">Entrada registrada</span>
              </div>
              <p class="mt-2 text-sm">
                Hora de entrada: {{ formatDateTime(lastCheckin.check_in_time) }}
              </p>
            </div>
            <div
              v-else-if="lastCheckin && lastCheckin.check_out_time"
              class="rounded-lg bg-muted p-4"
            >
              <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-full bg-muted-foreground"></div>
                <span class="font-semibold text-foreground">Sin entrada registrada hoy</span>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                Última salida: {{ formatDateTime(lastCheckin.check_out_time) }}
              </p>
            </div>
            <div
              v-else
              class="rounded-lg bg-muted p-4"
            >
              <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-full bg-muted-foreground"></div>
                <span class="font-semibold text-foreground">Sin entrada registrada hoy</span>
              </div>
            </div>
          </div>

          <!-- Advertencia GPS -->
          <div
            v-if="gpsError"
            class="mt-3 rounded-lg bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
          >
            {{ gpsError }}
          </div>

          <!-- Errores del formulario -->
          <div
            v-if="pageErrors.checkin"
            class="mt-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
          >
            {{ pageErrors.checkin }}
          </div>
          <div
            v-if="pageErrors.checkout"
            class="mt-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
          >
            {{ pageErrors.checkout }}
          </div>
        </div>

        <!-- Botones de acción -->
        <div class="grid gap-3 sm:grid-cols-2">
          <button
            type="button"
            class="h-16 rounded-xl bg-primary px-6 text-lg font-semibold text-primary-foreground shadow-lg transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="hasCheckedIn || checkInForm.processing || gpsLoading"
            @click="handleCheckIn"
          >
            <span v-if="gpsLoading && !hasCheckedIn">Obteniendo ubicación...</span>
            <span v-else-if="checkInForm.processing">Registrando...</span>
            <span v-else>Registrar entrada</span>
          </button>

          <button
            type="button"
            class="h-16 rounded-xl bg-destructive px-6 text-lg font-semibold text-destructive-foreground shadow-lg transition hover:bg-destructive/90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!hasCheckedIn || checkOutForm.processing || gpsLoading"
            @click="handleCheckOut"
          >
            <span v-if="gpsLoading && hasCheckedIn">Obteniendo ubicación...</span>
            <span v-else-if="checkOutForm.processing">Registrando...</span>
            <span v-else>Registrar salida</span>
          </button>
        </div>

        <!-- Información GPS -->
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-foreground">Información GPS</h2>
          <p class="mt-2 text-sm text-muted-foreground">
            Al checar entrada o salida, se registrará automáticamente tu ubicación GPS.
            Asegúrate de haber permitido el acceso a tu ubicación en el navegador.
          </p>
        </div>
      </div>
    </div>
  </LeaderLayout>
</template>
