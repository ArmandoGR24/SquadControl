<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Notifications\TaskMaterialsUpdated;
use App\Services\TaskNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TareasController extends Controller
{
    public function __construct(private TaskNotificationService $notificationService) {}

    public function materialsIndex(Request $request)
    {
        $tareas = Task::query()
            ->with(['leaders:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'nombre' => $task->name,
                'estado' => $task->status,
                'materiales' => $task->materials,
                'lideres' => $task->leaders
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'nombre' => $user->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Materiales', [
            'tareas' => $tareas,
        ]);
    }

    public function materialsShow(Request $request, Task $task)
    {
        $task->load(['leaders:id,name']);

        $tarea = [
            'id' => $task->id,
            'nombre' => $task->name,
            'estado' => $task->status,
            'materiales' => $task->materials,
            'lideres' => $task->leaders
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                ])
                ->values()
                ->all(),
        ];

        $usuarios = User::query()
            ->where('status', 'Activo')
            ->whereIn('role', ['Admin', 'Supervisor'])
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nombre' => $user->name,
                'rol' => $user->role,
            ])
            ->values()
            ->all();

        return Inertia::render('MaterialesDetalle', [
            'tarea' => $tarea,
            'usuarios' => $usuarios,
        ]);
    }

    public function updateMaterials(Request $request, Task $task)
    {
        $validated = $request->validate([
            'materials' => ['required', 'array'],
            'materials.*.label' => ['required', 'string', 'max:120'],
            'materials.*.in_stock' => ['nullable', 'boolean'],
            'materials.*.holder_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', ['Admin', 'Supervisor'])
                    ->where('status', 'Activo')),
            ],
        ]);

        $holderIds = collect($validated['materials'])
            ->pluck('holder_user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $holdersById = User::query()
            ->whereIn('id', $holderIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $normalizedMaterials = collect($validated['materials'])
            ->map(function (array $material) use ($holdersById) {
                $holderUserId = isset($material['holder_user_id']) ? (int) $material['holder_user_id'] : null;
                $holderName = $holderUserId ? ($holdersById->get($holderUserId)?->name ?? null) : null;

                return [
                    'label' => trim((string) $material['label']),
                    'in_stock' => (bool) ($material['in_stock'] ?? false),
                    'holder_user_id' => $holderUserId,
                    'holder_name' => $holderName,
                ];
            })
            ->filter(fn (array $material) => $material['label'] !== '')
            ->values()
            ->all();

        $task->update([
            'materials' => json_encode($normalizedMaterials, JSON_UNESCAPED_UNICODE),
        ]);

        $leaderRecipientIds = $task->leaders()->pluck('users.id')->toArray();

        if (empty($leaderRecipientIds)) {
            $leaderRecipientIds = User::query()
                ->where('role', 'Lider de Cuadrilla')
                ->pluck('id')
                ->toArray();
        }

        if (! empty($leaderRecipientIds)) {
            $leaders = User::query()->whereIn('id', $leaderRecipientIds)->get();
            $leaders->each(fn (User $leader) => $leader->notify(new TaskMaterialsUpdated($task, $normalizedMaterials, $request->user())));
        }

        return redirect()->back();
    }

    public function materialsMine(Request $request)
    {
        $user = $request->user();
        $isLeader = $user?->role === 'Lider de Cuadrilla';

        if (! $isLeader) {
            abort(403);
        }

        $query = Task::query();

        $tareas = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'nombre' => $task->name,
                'estado' => $task->status,
                'materiales' => $task->materials,
            ])
            ->values()
            ->all();

        return Inertia::render('users/Materiales', [
            'tareas' => $tareas,
        ]);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id;
        $isLeader = $user?->role === 'Lider de Cuadrilla';

        $query = Task::query();

        if (! $isLeader) {
            $query->whereHas('leaders', fn ($leadersQuery) => $leadersQuery->where('users.id', $userId));
        }

        $tareas = $query
            ->with([
                'leaders:id,name',
                'evidences:id,task_id,user_id,path,comment,created_at',
                'evidences.uploader:id,name',
                'statusHistories:id,task_id,user_id,status,comment,created_at',
                'statusHistories.reporter:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Task $task) {
                return [
                    'id' => $task->id,
                    'nombre' => $task->name,
                    'instrucciones' => $task->instructions,
                    'materiales' => $task->materials,
                    'estado' => $task->status,
                    'lideres' => $task->leaders
                        ->map(fn (User $leader) => [
                            'id' => $leader->id,
                            'nombre' => $leader->name,
                        ])
                        ->values()
                        ->all(),
                    'evidencias' => $task->evidences
                        ->sortByDesc('created_at')
                        ->map(function (TaskEvidence $evidence) {
                            return [
                                'id' => $evidence->id,
                                'url' => route('public.media.fallback', ['path' => $evidence->path]),
                                'comentario' => $evidence->comment,
                                'fecha' => optional($evidence->created_at)->toDateTimeString(),
                                'subido_por' => $evidence->uploader?->name,
                            ];
                        })
                        ->values()
                        ->all(),
                    'historial' => $task->statusHistories
                        ->sortByDesc('created_at')
                        ->map(function (TaskStatusHistory $history) {
                            return [
                                'id' => $history->id,
                                'estado' => $history->status,
                                'comentario' => $history->comment,
                                'fecha' => optional($history->created_at)->toDateTimeString(),
                                'reportado_por' => $history->reporter?->name,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        return Inertia::render('users/Tareas', [
            'tareas' => $tareas,
        ]);
    }

    public function showMine(Request $request, Task $task)
    {
        $user = $request->user();
        $userId = $user?->id;
        $isLeader = $user?->role === 'Lider de Cuadrilla';
        $isAssigned = $task->leaders()->where('users.id', $userId)->exists();

        if (! $isLeader && ! $isAssigned) {
            abort(403);
        }

        // Si es líder y aún no está asignado, toma la tarea automáticamente.
        if ($isLeader && ! $isAssigned && $userId) {
            $task->leaders()->syncWithoutDetaching([$userId]);
        }

        $task->load([
            'evidences:id,task_id,user_id,path,comment,created_at',
            'evidences.uploader:id,name',
            'statusHistories:id,task_id,user_id,status,comment,created_at',
            'statusHistories.reporter:id,name',
        ]);

        $tarea = [
            'id' => $task->id,
            'nombre' => $task->name,
            'instrucciones' => $task->instructions,
            'materiales' => $task->materials,
            'estado' => $task->status,
            'evidencias' => $task->evidences
                ->sortByDesc('created_at')
                ->map(function (TaskEvidence $evidence) {
                    return [
                        'id' => $evidence->id,
                        'url' => route('public.media.fallback', ['path' => $evidence->path]),
                        'comentario' => $evidence->comment,
                        'fecha' => optional($evidence->created_at)->toDateTimeString(),
                        'subido_por' => $evidence->uploader?->name,
                    ];
                })
                ->values()
                ->all(),
            'historial' => $task->statusHistories
                ->sortByDesc('created_at')
                ->map(function (TaskStatusHistory $history) {
                    return [
                        'id' => $history->id,
                        'estado' => $history->status,
                        'comentario' => $history->comment,
                        'fecha' => optional($history->created_at)->toDateTimeString(),
                        'reportado_por' => $history->reporter?->name,
                    ];
                })
                ->values()
                ->all(),
        ];

        return Inertia::render('users/TareaDetalle', [
            'tarea' => $tarea,
        ]);
    }

    public function index()
    {
        $tareas = Task::query()
            ->with([
                'leaders:id,name',
                'evidences:id,task_id,user_id,path,comment,created_at',
                'evidences.uploader:id,name',
                'statusHistories:id,task_id,user_id,status,comment,created_at',
                'statusHistories.reporter:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Task $task) {
                return [
                    'id' => $task->id,
                    'nombre' => $task->name,
                    'instrucciones' => $task->instructions,
                    'materiales' => $task->materials,
                    'estado' => $task->status,
                    'lideres' => $task->leaders
                        ->map(fn (User $user) => [
                            'id' => $user->id,
                            'nombre' => $user->name,
                        ])
                        ->all(),
                    'evidencias' => $task->evidences
                        ->sortByDesc('created_at')
                        ->map(function (TaskEvidence $evidence) {
                            return [
                                'id' => $evidence->id,
                                'url' => route('public.media.fallback', ['path' => $evidence->path]),
                                'comentario' => $evidence->comment,
                                'fecha' => optional($evidence->created_at)->toDateTimeString(),
                                'subido_por' => $evidence->uploader?->name,
                            ];
                        })
                        ->values()
                        ->all(),
                    'historial' => $task->statusHistories
                        ->sortByDesc('created_at')
                        ->map(function (TaskStatusHistory $history) {
                            return [
                                'id' => $history->id,
                                'estado' => $history->status,
                                'comentario' => $history->comment,
                                'fecha' => optional($history->created_at)->toDateTimeString(),
                                'reportado_por' => $history->reporter?->name,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        return Inertia::render('Tareas', [
            'tareas' => $tareas,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'materials' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completada'])],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $task = Task::create([
            'name' => $validated['name'],
            'instructions' => $validated['instructions'],
            'materials' => $validated['materials'] ?? null,
            'status' => $validated['status'],
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id,
            'status' => $validated['status'],
            'comment' => $validated['status_comment'] ?? null,
        ]);

        $this->notificationService->notifyTaskPublished(
            $task,
            $request->user(),
        );

        return redirect()->back();
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'materials' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completada'])],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $previousStatus = $task->status;

        $task->update([
            'name' => $validated['name'],
            'instructions' => $validated['instructions'],
            'materials' => $validated['materials'] ?? null,
            'status' => $validated['status'],
        ]);

        if ($previousStatus !== $validated['status'] || ! empty($validated['status_comment'])) {
            TaskStatusHistory::create([
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
                'status' => $validated['status'],
                'comment' => $validated['status_comment'] ?? null,
            ]);

            // Notificar a todos los usuarios involucrados sobre el cambio de estado
            if ($previousStatus !== $validated['status']) {
                $this->notificationService->notifyTaskStatusChanged(
                    $task,
                    $previousStatus,
                    $validated['status'],
                    $validated['status_comment'] ?? null,
                    $request->user(),
                    $task->leaders()->pluck('users.id')->toArray()
                );

                if ($validated['status'] === 'Completada') {
                    $this->notificationService->notifyTaskCompleted(
                        $task,
                        $validated['status_comment'] ?? null,
                        $request->user(),
                    );
                }
            }
        }

        return redirect()->back();
    }

    public function destroy(Task $task)
    {
        $evidencePaths = $task->evidences()
            ->pluck('path')
            ->filter()
            ->values();

        if ($evidencePaths->isNotEmpty()) {
            Storage::disk('public')->delete($evidencePaths->all());
        }

        $task->evidences()->delete();
        $task->statusHistories()->delete();
        $task->leaders()->detach();
        $task->delete();

        return redirect()->back();
    }

    public function storeEvidence(Request $request, Task $task)
    {
        $user = $request->user();
        $userId = $user?->id;
        $isLeader = $user?->role === 'Lider de Cuadrilla';
        $canManageTasks = in_array($user?->role, ['Admin', 'RH', 'Supervisor'], true);
        $isAssigned = $task->leaders()->where('users.id', $userId)->exists();

        if (! $isAssigned && ! $isLeader && ! $canManageTasks) {
            abort(403);
        }

        if ($isLeader && ! $isAssigned && $userId) {
            $task->leaders()->syncWithoutDetaching([$userId]);
        }

        $validated = $request->validate([
            'evidence' => [
                'nullable',
                'file',
                'required_without:evidences',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-m4v,video/webm,video/3gpp,video/3gpp2',
                'max:256000',
            ],
            'evidences' => ['nullable', 'array', 'required_without:evidence'],
            'evidences.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-m4v,video/webm,video/3gpp,video/3gpp2',
                'max:256000',
            ],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $files = [];

        if ($request->hasFile('evidences')) {
            $files = $request->file('evidences');
        } elseif ($request->hasFile('evidence')) {
            $singleEvidence = $request->file('evidence');
            if ($singleEvidence) {
                $files = [$singleEvidence];
            }
        }

        foreach ($files as $file) {
            $path = $file->store('task-evidences', 'public');

            $task->evidences()->create([
                'user_id' => $request->user()?->id,
                'path' => $path,
                'comment' => $validated['comment'] ?? null,
            ]);
        }

        // Notificar sobre la nueva evidencia
        $this->notificationService->notifyEvidenceAdded(
            $task,
            $validated['comment'] ?? null,
            $request->user()
        );

        return redirect()->back();
    }

    public function updateStatus(Request $request, Task $task)
    {
        $user = $request->user();
        $userId = $user?->id;
        $isLeader = $user?->role === 'Lider de Cuadrilla';
        $isAssigned = $task->leaders()->where('users.id', $userId)->exists();

        if (! $isAssigned && ! $isLeader) {
            abort(403);
        }

        // Si un líder no estaba asignado y actualiza estado, se asigna automáticamente.
        if ($isLeader && ! $isAssigned && $userId) {
            $task->leaders()->syncWithoutDetaching([$userId]);
        }

        // Líderes y empleados solo pueden usar estos estados
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión'])],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $previousStatus = $task->status;
        $task->update([
            'status' => $validated['status'],
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'status' => $validated['status'],
            'comment' => $validated['status_comment'] ?? null,
        ]);

        // Notificar cambio de estado a otros líderes
        $this->notificationService->notifyTaskStatusChanged(
            $task,
            $previousStatus,
            $validated['status'],
            $validated['status_comment'] ?? null,
            $request->user(),
            $task->leaders()->pluck('users.id')->toArray()
        );

        // Si se envía a revisión, notificar a supervisores y admins
        if ($validated['status'] === 'En revisión') {
            $this->notificationService->notifyTaskSentForReview($task, $request->user());
        }

        return redirect()->back();
    }

    public function review(Request $request, Task $task)
    {
        $role = $request->user()?->role;

        if (! in_array($role, ['Supervisor', 'Admin'], true)) {
            abort(403);
        }

        if ($task->status !== 'En revisión') {
            return redirect()->back()->withErrors([
                'status' => 'La tarea no está en revisión.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Completada', 'En progreso'])],
            'status_comment' => ['required_if:status,En progreso', 'string', 'max:500'],
            'evidence' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-m4v,video/webm,video/3gpp,video/3gpp2',
                'max:256000',
            ],
        ]);

        $task->update([
            'status' => $validated['status'],
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id,
            'status' => $validated['status'],
            'comment' => $validated['status_comment'] ?? null,
        ]);

        if ($validated['status'] === 'En progreso' && $request->hasFile('evidence')) {
            $path = $request->file('evidence')->store('task-evidences', 'public');

            $task->evidences()->create([
                'user_id' => $request->user()?->id,
                'path' => $path,
                'comment' => $validated['status_comment'] ?? 'Guia de revision.',
            ]);
        }

        // Procesar revisión y enviar todas las notificaciones
        $this->notificationService->processReview(
            $task,
            $validated['status'],
            $validated['status_comment'] ?? null,
            $request->user()
        );

        return redirect()->back();
    }
}
