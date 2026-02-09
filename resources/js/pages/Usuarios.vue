<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Usuario = {
  id: number;
  nombre: string;
  email: string;
  rol: string;
  estado: 'Activo' | 'Inactivo';
};

const { usuarios } = defineProps<{ usuarios: Usuario[] }>();

const isEditOpen = ref(false);
const editingUserId = ref<number | null>(null);
const isCreateOpen = ref(false);

const form = useForm({
  name: '',
  email: '',
  role: '',
  status: 'Activo' as 'Activo' | 'Inactivo',
});

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'Empleado',
  status: 'Activo' as 'Activo' | 'Inactivo',
});

const openEdit = (usuario: Usuario) => {
  editingUserId.value = usuario.id;
  form.name = usuario.nombre;
  form.email = usuario.email;
  form.role = usuario.rol;
  form.status = usuario.estado;
  form.clearErrors();
  isEditOpen.value = true;
};

const closeEdit = () => {
  isEditOpen.value = false;
  editingUserId.value = null;
  form.reset();
  form.clearErrors();
};

const openCreate = () => {
  createForm.reset();
  createForm.clearErrors();
  isCreateOpen.value = true;
};

const closeCreate = () => {
  isCreateOpen.value = false;
  createForm.reset();
  createForm.clearErrors();
};

const submitEdit = () => {
  if (!editingUserId.value) return;
  form.put(`/usuarios/${editingUserId.value}`, {
    preserveScroll: true,
    onSuccess: () => closeEdit(),
  });
};

const submitCreate = () => {
  createForm.post('/usuarios', {
    preserveScroll: true,
    onSuccess: () => closeCreate(),
  });
};

const deleteUser = (usuario: Usuario) => {
  if (!confirm(`Eliminar al usuario ${usuario.nombre}?`)) return;
  router.delete(`/usuarios/${usuario.id}`, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Usuarios" />
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-foreground">Usuarios</h1>
          <p class="text-sm text-muted-foreground">
            Gestiona usuarios, roles y estado.
          </p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
          <input
            type="search"
            placeholder="Buscar..."
            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary sm:w-64"
          />
          <button
            type="button"
            class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
            @click="openCreate"
          >
            Nuevo usuario
          </button>
        </div>
      </div>

      <div
        v-if="isCreateOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="closeCreate"
      >
        <div class="w-full max-w-2xl rounded-xl bg-background p-6 shadow-lg">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-foreground">Nuevo usuario</h2>
              <p class="text-sm text-muted-foreground">
                Completa los datos para crear el usuario.
              </p>
            </div>
            <button
              type="button"
              class="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
              @click="closeCreate"
            >
              Cerrar
            </button>
          </div>

          <form class="mt-6 grid gap-4" @submit.prevent="submitCreate">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-name">
                  Nombre
                </label>
                <input
                  id="create-name"
                  v-model="createForm.name"
                  type="text"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
                <p v-if="createForm.errors.name" class="text-xs text-destructive">
                  {{ createForm.errors.name }}
                </p>
              </div>

              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-email">
                  Email
                </label>
                <input
                  id="create-email"
                  v-model="createForm.email"
                  type="email"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
                <p v-if="createForm.errors.email" class="text-xs text-destructive">
                  {{ createForm.errors.email }}
                </p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-password">
                  Contrasena
                </label>
                <input
                  id="create-password"
                  v-model="createForm.password"
                  type="password"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
                <p v-if="createForm.errors.password" class="text-xs text-destructive">
                  {{ createForm.errors.password }}
                </p>
              </div>

              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-password-confirmation">
                  Confirmar contrasena
                </label>
                <input
                  id="create-password-confirmation"
                  v-model="createForm.password_confirmation"
                  type="password"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-role">
                  Rol
                </label>
                <select
                  id="create-role"
                  v-model="createForm.role"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                >
                  <option value="Admin">Admin</option>
                  <option value="Supervisor">Supervisor</option>
                  <option value="RH">RH</option>
                  <option value="Lider de Cuadrilla">Lider de Cuadrilla</option>
                  <option value="Empleado">Empleado</option>
                </select>
                <p v-if="createForm.errors.role" class="text-xs text-destructive">
                  {{ createForm.errors.role }}
                </p>
              </div>

              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="create-status">
                  Estado
                </label>
                <select
                  id="create-status"
                  v-model="createForm.status"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                >
                  <option value="Activo">Activo</option>
                  <option value="Inactivo">Inactivo</option>
                </select>
                <p v-if="createForm.errors.status" class="text-xs text-destructive">
                  {{ createForm.errors.status }}
                </p>
              </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                @click="closeCreate"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
                :disabled="createForm.processing"
              >
                Crear usuario
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <table class="min-w-full divide-y divide-border">
          <thead class="bg-muted/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Nombre
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Email
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Rol
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Estado
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border bg-background">
            <tr v-for="usuario in usuarios" :key="usuario.id">
              <td class="px-4 py-3 text-sm font-medium text-foreground">
                {{ usuario.nombre }}
              </td>
              <td class="px-4 py-3 text-sm text-muted-foreground">
                {{ usuario.email }}
              </td>
              <td class="px-4 py-3 text-sm text-foreground">
                {{ usuario.rol || 'Sin rol' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                  :class="
                    usuario.estado === 'Activo'
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-amber-100 text-amber-700'
                  "
                >
                  {{ usuario.estado }}
                </span>
              </td>
              <td class="px-4 py-3 text-right text-sm">
                <button
                  type="button"
                  class="rounded-md px-2 py-1 text-sm font-medium text-primary hover:bg-primary/10"
                  @click="openEdit(usuario)"
                >
                  Editar
                </button>
                <button
                  type="button"
                  class="ml-2 rounded-md px-2 py-1 text-sm font-medium text-destructive hover:bg-destructive/10"
                  @click="deleteUser(usuario)"
                >
                  Eliminar
                </button>
              </td>
            </tr>
            <tr v-if="usuarios.length === 0">
              <td class="px-4 py-6 text-center text-sm text-muted-foreground" colspan="5">
                No hay usuarios para mostrar.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="isEditOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="closeEdit"
      >
        <div class="w-full max-w-2xl rounded-xl bg-background p-6 shadow-lg">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-foreground">Editar usuario</h2>
              <p class="text-sm text-muted-foreground">
                Actualiza los parametros del usuario.
              </p>
            </div>
            <button
              type="button"
              class="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
              @click="closeEdit"
            >
              Cerrar
            </button>
          </div>

          <form class="mt-6 grid gap-4" @submit.prevent="submitEdit">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="edit-name">
                  Nombre
                </label>
                <input
                  id="edit-name"
                  v-model="form.name"
                  type="text"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
                <p v-if="form.errors.name" class="text-xs text-destructive">
                  {{ form.errors.name }}
                </p>
              </div>

              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="edit-email">
                  Email
                </label>
                <input
                  id="edit-email"
                  v-model="form.email"
                  type="email"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                />
                <p v-if="form.errors.email" class="text-xs text-destructive">
                  {{ form.errors.email }}
                </p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="edit-role">
                  Rol
                </label>
                <select
                  id="edit-role"
                  v-model="form.role"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                >
                  <option value="Admin">Admin</option>
                  <option value="Supervisor">Supervisor</option>
                  <option value="RH">RH</option>
                  <option value="Lider de Cuadrilla">Lider de Cuadrilla</option>
                  <option value="Empleado">Empleado</option>
                </select>
                <p v-if="form.errors.role" class="text-xs text-destructive">
                  {{ form.errors.role }}
                </p>
              </div>

              <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground" for="edit-status">
                  Estado
                </label>
                <select
                  id="edit-status"
                  v-model="form.status"
                  class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                >
                  <option value="Activo">Activo</option>
                  <option value="Inactivo">Inactivo</option>
                </select>
                <p v-if="form.errors.status" class="text-xs text-destructive">
                  {{ form.errors.status }}
                </p>
              </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                @click="closeEdit"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
                :disabled="form.processing"
              >
                Guardar cambios
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>