<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Notifications\EvidenceAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskFeedback;
use App\Notifications\TaskPublished;
use App\Notifications\TaskReviewDecision;
use App\Notifications\TaskSentForReview;
use App\Notifications\TaskStatusChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskNotificationService
{
    private ?NotificationSetting $notificationSetting = null;

    /**
     * Notificar a todos los líderes que hay una tarea nueva disponible
     */
    public function notifyTaskPublished(Task $task, ?User $publishedBy = null): void
    {
        if (!$this->isEventEnabled('task_published')) {
            return;
        }

        $leaders = User::query()
            ->where('role', 'Lider de Cuadrilla')
            ->whereIn('role', $this->allowedRolesForEvent('task_published'))
            ->get(['id']);

        $leaders->each(function (User $leader) use ($task, $publishedBy) {
            $leader->notify(new TaskPublished($task, $publishedBy));
        });

        $this->sendPushToUsers(
            $leaders->pluck('id')->all(),
            'Nueva tarea publicada',
            "Hay una nueva tarea disponible: {$task->name}",
            [
                'type' => 'task_published',
                'task_id' => $task->id,
                'url' => "/mis-tareas/{$task->id}",
                'published_by_id' => $publishedBy?->id,
            ]
        );
    }

    private function sendPushToUsers(array $userIds, string $title, string $message, array $data = []): void
    {
        $recipientIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $tokens = UserFcmToken::query()
            ->whereIn('user_id', $recipientIds->all())
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $payloadData = collect($data)
            ->map(function ($value) {
                if ($value === null) {
                    return '';
                }

                if (is_scalar($value)) {
                    return (string) $value;
                }

                return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            })
            ->all();

        try {
            $firebaseService = app(FirebaseService::class);
            $result = $firebaseService->sendMulticast($tokens, $title, $message, $payloadData);

            if (($result['success'] ?? false) !== true) {
                Log::warning('FCM multicast failed for task notifications', [
                    'title' => $title,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);

                return;
            }

            $tokensToRemove = collect($result['invalid_tokens'] ?? [])
                ->merge($result['unknown_tokens'] ?? [])
                ->filter()
                ->unique()
                ->values();

            if ($tokensToRemove->isNotEmpty()) {
                UserFcmToken::query()
                    ->whereIn('token', $tokensToRemove->all())
                    ->delete();
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to send FCM task notification', [
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notificar a los líderes que se les ha asignado una tarea
     */
    public function notifyTaskAssigned(Task $task, ?User $assignedBy = null, array $leaderIds = []): void
    {
        if (!$this->isEventEnabled('task_assigned')) {
            return;
        }

        $recipientIds = $this->filterUserIdsByEventRoles($leaderIds, 'task_assigned');

        if (empty($recipientIds)) {
            return;
        }

        foreach ($recipientIds as $leaderId) {
            $leader = User::find((int) $leaderId);
            if ($leader) {
                $leader->notify(new TaskAssigned($task, $assignedBy));
            }
        }

        $this->sendPushToUsers(
            $recipientIds,
            'Nueva tarea asignada',
            "Se te asignó la tarea: {$task->name}",
            [
                'type' => 'task_assigned',
                'task_id' => $task->id,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    /**
     * Notificar cambio de estado de tarea
     */
    public function notifyTaskStatusChanged(
        Task $task,
        string $previousStatus,
        string $newStatus,
        ?string $comment = null,
        ?User $actor = null,
        array $recipientIds = []
    ): void {
        if (!$this->isEventEnabled('task_status_changed')) {
            return;
        }

        // Si no se especifican destinatarios, notificar a los líderes
        if (empty($recipientIds)) {
            $recipientIds = $task->leaders()->pluck('users.id')->toArray();
        }

        $recipientIds = $this->filterUserIdsByEventRoles(
            $recipientIds,
            'task_status_changed',
            $actor?->id
        );

        if (empty($recipientIds)) {
            return;
        }

        foreach ($recipientIds as $userId) {
            $user = User::find((int) $userId);
            if ($user) {
                $user->notify(new TaskStatusChanged(
                    $task,
                    $previousStatus,
                    $newStatus,
                    $comment,
                    $actor
                ));
            }
        }

        $this->sendPushToUsers(
            $recipientIds,
            'Estado de tarea actualizado',
            "{$task->name}: {$previousStatus} → {$newStatus}",
            [
                'type' => 'task_status_changed',
                'task_id' => $task->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    /**
     * Notificar que una tarea fue enviada para revisión
     */
    public function notifyTaskSentForReview(Task $task, ?User $sentBy = null): void
    {
        if (!$this->isEventEnabled('task_review_requested')) {
            return;
        }

        $recipients = User::whereIn('role', ['Supervisor', 'Admin'])->get(['id']);

        $allowedRecipientIds = $this->filterUserIdsByEventRoles(
            $recipients->pluck('id')->all(),
            'task_review_requested'
        );

        if (empty($allowedRecipientIds)) {
            return;
        }

        $recipients = User::query()->whereIn('id', $allowedRecipientIds)->get(['id']);

        $recipients->each(fn (User $user) => $user->notify(new TaskSentForReview($task, $sentBy)));

        $this->sendPushToUsers(
            $recipients->pluck('id')->all(),
            'Tarea enviada a revisión',
            "{$task->name} fue enviada para revisión",
            [
                'type' => 'task_review_requested',
                'task_id' => $task->id,
                'actor_id' => $sentBy?->id,
                'url' => '/tareas',
            ]
        );
    }

    /**
     * Notificar decisión de revisión (aprobado/rechazado)
     */
    public function notifyTaskReviewDecision(
        Task $task,
        string $decision,
        ?string $comment = null,
        ?User $reviewer = null
    ): void {
        if (!$this->isEventEnabled('task_review_decision')) {
            return;
        }

        $recipientIds = $this->filterUserIdsByEventRoles(
            $task->leaders->pluck('id')->all(),
            'task_review_decision'
        );

        if (empty($recipientIds)) {
            return;
        }

        $users = User::query()->whereIn('id', $recipientIds)->get();

        $users->each(fn (User $user) => $user->notify(new TaskReviewDecision(
            $task,
            $decision,
            $comment,
            $reviewer,
        )));

        $this->sendPushToUsers(
            $recipientIds,
            'Resultado de revisión',
            "{$task->name}: {$decision}",
            [
                'type' => 'task_review_decision',
                'task_id' => $task->id,
                'decision' => $decision,
                'comment' => $comment,
                'actor_id' => $reviewer?->id,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    /**
     * Notificar feedback de revisión
     */
    public function notifyTaskFeedback(
        Task $task,
        string $feedback,
        ?string $comment = null,
        ?User $feedbackFrom = null
    ): void {
        if (!$this->isEventEnabled('task_feedback')) {
            return;
        }

        $recipientIds = $this->filterUserIdsByEventRoles(
            $task->leaders->pluck('id')->all(),
            'task_feedback'
        );

        if (empty($recipientIds)) {
            return;
        }

        $users = User::query()->whereIn('id', $recipientIds)->get();

        $users->each(fn (User $user) => $user->notify(new TaskFeedback(
            $task,
            $feedback,
            $comment,
            $feedbackFrom,
        )));

        $this->sendPushToUsers(
            $recipientIds,
            'Feedback de revisión',
            "{$task->name}: {$feedback}",
            [
                'type' => 'task_feedback',
                'task_id' => $task->id,
                'feedback' => $feedback,
                'comment' => $comment,
                'actor_id' => $feedbackFrom?->id,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    /**
     * Notificar que nueva evidencia fue agregada
     */
    public function notifyEvidenceAdded(
        Task $task,
        ?string $comment = null,
        ?User $uploadedBy = null
    ): void {
        if (!$this->isEventEnabled('evidence_added')) {
            return;
        }

        $defaultRecipientIds = User::query()
            ->whereIn('role', ['Supervisor', 'Admin'])
            ->pluck('id')
            ->merge($task->leaders->pluck('id'))
            ->filter(fn ($id) => (int) $id !== (int) ($uploadedBy?->id ?? 0))
            ->unique()
            ->values()
            ->all();

        $recipientIds = $this->filterUserIdsByEventRoles(
            $defaultRecipientIds,
            'evidence_added'
        );

        if (empty($recipientIds)) {
            return;
        }

        $users = User::query()->whereIn('id', $recipientIds)->get();

        $users->each(function (User $user) use ($task, $comment, $uploadedBy) {
            $user->notify(new EvidenceAdded($task, $comment, $uploadedBy));
        });

        $this->sendPushToUsers(
            $recipientIds,
            'Nueva evidencia agregada',
            "Se agregó evidencia a la tarea: {$task->name}",
            [
                'type' => 'evidence_added',
                'task_id' => $task->id,
                'uploaded_by_id' => $uploadedBy?->id,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    /**
     * Notificar sobre cambios de asignación de líderes
     */
    public function notifyNewLeaderAssignments(Task $task, array $newLeaderIds, ?User $assignedBy = null): void
    {
        $currentLeaderIds = $task->leaders()->pluck('users.id')->toArray();
        $addedLeaderIds = array_diff($newLeaderIds, $currentLeaderIds);

        $this->notifyTaskAssigned($task, $assignedBy, $addedLeaderIds);
    }

    /**
     * Procesar revisión completa con todas las notificaciones
     */
    public function processReview(
        Task $task,
        string $newStatus,
        ?string $comment = null,
        ?User $reviewer = null
    ): void {
        $feedback = $newStatus === 'Completada' ? 'approved' : 'changes_requested';

        // Notificar feedback
        $this->notifyTaskFeedback($task, $feedback, $comment, $reviewer);

        // Notificar decisión (para compatibilidad)
        $decision = $newStatus === 'Completada' ? 'Aceptada' : 'Rechazada';
        $this->notifyTaskReviewDecision($task, $decision, $comment, $reviewer);

        $this->notifyTaskStatusChanged(
            $task,
            'En revisión',
            $newStatus,
            $comment,
            $reviewer,
            $task->leaders()->pluck('users.id')->toArray(),
        );

        if ($newStatus === 'Completada') {
            $this->notifyTaskCompleted($task, $comment, $reviewer);
        }
    }

    public function notifyTaskCompleted(Task $task, ?string $comment = null, ?User $completedBy = null): void
    {
        if (!$this->isEventEnabled('task_completed')) {
            return;
        }

        $defaultRecipientIds = User::query()
            ->whereIn('role', ['Lider de Cuadrilla', 'Supervisor', 'Admin'])
            ->pluck('id')
            ->merge($task->leaders->pluck('id'))
            ->filter(fn ($id) => (int) $id !== (int) ($completedBy?->id ?? 0))
            ->unique()
            ->values()
            ->all();

        $recipientIds = $this->filterUserIdsByEventRoles($defaultRecipientIds, 'task_completed');

        if (empty($recipientIds)) {
            return;
        }

        $users = User::query()->whereIn('id', $recipientIds)->get();

        $users->each(function (User $user) use ($task, $comment, $completedBy) {
            $user->notify(new TaskCompleted($task, $comment, $completedBy));
        });

        $this->sendPushToUsers(
            $recipientIds,
            'Tarea completada',
            "La tarea {$task->name} fue completada",
            [
                'type' => 'task_completed',
                'task_id' => $task->id,
                'completed_by_id' => $completedBy?->id,
                'url' => "/mis-tareas/{$task->id}",
            ]
        );
    }

    private function getSettings(): NotificationSetting
    {
        if ($this->notificationSetting instanceof NotificationSetting) {
            return $this->notificationSetting;
        }

        $this->notificationSetting = NotificationSetting::query()->firstOrCreate([], [
            'event_settings' => NotificationSetting::defaultEventSettings(),
            'recipient_settings' => NotificationSetting::defaultRecipientSettings(),
        ]);

        return $this->notificationSetting;
    }

    private function isEventEnabled(string $eventKey): bool
    {
        $settings = $this->getSettings();
        $events = $settings->resolvedEventSettings();

        return (bool) ($events[$eventKey] ?? true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedRolesForEvent(string $eventKey): array
    {
        $settings = $this->getSettings();
        $recipients = $settings->resolvedRecipientSettings();

        return collect($recipients[$eventKey] ?? [])
            ->filter(fn ($role) => in_array($role, NotificationSetting::ROLE_OPTIONS, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<int, int>
     */
    private function filterUserIdsByEventRoles(array $userIds, string $eventKey, ?int $excludeUserId = null): array
    {
        $allowedRoles = $this->allowedRolesForEvent($eventKey);

        if (empty($allowedRoles)) {
            return [];
        }

        return User::query()
            ->whereIn('id', collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->all())
            ->whereIn('role', $allowedRoles)
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
