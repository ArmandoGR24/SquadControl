<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirebaseController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Guarda el token FCM del usuario autenticado
     */
    public function saveToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:512',
            'device_name' => 'nullable|string|max:255',
            'previous_token' => 'nullable|string|max:512',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $deviceUserAgent = $request->userAgent();
        $newToken = trim((string) $request->token);
        $previousToken = trim((string) ($request->input('previous_token') ?? ''));

        if ($newToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token FCM inválido',
            ], 422);
        }

        UserFcmToken::query()
            ->where('user_id', $user->id)
            ->where('device_user_agent', $deviceUserAgent)
            ->where('token', '!=', $newToken)
            ->delete();

        if ($previousToken !== '' && $previousToken !== $newToken) {
            UserFcmToken::query()
                ->where('user_id', $user->id)
                ->where('token', $previousToken)
                ->delete();
        }

        UserFcmToken::updateOrCreate(
            ['token' => $newToken],
            [
                'user_id' => $user->id,
                'device_name' => $request->device_name,
                'device_user_agent' => $deviceUserAgent,
                'last_used_at' => now(),
            ]
        );

        $removedExpiredTokens = UserFcmToken::query()
            ->where('user_id', $user->id)
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '<', now()->subDays(45))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token FCM guardado correctamente',
            'removed_expired_tokens' => $removedExpiredTokens,
        ]);
    }

    /**
     * Obtiene los tokens FCM del usuario autenticado
     */
    public function myTokens(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $tokens = $user->fcmTokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'token' => $tokens->first(),
            'tokens' => $tokens,
            'count' => $tokens->count(),
        ]);
    }

    /**
     * Envía una notificación de prueba al usuario autenticado
     */
    public function sendTestNotification(Request $request)
    {
        $user = Auth::user();
        $tokens = $user?->fcmTokens()
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray() ?? [];

        if (!$user || empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene token FCM registrado',
            ], 400);
        }

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            'Notificación de Prueba',
            'Esta es una notificación de prueba desde SquadControl',
            ['type' => 'test']
        );

        if (($result['success'] ?? false) === true) {
            $invalidTokens = collect($result['invalid_tokens'] ?? []);
            $unknownTokens = collect($result['unknown_tokens'] ?? []);
            $tokensToRemove = $invalidTokens->merge($unknownTokens)->filter()->unique()->values();

            if ($tokensToRemove->isNotEmpty() && $user) {
                $user->fcmTokens()
                    ->whereIn('token', $tokensToRemove->all())
                    ->delete();
            }

            if (($result['successful'] ?? 0) === 0 && ($result['failed'] ?? 0) > 0) {
                return response()->json([
                    ...$result,
                    'success' => false,
                    'message' => 'Firebase rechazó todos los tokens. Se eliminaron tokens inválidos/expirados cuando aplicó.',
                    'removed_tokens' => $tokensToRemove->count(),
                ], 422);
            }

            return response()->json([
                ...$result,
                'removed_tokens' => $tokensToRemove->count(),
            ]);
        }

        return response()->json($result);
    }

    /**
     * Envía notificación a todos los usuarios (solo para admin)
     */
    public function sendBroadcast(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'data' => 'nullable|array',
        ]);

        // Obtener todos los tokens válidos
        $tokens = UserFcmToken::query()
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay usuarios con tokens FCM registrados',
            ], 400);
        }

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }
}
