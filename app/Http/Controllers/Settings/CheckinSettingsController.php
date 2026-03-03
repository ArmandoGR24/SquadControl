<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CheckinSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckinSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = CheckinSetting::query()->firstOrCreate([], CheckinSetting::defaults());

        return Inertia::render('settings/Checkins', [
            'settings' => $settings->resolved(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'minimum_checkout_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'require_location' => ['required', 'boolean'],
            'allow_leader_bulk_actions' => ['required', 'boolean'],
            'allow_leader_include_self' => ['required', 'boolean'],
            'max_targets_per_action' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $settings = CheckinSetting::query()->firstOrCreate([], CheckinSetting::defaults());

        $settings->update([
            'minimum_checkout_hours' => (int) $validated['minimum_checkout_hours'],
            'require_location' => (bool) $validated['require_location'],
            'allow_leader_bulk_actions' => (bool) $validated['allow_leader_bulk_actions'],
            'allow_leader_include_self' => (bool) $validated['allow_leader_include_self'],
            'max_targets_per_action' => (int) $validated['max_targets_per_action'],
        ]);

        return back()->with('status', 'Configuración de check-ins actualizada.');
    }
}
