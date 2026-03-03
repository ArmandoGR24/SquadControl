<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type LastCheckin = {
  id: number;
  check_in_time: string;
  check_out_time: string | null;
} | null;

type TargetUser = {
  id: number;
  name: string;
  has_active_checkin: boolean;
};

const props = defineProps<{
  hasCheckedIn: boolean;
  lastCheckin: LastCheckin;
  isLeaderMode: boolean;
  targetUsers: TargetUser[];
  minimumCheckoutHours: number;
  requireLocation: boolean;
  allowLeaderIncludeSelf: boolean;
  maxTargetsPerAction: number;
}>();

const currentTime = ref(new Date());
const gpsLoading = ref(false);
const gpsError = ref<string | null>(null);
const showCheckInSelector = ref(false);
const showCheckOutSelector = ref(false);
const selectedUserIdsCheckIn = ref<number[]>([]);
const selectedUserIdsCheckOut = ref<number[]>([]);
const includeSelfCheckIn = ref(props.allowLeaderIncludeSelf);
const includeSelfCheckOut = ref(props.allowLeaderIncludeSelf);

const checkInForm = useForm({
  latitude: null as number | null,
  longitude: null as number | null,
  target_user_ids: [] as number[],
  include_self: false,
});

const checkOutForm = useForm({
  latitude: null as number | null,
  longitude: null as number | null,
  target_user_ids: [] as number[],
  include_self: false,
});

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string>);

const selectedUsersCountCheckIn = computed(() => selectedUserIdsCheckIn.value.length + (includeSelfCheckIn.value && props.isLeaderMode && props.allowLeaderIncludeSelf ? 1 : 0));
const selectedUsersCountCheckOut = computed(() => selectedUserIdsCheckOut.value.length + (includeSelfCheckOut.value && props.isLeaderMode && props.allowLeaderIncludeSelf ? 1 : 0));
const activeTargetUsers = computed(() => props.targetUsers.filter((user) => user.has_active_checkin));

const selectedUsersActiveCount = computed(() => {
  if (!props.isLeaderMode) {
    return props.hasCheckedIn ? 1 : 0;
  }

  const activeSelected = props.targetUsers.filter((user) => user.has_active_checkin).length;
  return activeSelected + (props.hasCheckedIn ? 1 : 0);
});

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

const prepareLeaderPayload = (
  form: typeof checkInForm | typeof checkOutForm,
  selectedUserIds: number[],
  includeSelf: boolean,
) => {
  form.target_user_ids = [...selectedUserIds];
  form.include_self = includeSelf;
};

const selectAllUsersCheckIn = () => {
  selectedUserIdsCheckIn.value = props.targetUsers.map((user) => user.id);
};

const selectAllUsersCheckOut = () => {
  selectedUserIdsCheckOut.value = activeTargetUsers.value.map((user) => user.id);
};

const clearSelectionCheckIn = () => {
  selectedUserIdsCheckIn.value = [];
};

const clearSelectionCheckOut = () => {
  selectedUserIdsCheckOut.value = [];
};

const openCheckInSelector = () => {
  showCheckInSelector.value = true;
};

const openCheckOutSelector = () => {
  showCheckOutSelector.value = true;
};

const submitCheckIn = async () => {
  if (props.isLeaderMode) {
    prepareLeaderPayload(checkInForm, selectedUserIdsCheckIn.value, includeSelfCheckIn.value && props.allowLeaderIncludeSelf);
  }

  try {
    const location = await getLocation();
    checkInForm.latitude = location.latitude;
    checkInForm.longitude = location.longitude;
    checkInForm.post('/checkin/entrada', {
      preserveScroll: true,
      onSuccess: () => {
        showCheckInSelector.value = false;
      },
    });
  } catch (error) {
    gpsError.value = error instanceof Error ? error.message : 'Error desconocido';
    checkInForm.post('/checkin/entrada', {
      preserveScroll: true,
      onSuccess: () => {
        showCheckInSelector.value = false;
      },
    });
  }
};

const submitCheckOut = async () => {
  if (props.isLeaderMode) {
    prepareLeaderPayload(checkOutForm, selectedUserIdsCheckOut.value, includeSelfCheckOut.value && props.allowLeaderIncludeSelf);
  }

  try {
    const location = await getLocation();
    checkOutForm.latitude = location.latitude;
    checkOutForm.longitude = location.longitude;
    checkOutForm.post('/checkin/salida', {
      preserveScroll: true,
      onSuccess: () => {
        showCheckOutSelector.value = false;
      },
    });
  } catch (error) {
    gpsError.value = error instanceof Error ? error.message : 'Error desconocido';
    checkOutForm.post('/checkin/salida', {
      preserveScroll: true,
      onSuccess: () => {
        showCheckOutSelector.value = false;
      },
    });
  }
};

const handleCheckIn = () => {
  if (props.isLeaderMode) {
    openCheckInSelector();
    return;
  }

  void submitCheckIn();
};

const handleCheckOut = () => {
  if (props.isLeaderMode) {
    openCheckOutSelector();
    return;
  }

  void submitCheckOut();
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
            {{ props.isLeaderMode ? 'Registra entrada/salida para usuarios y para ti' : 'Registra entrada y salida diaria' }}
          </p>
        </div>
        <Link
          href="/checkin/historial"
          class="flex h-9 items-center rounded-md bg-muted px-4 text-sm font-medium text-foreground shadow hover:bg-muted/80"
        >
          Historial
        </Link>
      </div>

      <div class="grid gap-4">
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-8 text-center shadow-sm">
          <div class="text-6xl font-bold text-foreground md:text-8xl">
            {{ formattedTime }}
          </div>
          <div class="mt-3 text-base capitalize text-muted-foreground md:text-lg">
            {{ formattedDate }}
          </div>
        </div>

        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-foreground">Estado actual</h2>
          <div class="mt-4">
            <div
              v-if="hasCheckedIn && lastCheckin && !props.isLeaderMode"
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
              v-else-if="!props.isLeaderMode && lastCheckin && lastCheckin.check_out_time"
              class="rounded-lg bg-muted p-4"
            >
              <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-full bg-muted-foreground"></div>
                <span class="font-semibold text-foreground">Sin entrada activa</span>
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
                <span class="font-semibold text-foreground">
                  {{ props.isLeaderMode ? `Usuarios con entrada activa: ${selectedUsersActiveCount}` : 'Sin entrada activa' }}
                </span>
              </div>
            </div>
          </div>

          <div
            v-if="gpsError"
            class="mt-3 rounded-lg bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
          >
            {{ gpsError }}
          </div>

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

        <div class="grid gap-3 sm:grid-cols-2">
          <button
            type="button"
            class="h-16 rounded-xl bg-primary px-6 text-lg font-semibold text-primary-foreground shadow-lg transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="checkInForm.processing || gpsLoading || (!props.isLeaderMode && hasCheckedIn)"
            @click="handleCheckIn"
          >
            <span v-if="gpsLoading">Obteniendo ubicación...</span>
            <span v-else-if="checkInForm.processing">Registrando...</span>
            <span v-else>
              {{ props.isLeaderMode ? 'Registrar entrada' : 'Registrar entrada' }}
            </span>
          </button>

          <button
            type="button"
            class="h-16 rounded-xl bg-destructive px-6 text-lg font-semibold text-destructive-foreground shadow-lg transition hover:bg-destructive/90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="checkOutForm.processing || gpsLoading || (!props.isLeaderMode && !hasCheckedIn)"
            @click="handleCheckOut"
          >
            <span v-if="gpsLoading">Obteniendo ubicación...</span>
            <span v-else-if="checkOutForm.processing">Registrando...</span>
            <span v-else>
              {{ props.isLeaderMode ? 'Registrar salida' : 'Registrar salida' }}
            </span>
          </button>
        </div>

        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-foreground">Información GPS</h2>
          <p class="mt-2 text-sm text-muted-foreground">
            {{ props.requireLocation
              ? 'La ubicación GPS es obligatoria para registrar entrada y salida.'
              : 'Al registrar entrada o salida se guarda la ubicación GPS para control operativo.' }}
          </p>
          <p class="mt-2 text-xs text-muted-foreground">
            Salida permitida después de {{ props.minimumCheckoutHours }} hora(s) desde la entrada.
          </p>
          <p v-if="props.isLeaderMode" class="mt-1 text-xs text-muted-foreground">
            Máximo {{ props.maxTargetsPerAction }} usuario(s) por operación.
          </p>
        </div>
      </div>

      <div
        v-if="props.isLeaderMode && showCheckInSelector"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showCheckInSelector = false"
      >
        <div class="w-full max-w-3xl rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-lg">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">Registrar entrada</h2>
            <span class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
              {{ selectedUsersCountCheckIn }} seleccionado(s)
            </span>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="h-8 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
              @click="selectAllUsersCheckIn"
            >
              Seleccionar todos
            </button>
            <button
              type="button"
              class="h-8 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
              @click="clearSelectionCheckIn"
            >
              Limpiar
            </button>
          </div>

          <label class="mt-3 flex items-center gap-2 text-sm text-foreground" :class="{ 'opacity-60': !props.allowLeaderIncludeSelf }">
            <input v-model="includeSelfCheckIn" :disabled="!props.allowLeaderIncludeSelf" type="checkbox" class="h-4 w-4 rounded border-input" />
            Incluir mi entrada (líder)
          </label>
          <p v-if="!props.allowLeaderIncludeSelf" class="mt-1 text-xs text-muted-foreground">
            Esta opción está deshabilitada por configuración.
          </p>

          <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-sidebar-border/70 p-3">
            <div class="grid gap-2 md:grid-cols-2">
              <label
                v-for="user in props.targetUsers"
                :key="`in-${user.id}`"
                class="flex items-center justify-between gap-2 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <input
                    v-model="selectedUserIdsCheckIn"
                    :value="user.id"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input"
                  />
                  <span class="truncate text-sm text-foreground">{{ user.name }}</span>
                </div>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="user.has_active_checkin ? 'bg-green-500/20 text-green-700 dark:text-green-400' : 'bg-muted text-muted-foreground'">
                  {{ user.has_active_checkin ? 'Con entrada' : 'Sin entrada' }}
                </span>
              </label>
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
              @click="showCheckInSelector = false"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
              :disabled="selectedUsersCountCheckIn === 0 || checkInForm.processing || gpsLoading"
              @click="submitCheckIn"
            >
              Confirmar entrada
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="props.isLeaderMode && showCheckOutSelector"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showCheckOutSelector = false"
      >
        <div class="w-full max-w-3xl rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-lg">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">Registrar salida</h2>
            <span class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
              {{ selectedUsersCountCheckOut }} seleccionado(s)
            </span>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="h-8 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
              @click="selectAllUsersCheckOut"
            >
              Seleccionar todos
            </button>
            <button
              type="button"
              class="h-8 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
              @click="clearSelectionCheckOut"
            >
              Limpiar
            </button>
          </div>

          <label class="mt-3 flex items-center gap-2 text-sm text-foreground" :class="{ 'opacity-60': !props.allowLeaderIncludeSelf }">
            <input v-model="includeSelfCheckOut" :disabled="!props.allowLeaderIncludeSelf" type="checkbox" class="h-4 w-4 rounded border-input" />
            Incluir mi salida (líder)
          </label>
          <p v-if="!props.allowLeaderIncludeSelf" class="mt-1 text-xs text-muted-foreground">
            Esta opción está deshabilitada por configuración.
          </p>

          <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-sidebar-border/70 p-3">
            <div v-if="activeTargetUsers.length > 0" class="grid gap-2 md:grid-cols-2">
              <label
                v-for="user in activeTargetUsers"
                :key="`out-${user.id}`"
                class="flex items-center justify-between gap-2 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <input
                    v-model="selectedUserIdsCheckOut"
                    :value="user.id"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input"
                  />
                  <span class="truncate text-sm text-foreground">{{ user.name }}</span>
                </div>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="user.has_active_checkin ? 'bg-green-500/20 text-green-700 dark:text-green-400' : 'bg-muted text-muted-foreground'">
                  {{ user.has_active_checkin ? 'Con entrada' : 'Sin entrada' }}
                </span>
              </label>
            </div>
            <p v-else class="text-sm text-muted-foreground">
              No hay usuarios con entrada activa para registrar salida.
            </p>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
              @click="showCheckOutSelector = false"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="h-9 rounded-md bg-destructive px-4 text-sm font-medium text-destructive-foreground hover:bg-destructive/90 disabled:opacity-60"
              :disabled="selectedUsersCountCheckOut === 0 || checkOutForm.processing || gpsLoading"
              @click="submitCheckOut"
            >
              Confirmar salida
            </button>
          </div>
        </div>
      </div>
    </div>
  </LeaderLayout>
</template>
