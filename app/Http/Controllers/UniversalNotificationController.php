<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class UniversalNotificationController extends Controller
{
    public function __construct(protected FirebaseService $firebaseService)
    {
    }

    /**
     * Envío universal de notificaciones.
     *
     * Variables requeridas:
     * - title: Título de la notificación
     * - msg: Mensaje/cuerpo de la notificación
     *
     * Variables opcionales:
     * - user_id: si se envía, notifica a todos los dispositivos de ese usuario
     * - data: array de datos extra para la notificación
     */
    public function send(Request $request)
    {
        $payload = $request->validate([
            'title' => 'required|string|max:255',
            'msg' => 'required|string|max:500',
            'user_id' => 'nullable|integer|exists:users,id',
            'data' => 'nullable|array',
        ]);

        $query = UserFcmToken::query();

        if (!empty($payload['user_id'])) {
            $query->where('user_id', $payload['user_id']);
        }

        $tokens = $query
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay tokens FCM registrados para el destino indicado',
            ], 400);
        }

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            $payload['title'],
            $payload['msg'],
            $payload['data'] ?? []
        );

        return response()->json([
            ...$result,
            'target_tokens' => count($tokens),
            'scope' => !empty($payload['user_id']) ? 'user' : 'all',
            'user_id' => $payload['user_id'] ?? null,
        ]);
    }

    /**
     * Envío de notificaciones por roles.
     *
     * Variables requeridas:
     * - title: Título de la notificación
     * - msg: Mensaje/cuerpo de la notificación
     * - roles: array de roles destino
     *
     * Variable opcional:
     * - data: array de datos extra para la notificación
     */
    public function sendByRoles(Request $request)
    {
        $payload = $request->validate([
            'title' => 'required|string|max:255',
            'msg' => 'required|string|max:500',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|max:50',
            'data' => 'nullable|array',
        ]);

        $roles = collect($payload['roles'])
            ->filter()
            ->map(static fn ($role) => trim((string) $role))
            ->unique()
            ->values()
            ->toArray();

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
            return response()->json([
                'success' => false,
                'message' => 'No hay tokens FCM para los roles indicados',
                'roles' => $roles,
            ], 400);
        }

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            $payload['title'],
            $payload['msg'],
            $payload['data'] ?? []
        );

        return response()->json([
            ...$result,
            'target_tokens' => count($tokens),
            'scope' => 'roles',
            'roles' => $roles,
        ]);
    }
}
