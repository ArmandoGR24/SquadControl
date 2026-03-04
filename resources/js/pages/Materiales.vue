<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

type Lider = {
  id: number;
  nombre: string;
};

type TareaMaterial = {
  id: number;
  nombre: string;
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  materiales: string | null;
  lideres: Lider[];
};

type MaterialItem = {
  label: string;
  in_stock: boolean;
  holder_name: string | null;
};

const { tareas } = defineProps<{ tareas: TareaMaterial[] }>();

const splitMaterials = (materiales: string | null): MaterialItem[] => {
  if (!materiales) return [];

  const normalized = materiales.trim();
  if (!normalized) return [];

  try {
    const parsed = JSON.parse(normalized) as Array<{
      label?: string;
      name?: string;
      in_stock?: boolean;
      holder_user_id?: number | null;
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
    // fallback for legacy plain text values
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

const materialStatus = (materiales: string | null) => {
  const items = splitMaterials(materiales);

  if (items.length === 0) {
    return {
      estado: 'Pendiente',
      detalle: 'Sin material definido',
    };
  }

  const completo = items.every((item) => item.in_stock);

  if (!completo) {
    return {
      estado: 'Pendiente',
      detalle: 'Faltan materiales por marcar',
    };
  }

  const holders = Array.from(
    new Set(
      items
        .map((item) => (item.holder_name ?? '').trim())
        .filter((holder) => holder.length > 0),
    ),
  );

  return {
    estado: 'Completo',
    detalle: holders.length > 0 ? `Lo tiene: ${holders.join(', ')}` : 'En almacén',
  };
};

</script>

<template>
  <Head title="Materiales por tarea" />
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div>
        <h1 class="text-xl font-semibold text-foreground">Materiales por tarea</h1>
        <p class="text-sm text-muted-foreground">
          Elige una tarea para ver y actualizar el detalle de sus materiales.
        </p>
      </div>

      <div v-if="tareas.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground">
        No hay tareas registradas.
      </div>

      <div v-else class="grid gap-4">
        <Link
          v-for="tarea in tareas"
          :key="tarea.id"
          :href="`/materiales/${tarea.id}`"
          class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm"
        >
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-foreground">{{ tarea.nombre }}</h2>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">{{ tarea.estado }}</span>
          </div>

          <p class="mt-2 text-xs text-muted-foreground">
            <span class="font-medium text-foreground">Líderes:</span>
            {{ tarea.lideres.length ? tarea.lideres.map((lider) => lider.nombre).join(', ') : 'Sin asignar' }}
          </p>

          <div class="mt-3 rounded-md border border-input bg-muted/20 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Material requerido</p>
            <ul v-if="splitMaterials(tarea.materiales).length" class="mt-2 list-disc space-y-1 pl-5 text-sm text-foreground">
              <li v-for="(material, index) in splitMaterials(tarea.materiales)" :key="`${tarea.id}-${index}`">
                {{ material.label }}
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-muted-foreground">Sin material definido para esta tarea.</p>
            <div class="mt-3 rounded-md border border-input bg-background p-2">
              <p class="text-xs font-semibold text-foreground">
                Estado material: {{ materialStatus(tarea.materiales).estado }}
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ materialStatus(tarea.materiales).detalle }}
              </p>
            </div>
            <p class="mt-3 text-xs font-medium text-primary">Abrir detalle de materiales</p>
          </div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
