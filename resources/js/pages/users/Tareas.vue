<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type Tarea = {
  id: number;
  nombre: string;
  instrucciones: string;
  materiales: string | null;
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  lideres?: { id: number; nombre: string }[];
  evidencias: { id: number }[];
  historial: { id: number }[];
};

const { tareas } = defineProps<{ tareas: Tarea[] }>();

type MaterialItem = {
  label: string;
  in_stock: boolean;
  holder_name: string | null;
};

const parseMaterialItems = (materiales: string | null): MaterialItem[] => {
  if (!materiales) return [];

  const normalized = materiales.trim();
  if (!normalized) return [];

  try {
    const parsed = JSON.parse(normalized) as Array<{
      label?: string;
      name?: string;
      in_stock?: boolean;
      holder_name?: string | null;
    }>;

    if (Array.isArray(parsed)) {
      return parsed
        .map((item) => ({
          label: (item?.label ?? item?.name ?? '').trim(),
          in_stock: Boolean(item?.in_stock),
          holder_name: item?.holder_name ?? null,
        }))
        .filter((item) => item.label.length > 0);
    }
  } catch {
    // fallback for plain text format
  }

  return normalized
    .split(/\r?\n|,/)
    .map((item) => item.trim())
    .filter((item) => item.length > 0)
    .map((label) => ({
      label,
      in_stock: false,
      holder_name: null,
    }));
};
</script>

<template>
  <LeaderLayout title="Mis tareas">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Mis tareas</h1>
        <p class="text-sm text-muted-foreground">
          Tareas disponibles para cuadrillas. Abre una tarea para tomarla y continuar con evidencias previas.
        </p>
      </div>

      <div v-if="tareas.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6">
        <p class="text-sm text-muted-foreground">No hay tareas disponibles.</p>
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
          <div v-if="parseMaterialItems(tarea.materiales).length" class="mt-2 grid gap-1 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">Materiales:</p>
            <p
              v-for="(material, index) in parseMaterialItems(tarea.materiales)"
              :key="`${tarea.id}-material-${index}`"
              class="text-xs"
            >
              {{ material.label }} ·
              {{ material.in_stock ? (material.holder_name ? `Lo tiene: ${material.holder_name}` : 'En almacén') : 'Pendiente' }}
            </p>
          </div>
          <div class="mt-4 flex items-center gap-4 text-xs text-muted-foreground">
            <span>Evidencias: {{ tarea.evidencias.length }}</span>
            <span>Reportes: {{ tarea.historial.length }}</span>
          </div>
        </Link>
      </div>
    </div>
  </LeaderLayout>
</template>
