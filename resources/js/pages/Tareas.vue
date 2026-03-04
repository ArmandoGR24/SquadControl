<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { optimizeEvidenceFile, validateEvidenceFile } from '@/lib/evidenceUpload';

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
    materiales: string | null;
    estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
    lideres: Lider[];
    evidencias: Evidencia[];
    historial: Historial[];
};

type MaterialTag = {
    id: string;
    label: string;
};

type MaterialStatusItem = {
    label: string;
    in_stock: boolean;
    holder_name: string | null;
};

const { tareas } = defineProps<{ tareas: Tarea[] }>();

const isCreateOpen = ref(false);
const isEditOpen = ref(false);
const isDeleteConfirmOpen = ref(false);
const editingTaskId = ref<number | null>(null);
const taskToDelete = ref<Tarea | null>(null);
const activeTab = ref<'list' | 'edit'>('list');

const selectedTask = computed(() =>
    tareas.find((tarea) => tarea.id === editingTaskId.value) ?? null,
);

const createForm = useForm({
    name: '',
    instructions: '',
    materials: '',
    status: 'Pendiente' as Tarea['estado'],
    status_comment: '',
});

const editForm = useForm({
    name: '',
    instructions: '',
    materials: '',
    status: 'Pendiente' as Tarea['estado'],
    status_comment: '',
});

const deleteForm = useForm({});

const reviewForm = useForm({
    status: 'Completada' as Tarea['estado'],
    status_comment: '',
    evidence: null as File | null,
});

const evidenceForm = useForm({
    evidences: [] as File[],
    comment: '',
});

const evidenceInputRef = ref<HTMLInputElement | null>(null);
const reviewEvidenceInputRef = ref<HTMLInputElement | null>(null);
const createMaterialInput = ref('');
const editMaterialInput = ref('');
const createMaterialTags = ref<MaterialTag[]>([]);
const editMaterialTags = ref<MaterialTag[]>([]);
const isMaterialModalOpen = ref(false);
const materialModalTarget = ref<'create' | 'edit'>('create');

const buildMaterialTag = (label: string): MaterialTag => ({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    label,
});

const parseMaterials = (value: string | null): MaterialTag[] => {
    if (!value) return [];

    const normalized = value.trim();
    if (!normalized) return [];

    try {
        const parsed = JSON.parse(normalized) as Array<{ label?: string; name?: string }>;
        if (Array.isArray(parsed)) {
            return parsed
                .map((item) => (item?.label ?? item?.name ?? '').trim())
                .filter((item) => item.length > 0)
                .map((label) => buildMaterialTag(label));
        }
    } catch {
        // fallback to plain text format
    }

    return normalized
        .split(/\r?\n|,/) 
        .map((item) => item.trim())
        .filter((item) => item.length > 0)
        .map((label) => buildMaterialTag(label));
};

const serializeMaterials = (tags: MaterialTag[]): string => {
    if (tags.length === 0) return '';

    return JSON.stringify(
        tags.map((tag) => ({ label: tag.label })),
    );
};

const addMaterialTag = (target: 'create' | 'edit') => {
    const inputRef = target === 'create' ? createMaterialInput : editMaterialInput;
    const tagsRef = target === 'create' ? createMaterialTags : editMaterialTags;
    const formRef = target === 'create' ? createForm : editForm;

    const value = inputRef.value.trim();
    if (!value) return;

    const exists = tagsRef.value.some((tag) => tag.label.toLowerCase() === value.toLowerCase());
    if (!exists) {
        tagsRef.value = [...tagsRef.value, buildMaterialTag(value)];
        formRef.materials = serializeMaterials(tagsRef.value);
    }

    inputRef.value = '';
};

const removeMaterialTag = (target: 'create' | 'edit', tagId: string) => {
    const tagsRef = target === 'create' ? createMaterialTags : editMaterialTags;
    const formRef = target === 'create' ? createForm : editForm;

    tagsRef.value = tagsRef.value.filter((tag) => tag.id !== tagId);
    formRef.materials = serializeMaterials(tagsRef.value);
};

const currentMaterialInput = computed({
    get: () => (materialModalTarget.value === 'create' ? createMaterialInput.value : editMaterialInput.value),
    set: (value: string) => {
        if (materialModalTarget.value === 'create') {
            createMaterialInput.value = value;
            return;
        }

        editMaterialInput.value = value;
    },
});

const openMaterialModal = (target: 'create' | 'edit') => {
    materialModalTarget.value = target;

    if (target === 'create') {
        createMaterialInput.value = '';
    } else {
        editMaterialInput.value = '';
    }

    isMaterialModalOpen.value = true;
};

const closeMaterialModal = () => {
    isMaterialModalOpen.value = false;

    if (materialModalTarget.value === 'create') {
        createMaterialInput.value = '';
    } else {
        editMaterialInput.value = '';
    }
};

const confirmAddMaterial = () => {
    const target = materialModalTarget.value;
    const value = target === 'create' ? createMaterialInput.value : editMaterialInput.value;

    if (!value.trim()) return;

    addMaterialTag(target);

    if (target === 'edit') {
        submitEdit(false);
    }

    closeMaterialModal();
};

const formatMaterialsPreview = (value: string | null): string =>
    parseMaterials(value)
        .map((tag) => tag.label)
        .join(', ');

const parseMaterialStatus = (value: string | null): MaterialStatusItem[] => {
    if (!value) return [];

    const normalized = value.trim();
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
        // fallback to plain text format
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

const formatMaterialStatusPreview = (value: string | null): string =>
    parseMaterialStatus(value)
        .map((item) => `${item.label} (${item.in_stock ? (item.holder_name ? `Lo tiene: ${item.holder_name}` : 'En almacén') : 'Pendiente'})`)
        .join(', ');

const triggerEvidencePicker = () => {
    evidenceInputRef.value?.click();
};

const triggerReviewEvidencePicker = () => {
    reviewEvidenceInputRef.value?.click();
};

const setEvidenceFiles = async (files: FileList | null) => {
    evidenceForm.clearErrors('evidence');
    evidenceForm.clearErrors('evidences');

    if (!files || files.length === 0) {
        evidenceForm.evidences = [];
        return;
    }

    const selectedFiles = Array.from(files);
    const optimizedFiles: File[] = [];

    for (const file of selectedFiles) {
        const validationError = validateEvidenceFile(file);
        if (validationError) {
            evidenceForm.evidences = [];
            evidenceForm.setError('evidences', `${validationError} Archivo: ${file.name}`);
            return;
        }

        const optimizedFile = await optimizeEvidenceFile(file);
        const optimizedValidationError = validateEvidenceFile(optimizedFile);
        if (optimizedValidationError) {
            evidenceForm.evidences = [];
            evidenceForm.setError('evidences', `${optimizedValidationError} Archivo: ${optimizedFile.name}`);
            return;
        }

        optimizedFiles.push(optimizedFile);
    }

    evidenceForm.evidences = optimizedFiles;
};

const setReviewEvidenceFile = async (file: File | null) => {
    reviewForm.clearErrors('evidence');
    if (!file) {
        reviewForm.evidence = null;
        return;
    }

    const validationError = validateEvidenceFile(file);
    if (validationError) {
        reviewForm.evidence = null;
        reviewForm.setError('evidence', validationError);
        return;
    }

    const optimizedFile = await optimizeEvidenceFile(file);
    const optimizedValidationError = validateEvidenceFile(optimizedFile);
    if (optimizedValidationError) {
        reviewForm.evidence = null;
        reviewForm.setError('evidence', optimizedValidationError);
        return;
    }

    reviewForm.evidence = optimizedFile;
};

const openCreate = () => {
    createForm.reset();
    createMaterialInput.value = '';
    createMaterialTags.value = [];
    createForm.clearErrors();
    isCreateOpen.value = true;
};

const closeCreate = () => {
    isCreateOpen.value = false;
    createForm.reset();
    createMaterialInput.value = '';
    createMaterialTags.value = [];
    createForm.clearErrors();
};

const openEdit = (tarea: Tarea) => {
    editingTaskId.value = tarea.id;
    editForm.name = tarea.nombre;
    editForm.instructions = tarea.instrucciones;
    editMaterialTags.value = parseMaterials(tarea.materiales);
    editMaterialInput.value = '';
    editForm.materials = serializeMaterials(editMaterialTags.value);
    editForm.status = tarea.estado;
    editForm.status_comment = '';
    editForm.clearErrors();
    reviewForm.reset();
    reviewForm.clearErrors();
    evidenceForm.reset();
    evidenceForm.clearErrors();
    isEditOpen.value = true;
    activeTab.value = 'edit';
};

const closeEdit = () => {
    isEditOpen.value = false;
    editingTaskId.value = null;
    editForm.reset();
    editMaterialInput.value = '';
    editMaterialTags.value = [];
    editForm.clearErrors();
    reviewForm.reset();
    reviewForm.clearErrors();
    evidenceForm.reset();
    evidenceForm.clearErrors();
    activeTab.value = 'list';
};

const submitCreate = () => {
    createForm.materials = serializeMaterials(createMaterialTags.value);
    createForm.post('/tareas', {
        preserveScroll: true,
        onSuccess: () => closeCreate(),
    });
};

const submitEdit = (closeOnSuccess = true) => {
    if (!editingTaskId.value) return;
    editForm.materials = serializeMaterials(editMaterialTags.value);
    editForm.put(`/tareas/${editingTaskId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (closeOnSuccess) {
                closeEdit();
            }
        },
    });
};

const submitEvidence = () => {
    if (!editingTaskId.value || evidenceForm.evidences.length === 0) return;
    evidenceForm.post(`/tareas/${editingTaskId.value}/evidencias`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            evidenceForm.reset();
            evidenceForm.clearErrors();
            if (evidenceInputRef.value) {
                evidenceInputRef.value.value = '';
            }
        },
    });
};

const submitReview = (status: 'Completada' | 'En progreso') => {
    if (!editingTaskId.value) return;
    reviewForm.status = status;
    reviewForm.patch(`/tareas/${editingTaskId.value}/revision`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            reviewForm.reset('status_comment', 'evidence');
            reviewForm.clearErrors();
            if (reviewEvidenceInputRef.value) {
                reviewEvidenceInputRef.value.value = '';
            }
        },
    });
};

const openDeleteConfirm = (tarea: Tarea) => {
    taskToDelete.value = tarea;
    isDeleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    if (deleteForm.processing) return;
    isDeleteConfirmOpen.value = false;
    taskToDelete.value = null;
};

const confirmDeleteTask = () => {
    if (!taskToDelete.value) return;

    const deletingTaskId = taskToDelete.value.id;

    deleteForm.delete(`/tareas/${deletingTaskId}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingTaskId.value === deletingTaskId) {
                closeEdit();
            }
            closeDeleteConfirm();
        },
    });
};

const isVideoEvidence = (url: string) => /\.(mp4|mov|m4v|webm|3gp|3gpp|3g2)(\?|#|$)/i.test(url);

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
                } catch {
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
    } catch {
        // Ignore poster failures to avoid blocking the UI.
    }
};

const openMedia = (evidencia: Evidencia) => {
    selectedMedia.value = evidencia;
};

const closeMedia = () => {
    selectedMedia.value = null;
};

const canReview = computed(() => selectedTask.value?.estado === 'En revisión');

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
                                <p v-if="tarea.materiales" class="mt-2 text-xs text-muted-foreground line-clamp-2">
                                    <span class="font-medium text-foreground">Materiales:</span>
                                    {{ formatMaterialStatusPreview(tarea.materiales) }}
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
                                @click="openDeleteConfirm(tarea)"
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
                                        <span
                                            v-if="tarea.materiales"
                                            class="text-xs text-muted-foreground line-clamp-1"
                                        >
                                            Materiales: {{ formatMaterialStatusPreview(tarea.materiales) }}
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
                                        @click="openDeleteConfirm(tarea)"
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

                        <div class="grid gap-2">
                            <label class="text-sm font-medium text-foreground" for="create-materials">
                                Material necesario
                            </label>
                            <div class="grid gap-2 rounded-md border border-input bg-background p-2">
                                <div v-if="createMaterialTags.length" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="tag in createMaterialTags"
                                        :key="tag.id"
                                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                    >
                                        {{ tag.label }}
                                        <button
                                            type="button"
                                            class="text-primary/80 hover:text-primary"
                                            @click="removeMaterialTag('create', tag.id)"
                                        >
                                            ×
                                        </button>
                                    </span>
                                </div>
                                <button
                                    id="create-materials"
                                    type="button"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground shadow-sm hover:bg-muted"
                                    @click="openMaterialModal('create')"
                                >
                                    Agregar material
                                </button>
                            </div>
                            <p v-if="createForm.errors.materials" class="text-xs text-destructive">
                                {{ createForm.errors.materials }}
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
                                    <option value="En revisión">En revisión</option>
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

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-foreground" for="edit-materials">
                            Material necesario
                        </label>
                        <div class="grid gap-2 rounded-md border border-input bg-background p-2">
                            <div v-if="editMaterialTags.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="tag in editMaterialTags"
                                    :key="tag.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                >
                                    {{ tag.label }}
                                    <button
                                        type="button"
                                        class="text-primary/80 hover:text-primary"
                                        @click="removeMaterialTag('edit', tag.id)"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                            <button
                                id="edit-materials"
                                type="button"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground shadow-sm hover:bg-muted"
                                @click="openMaterialModal('edit')"
                            >
                                Agregar material
                            </button>
                        </div>
                        <p v-if="editForm.errors.materials" class="text-xs text-destructive">
                            {{ editForm.errors.materials }}
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
                                <option value="En revisión">En revisión</option>
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
                                multiple
                                accept="image/*,video/mp4,video/quicktime,video/x-m4v,video/webm,video/3gpp,video/3gpp2"
                                class="hidden"
                                @change="(event) => setEvidenceFiles((event.target as HTMLInputElement).files ?? null)"
                            />
                            <button
                                type="button"
                                class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                                @click="triggerEvidencePicker"
                            >
                                Agregar archivos
                            </button>
                            <p v-if="evidenceForm.evidences.length > 0" class="text-xs text-muted-foreground">
                                {{ evidenceForm.evidences.length }} archivo(s) seleccionado(s)
                            </p>
                            <textarea
                                v-model="evidenceForm.comment"
                                rows="2"
                                placeholder="Comentario de evidencia (opcional)"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            ></textarea>
                            <p v-if="evidenceForm.errors.evidences || evidenceForm.errors.evidence" class="text-xs text-destructive">
                                {{ evidenceForm.errors.evidences || evidenceForm.errors.evidence }}
                            </p>
                            <div
                                v-if="evidenceForm.processing"
                                class="rounded-md border border-input bg-muted/30 p-3"
                            >
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Subiendo evidencias multimedia...</span>
                                    <span>{{ evidenceForm.progress?.percentage ?? 0 }}%</span>
                                </div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded bg-muted">
                                    <div
                                        class="h-full bg-primary transition-all"
                                        :style="{ width: `${evidenceForm.progress?.percentage ?? 0}%` }"
                                    ></div>
                                </div>
                            </div>
                            <div
                                v-if="evidenceForm.recentlySuccessful"
                                class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700"
                            >
                                Carga completa: las evidencias se subieron correctamente.
                            </div>
                            <button
                                type="submit"
                                class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
                                :disabled="evidenceForm.processing || evidenceForm.evidences.length === 0"
                            >
                                Guardar evidencias
                            </button>
                        </form>

                        <div class="mt-4">
                            <div
                                v-if="selectedTask.evidencias.length > 0"
                                class="grid grid-cols-1 gap-2 sm:flex sm:snap-x sm:snap-mandatory sm:gap-3 sm:overflow-x-auto sm:pb-2"
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
                                        class="mt-2 flex h-20 w-full items-center justify-center rounded-md bg-black/80 text-[11px] font-medium text-white sm:h-28"
                                    >
                                        Video
                                    </div>
                                    <img
                                        v-else
                                        :src="evidencia.url"
                                        alt="Evidencia"
                                        class="mt-2 h-20 w-full rounded-md object-cover sm:h-28"
                                    />
                                    <p class="mt-2 line-clamp-2 break-words text-[10px] text-muted-foreground sm:text-xs">
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

                <div class="mt-6 rounded-xl border border-sidebar-border/70 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Revisión de supervisor</h3>
                            <p class="text-xs text-muted-foreground">
                                Aprueba como completada o solicita ajustes cuando la tarea esté en revisión.
                            </p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">
                            Estado actual: {{ selectedTask.estado }}
                        </span>
                    </div>

                    <form class="mt-4 grid gap-3" @submit.prevent>
                        <div class="grid gap-2">
                            <label class="text-xs font-medium text-muted-foreground" for="review-comment">
                                Comentario
                            </label>
                            <textarea
                                id="review-comment"
                                v-model="reviewForm.status_comment"
                                rows="3"
                                placeholder="Describe la aprobación o los ajustes solicitados."
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            ></textarea>
                            <p v-if="reviewForm.errors.status_comment" class="text-xs text-destructive">
                                {{ reviewForm.errors.status_comment }}
                            </p>
                            <p v-if="reviewForm.errors.status" class="text-xs text-destructive">
                                {{ reviewForm.errors.status }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                El motivo es obligatorio si rechazas la revisión.
                            </p>
                            <p v-if="!canReview" class="text-xs text-muted-foreground">
                                La tarea debe estar en revisión para aprobarla o enviar ajustes.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-xs font-medium text-muted-foreground" for="review-evidence">
                                Archivo de guia (opcional)
                            </label>
                            <input
                                ref="reviewEvidenceInputRef"
                                id="review-evidence"
                                type="file"
                                accept="image/*,video/mp4,video/quicktime,video/x-m4v,video/webm,video/3gpp,video/3gpp2"
                                class="hidden"
                                @change="(event) => setReviewEvidenceFile((event.target as HTMLInputElement).files?.[0] ?? null)"
                            />
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                                    @click="triggerReviewEvidencePicker"
                                >
                                    Adjuntar guia
                                </button>
                                <span class="text-xs text-muted-foreground">
                                    {{ reviewForm.evidence?.name || 'Sin archivo seleccionado.' }}
                                </span>
                            </div>
                            <p v-if="reviewForm.errors.evidence" class="text-xs text-destructive">
                                {{ reviewForm.errors.evidence }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted disabled:opacity-60"
                                :disabled="reviewForm.processing || !canReview"
                                @click="submitReview('En progreso')"
                            >
                                Rechazada
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-md bg-emerald-600 px-4 text-sm font-medium text-white shadow hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="reviewForm.processing || !canReview"
                                @click="submitReview('Completada')"
                            >
                                Aceptada
                            </button>
                        </div>
                    </form>
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
                        playsinline
                        preload="metadata"
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

            <div
                v-if="isDeleteConfirmOpen && taskToDelete"
                class="fixed inset-0 z-[75] flex items-center justify-center bg-black/50 p-4"
                @click.self="closeDeleteConfirm"
            >
                <div class="w-full max-w-md rounded-xl bg-background p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-foreground">Confirmar eliminación</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Vas a eliminar la tarea
                        <span class="font-medium text-foreground">"{{ taskToDelete.nombre }}"</span>.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        También se eliminarán sus evidencias y archivos multimedia para liberar espacio.
                    </p>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                            :disabled="deleteForm.processing"
                            @click="closeDeleteConfirm"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="h-9 rounded-md bg-destructive px-4 text-sm font-medium text-destructive-foreground hover:bg-destructive/90 disabled:opacity-60"
                            :disabled="deleteForm.processing"
                            @click="confirmDeleteTask"
                        >
                            {{ deleteForm.processing ? 'Eliminando...' : 'Eliminar tarea' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="isMaterialModalOpen"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                @click.self="closeMaterialModal"
            >
                <div class="w-full max-w-md rounded-xl bg-background p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-foreground">Agregar material</h3>
                    <div class="mt-3 grid gap-2">
                        <label class="text-sm font-medium text-foreground" for="material-modal-input">
                            Nombre del material
                        </label>
                        <input
                            id="material-modal-input"
                            v-model="currentMaterialInput"
                            type="text"
                            placeholder="Escribe el material"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
                            @keydown.enter.prevent="confirmAddMaterial"
                        >
                    </div>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="h-9 rounded-md border border-input bg-background px-4 text-sm font-medium text-foreground hover:bg-muted"
                            @click="closeMaterialModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                            @click="confirmAddMaterial"
                        >
                            Agregar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>