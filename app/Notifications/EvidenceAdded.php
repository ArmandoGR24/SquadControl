<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EvidenceAdded extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?string $comment,
        private readonly ?User $uploadedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $targetUrl = in_array($notifiable->role ?? '', ['Admin', 'Supervisor', 'RH'], true)
            ? '/tareas'
            : "/mis-tareas/{$this->task->id}";

        return [
            'type' => 'evidence_added',
            'title' => 'Nueva evidencia agregada',
            'url' => $targetUrl,
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'comment' => $this->comment,
            'uploaded_by_id' => $this->uploadedBy?->id,
            'uploaded_by_name' => $this->uploadedBy?->name,
            'message' => "Se agregó nueva evidencia a la tarea: {$this->task->name}",
        ];
    }
}
