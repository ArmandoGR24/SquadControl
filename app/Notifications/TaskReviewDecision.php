<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReviewDecision extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly string $decision,
        private readonly ?string $comment,
        private readonly ?User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_review_decision',
            'title' => 'Decisión de revisión',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'decision' => $this->decision,
            'comment' => $this->comment,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
        ];
    }
}
