<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Events\notificationsTask;

class CheckinController extends Controller
{
    private const MAX_CHECKIN_HOURS = 4;

    private function hasCheckedByColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('checkins', 'checked_by_user_id');
        }

        return $hasColumn;
    }

    public function index(Request $request)
    {
        $authUser = $request->user();
        $userId = $authUser?->id;
        $isLeader = $authUser?->role === 'Lider de Cuadrilla';

        // Obtener el último checkin del usuario autenticado (modo no líder)
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

        $targetUsers = [];
        if ($isLeader) {
            $managedUsers = User::query()
                ->whereIn('role', ['user', 'User', 'Empleado'])
                ->where('status', 'Activo')
                ->orderBy('name')
                ->get(['id', 'name']);

            $activeCheckinUserIds = Checkin::query()
                ->whereDate('check_in_time', today())
                ->whereNull('check_out_time')
                ->whereIn('user_id', $managedUsers->pluck('id'))
                ->pluck('user_id')
                ->all();

            $targetUsers = $managedUsers
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'has_active_checkin' => in_array($user->id, $activeCheckinUserIds, true),
                ])
                ->all();
        }

        return Inertia::render('users/Checkin', [
            'hasCheckedIn' => $hasCheckedIn,
            'lastCheckin' => $lastCheckin,
            'isLeaderMode' => $isLeader,
            'targetUsers' => $targetUsers,
        ]);
    }

    public function checkIn(Request $request)
{
    $validated = $request->validate([
        'latitude' => ['nullable', 'numeric'],
        'longitude' => ['nullable', 'numeric'],
        'target_user_ids' => ['nullable', 'array'],
        'target_user_ids.*' => ['integer', 'exists:users,id'],
        'include_self' => ['nullable', 'boolean'],
    ]);

    $user = $request->user();
    $userId = $user?->id;

    $targetUserIds = [];

    if ($user?->role === 'Lider de Cuadrilla') {
        $allowedUserIds = User::query()
            ->whereIn('role', ['user', 'User', 'Empleado'])
            ->where('status', 'Activo')
            ->pluck('id')
            ->all();

        $selectedUserIds = collect($validated['target_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $allowedUserIds, true))
            ->unique()
            ->values()
            ->all();

        if (($validated['include_self'] ?? false) === true && $userId) {
            $selectedUserIds[] = $userId;
        }

        $targetUserIds = collect($selectedUserIds)->unique()->values()->all();

        if (empty($targetUserIds)) {
            return redirect()->back()->withErrors([
                'checkin' => 'Selecciona al menos un usuario para registrar entrada.',
            ]);
        }
    } else {
        $targetUserIds = [$userId];
    }

    $alreadyCheckedInIds = Checkin::query()
        ->whereIn('user_id', $targetUserIds)
        ->whereDate('check_in_time', today())
        ->whereNull('check_out_time')
        ->pluck('user_id')
        ->all();

    $creatableUserIds = collect($targetUserIds)
        ->reject(fn ($id) => in_array($id, $alreadyCheckedInIds, true))
        ->values()
        ->all();

    if (empty($creatableUserIds)) {
        return redirect()->back()->withErrors([
            'checkin' => 'Todos los usuarios seleccionados ya tienen entrada activa.',
        ]);
    }

    foreach ($creatableUserIds as $targetUserId) {
        $payload = [
            'user_id' => $targetUserId,
            'check_in_time' => now(),
            'check_in_latitude' => $validated['latitude'] ?? null,
            'check_in_longitude' => $validated['longitude'] ?? null,
        ];

        if ($this->hasCheckedByColumn()) {
            $payload['checked_by_user_id'] = $userId;
        }

        Checkin::create($payload);
    }

    // 2. Disparar la notificación en tiempo real
    $mensaje = $user?->role === 'Lider de Cuadrilla'
        ? "{$user->name} registró check-in para ".count($creatableUserIds)." usuario(s)."
        : "El usuario {$user->name} ha hecho check-in.";
    broadcast(new notificationsTask($mensaje))->toOthers();

    if ($user?->role === 'Lider de Cuadrilla') {
        $this->notifyRoles(
            'Check-in de Lider de Cuadrilla',
            $mensaje,
            ['Admin', 'RH', 'Supervisor'],
            [
                'type' => 'checkin',
                'user_id' => (string) $user->id,
            ]
        );
    }

    return redirect()->back();
}

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'target_user_ids' => ['nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'include_self' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $userId = $user?->id;

        $targetUserIds = [];

        if ($user?->role === 'Lider de Cuadrilla') {
            $allowedUserIds = User::query()
                ->whereIn('role', ['user', 'User', 'Empleado'])
                ->where('status', 'Activo')
                ->pluck('id')
                ->all();

            $selectedUserIds = collect($validated['target_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $allowedUserIds, true))
                ->unique()
                ->values()
                ->all();

            if (($validated['include_self'] ?? false) === true && $userId) {
                $selectedUserIds[] = $userId;
            }

            $targetUserIds = collect($selectedUserIds)->unique()->values()->all();

            if (empty($targetUserIds)) {
                return redirect()->back()->withErrors([
                    'checkout' => 'Selecciona al menos un usuario para registrar salida.',
                ]);
            }
        } else {
            $targetUserIds = [$userId];
        }

        // Buscar checkins activos de hoy para los usuarios seleccionados
        $activeCheckins = Checkin::query()
            ->whereIn('user_id', $targetUserIds)
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time')
            ->get();

        if ($activeCheckins->isEmpty()) {
            return redirect()->back()->withErrors([
                'checkout' => 'No hay entradas activas para los usuarios seleccionados.',
            ]);
        }

        $notReadyCheckins = $activeCheckins->filter(function (Checkin $checkin) {
            $minimumCheckoutTime = $checkin->check_in_time->copy()->addHours(self::MAX_CHECKIN_HOURS);
            return now()->lt($minimumCheckoutTime);
        });

        if ($notReadyCheckins->isNotEmpty()) {
            return redirect()->back()->withErrors([
                'checkout' => 'No se puede registrar salida antes de 4 horas desde la entrada.',
            ]);
        }

        foreach ($activeCheckins as $checkin) {
            $payload = [
                'check_out_time' => now(),
                'check_out_latitude' => $validated['latitude'] ?? null,
                'check_out_longitude' => $validated['longitude'] ?? null,
            ];

            if ($this->hasCheckedByColumn()) {
                $payload['checked_by_user_id'] = $userId;
            }

            $checkin->update($payload);
        }

            // Disparar la notificación en tiempo real
            $mensaje = $user?->role === 'Lider de Cuadrilla'
                ? "{$user->name} registró check-out para ".$activeCheckins->count()." usuario(s)."
                : "El usuario {$user->name} ha hecho check-out.";
            broadcast(new notificationsTask($mensaje))->toOthers();

            if ($user?->role === 'Lider de Cuadrilla') {
                $this->notifyRoles(
                    'Check-out de Lider de Cuadrilla',
                    $mensaje,
                    ['Admin', 'RH', 'Supervisor'],
                    [
                        'type' => 'checkout',
                        'user_id' => (string) $user->id,
                    ]
                );
            }

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
        $with = ['user:id,name'];

        if ($this->hasCheckedByColumn()) {
            $with[] = 'checkedBy:id,name';
        }

        $query = Checkin::with($with)
            ->orderByDesc('check_in_time');

        // Filtrar por usuario si se especifica
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtrar por fecha si se especifica
        if ($request->filled('date')) {
            $query->whereDate('check_in_time', $request->date);
        }

        // Filtrar por usuario que registró (líder o auto-registro)
        if ($this->hasCheckedByColumn() && $request->filled('checked_by_user_id')) {
            $query->where('checked_by_user_id', $request->checked_by_user_id);
        }

        $checkins = $query->get()
            ->map(function (Checkin $checkin) {
                return [
                    'id' => $checkin->id,
                    'usuario' => $checkin->user?->name,
                    'registrado_por' => $checkin->checkedBy?->name,
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
            ->whereIn('role', ['Lider de Cuadrilla', 'Empleado', 'user', 'User'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nombre' => $user->name,
            ])
            ->all();

        $registradores = User::select(['id', 'name'])
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
            'registradores' => $registradores,
        ]);
    }

    private function notifyRoles(string $title, string $message, array $roles, array $data = []): void
    {
        $tokens = UserFcmToken::query()
            ->whereHas('user', function ($query) use ($roles) {
                $query->whereIn('role', $roles);
            })
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            \Log::warning('FCM roles notification skipped: no tokens found.', [
                'roles' => $roles,
                'title' => $title,
            ]);
            return;
        }

        $normalizedData = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalizedData[$key] = (string) $value;
                continue;
            }

            $normalizedData[$key] = json_encode($value);
        }

        $result = app(FirebaseService::class)->sendMulticast(
            $tokens,
            $title,
            $message,
            $normalizedData
        );

        if (!($result['success'] ?? false)) {
            \Log::error('FCM roles notification failed.', [
                'roles' => $roles,
                'title' => $title,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
