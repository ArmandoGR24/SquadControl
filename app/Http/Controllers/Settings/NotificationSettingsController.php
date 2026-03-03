<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = NotificationSetting::query()->firstOrCreate([], [
            'event_settings' => NotificationSetting::defaultEventSettings(),
            'recipient_settings' => NotificationSetting::defaultRecipientSettings(),
        ]);

        return Inertia::render('settings/Notifications', [
            'eventSettings' => $settings->resolvedEventSettings(),
            'recipientSettings' => $settings->resolvedRecipientSettings(),
            'eventLabels' => [
                'task_published' => 'Nueva tarea publicada',
                'task_assigned' => 'Tarea asignada',
                'task_status_changed' => 'Cambio de estado',
                'task_review_requested' => 'Tarea enviada a revisión',
                'task_review_decision' => 'Decisión de revisión',
                'task_feedback' => 'Feedback de revisión',
                'evidence_added' => 'Nueva evidencia agregada',
                'task_completed' => 'Tarea completada',
                'checkin_registered' => 'Check-in registrado',
                'checkout_registered' => 'Check-out registrado',
            ],
            'roleOptions' => NotificationSetting::ROLE_OPTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $eventRules = collect(NotificationSetting::EVENT_KEYS)
            ->mapWithKeys(fn (string $eventKey) => ["events.$eventKey" => ['required', 'boolean']])
            ->all();

        $recipientRules = collect(NotificationSetting::EVENT_KEYS)
            ->mapWithKeys(fn (string $eventKey) => [
                "recipients.$eventKey" => ['required', 'array'],
                "recipients.$eventKey.*" => ['string', 'in:'.implode(',', NotificationSetting::ROLE_OPTIONS)],
            ])
            ->all();

        $validated = $request->validate(array_merge([
            'events' => ['required', 'array'],
            'recipients' => ['required', 'array'],
        ], $eventRules, $recipientRules));

        $normalizedEvents = collect(NotificationSetting::EVENT_KEYS)
            ->mapWithKeys(fn (string $eventKey) => [
                $eventKey => (bool) data_get($validated, "events.$eventKey", true),
            ])
            ->all();

        $normalizedRecipients = collect(NotificationSetting::EVENT_KEYS)
            ->mapWithKeys(function (string $eventKey) use ($validated) {
                $roles = data_get($validated, "recipients.$eventKey", []);

                $normalized = collect(is_array($roles) ? $roles : [])
                    ->filter(fn (string $role) => in_array($role, NotificationSetting::ROLE_OPTIONS, true))
                    ->unique()
                    ->values()
                    ->all();

                return [$eventKey => $normalized];
            })
            ->all();

        $settings = NotificationSetting::query()->firstOrCreate([], [
            'event_settings' => NotificationSetting::defaultEventSettings(),
            'recipient_settings' => NotificationSetting::defaultRecipientSettings(),
        ]);

        $settings->update([
            'event_settings' => $normalizedEvents,
            'recipient_settings' => $normalizedRecipients,
        ]);

        return back();
    }
}
