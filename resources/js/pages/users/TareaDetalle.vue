<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LeaderLayout from '@/layouts/LeaderLayout.vue';
import { optimizeEvidenceFile, validateEvidenceFile } from '@/lib/evidenceUpload';

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
  estado: 'Pendiente' | 'En progreso' | 'En revisión' | 'Completada';
  evidencias: Evidencia[];
  historial: Historial[];
};

const { tarea } = defineProps<{ tarea: Tarea }>();

const statusForm = useForm({
  status: tarea.estado as Tarea['estado'],
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

const setEvidenceFile = async (file: File | null) => {
  evidenceForm.clearErrors('evidence');
  if (!file) {
    evidenceForm.evidence = null;
    return;
  }

  const validationError = validateEvidenceFile(file);
  if (validationError) {
    evidenceForm.evidence = null;
    evidenceForm.setError('evidence', validationError);
    return;
  }

  const optimizedFile = await optimizeEvidenceFile(file);
  const optimizedValidationError = validateEvidenceFile(optimizedFile);
  if (optimizedValidationError) {
    evidenceForm.evidence = null;
    evidenceForm.setError('evidence', optimizedValidationError);
    return;
  }

  evidenceForm.evidence = optimizedFile;
};

const submitStatus = () => {
  statusForm.patch(`/tareas/${tarea.id}/estado`, {
    preserveScroll: true,
  });
};

const submitReviewRequest = () => {
  statusForm.status = 'En revisión';
  submitStatus();
};

const submitEvidence = () => {
  if (!evidenceForm.evidence) return;
  evidenceForm.post(`/tareas/${tarea.id}/evidencias`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      evidenceForm.reset();
      evidenceForm.clearErrors();
    },
  });
};

const isVideoEvidence = (url: string) => /\.(mp4)(\?|#|$)/i.test(url);

const selectedMedia = ref<Evidencia | null>(null);

const openMedia = (evidencia: Evidencia) => {
  selectedMedia.value = evidencia;
};

const closeMedia = () => {
  selectedMedia.value = null;
};

const canSendReview = computed(
  () => tarea.estado !== 'En revisión' && tarea.estado !== 'Completada',
);
</script>

<template>
  <LeaderLayout :title="tarea.nombre">
    <div class="flex h-full flex-1 flex-col gap-4">
      <div class="flex items-center gap-3">
        <Link
          href="/mis-tareas"
          class="text-sm font-medium text-primary hover:underline"
        >
          Volver
        </Link>
        <span class="text-xs text-muted-foreground">Detalle de tarea</span>
      </div>

      <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5 shadow-sm">
        <div class="flex flex-col gap-3">
          <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-foreground">
              {{ tarea.nombre }}
            </h1>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">
              {{ tarea.estado }}
            </span>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ tarea.instrucciones }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5">
          <h2 class="text-sm font-semibold text-foreground">Reportar estado</h2>
          <div class="mt-3 rounded-md border border-dashed border-input bg-muted/30 p-3">
            <p class="text-xs text-muted-foreground">
              Cuando la actividad esté lista, envíala a revisión para que el supervisor la apruebe.
            </p>
            <button
              type="button"
              class="mt-2 h-8 rounded-md bg-primary/10 px-3 text-xs font-semibold text-primary disabled:opacity-60"
              :disabled="statusForm.processing || !canSendReview"
              @click="submitReviewRequest"
            >
              Enviar a revisión
            </button>
          </div>
          <form class="mt-4 grid gap-3" @submit.prevent="submitStatus">
            <div class="grid gap-2">
              <label class="text-xs font-medium text-muted-foreground" for="status-select">
                Estado
              </label>
              <select
                id="status-select"
                v-model="statusForm.status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
              >
                <option value="Pendiente">Pendiente</option>
                <option value="En progreso">En progreso</option>
                <option value="En revisión">En revisión</option>
              </select>
              <p v-if="statusForm.errors.status" class="text-xs text-destructive">
                {{ statusForm.errors.status }}
              </p>
            </div>

            <div class="grid gap-2">
              <label class="text-xs font-medium text-muted-foreground" for="status-comment">
                Comentario
              </label>
              <textarea
                id="status-comment"
                v-model="statusForm.status_comment"
                rows="3"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none transition focus:border-primary"
              ></textarea>
              <p v-if="statusForm.errors.status_comment" class="text-xs text-destructive">
                {{ statusForm.errors.status_comment }}
              </p>
            </div>

            <button
              type="submit"
              class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60"
              :disabled="statusForm.processing"
            >
              Guardar estado
            </button>
          </form>
        </div>

        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5">
          <h2 class="text-sm font-semibold text-foreground">Subir evidencia</h2>
          <form class="mt-4 grid gap-3" @submit.prevent="submitEvidence">
            <input
              ref="evidenceInputRef"
              type="file"
              accept="image/*,video/mp4,video/quicktime,video/x-m4v"
              class="hidden"
              @change="(event) => setEvidenceFile((event.target as HTMLInputElement).files?.[0] ?? null)"
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
              rows="3"
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
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5">
          <h2 class="text-sm font-semibold text-foreground">Evidencias</h2>
          <div class="mt-4">
            <div
              v-if="tarea.evidencias.length > 0"
              class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2"
            >
              <button
                v-for="evidencia in tarea.evidencias"
                :key="evidencia.id"
                type="button"
                class="min-w-[180px] max-w-[220px] flex-shrink-0 snap-start rounded-md border border-input p-2 text-left"
                @click="openMedia(evidencia)"
              >
                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                  <span class="truncate">{{ evidencia.subido_por || 'Sin autor' }}</span>
                  <span class="ml-2 shrink-0">{{ evidencia.fecha }}</span>
                </div>
                <video
                  v-if="isVideoEvidence(evidencia.url)"
                  :src="evidencia.url"
                  class="mt-2 h-28 w-full rounded-md object-cover"
                  muted
                  preload="metadata"
                ></video>
                <img
                  v-else
                  :src="evidencia.url"
                  alt="Evidencia"
                  class="mt-2 h-28 w-full rounded-md object-cover"
                />
                <p class="mt-2 line-clamp-1 text-xs text-muted-foreground">
                  {{ evidencia.comentario || 'Sin comentario.' }}
                </p>
              </button>
            </div>
            <p v-else class="text-sm text-muted-foreground">
              Sin evidencias registradas.
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5">
          <h2 class="text-sm font-semibold text-foreground">Historial de estado</h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="registro in tarea.historial"
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
            <p v-if="tarea.historial.length === 0" class="text-sm text-muted-foreground">
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
  </LeaderLayout>
</template>
