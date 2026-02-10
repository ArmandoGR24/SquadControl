<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Usuario = {
  id: number;
  nombre: string;
};

type Checkin = {
  id: number;
  usuario: string;
  user_id: number;
  check_in_time: string;
  check_in_latitude: number | null;
  check_in_longitude: number | null;
  check_out_time: string | null;
  check_out_latitude: number | null;
  check_out_longitude: number | null;
  duracion: string | null;
};

const { checkins, usuarios } = defineProps<{
  checkins: Checkin[];
  usuarios: Usuario[];
}>();

const selectedUserId = ref<number | null>(null);
const selectedDate = ref('');

const applyFilters = () => {
  const params: Record<string, string | number> = {};
  
  if (selectedUserId.value) {
    params.user_id = selectedUserId.value;
  }
  
  if (selectedDate.value) {
    params.date = selectedDate.value;
  }
  
  router.get('/checkins-admin', params, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  selectedUserId.value = null;
  selectedDate.value = '';
  router.get('/checkins-admin', {}, {
    preserveState: true,
    preserveScroll: true,
  });
};

const formatDateTime = (isoString: string) => {
  const date = new Date(isoString);
  return date.toLocaleString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const formatDate = (isoString: string) => {
  const date = new Date(isoString);
  return date.toLocaleDateString('es-MX', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatTime = (isoString: string) => {
  const date = new Date(isoString);
  return date.toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const openMapLink = (lat: number | null, lng: number | null) => {
  if (!lat || !lng) return;
  const url = `https://www.google.com/maps?q=${lat},${lng}`;
  window.open(url, '_blank');
};

// Agrupar checkins por fecha
const groupedCheckins = ref<Record<string, Checkin[]>>({});

watch(
  () => checkins,
  (newCheckins) => {
    const grouped: Record<string, Checkin[]> = {};
    newCheckins.forEach((checkin) => {
      const date = new Date(checkin.check_in_time).toLocaleDateString('es-MX');
      if (!grouped[date]) {
        grouped[date] = [];
      }
      grouped[date].push(checkin);
    });
    groupedCheckins.value = grouped;
  },
  { immediate: true }
);
</script>

<template>
  <AppLayout>
    <Head title="Checkins - Admin" />
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Panel de Checkins</h1>
        <p class="text-sm text-muted-foreground">
          Visualiza todos los registros de entrada y salida del personal
        </p>
      </div>

      <!-- Filtros -->
      <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-foreground">Filtros</h2>
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="grid gap-2">
            <label class="text-xs font-medium text-muted-foreground" for="filter-user">
              Usuario
            </label>
            <select
              id="filter-user"
              v-model="selectedUserId"
              class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
            >
              <option :value="null">Todos los usuarios</option>
              <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
                {{ usuario.nombre }}
              </option>
            </select>
          </div>

          <div class="grid gap-2">
            <label class="text-xs font-medium text-muted-foreground" for="filter-date">
              Fecha
            </label>
            <input
              id="filter-date"
              v-model="selectedDate"
              type="date"
              class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
            />
          </div>

          <div class="flex items-end gap-2">
            <button
              type="button"
              class="h-9 flex-1 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
              @click="applyFilters"
            >
              Aplicar
            </button>
            <button
              type="button"
              class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
              @click="clearFilters"
            >
              Limpiar
            </button>
          </div>
        </div>
      </div>

      <!-- Resumen -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm">
          <div class="text-2xl font-bold text-foreground">{{ checkins.length }}</div>
          <div class="text-xs text-muted-foreground">Total de registros</div>
        </div>
        <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm">
          <div class="text-2xl font-bold text-green-600 dark:text-green-400">
            {{ checkins.filter((c) => c.check_out_time !== null).length }}
          </div>
          <div class="text-xs text-muted-foreground">Completos</div>
        </div>
        <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm">
          <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
            {{ checkins.filter((c) => c.check_out_time === null).length }}
          </div>
          <div class="text-xs text-muted-foreground">En progreso</div>
        </div>
      </div>

      <!-- Lista de checkins -->
      <div v-if="checkins.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6">
        <p class="text-sm text-muted-foreground">No hay registros de checkin.</p>
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="checkin in checkins"
          :key="checkin.id"
          class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm"
        >
          <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-base font-semibold text-foreground">
                {{ checkin.usuario }}
              </h2>
              <p class="text-xs capitalize text-muted-foreground">
                {{ formatDate(checkin.check_in_time) }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span
                v-if="checkin.check_out_time"
                class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground"
              >
                Completo
              </span>
              <span
                v-else
                class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-medium text-green-700 dark:text-green-400"
              >
                En progreso
              </span>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <!-- Entrada -->
            <div class="rounded-lg bg-muted/40 p-4">
              <div class="mb-2 flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Entrada
                </span>
              </div>
              <div class="text-2xl font-bold text-foreground">
                {{ formatTime(checkin.check_in_time) }}
              </div>
              <div class="mt-2 text-xs text-muted-foreground">
                {{ formatDateTime(checkin.check_in_time) }}
              </div>
              <div
                v-if="checkin.check_in_latitude && checkin.check_in_longitude"
                class="mt-3"
              >
                <button
                  type="button"
                  class="text-xs text-primary hover:underline"
                  @click="
                    openMapLink(checkin.check_in_latitude, checkin.check_in_longitude)
                  "
                >
                  📍 Ver ubicación en mapa
                </button>
              </div>
              <div v-else class="mt-3 text-xs text-muted-foreground">
                Sin ubicación GPS
              </div>
            </div>

            <!-- Salida -->
            <div class="rounded-lg bg-muted/40 p-4">
              <div class="mb-2 flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-destructive"></div>
                <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Salida
                </span>
              </div>
              <div v-if="checkin.check_out_time">
                <div class="text-2xl font-bold text-foreground">
                  {{ formatTime(checkin.check_out_time) }}
                </div>
                <div class="mt-2 text-xs text-muted-foreground">
                  {{ formatDateTime(checkin.check_out_time) }}
                </div>
                <div
                  v-if="checkin.check_out_latitude && checkin.check_out_longitude"
                  class="mt-3"
                >
                  <button
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click="
                      openMapLink(
                        checkin.check_out_latitude,
                        checkin.check_out_longitude
                      )
                    "
                  >
                    📍 Ver ubicación en mapa
                  </button>
                </div>
                <div v-else class="mt-3 text-xs text-muted-foreground">
                  Sin ubicación GPS
                </div>
              </div>
              <div v-else class="text-base text-muted-foreground">Sin registro</div>
            </div>
          </div>

          <!-- Duración -->
          <div
            v-if="checkin.duracion"
            class="mt-4 rounded-lg bg-primary/10 p-3 text-center"
          >
            <span class="text-xs font-semibold uppercase tracking-wide text-primary">
              Duración total
            </span>
            <div class="mt-1 text-lg font-bold text-primary">
              {{ checkin.duracion }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
