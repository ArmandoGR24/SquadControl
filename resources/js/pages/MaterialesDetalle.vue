<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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

type Usuario = {
  id: number;
  nombre: string;
  rol: string;
};

type MaterialItem = {
  label: string;
  in_stock: boolean;
  holder_user_id: number | null;
  holder_name: string | null;
};

const { tarea, usuarios } = defineProps<{ tarea: TareaMaterial; usuarios: Usuario[] }>();

const form = useForm({
  materials: [] as MaterialItem[],
});

const newMaterialLabel = ref('');
const materialToDeleteIndex = ref<number | null>(null);
const isAddMaterialModalOpen = ref(false);

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
          holder_user_id: item?.holder_user_id ? Number(item.holder_user_id) : null,
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
      holder_user_id: null,
      holder_name: null,
    }));
};

const materials = ref<MaterialItem[]>(splitMaterials(tarea.materiales));
const selectedHolderUserId = ref<string>('');

const syncSelectedHolderFromMaterials = () => {
  if (materials.value.length === 0) {
    selectedHolderUserId.value = '';
    return;
  }

  const holderIds = Array.from(
    new Set(
      materials.value
        .map((material) => material.holder_user_id)
        .filter((holderId): holderId is number => holderId !== null),
    ),
  );

  selectedHolderUserId.value = holderIds.length === 1 ? String(holderIds[0]) : '';
};

syncSelectedHolderFromMaterials();

const addMaterial = () => {
  const label = newMaterialLabel.value.trim();
  if (!label) return;

  const parsedUserId = selectedHolderUserId.value ? Number(selectedHolderUserId.value) : null;
  const holder = usuarios.find((usuario) => usuario.id === parsedUserId);

  materials.value.push({
    label,
    in_stock: Boolean(parsedUserId),
    holder_user_id: parsedUserId,
    holder_name: holder?.nombre ?? null,
  });

  newMaterialLabel.value = '';
  isAddMaterialModalOpen.value = false;
};

const openAddMaterialModal = () => {
  newMaterialLabel.value = '';
  isAddMaterialModalOpen.value = true;
};

const closeAddMaterialModal = () => {
  isAddMaterialModalOpen.value = false;
  newMaterialLabel.value = '';
};

const removeMaterial = (materialIndex: number) => {
  materials.value = materials.value.filter((_, index) => index !== materialIndex);
};

const openDeleteConfirm = (materialIndex: number) => {
  materialToDeleteIndex.value = materialIndex;
};

const closeDeleteConfirm = () => {
  materialToDeleteIndex.value = null;
};

const confirmDeleteMaterial = () => {
  if (materialToDeleteIndex.value === null) return;
  removeMaterial(materialToDeleteIndex.value);
  syncSelectedHolderFromMaterials();
  closeDeleteConfirm();
};

const markInStock = (materialIndex: number, checked: boolean) => {
  const target = materials.value[materialIndex];
  if (!target) return;

  target.in_stock = checked;
};

const assignHolderToAll = (userId: string) => {
  const parsedUserId = userId ? Number(userId) : null;
  const holder = usuarios.find((usuario) => usuario.id === parsedUserId);

  selectedHolderUserId.value = userId;
  materials.value = materials.value.map((material) => ({
    ...material,
    holder_user_id: parsedUserId,
    holder_name: holder?.nombre ?? null,
    in_stock: parsedUserId ? true : material.in_stock,
  }));
};

const saveTaskMaterials = () => {
  form.materials = materials.value
    .map((material) => ({
      label: material.label.trim(),
      in_stock: material.in_stock,
      holder_user_id: material.holder_user_id,
      holder_name: material.holder_name,
    }))
    .filter((material) => material.label.length > 0);

  form.patch(`/tareas/${tarea.id}/materiales`, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="`Materiales - ${tarea.nombre}`" />
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="flex items-center gap-3">
        <Link href="/materiales" class="text-sm font-medium text-primary hover:underline">
          Volver
        </Link>
        <span class="text-xs text-muted-foreground">Detalle de materiales</span>
      </div>

      <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <h1 class="text-lg font-semibold text-foreground">{{ tarea.nombre }}</h1>
          <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">{{ tarea.estado }}</span>
        </div>

        <p class="mt-2 text-xs text-muted-foreground">
          <span class="font-medium text-foreground">Líderes:</span>
          {{ tarea.lideres.length ? tarea.lideres.map((lider) => lider.nombre).join(', ') : 'Sin asignar' }}
        </p>
      </div>

      <div class="rounded-md border border-input bg-muted/20 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Material requerido</p>

        <div class="mt-2">
          <button
            type="button"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground hover:bg-muted"
            @click="openAddMaterialModal"
          >
            Agregar material
          </button>
        </div>

        <div class="mt-2 grid gap-1">
          <label class="text-xs text-muted-foreground">Usuario responsable de todo el material</label>
          <select
            :value="selectedHolderUserId"
            class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground"
            @change="assignHolderToAll(($event.target as HTMLSelectElement).value)"
          >
            <option value="">Sin asignar</option>
            <option
              v-for="usuario in usuarios"
              :key="usuario.id"
              :value="usuario.id"
            >
              {{ usuario.nombre }} - {{ usuario.rol }}
            </option>
          </select>
        </div>

        <div v-if="materials.length" class="mt-2 grid gap-2">
          <div
            v-for="(material, index) in materials"
            :key="`${tarea.id}-${index}`"
            class="rounded-md border border-input bg-background p-2"
          >
            <div class="flex items-center justify-between gap-2">
              <input
                v-model="material.label"
                type="text"
                class="h-8 w-full rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                placeholder="Nombre del material"
              >
              <label class="inline-flex items-center gap-2 text-xs text-muted-foreground">
                <input
                  :checked="material.in_stock"
                  type="checkbox"
                  class="h-4 w-4 rounded border-input"
                  @change="markInStock(index, ($event.target as HTMLInputElement).checked)"
                >
                En almacén
              </label>
              <button
                type="button"
                class="h-8 rounded-md bg-destructive/10 px-2 text-xs font-medium text-destructive"
                @click="openDeleteConfirm(index)"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
        <p v-else class="mt-2 text-sm text-muted-foreground">Sin material definido para esta tarea.</p>

        <div class="mt-3">
          <button
            type="button"
            class="h-9 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
            :disabled="form.processing"
            @click="saveTaskMaterials"
          >
            {{ form.processing ? 'Guardando...' : 'Guardar y notificar' }}
          </button>
        </div>
      </div>

      <div
        v-if="materialToDeleteIndex !== null"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
        @click.self="closeDeleteConfirm"
      >
        <div class="w-full max-w-md rounded-xl bg-background p-5 shadow-lg">
          <h3 class="text-base font-semibold text-foreground">Confirmar eliminación</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            ¿Seguro que deseas eliminar este material?
          </p>

          <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground hover:bg-muted"
              @click="closeDeleteConfirm"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="h-9 rounded-md bg-destructive px-3 text-sm font-medium text-destructive-foreground hover:bg-destructive/90"
              @click="confirmDeleteMaterial"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="isAddMaterialModalOpen"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
        @click.self="closeAddMaterialModal"
      >
        <div class="w-full max-w-md rounded-xl bg-background p-5 shadow-lg">
          <h3 class="text-base font-semibold text-foreground">Agregar material</h3>
          <div class="mt-3 grid gap-2">
            <label class="text-xs text-muted-foreground">Nombre del material</label>
            <input
              v-model="newMaterialLabel"
              type="text"
              class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
              placeholder="Escribe el material"
              @keydown.enter.prevent="addMaterial"
            >
          </div>

          <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground hover:bg-muted"
              @click="closeAddMaterialModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
              @click="addMaterial"
            >
              Agregar
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
