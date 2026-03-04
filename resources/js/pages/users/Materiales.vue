<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LeaderLayout from '@/layouts/LeaderLayout.vue';

type TareaMaterial = {
  id: number;
  nombre: string;
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  materiales_asignados?: Array<{
    label: string;
    in_stock: boolean;
    holder_user_id: number | null;
    holder_name: string | null;
  }>;
  materiales?: string | null | Array<{
    label?: string;
    name?: string;
    in_stock?: boolean;
    holder_user_id?: number | null;
    holder_name?: string | null;
  }>;
};

const { tareas } = defineProps<{ tareas: TareaMaterial[] }>();

const toAssignedMaterials = (tarea: TareaMaterial) => {
  if (Array.isArray(tarea.materiales_asignados)) {
    return tarea.materiales_asignados;
  }

  if (Array.isArray(tarea.materiales)) {
    return tarea.materiales
      .map((material) => ({
        label: (material?.label ?? material?.name ?? '').trim(),
        in_stock: Boolean(material?.in_stock),
        holder_user_id: material?.holder_user_id ? Number(material.holder_user_id) : null,
        holder_name: material?.holder_name ?? null,
      }))
      .filter((material) => material.label.length > 0);
  }

  if (typeof tarea.materiales === 'string' && tarea.materiales.trim() !== '') {
    return tarea.materiales
      .split(/\r?\n|,/)
      .map((item) => item.trim())
      .filter((item) => item.length > 0)
      .map((label) => ({
        label,
        in_stock: false,
        holder_user_id: null,
        holder_name: null,
      }));
  }

  return [];
};

const normalizedTareas = computed(() =>
  tareas.map((tarea) => ({
    ...tarea,
    materiales_asignados: toAssignedMaterials(tarea),
  })),
);

const selectedFilter = ref<'all' | 'pending' | 'in-stock'>('all');

const filteredTareas = computed(() => {
  if (selectedFilter.value === 'all') {
    return normalizedTareas.value;
  }

  return normalizedTareas.value
    .map((tarea) => ({
      ...tarea,
      materiales_asignados: (tarea.materiales_asignados ?? []).filter((material) =>
        selectedFilter.value === 'in-stock' ? material.in_stock : !material.in_stock,
      ),
    }))
    .filter((tarea) => (tarea.materiales_asignados ?? []).length > 0);
});
</script>

<template>
  <LeaderLayout title="Mis materiales">
    <Head title="Mis materiales" />

    <div class="flex h-full flex-1 flex-col gap-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Materiales asignados</h1>
        <p class="text-sm text-muted-foreground">
          Revisa únicamente los materiales donde tú eres el responsable.
        </p>
      </div>

      <div class="rounded-md border border-input bg-muted/20 p-3">
        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Filtrar materiales</label>
        <select
          v-model="selectedFilter"
          class="mt-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm text-foreground sm:w-56"
        >
          <option value="all">Todos</option>
          <option value="pending">Pendiente</option>
          <option value="in-stock">En almacén</option>
        </select>
      </div>

      <div v-if="filteredTareas.length === 0" class="rounded-2xl border border-sidebar-border/70 p-6 text-sm text-muted-foreground">
        No tienes materiales asignados actualmente.
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="tarea in filteredTareas"
          :key="tarea.id"
          class="rounded-2xl border border-sidebar-border/70 bg-background p-4 shadow-sm"
        >
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">{{ tarea.nombre }}</h2>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">{{ tarea.estado }}</span>
          </div>

          <div class="mt-3 rounded-md border border-input bg-muted/20 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Material asignado a ti</p>
            <ul v-if="tarea.materiales_asignados.length" class="mt-2 space-y-2 text-sm text-foreground">
              <li
                v-for="(material, index) in tarea.materiales_asignados"
                :key="`${tarea.id}-${index}`"
                class="rounded-md border border-input bg-background px-3 py-2"
              >
                <p class="font-medium">{{ material.label }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ material.in_stock ? 'En almacén' : 'Pendiente' }}
                </p>
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-muted-foreground">Sin material definido para esta tarea.</p>
          </div>
        </div>
      </div>
    </div>
  </LeaderLayout>
</template>
