<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?User $assignedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'title' => 'Nueva tarea asignada',
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'status' => $this->task->status,
            'assigned_by_id' => $this->assignedBy?->id,
            'assigned_by_name' => $this->assignedBy?->name,
            'message' => "Se te ha asignado la tarea: {$this->task->name}",
        ];
    }
}
