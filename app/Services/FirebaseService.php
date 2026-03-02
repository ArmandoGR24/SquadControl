<?php

namespace App\Services;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging = null;

    public function __construct()
    {
    }

    private function initializeMessaging(): void
    {
        if ($this->messaging !== null) {
            return;
        }

        $serviceAccountPath = storage_path('app/firebase-service-account.json');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception('Firebase service account file not found at: ' . $serviceAccountPath);
        }

        $factory = (new Factory)->withServiceAccount($serviceAccountPath);

        $httpOptions = HttpClientOptions::default();
        $caBundlePath = $this->resolveCaBundlePath();

        if ($caBundlePath) {
            $httpOptions = $httpOptions->withGuzzleConfigOption(RequestOptions::VERIFY, $caBundlePath);
        } elseif ((bool) config('services.firebase.allow_insecure_ssl', false)) {
            $httpOptions = $httpOptions->withGuzzleConfigOption(RequestOptions::VERIFY, false);
            Log::warning('FirebaseService running with insecure SSL verification disabled (FIREBASE_ALLOW_INSECURE_SSL=true).');
        }

        $factory = $factory->withHttpClientOptions($httpOptions);
        $this->messaging = $factory->createMessaging();
    }

    private function resolveCaBundlePath(): ?string
    {
        $opensslLocations = function_exists('openssl_get_cert_locations')
            ? (openssl_get_cert_locations() ?: [])
            : [];

        $phpBinaryDir = dirname(PHP_BINARY);

        $candidates = [
            config('services.firebase.cacert_path'),
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            $opensslLocations['default_cert_file'] ?? null,
            $opensslLocations['ini_cafile'] ?? null,
            $phpBinaryDir.'\\extras\\ssl\\cacert.pem',
            $phpBinaryDir.'\\cacert.pem',
            base_path('cacert.pem'),
            storage_path('certs/cacert.pem'),
            storage_path('app/cacert.pem'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $path = trim($candidate, "\"' ");

            if (!str_contains($path, ':') && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = base_path($path);
            }

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Envía una notificación push a un token FCM específico
     *
     * @param string $token Token FCM del dispositivo
     * @param string $title Título de la notificación
     * @param string $body Cuerpo de la notificación
     * @param array $data Datos adicionales (opcional)
     * @return array Resultado del envío
     */
    public function sendNotification(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $this->initializeMessaging();

            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification($notification);

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            $this->messaging->send($message);

            return [
                'success' => true,
                'message' => 'Notification sent successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envía notificación a múltiples tokens
     *
     * @param array $tokens Array de tokens FCM
     * @param string $title Título de la notificación
     * @param string $body Cuerpo de la notificación
     * @param array $data Datos adicionales
     * @return array Resultados del envío
     */
    public function sendMulticast(array $tokens, string $title, string $body, array $data = []): array
    {
        try {
            $this->initializeMessaging();

            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification);

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            $report = $this->messaging->sendMulticast($message, $tokens);

            $failureReasons = [];
            foreach ($report->failures()->getItems() as $failure) {
                $token = method_exists($failure, 'target') ? (string) $failure->target()->value() : 'unknown';
                $error = method_exists($failure, 'error') ? $failure->error()->getMessage() : 'Unknown error';
                $failureReasons[] = [
                    'token' => $token,
                    'error' => $error,
                ];
            }

            $invalidTokens = array_values($report->invalidTokens());
            $unknownTokens = array_values($report->unknownTokens());

            return [
                'success' => true,
                'successful' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
                'invalid_tokens' => $invalidTokens,
                'unknown_tokens' => $unknownTokens,
                'failure_reasons' => $failureReasons,
                'message' => 'Multicast notification sent',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send multicast: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Valida si un token FCM es válido
     *
     * @param string $token Token FCM a validar
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->initializeMessaging();
            $this->messaging->validateRegistrationTokens([$token]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
