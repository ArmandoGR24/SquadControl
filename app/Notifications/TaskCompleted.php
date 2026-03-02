<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompleted extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?string $comment,
        private readonly ?User $completedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_completed',
            'title' => 'Tarea completada',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'comment' => $this->comment,
            'completed_by_id' => $this->completedBy?->id,
            'completed_by_name' => $this->completedBy?->name,
            'message' => "La tarea '{$this->task->name}' fue marcada como completada.",
        ];
    }
}
