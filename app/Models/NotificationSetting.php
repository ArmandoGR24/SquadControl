<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    public const EVENT_KEYS = [
        'task_published',
        'task_assigned',
        'task_status_changed',
        'task_review_requested',
        'task_review_decision',
        'task_feedback',
        'evidence_added',
        'task_completed',
        'checkin_registered',
        'checkout_registered',
    ];

    public const ROLE_OPTIONS = [
        'Lider de Cuadrilla',
        'Supervisor',
        'Admin',
        'RH',
    ];

    protected $fillable = [
        'event_settings',
        'recipient_settings',
    ];

    protected function casts(): array
    {
        return [
            'event_settings' => 'array',
            'recipient_settings' => 'array',
        ];
    }

    public static function defaultEventSettings(): array
    {
        return [
            'task_published' => true,
            'task_assigned' => true,
            'task_status_changed' => true,
            'task_review_requested' => true,
            'task_review_decision' => false,
            'task_feedback' => true,
            'evidence_added' => true,
            'task_completed' => true,
            'checkin_registered' => true,
            'checkout_registered' => true,
        ];
    }

    public static function defaultRecipientSettings(): array
    {
        return [
            'task_published' => ['Lider de Cuadrilla'],
            'task_assigned' => ['Lider de Cuadrilla'],
            'task_status_changed' => ['Lider de Cuadrilla'],
            'task_review_requested' => ['Supervisor', 'Admin'],
            'task_review_decision' => ['Lider de Cuadrilla'],
            'task_feedback' => ['Lider de Cuadrilla'],
            'evidence_added' => ['Lider de Cuadrilla', 'Supervisor', 'Admin'],
            'task_completed' => ['Lider de Cuadrilla', 'Supervisor', 'Admin'],
            'checkin_registered' => ['Admin', 'RH', 'Supervisor'],
            'checkout_registered' => ['Admin', 'RH', 'Supervisor'],
        ];
    }

    public function resolvedEventSettings(): array
    {
        $defaults = self::defaultEventSettings();
        $current = is_array($this->event_settings) ? $this->event_settings : [];

        return collect($defaults)
            ->mapWithKeys(fn (bool $enabled, string $eventKey) => [
                $eventKey => (bool) ($current[$eventKey] ?? $enabled),
            ])
            ->all();
    }

    public function resolvedRecipientSettings(): array
    {
        $defaults = self::defaultRecipientSettings();
        $current = is_array($this->recipient_settings) ? $this->recipient_settings : [];

        return collect($defaults)
            ->mapWithKeys(function (array $roles, string $eventKey) use ($current) {
                $configured = $current[$eventKey] ?? $roles;

                if (! is_array($configured)) {
                    return [$eventKey => $roles];
                }

                $sanitized = collect($configured)
                    ->filter(fn ($role) => in_array($role, self::ROLE_OPTIONS, true))
                    ->unique()
                    ->values()
                    ->all();

                return [$eventKey => $sanitized];
            })
            ->all();
    }
}
