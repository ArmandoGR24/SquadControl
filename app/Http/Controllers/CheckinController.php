<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Events\notificationsTask;

class CheckinController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()?->id;

        // Obtener el último checkin del usuario
        $todayCheckin = Checkin::where('user_id', $userId)
            ->whereDate('check_in_time', today())
            ->latest('check_in_time')
            ->first();

        $hasCheckedIn = $todayCheckin && !$todayCheckin->check_out_time;
        $lastCheckin = $todayCheckin ? [
            'id' => $todayCheckin->id,
            'check_in_time' => $todayCheckin->check_in_time->toIso8601String(),
            'check_out_time' => $todayCheckin->check_out_time?->toIso8601String(),
        ] : null;

        return Inertia::render('users/Checkin', [
            'hasCheckedIn' => $hasCheckedIn,
            'lastCheckin' => $lastCheckin,
        ]);
    }

    public function checkIn(Request $request)
{
    $validated = $request->validate([
        'latitude' => ['nullable', 'numeric'],
        'longitude' => ['nullable', 'numeric'],
    ]);

    $user = $request->user();
    $userId = $user?->id;

    // Verificar si ya hay un checkin activo hoy
    $existingCheckin = Checkin::where('user_id', $userId)
        ->whereDate('check_in_time', today())
        ->whereNull('check_out_time')
        ->first();

    if ($existingCheckin) {
        return redirect()->back()->withErrors([
            'checkin' => 'Ya has registrado una entrada hoy.',
        ]);
    }

    // 1. Crear el registro
    Checkin::create([
        'user_id' => $userId,
        'check_in_time' => now(),
        'check_in_latitude' => $validated['latitude'] ?? null,
        'check_in_longitude' => $validated['longitude'] ?? null,
    ]);

    // 2. Disparar la notificación en tiempo real
    $mensaje = "El usuario {$user->name} ha hecho check-in.";
    broadcast(new notificationsTask($mensaje))->toOthers();

    return redirect()->back();
}

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $userId = $request->user()?->id;
        $user = $request->user();

        // Buscar el checkin activo de hoy
        $checkin = Checkin::where('user_id', $userId)
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time')
            ->latest('check_in_time')
            ->first();

        if (!$checkin) {
            return redirect()->back()->withErrors([
                'checkout' => 'No hay un registro de entrada activo.',
            ]);
        }

        $checkin->update([
            'check_out_time' => now(),
            'check_out_latitude' => $validated['latitude'] ?? null,
            'check_out_longitude' => $validated['longitude'] ?? null,
        ]);

            // Disparar la notificación en tiempo real
            $mensaje = "El usuario {$user->name} ha hecho check-out.";
            broadcast(new notificationsTask($mensaje))->toOthers();

        return redirect()->back();
    }

    public function history(Request $request)
    {
        $userId = $request->user()?->id;

        $checkins = Checkin::where('user_id', $userId)
            ->orderByDesc('check_in_time')
            ->get()
            ->map(function (Checkin $checkin) {
                return [
                    'id' => $checkin->id,
                    'check_in_time' => $checkin->check_in_time->toIso8601String(),
                    'check_in_latitude' => $checkin->check_in_latitude,
                    'check_in_longitude' => $checkin->check_in_longitude,
                    'check_out_time' => $checkin->check_out_time?->toIso8601String(),
                    'check_out_latitude' => $checkin->check_out_latitude,
                    'check_out_longitude' => $checkin->check_out_longitude,
                    'duracion' => $checkin->check_out_time
                        ? $checkin->check_in_time->diff($checkin->check_out_time)->format('%H:%I:%S')
                        : null,
                ];
            })
            ->all();

        return Inertia::render('users/HistorialCheckin', [
            'checkins' => $checkins,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Checkin::with(['user:id,name'])
            ->orderByDesc('check_in_time');

        // Filtrar por usuario si se especifica
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtrar por fecha si se especifica
        if ($request->filled('date')) {
            $query->whereDate('check_in_time', $request->date);
        }

        $checkins = $query->get()
            ->map(function (Checkin $checkin) {
                return [
                    'id' => $checkin->id,
                    'usuario' => $checkin->user?->name,
                    'user_id' => $checkin->user_id,
                    'check_in_time' => $checkin->check_in_time->toIso8601String(),
                    'check_in_latitude' => $checkin->check_in_latitude,
                    'check_in_longitude' => $checkin->check_in_longitude,
                    'check_out_time' => $checkin->check_out_time?->toIso8601String(),
                    'check_out_latitude' => $checkin->check_out_latitude,
                    'check_out_longitude' => $checkin->check_out_longitude,
                    'duracion' => $checkin->check_out_time
                        ? $checkin->check_in_time->diff($checkin->check_out_time)->format('%H:%I:%S')
                        : null,
                ];
            })
            ->all();

        // Obtener lista de usuarios para filtro
        $usuarios = User::select(['id', 'name'])
            ->whereIn('role', ['Lider de Cuadrilla', 'Empleado'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nombre' => $user->name,
            ])
            ->all();

        return Inertia::render('CheckinAdmin', [
            'checkins' => $checkins,
            'usuarios' => $usuarios,
        ]);
    }
}
