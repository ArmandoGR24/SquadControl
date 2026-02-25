<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\EvidenceAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskFeedback;
use App\Notifications\TaskReviewDecision;
use App\Notifications\TaskSentForReview;
use App\Notifications\TaskStatusChanged;

class TaskNotificationService
{
    /**
     * Notificar a los líderes que se les ha asignado una tarea
     */
    public function notifyTaskAssigned(Task $task, ?User $assignedBy = null, array $leaderIds = []): void
    {
        foreach ($leaderIds as $leaderId) {
            $leader = User::find($leaderId);
            if ($leader) {
                $leader->notify(new TaskAssigned($task, $assignedBy));
            }
        }
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
        // Si no se especifican destinatarios, notificar a los líderes
        if (empty($recipientIds)) {
            $recipientIds = $task->leaders()->pluck('users.id')->toArray();
        }

        foreach ($recipientIds as $userId) {
            $user = User::find($userId);
            if ($user && $user->id !== $actor?->id) {
                $user->notify(new TaskStatusChanged(
                    $task,
                    $previousStatus,
                    $newStatus,
                    $comment,
                    $actor
                ));
            }
        }
    }

    /**
     * Notificar que una tarea fue enviada para revisión
     */
    public function notifyTaskSentForReview(Task $task, ?User $sentBy = null): void
    {
        User::whereIn('role', ['Supervisor', 'Admin'])
            ->each(fn (User $user) => $user->notify(new TaskSentForReview($task, $sentBy)));
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
        $task->leaders->each(fn (User $user) => $user->notify(new TaskReviewDecision(
            $task,
            $decision,
            $comment,
            $reviewer,
        )));
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
        $task->leaders->each(fn (User $user) => $user->notify(new TaskFeedback(
            $task,
            $feedback,
            $comment,
            $feedbackFrom,
        )));
    }

    /**
     * Notificar que nueva evidencia fue agregada
     */
    public function notifyEvidenceAdded(
        Task $task,
        ?string $comment = null,
        ?User $uploadedBy = null
    ): void {
        // Notificar supervisores y admins
        User::whereIn('role', ['Supervisor', 'Admin'])
            ->each(fn (User $user) => $user->notify(new EvidenceAdded($task, $comment, $uploadedBy)));

        // Notificar a los líderes si no son quienes subieron
        $task->leaders->each(function (User $leader) use ($task, $comment, $uploadedBy) {
            if ($leader->id !== $uploadedBy?->id) {
                $leader->notify(new EvidenceAdded($task, $comment, $uploadedBy));
            }
        });
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
    }
}
