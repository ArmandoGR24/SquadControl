<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskMaterialsUpdated extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{label:string,in_stock:bool,holder_user_id:int|null,holder_name:string|null}>  $materials
     */
    public function __construct(
        private readonly Task $task,
        private readonly array $materials,
        private readonly ?User $updatedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $availableCount = collect($this->materials)
            ->filter(fn (array $material) => ($material['in_stock'] ?? false) || ! empty($material['holder_user_id']))
            ->count();

        return [
            'type' => 'task_materials_updated',
            'title' => 'Materiales actualizados',
            'url' => "/mis-tareas/{$this->task->id}",
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'materials' => $this->materials,
            'updated_by_id' => $this->updatedBy?->id,
            'updated_by_name' => $this->updatedBy?->name,
            'message' => "Se actualizó disponibilidad de {$availableCount} material(es) en '{$this->task->name}'.",
        ];
    }
}
