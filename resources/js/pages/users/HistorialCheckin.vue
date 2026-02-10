<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type Checkin = {
  id: number;
  check_in_time: string;
  check_in_latitude: number | null;
  check_in_longitude: number | null;
  check_out_time: string | null;
  check_out_latitude: number | null;
  check_out_longitude: number | null;
  duracion: string | null;
};

const { checkins } = defineProps<{ checkins: Checkin[] }>();

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
</script>

<template>
  <LeaderLayout title="Historial de Checkins">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div class="flex items-center gap-3">
        <Link
          href="/checkin"
          class="text-sm font-medium text-primary hover:underline"
        >
          Volver al reloj checador
        </Link>
      </div>

      <div>
        <h1 class="text-xl font-semibold text-foreground">Historial de Checkins</h1>
        <p class="text-sm text-muted-foreground">
          Registro completo de entradas y salidas
        </p>
      </div>

      <div v-if="checkins.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6">
        <p class="text-sm text-muted-foreground">No hay registros de checkin.</p>
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="checkin in checkins"
          :key="checkin.id"
          class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm"
        >
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold capitalize text-foreground">
              {{ formatDate(checkin.check_in_time) }}
            </h2>
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
                  @click="openMapLink(checkin.check_in_latitude, checkin.check_in_longitude)"
                >
                  Ver ubicación en mapa
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
                    @click="openMapLink(checkin.check_out_latitude, checkin.check_out_longitude)"
                  >
                    Ver ubicación en mapa
                  </button>
                </div>
                <div v-else class="mt-3 text-xs text-muted-foreground">
                  Sin ubicación GPS
                </div>
              </div>
              <div v-else class="text-base text-muted-foreground">
                Sin registro
              </div>
            </div>
          </div>

          <!-- Duración -->
          <div v-if="checkin.duracion" class="mt-4 rounded-lg bg-primary/10 p-3 text-center">
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
  </LeaderLayout>
</template>
