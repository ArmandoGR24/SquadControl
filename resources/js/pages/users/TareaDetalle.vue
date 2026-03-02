<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
  evidences: [] as File[],
  comment: '',
});

const evidenceInputRef = ref<HTMLInputElement | null>(null);
const evidencePreviews = ref<
  Array<{
    name: string;
    size: number;
    url: string;
    isVideo: boolean;
  }>
>([]);

const releaseEvidencePreviews = () => {
  evidencePreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
  evidencePreviews.value = [];
};

const formatEvidenceSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const clearSelectedEvidence = () => {
  evidenceForm.evidences = [];
  evidenceForm.clearErrors('evidence');
  evidenceForm.clearErrors('evidences');
  releaseEvidencePreviews();

  if (evidenceInputRef.value) {
    evidenceInputRef.value.value = '';
  }
};

const removeSelectedEvidence = (index: number) => {
  const targetPreview = evidencePreviews.value[index];
  if (targetPreview) {
    URL.revokeObjectURL(targetPreview.url);
  }

  evidenceForm.evidences = evidenceForm.evidences.filter((_, fileIndex) => fileIndex !== index);
  evidencePreviews.value = evidencePreviews.value.filter((_, previewIndex) => previewIndex !== index);

  if (evidenceForm.evidences.length === 0 && evidenceInputRef.value) {
    evidenceInputRef.value.value = '';
  }
};

const triggerEvidencePicker = () => {
  evidenceInputRef.value?.click();
};

const setEvidenceFiles = async (files: FileList | null) => {
  evidenceForm.clearErrors('evidence');
  evidenceForm.clearErrors('evidences');

  if (!files || files.length === 0) {
    clearSelectedEvidence();
    return;
  }

  const selectedFiles = Array.from(files);
  const optimizedFiles: File[] = [];

  for (const file of selectedFiles) {
    const validationError = validateEvidenceFile(file);
    if (validationError) {
      clearSelectedEvidence();
      evidenceForm.setError('evidences', `${validationError} Archivo: ${file.name}`);
      return;
    }

    const optimizedFile = await optimizeEvidenceFile(file);
    const optimizedValidationError = validateEvidenceFile(optimizedFile);
    if (optimizedValidationError) {
      clearSelectedEvidence();
      evidenceForm.setError('evidences', `${optimizedValidationError} Archivo: ${optimizedFile.name}`);
      return;
    }

    optimizedFiles.push(optimizedFile);
  }

  releaseEvidencePreviews();
  evidenceForm.evidences = optimizedFiles;
  evidencePreviews.value = optimizedFiles.map((file) => ({
    name: file.name,
    size: file.size,
    url: URL.createObjectURL(file),
    isVideo: file.type.startsWith('video/'),
  }));
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
  if (evidenceForm.evidences.length === 0) return;
  evidenceForm.post(`/tareas/${tarea.id}/evidencias`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      evidenceForm.reset();
      evidenceForm.clearErrors();
      clearSelectedEvidence();
    },
  });
};

onBeforeUnmount(() => {
  releaseEvidencePreviews();
});

const isVideoEvidence = (url: string) => /\.(mp4|mov|m4v|webm)(\?|#|$)/i.test(url);

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
          const context = canvas.getContext('2d');
          if (!context) {
            fail();
            return;
          }
          context.drawImage(video, 0, 0, width, height);
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
    return;
  }
};

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

watch(
  () => tarea.evidencias,
  (evidencias) => {
    evidencias.forEach((evidencia) => {
      ensureVideoPoster(evidencia);
    });
  },
  { immediate: true },
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
              multiple
              accept="image/*,video/mp4,video/quicktime,video/x-m4v"
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
            <div v-if="evidencePreviews.length > 0" class="space-y-2 rounded-md border border-input p-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-muted-foreground">
                  {{ evidencePreviews.length }} archivo(s) seleccionado(s)
                </p>
                <button
                  type="button"
                  class="h-8 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
                  @click="clearSelectedEvidence"
                >
                  Quitar todos
                </button>
              </div>

              <div
                v-for="(preview, index) in evidencePreviews"
                :key="`${preview.name}-${index}`"
                class="rounded-md border border-input p-2"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p class="break-all text-sm font-medium text-foreground">{{ preview.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatEvidenceSize(preview.size) }}</p>
                  </div>
                  <button
                    type="button"
                    class="h-8 shrink-0 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-muted"
                    @click="removeSelectedEvidence(index)"
                  >
                    Quitar
                  </button>
                </div>

                <video
                  v-if="preview.isVideo"
                  :src="preview.url"
                  class="mt-2 max-h-56 w-full rounded-md bg-black"
                  controls
                  autoplay
                  loop
                  muted
                  playsinline
                  preload="auto"
                ></video>
                <img
                  v-else
                  :src="preview.url"
                  alt="Previsualización de evidencia"
                  class="mt-2 max-h-56 w-full rounded-md object-contain"
                />
              </div>
            </div>
            <textarea
              v-model="evidenceForm.comment"
              rows="3"
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
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-sidebar-border/70 bg-background p-5">
          <h2 class="text-sm font-semibold text-foreground">Evidencias</h2>
          <div class="mt-4">
            <div
              v-if="tarea.evidencias.length > 0"
              class="grid grid-cols-1 gap-2 sm:flex sm:snap-x sm:snap-mandatory sm:gap-3 sm:overflow-x-auto sm:pb-2"
            >
              <button
                v-for="evidencia in tarea.evidencias"
                :key="evidencia.id"
                type="button"
                class="w-full rounded-md border border-input p-2 text-left sm:min-w-[180px] sm:max-w-[220px] sm:flex-shrink-0 sm:snap-start"
                @click="openMedia(evidencia)"
              >
                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                  <span class="truncate">{{ evidencia.subido_por || 'Sin autor' }}</span>
                  <span class="ml-2 shrink-0">{{ evidencia.fecha }}</span>
                </div>
                <img
                  v-if="isVideoEvidence(evidencia.url) && videoPosters[evidencia.id]"
                  :src="videoPosters[evidencia.id]"
                  alt="Evidencia en video"
                  class="mt-2 h-28 w-full rounded-md object-cover"
                />
                <div
                  v-else-if="isVideoEvidence(evidencia.url)"
                  class="mt-2 h-28 w-full rounded-md bg-muted/40"
                ></div>
                <img
                  v-else
                  :src="evidencia.url"
                  alt="Evidencia"
                  class="mt-2 h-28 w-full rounded-md object-cover"
                />
                <p class="mt-2 line-clamp-2 break-words text-xs text-muted-foreground">
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
          playsinline
          preload="auto"
        >
          Tu navegador no pudo previsualizar este video.
        </video>
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
