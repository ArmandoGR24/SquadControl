<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly ?string $comment,
        private readonly ?User $actor,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_status_changed',
            'title' => 'Estado de tarea actualizado',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'comment' => $this->comment,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'message' => "La tarea '{$this->task->name}' cambió de {$this->previousStatus} a {$this->newStatus}",
        ];
    }
}
