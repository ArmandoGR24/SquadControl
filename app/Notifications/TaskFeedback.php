<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskFeedback extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly string $feedback,
        private readonly ?string $comment,
        private readonly ?User $feedbackFrom,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $feedbackLabel = match ($this->feedback) {
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'changes_requested' => 'Cambios solicitados',
            default => ucfirst($this->feedback),
        };

        return [
            'type' => 'task_feedback',
            'title' => 'Feedback de revisión',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'feedback' => $this->feedback,
            'feedback_label' => $feedbackLabel,
            'comment' => $this->comment,
            'feedback_from_id' => $this->feedbackFrom?->id,
            'feedback_from_name' => $this->feedbackFrom?->name,
            'message' => "Tu tarea '{$this->task->name}' ha sido {$feedbackLabel} por {$this->feedbackFrom?->name}",
        ];
    }
}
