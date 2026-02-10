<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TareasController extends Controller
{
    public function mine(Request $request)
    {
        $userId = $request->user()?->id;

        $tareas = Task::query()
            ->whereHas('leaders', fn ($query) => $query->where('users.id', $userId))
            ->with([
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
                    'estado' => $task->status,
                    'evidencias' => $task->evidences
                        ->sortByDesc('created_at')
                        ->map(function (TaskEvidence $evidence) {
                            return [
                                'id' => $evidence->id,
                                'url' => Storage::url($evidence->path),
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
        $userId = $request->user()?->id;
        $isAssigned = $task->leaders()->where('users.id', $userId)->exists();

        if (!$isAssigned) {
            abort(403);
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
            'estado' => $task->status,
            'evidencias' => $task->evidences
                ->sortByDesc('created_at')
                ->map(function (TaskEvidence $evidence) {
                    return [
                        'id' => $evidence->id,
                        'url' => Storage::url($evidence->path),
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
                                'url' => Storage::url($evidence->path),
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

        $lideres = User::query()
            ->select(['id', 'name'])
            ->where('role', 'Lider de Cuadrilla')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nombre' => $user->name,
            ])
            ->all();

        return Inertia::render('Tareas', [
            'tareas' => $tareas,
            'lideres' => $lideres,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completada'])],
            'leader_ids' => ['array'],
            'leader_ids.*' => ['integer', Rule::exists('users', 'id')],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $task = Task::create([
            'name' => $validated['name'],
            'instructions' => $validated['instructions'],
            'status' => $validated['status'],
        ]);

        $task->leaders()->sync($validated['leader_ids'] ?? []);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id,
            'status' => $validated['status'],
            'comment' => $validated['status_comment'] ?? null,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completada'])],
            'leader_ids' => ['array'],
            'leader_ids.*' => ['integer', Rule::exists('users', 'id')],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $previousStatus = $task->status;

        $task->update([
            'name' => $validated['name'],
            'instructions' => $validated['instructions'],
            'status' => $validated['status'],
        ]);

        $task->leaders()->sync($validated['leader_ids'] ?? []);

        if ($previousStatus !== $validated['status'] || !empty($validated['status_comment'])) {
            TaskStatusHistory::create([
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
                'status' => $validated['status'],
                'comment' => $validated['status_comment'] ?? null,
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return redirect()->back();
    }

    public function storeEvidence(Request $request, Task $task)
    {
        $validated = $request->validate([
            'evidence' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-m4v',
                'max:20480',
            ],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $validated['evidence']->store('task-evidences', 'public');

        $task->evidences()->create([
            'user_id' => $request->user()?->id,
            'path' => $path,
            'comment' => $validated['comment'] ?? null,
        ]);

        return redirect()->back();
    }

    public function updateStatus(Request $request, Task $task)
    {
        $userId = $request->user()?->id;
        $isAssigned = $task->leaders()->where('users.id', $userId)->exists();

        if (!$isAssigned) {
            abort(403);
        }

        // Líderes y empleados solo pueden usar estos estados
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Pendiente', 'En progreso', 'En revisión'])],
            'status_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $task->update([
            'status' => $validated['status'],
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'status' => $validated['status'],
            'comment' => $validated['status_comment'] ?? null,
        ]);

        return redirect()->back();
    }
}
