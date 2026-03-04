<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type TareaMaterial = {
  id: number;
  nombre: string;
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  materiales: string | null;
};

const { tareas } = defineProps<{ tareas: TareaMaterial[] }>();

const splitMaterials = (materiales: string | null) => {
  if (!materiales) return [];

  const normalized = materiales.trim();
  if (!normalized) return [];

  try {
    const parsed = JSON.parse(normalized) as Array<{ label?: string; name?: string }>;
    if (Array.isArray(parsed)) {
      return parsed
        .map((item) => (item?.label ?? item?.name ?? '').trim())
        .filter((item) => item.length > 0);
    }
  } catch {
    // fallback for legacy plain text values
  }

  return normalized
    .split(/\r?\n|,/)
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
};
</script>

<template>
  <LeaderLayout title="Mis materiales">
    <Head title="Mis materiales" />

    <div class="flex h-full flex-1 flex-col gap-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Materiales por tarea</h1>
        <p class="text-sm text-muted-foreground">
          Revisa qué material necesitas en cada tarea.
        </p>
      </div>

      <div v-if="tareas.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6 text-sm text-muted-foreground">
        No hay tareas para mostrar.
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="tarea in tareas"
          :key="tarea.id"
          class="rounded-2xl border border-sidebar-border/70 bg-background p-4 shadow-sm"
        >
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">{{ tarea.nombre }}</h2>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">{{ tarea.estado }}</span>
          </div>

          <div class="mt-3 rounded-md border border-input bg-muted/20 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Material requerido</p>
            <ul v-if="splitMaterials(tarea.materiales).length" class="mt-2 list-disc space-y-1 pl-5 text-sm text-foreground">
              <li v-for="(material, index) in splitMaterials(tarea.materiales)" :key="`${tarea.id}-${index}`">
                {{ material }}
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-muted-foreground">Sin material definido para esta tarea.</p>
          </div>
        </div>
      </div>
    </div>
  </LeaderLayout>
</template>
