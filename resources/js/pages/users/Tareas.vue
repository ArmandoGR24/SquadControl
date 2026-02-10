<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type Tarea = {
  id: number;
  nombre: string;
  instrucciones: string;
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  evidencias: { id: number }[];
  historial: { id: number }[];
};

const { tareas } = defineProps<{ tareas: Tarea[] }>();
</script>

<template>
  <LeaderLayout title="Mis tareas">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Mis tareas</h1>
        <p class="text-sm text-muted-foreground">
          Tareas asignadas a tu usuario. Toca una tarjeta para ver el detalle.
        </p>
      </div>

      <div v-if="tareas.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6">
        <p class="text-sm text-muted-foreground">No tienes tareas asignadas.</p>
      </div>

      <div v-else class="grid gap-4">
        <Link
          v-for="tarea in tareas"
          :key="tarea.id"
          :href="`/mis-tareas/${tarea.id}`"
          class="block rounded-2xl border border-sidebar-border/70 bg-background p-4 shadow-sm transition hover:shadow-md"
        >
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">
              {{ tarea.nombre }}
            </h2>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">
              {{ tarea.estado }}
            </span>
          </div>
          <p class="mt-2 line-clamp-2 text-sm text-muted-foreground">
            {{ tarea.instrucciones }}
          </p>
          <div class="mt-4 flex items-center gap-4 text-xs text-muted-foreground">
            <span>Evidencias: {{ tarea.evidencias.length }}</span>
            <span>Reportes: {{ tarea.historial.length }}</span>
          </div>
        </Link>
      </div>
    </div>
  </LeaderLayout>
</template>
