<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Lider = {
    id: number;
    nombre: string;
};

type Evidencia = {
    id: number;
    url: string;
    comentario: string | null;
    fecha: string | null;
    subido_por: string | null;
};

type Historial = {
    id: number;
    estado: string;
    comentario: string | null;
    fecha: string | null;
    reportado_por: string | null;
};

type Tarea = {
    id: number;
    nombre: string;
    instrucciones: string;
    estado: 'Pendiente' | 'En progreso' | 'Completada';
    lideres: Lider[];
    evidencias: Evidencia[];
    historial: Historial[];
};

const { tareas, lideres } = defineProps<{ tareas: Tarea[]; lideres: Lider[] }>();

const isCreateOpen = ref(false);
const isEditOpen = ref(false);
const editingTaskId = ref<number | null>(null);
const activeTab = ref<'list' | 'edit'>('list');

const selectedTask = computed(() =>
    tareas.find((tarea) => tarea.id === editingTaskId.value) ?? null,
);

const createForm = useForm({
    name: '',
    instructions: '',
    status: 'Pendiente' as Tarea['estado'],
    leader_ids: [] as number[],
    status_comment: '',
});

const editForm = useForm({
    name: '',
    instructions: '',
    status: 'Pendiente' as Tarea['estado'],
    leader_ids: [] as number[],
    status_comment: '',
});

const evidenceForm = useForm({
    evidence: null as File | null,
    comment: '',
});

const evidenceInputRef = ref<HTMLInputElement | null>(null);

const triggerEvidencePicker = () => {
    evidenceInputRef.value?.click();
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

const openEdit = (tarea: Tarea) => {
    editingTaskId.value = tarea.id;
    editForm.name = tarea.nombre;
    editForm.instructions = tarea.instrucciones;
    editForm.status = tarea.estado;
    editForm.leader_ids = tarea.lideres.map((lider) => lider.id);
    editForm.status_comment = '';
    editForm.clearErrors();
    evidenceForm.reset();
    evidenceForm.clearErrors();
    isEditOpen.value = true;
    activeTab.value = 'edit';
};

const closeEdit = () => {
    isEditOpen.value = false;
    editingTaskId.value = null;
    editForm.reset();
    editForm.clearErrors();
    evidenceForm.reset();
    evidenceForm.clearErrors();
    activeTab.value = 'list';
};

const submitCreate = () => {
    createForm.post('/tareas', {
        preserveScroll: true,
        onSuccess: () => closeCreate(),
    });
};

const submitEdit = () => {
    if (!editingTaskId.value) return;
    editForm.put(`/tareas/${editingTaskId.value}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const submitEvidence = () => {
    if (!editingTaskId.value || !evidenceForm.evidence) return;
    evidenceForm.post(`/tareas/${editingTaskId.value}/evidencias`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            evidenceForm.reset();
            evidenceForm.clearErrors();
        },
    });
};

const deleteTask = (tarea: Tarea) => {
    if (!confirm(`Eliminar la tarea ${tarea.nombre}?`)) return;
    router.delete(`/tareas/${tarea.id}`, {
        preserveScroll: true,
    });
};

const isVideoEvidence = (url: string) => /\.(mp4|mov|m4v|webm)(\?|#|$)/i.test(url);

const selectedMedia = ref<Evidencia | null>(null);

const videoPosters = ref<Record<number, string>>({});

const captureVideoPoster = (url: string) =>
    new Promise<string>((resolve, reject) => {
        const video = document.createElement('video');
        const canvas = document.createElement('canvas');

        const cleanup = () => {
            video.removeAttribute('src');
            video.load();
        };

        const fail = () => {
            cleanup();
            reject(new Error('poster-failed'));
        };

        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;
        video.crossOrigin = 'anonymous';
        video.src = url;

        video.addEventListener('error', fail, { once: true });
        video.addEventListener(
            'loadedmetadata',
            () => {
                const targetTime = Math.min(0.1, Math.max(0, video.duration || 0));
                video.currentTime = targetTime;
            },
            { once: true },
        );
        video.addEventListener(
            'seeked',
            () => {
                try {
                    const width = video.videoWidth || 320;
                    const height = video.videoHeight || 180;
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        fail();
                        return;
                    }
                    ctx.drawImage(video, 0, 0, width, height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.75);
                    cleanup();
                    resolve(dataUrl);
                } catch (error) {
                    fail();
                }
            },
            { once: true },
        );
    });

const ensureVideoPoster = async (evidencia: Evidencia) => {
    if (!isVideoEvidence(evidencia.url)) return;
    if (videoPosters.value[evidencia.id]) return;
    try {
        const poster = await captureVideoPoster(evidencia.url);
        videoPosters.value = { ...videoPosters.value, [evidencia.id]: poster };
    } catch (error) {
        // Ignore poster failures to avoid blocking the UI.
    }
};

const openMedia = (evidencia: Evidencia) => {
    selectedMedia.value = evidencia;
};

const closeMedia = () => {
    selectedMedia.value = null;
};

watch(
    () => selectedTask.value?.evidencias ?? [],
    (evidencias) => {
        evidencias.forEach((evidencia) => {
            ensureVideoPoster(evidencia);
        });
    },
    { immediate: true },
);
</script>

<template>
    <AppLayout>
        <Head title="Tareas"/>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Tareas</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestiona tareas, estados, asignaciones y evidencias.
                    </p>
                </div>
                <button
                    type="button"
                    class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                    @click="openCreate"
                >
                    Nueva tarea
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-muted/40 p-1 text-sm">
                <button
                    type="button"
                    class="h-8 rounded-md px-3 font-medium transition"
                    :class="
                        activeTab === 'list'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'list'"
                >
                    Listado
                </button>
                <button
                    type="button"
                    class="h-8 rounded-md px-3 font-medium transition disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        activeTab === 'edit'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :disabled="!isEditOpen"
                    @click="activeTab = 'edit'"
                >
                    Editar
                </button>
            </div>

            <div v-if="activeTab === 'list'" class="grid gap-4">
                <div class="grid gap-4 sm:hidden">
                    <div
                        v-for="tarea in tareas"
                        :key="tarea.id"
                        class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-foreground">
                                    {{ tarea.nombre }}
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground line-clamp-3">
                                    {{ tarea.instrucciones }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    tarea.estado === 'Completada'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : tarea.estado === 'En progreso'
                                          ? 'bg-amber-100 text-amber-700'
                                          : 'bg-slate-100 text-slate-700',
                                ]"
                            >
                                {{ tarea.estado }}
                            </span>
                        </div>

                        <div class="mt-3 text-sm text-muted-foreground">
                            <span class="font-medium text-foreground">Lideres:</span>
                            <span v-if="tarea.lideres.length" class="ml-1">
                                {{ tarea.lideres.map((lider) => lider.nombre).join(', ') }}
                            </span>
                            <span v-else class="ml-1">Sin asignar</span>
                        </div>

                        <div class="mt-4 flex flex-col gap-2">
                            <button
                                type="button"
                                class="h-9 w-full rounded-md bg-primary/10 text-sm font-medium text-primary"
                                @click="openEdit(tarea)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="h-9 w-full rounded-md bg-destructive/10 text-sm font-medium text-destructive"
                                @click="deleteTask(tarea)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="tareas.length === 0"
                        class="rounded-xl border border-dashed border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground"
                    >
                        No hay tareas para mostrar.
                    </div>
                </div>

                <div
                    class="hidden overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border sm:block"
                >
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Nombre
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Estado
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Lideres
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-background">
                            <tr v-for="tarea in tareas" :key="tarea.id">
                                <td class="px-4 py-3 text-sm font-medium text-foreground">
                                    <div class="flex flex-col">
                                        <span>{{ tarea.nombre }}</span>
                                        <span class="text-xs text-muted-foreground line-clamp-2">
                                            {{ tarea.instrucciones }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-foreground">
                                    {{ tarea.estado }}
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    <span v-if="tarea.lideres.length">
                                        {{ tarea.lideres.map((lider) => lider.nombre).join(', ') }}
                                    </span>
                                    <span v-else>Sin asignar</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <button
                                        type="button"
                                        class="rounded-md px-2 py-1 text-sm font-medium text-primary hover:bg-primary/10"
                                        @click="openEdit(tarea)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="ml-2 rounded-md px-2 py-1 text-sm font-medium text-destructive hover:bg-destructive/10"
                                        @click="deleteTask(tarea)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="tareas.length === 0">
                                <td
                                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                                    colspan="4"
                                >
                                    No hay tareas para mostrar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="isCreateOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeCreate"
            >
                <div class="w-full max-w-3xl rounded-xl bg-background p-6 shadow-lg">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Nueva tarea</h2>
                            <p class="text-sm text-muted-foreground">
                                Define instrucciones, estado y lideres asignados.
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
                            <label
                                class="text-sm font-medium text-foreground"
                                for="create-instructions"
                            >
                                Instrucciones
                            </label>
                            <textarea
                                id="create-instructions"
                                v-model="createForm.instructions"
                                rows="4"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            ></textarea>
                            <p
                                v-if="createForm.errors.instructions"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.instructions }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="create-status"
                                >
                                    Estado
                                </label>
                                <select
                                    id="create-status"
                                    v-model="createForm.status"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                                >
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="En progreso">En progreso</option>
                                    <option value="Completada">Completada</option>
                                </select>
                                <p
                                    v-if="createForm.errors.status"
                                    class="text-xs text-destructive"
                                >
                                    {{ createForm.errors.status }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="create-status-comment"
                                >
                                    Comentario de estado
                                </label>
                                <input
                                    id="create-status-comment"
                                    v-model="createForm.status_comment"
                                    type="text"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium text-foreground">
                                Asignar lideres
                            </label>
                            <div
                                class="grid gap-2 rounded-md border border-input bg-background p-3 text-sm text-foreground"
                            >
                                <label
                                    v-for="lider in lideres"
                                    :key="lider.id"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-input text-primary"
                                        :value="lider.id"
                                        v-model="createForm.leader_ids"
                                    />
                                    <span>{{ lider.nombre }}</span>
                                </label>
                                <span v-if="lideres.length === 0" class="text-xs text-muted-foreground">
                                    No hay lideres disponibles.
                                </span>
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
                                Crear tarea
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                v-if="activeTab === 'edit' && selectedTask"
                class="rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Editar tarea</h2>
                        <p class="text-sm text-muted-foreground">
                            Actualiza parametros y agrega evidencias.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
                        @click="closeEdit"
                    >
                        Volver al listado
                    </button>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-foreground" for="edit-name">
                            Nombre
                        </label>
                        <input
                            id="edit-name"
                            v-model="editForm.name"
                            type="text"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                        />
                        <p v-if="editForm.errors.name" class="text-xs text-destructive">
                            {{ editForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-foreground" for="edit-instructions">
                            Instrucciones
                        </label>
                        <textarea
                            id="edit-instructions"
                            v-model="editForm.instructions"
                            rows="4"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                        ></textarea>
                        <p v-if="editForm.errors.instructions" class="text-xs text-destructive">
                            {{ editForm.errors.instructions }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium text-foreground" for="edit-status">
                                Estado
                            </label>
                            <select
                                id="edit-status"
                                v-model="editForm.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            >
                                <option value="Pendiente">Pendiente</option>
                                <option value="En progreso">En progreso</option>
                                <option value="Completada">Completada</option>
                            </select>
                            <p v-if="editForm.errors.status" class="text-xs text-destructive">
                                {{ editForm.errors.status }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <label class="text-sm font-medium text-foreground" for="edit-status-comment">
                                Comentario de estado
                            </label>
                            <input
                                id="edit-status-comment"
                                v-model="editForm.status_comment"
                                type="text"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-foreground">Asignar lideres</label>
                        <div
                            class="grid gap-2 rounded-md border border-input bg-background p-3 text-sm text-foreground"
                        >
                            <label
                                v-for="lider in lideres"
                                :key="lider.id"
                                class="flex items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary"
                                    :value="lider.id"
                                    v-model="editForm.leader_ids"
                                />
                                <span>{{ lider.nombre }}</span>
                            </label>
                            <span v-if="lideres.length === 0" class="text-xs text-muted-foreground">
                                No hay lideres disponibles.
                            </span>
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
                            :disabled="editForm.processing"
                        >
                            Guardar cambios
                        </button>
                    </div>
                </form>

                <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-sidebar-border/70 p-3 sm:p-4">
                        <h3 class="text-sm font-semibold text-foreground">Evidencias</h3>
                        <form class="mt-4 grid gap-3" @submit.prevent="submitEvidence">
                            <input
                                ref="evidenceInputRef"
                                type="file"
                                accept="image/*,video/mp4,video/quicktime"
                                class="hidden"
                                @change="
                                    (event) =>
                                        (evidenceForm.evidence =
                                            (event.target as HTMLInputElement).files?.[0] ?? null)
                                "
                            />
                            <button
                                type="button"
                                class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                                @click="triggerEvidencePicker"
                            >
                                Agregar archivo
                            </button>
                            <textarea
                                v-model="evidenceForm.comment"
                                rows="2"
                                placeholder="Comentario de evidencia (opcional)"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            ></textarea>
                            <p v-if="evidenceForm.errors.evidence" class="text-xs text-destructive">
                                {{ evidenceForm.errors.evidence }}
                            </p>
                            <button
                                type="submit"
                                class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
                                :disabled="evidenceForm.processing"
                            >
                                Guardar evidencia
                            </button>
                        </form>

                        <div class="mt-4">
                            <div
                                v-if="selectedTask.evidencias.length > 0"
                                class="grid grid-cols-2 gap-2 sm:flex sm:snap-x sm:snap-mandatory sm:gap-3 sm:overflow-x-auto sm:pb-2"
                            >
                                <button
                                    v-for="evidencia in selectedTask.evidencias"
                                    :key="evidencia.id"
                                    type="button"
                                    class="w-full rounded-md border border-input p-2 text-left sm:min-w-[180px] sm:max-w-[220px] sm:flex-shrink-0 sm:snap-start sm:p-3"
                                    @click="openMedia(evidencia)"
                                >
                                    <div
                                        class="flex items-center justify-between text-[10px] text-muted-foreground sm:text-[11px]"
                                    >
                                        <span class="truncate">{{ evidencia.subido_por || 'Sin autor' }}</span>
                                        <span class="ml-2 shrink-0">{{ evidencia.fecha }}</span>
                                    </div>
                                    <img
                                        v-if="isVideoEvidence(evidencia.url) && videoPosters[evidencia.id]"
                                        :src="videoPosters[evidencia.id]"
                                        alt="Evidencia en video"
                                        class="mt-2 h-20 w-full rounded-md object-cover sm:h-28"
                                    />
                                    <div
                                        v-else-if="isVideoEvidence(evidencia.url)"
                                        class="mt-2 h-20 w-full rounded-md bg-muted/40 sm:h-28"
                                    ></div>
                                    <img
                                        v-else
                                        :src="evidencia.url"
                                        alt="Evidencia"
                                        class="mt-2 h-20 w-full rounded-md object-cover sm:h-28"
                                    />
                                    <p class="mt-2 line-clamp-1 text-[10px] text-muted-foreground sm:text-xs">
                                        {{ evidencia.comentario || 'Sin comentario.' }}
                                    </p>
                                </button>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Sin evidencias registradas.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <h3 class="text-sm font-semibold text-foreground">Historial de estado</h3>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="registro in selectedTask.historial"
                                :key="registro.id"
                                class="rounded-md border border-input p-3"
                            >
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{{ registro.reportado_por || 'Sistema' }}</span>
                                    <span>{{ registro.fecha }}</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-foreground">
                                    {{ registro.estado }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ registro.comentario || 'Sin comentario.' }}
                                </p>
                            </div>
                            <p v-if="selectedTask.historial.length === 0" class="text-sm text-muted-foreground">
                                Sin cambios de estado registrados.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="selectedMedia"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-4"
                @click.self="closeMedia"
            >
                <div class="relative w-full max-w-4xl">
                    <button
                        type="button"
                        class="absolute right-0 top-0 rounded-md bg-black/60 px-3 py-1 text-sm text-white"
                        @click="closeMedia"
                    >
                        Cerrar
                    </button>
                    <video
                        v-if="selectedMedia && isVideoEvidence(selectedMedia.url)"
                        :src="selectedMedia.url"
                        class="max-h-[70dvh] w-full rounded-lg bg-black"
                        controls
                        autoplay
                    ></video>
                    <img
                        v-else
                        :src="selectedMedia.url"
                        alt="Vista previa"
                        class="max-h-[70dvh] w-full rounded-lg object-contain"
                    />
                    <div class="mt-3 rounded-md bg-background/95 p-3 text-sm text-foreground">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{{ selectedMedia.subido_por || 'Sin autor' }}</span>
                            <span>{{ selectedMedia.fecha }}</span>
                        </div>
                        <p class="mt-2">
                            {{ selectedMedia.comentario || 'Sin comentario.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>