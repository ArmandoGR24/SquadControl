<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskPublished extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?User $publishedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_published',
            'title' => 'Nueva tarea publicada',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'status' => $this->task->status,
            'published_by_id' => $this->publishedBy?->id,
            'published_by_name' => $this->publishedBy?->name,
            'message' => "Hay una nueva tarea disponible: {$this->task->name}",
        ];
    }
}
