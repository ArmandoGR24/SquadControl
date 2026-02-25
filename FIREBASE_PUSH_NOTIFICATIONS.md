# Extensión de Notificaciones con Firebase Push Notifications

## Descripción

Este documento explica cómo extender las notificaciones de tareas para incluir push notifications usando Firebase Cloud Messaging (FCM).

## Requisitos

1. Proyecto Firebase configurado
2. `kreait/firebase-php` ya instalado en el proyecto
3. Servicio Firebase en `app/Services/FirebaseService.php`
4. Tabla `user_fcm_tokens` para almacenar tokens FCM de usuarios

## Paso 1: Crear el Canal de Notificación Firebase

Primero, crea un canal personalizado para Firebase:

```php
// app/Channels/FirebaseChannel.php

<?php

namespace App\Channels;

use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    public function __construct(private FirebaseService $firebase)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $fcmTokens = $notifiable->fcmTokens()->pluck('token')->toArray();

        if (empty($fcmTokens)) {
            return;
        }

        if (!method_exists($notification, 'toFirebase')) {
            return;
        }

        $message = $notification->toFirebase($notifiable);

        foreach ($fcmTokens as $token) {
            try {
                $this->firebase->sendMessage(
                    $token,
                    $message['title'] ?? '',
                    $message['body'] ?? '',
                    $message['data'] ?? []
                );
            } catch (\Exception $e) {
                // Log error
                \Log::error("FCM Error for token {$token}: " . $e->getMessage());
            }
        }
    }
}
```

## Paso 2: Actualizar la Relación del Modelo User

Asegúrate de que el modelo User tiene la relación con FCM tokens:

```php
// app/Models/User.php

public function fcmTokens(): HasMany
{
    return $this->hasMany(UserFcmToken::class);
}
```

## Paso 3: Actualizar las Clases de Notificación

Actualiza cada clase de notificación para incluir el canal Firebase y el método `toFirebase()`:

### Ejemplo: TaskAssigned.php

```php
<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?User $assignedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'firebase']; // Agregar firebase
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'title' => 'Nueva tarea asignada',
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'status' => $this->task->status,
            'assigned_by_id' => $this->assignedBy?->id,
            'assigned_by_name' => $this->assignedBy?->name,
            'message' => "Se te ha asignado la tarea: {$this->task->name}",
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => 'Nueva Tarea Asignada',
            'body' => "Se te ha asignado: {$this->task->name}",
            'data' => [
                'type' => 'task_assigned',
                'task_id' => (string) $this->task->id,
                'task_name' => $this->task->name,
            ],
        ];
    }
}
```

## Paso 4: Template para Todas las Notificaciones

Aquí hay una plantilla que puedes usar para todas las notificaciones:

```php
public function via(object $notifiable): array
{
    return ['database', 'firebase'];
}

public function toDatabase(object $notifiable): array
{
    // ... datos existentes
}

public function toFirebase(object $notifiable): array
{
    return [
        'title' => 'Título de la Notificación',
        'body' => 'Cuerpo del mensaje',
        'data' => [
            'type' => 'notification_type',
            'task_id' => (string) $this->task->id,
            // Otros datos relevantes
        ],
    ];
}
```

## Paso 5: Actualizar el Servicio de Notificaciones (Opcional)

Si prefieres centralizar toda la lógica, puedes extender `TaskNotificationService`:

```php
// En app/Services/TaskNotificationService.php

public function enablePushNotifications(bool $enabled = true): self
{
    $this->pushNotificationsEnabled = $enabled;
    return $this;
}

private function shouldSendPush(): bool
{
    return $this->pushNotificationsEnabled ?? config('notifications.push_enabled', true);
}
```

## Implementación Rápida: Script Actualizar Todas las Notificaciones

Si quieres actualizar rápidamente todas las clases de notificación, ejecuta este artisan command personalizado:

```php
// app/Console/Commands/UpdateNotificationsWithFirebase.php

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateNotificationsWithFirebase extends Command
{
    protected $signature = 'notifications:add-firebase';
    protected $description = 'Add Firebase support to all notification classes';

    public function handle(): int
    {
        $notificationsPath = app_path('Notifications');
        $files = File::allFiles($notificationsPath);

        foreach ($files as $file) {
            $content = $file->getContents();
            
            // Actualizar via() method
            $content = preg_replace(
                "/return \['database'\];/",
                "return ['database', 'firebase'];",
                $content
            );

            // Agregar método toFirebase si no existe
            if (!str_contains($content, 'toFirebase')) {
                $content .= "\n\n    public function toFirebase(object \$notifiable): array\n    {\n        return [\n            'title' => 'Actualización de Tarea',\n            'body' => 'Tienes una nueva notificación',\n            'data' => \$this->toDatabase(\$notifiable),\n        ];\n    }\n";
            }

            File::put($file->getRealPath(), $content);
            $this->info("Updated: {$file->getFilename()}");
        }

        return self::SUCCESS;
    }
}
```

## Registro del Canal

Registra el canal en `config/app.php` o en un service provider:

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    \Illuminate\Notifications\Notification::resolveChannelsUsing(function ($notifiable) {
        return [
            'firebase' => \App\Channels\FirebaseChannel::class,
        ];
    });
}
```

## Prueba de Push Notifications

Para probar las push notifications:

```php
// En una ruta de prueba o comando artisan

$user = User::first();
$task = Task::first();

// Crear un token FCM de prueba para el usuario
$user->fcmTokens()->create([
    'token' => 'tu_token_fcm_de_prueba',
]);

// Enviar notificación
$user->notify(new TaskAssigned($task, auth()->user()));
```

## Consideraciones de Producción

1. **Rate Limiting**: FCM tiene límites de velocidad. Implementa colas para notificaciones masivas.
2. **Tokens Expirados**: Maneja tokens FCM expirados y remuerve de la BD.
3. **Fallback**: Si FCM falla, las notificaciones en BD siguen funcionando.
4. **Configuración**: Usa variables de entorno para controlar qué notificaciones enviar.

## Variables de Entorno

Agrega a tu `.env`:

```env
FIREBASE_PUSH_NOTIFICATIONS_ENABLED=true
FCM_BATCH_SIZE=500
FCM_RETRY_ATTEMPTS=3
```

## Monitoreo de Errores

Vigila los errores de FCM:

```php
// En config/logging.php
'channels' => [
    'fcm' => [
        'driver' => 'single',
        'path' => storage_path('logs/fcm.log'),
        'level' => 'error',
    ],
]
```

Luego log en apps:

```php
\Log::channel('fcm')->error('FCM Error', [
    'token' => $token,
    'error' => $e->getMessage(),
]);
```

## Validación de Tokens FCM

Crea un middleware para validar tokens cuando el usuario se conecte:

```php
// app/Http/Middleware/ValidateFcmToken.php

public function handle(Request $request, Closure $next)
{
    $fcmToken = $request->header('X-FCM-Token');
    
    if ($fcmToken && $request->user()) {
        $request->user()->fcmTokens()->firstOrCreate([
            'token' => $fcmToken,
        ]);
    }

    return $next($request);
}
```

## Conclusión

Con esta configuración, tu aplicación SquadControl tendrá:

✅ Notificaciones en base de datos (ya implementado)
✅ Push notifications a dispositivos móviles
✅ Historial de notificaciones
✅ Fallback automático si FCM falla

Esto mantendrá a los usuarios completamente informados sobre cambios en tareas en tiempo real.
