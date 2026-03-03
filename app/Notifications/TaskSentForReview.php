<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskSentForReview extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_review_requested',
            'title' => 'Tarea enviada a revisión',
            'url' => '/tareas',
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'status' => $this->task->status,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
        ];
    }
}
