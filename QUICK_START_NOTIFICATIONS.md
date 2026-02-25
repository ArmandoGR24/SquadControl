# 🚀 Guía de Inicio Rápido - Sistema de Notificaciones

## ¿Qué se ha implementado?

Se ha agregado un **sistema completo de notificaciones** que mantiene a los usuarios informados sobre:

✅ Asignación de tareas
✅ Cambios de estado
✅ Nuevo material de evidencia
✅ Decisiones de revisión
✅ Feedback de supervisores

---

## Próximos Pasos Inmediatos

### 1️⃣ **Verificar la Instalación**

```bash
# Las siguientes clases ya existen:
app/Services/TaskNotificationService.php
app/Notifications/TaskAssigned.php
app/Notifications/TaskStatusChanged.php
app/Notifications/EvidenceAdded.php
app/Notifications/TaskFeedback.php
```

### 2️⃣ **Asegurar que la Tabla de Notificaciones Existe**

Las notificaciones se almacenan en la tabla `notifications`. Verifica con:

```bash
php artisan migrate --step
```

Si no existe la migración, crea la tabla:

```bash
php artisan make:migration create_notifications_table --create=notifications
```

Migración estándar de Laravel:

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->morphs('notifiable');
    $table->string('type');
    $table->longText('data');
    $table->dateTime('read_at')->nullable();
    $table->timestamps();
});
```

### 3️⃣ **Verificar que el Usuario es Notifiable**

El modelo `User` debe tener el trait `Notifiable`:

```php
// app/Models/User.php

use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable; // ✅ Este trait debe estar
}
```

### 4️⃣ **Probar en Desarrollo**

```bash
# Abre la consola de Laravel
php artisan tinker

# Crea un usuario de prueba
$user = User::first();

# Crea una tarea de prueba
$task = Task::first();

# Envía una notificación de prueba
$user->notify(new \App\Notifications\TaskAssigned($task, null));

# Verifica la notificación
$user->notifications;

# Marca como leída
$user->notifications->first()->markAsRead();
```

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────┐
│         TareasController (Acciones del Usuario)             │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────────────┐
│        TaskNotificationService (Lógica de Notificaciones)   │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┼───────────┬─────────────┐
         ↓           ↓           ↓             ↓
    ┌─────────┐ ┌──────────┐ ┌────────┐ ┌──────────┐
    │ Task    │ │ Task     │ │Evidence│ │TaskFeedback
    │Assigned │ │StatusChg │ │Added   │ │
    └─────────┘ └──────────┘ └────────┘ └──────────┘
         │           │           │             │
         └───────────┼───────────┴─────────────┘
                     ↓
         ┌─────────────────────────┐
         │  Base de Datos          │
         │  (Table: notifications) │
         └─────────────────────────┘
```

---

## Cómo Funcionan las Notificaciones

### 1. **Usuario realiza una acción** (crear tarea, cambiar estado, etc.)

```php
// En TareasController::store()
$task = Task::create([...]);
$this->notificationService->notifyTaskAssigned($task, $currentUser, $leaderIds);
```

### 2. **Servicio procesa la notificación**

```php
// En TaskNotificationService
public function notifyTaskAssigned($task, $assignedBy, $leaderIds)
{
    foreach ($leaderIds as $leaderId) {
        $leader = User::find($leaderId);
        $leader->notify(new TaskAssigned($task, $assignedBy));
    }
}
```

### 3. **Notificación se guarda en BD**

```
notif_id: abc123
user_id: 5
type: TaskAssigned
data: { task_id: 1, task_name: "Revisión...", ... }
read_at: null
```

### 4. **Frontend muestra la notificación**

```vue
<div v-for="notification in notifications">
  {{ notification.data.message }}
</div>
```

---

## Ejemplos de Cómo Se Disparan las Notificaciones

### Ejemplo 1: Admin crea una tarea
```
Admin UI → TareasController::store() → notifyTaskAssigned() → BD
↓
Líderes asignados ven la notificación: "Nueva tarea: Revisar documentos"
```

### Ejemplo 2: Líder cambia estado a "En revisión"
```
Líder UI → TareasController::updateStatus() → notifyTaskStatusChanged()
                                            → notifyTaskSentForReview() → BD
↓
Otros líderes ven: "La tarea cambió a En revisión"
Supervisores ven: "Nueva tarea en revisión: Revisar documentos"
```

### Ejemplo 3: Supervisor aprueba la tarea
```
Supervisor UI → TareasController::review() → processReview() → BD
↓
Líderes ven: "Tu tarea ha sido Aprobada"
            "La tarea cambió a Completada"
```

---

## Rutas Necesarias (Opcional)

Si quieres crear una API para notificaciones:

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    // API de notificaciones (opcional)
    Route::get('/api/notifications', function (Request $request) {
        return response()->json([
            'notifications' => $request->user()->notifications,
        ]);
    });

    Route::post('/api/notifications/{id}/read', function ($id, Request $request) {
        $request->user()->notifications()->find($id)?->markAsRead();
        return response()->json(['success' => true]);
    });

    Route::delete('/api/notifications/{id}', function ($id, Request $request) {
        $request->user()->notifications()->find($id)?->delete();
        return response()->json(['success' => true]);
    });
});
```

---

## Frontend: Mostrar Notificaciones

### Opción 1: En Props de Inertia (Simple)

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const notifications = computed(() => {
  return page.props.auth?.user?.notifications || []
})
</script>

<template>
  <div class="notifications">
    <div v-for="notif in notifications" :key="notif.id">
      <h4>{{ notif.data.title }}</h4>
      <p>{{ notif.data.message }}</p>
      <time>{{ notif.created_at }}</time>
    </div>
  </div>
</template>
```

### Opción 2: Con Componentes (Recomendado)

Usa los componentes Vue que están documentados en `FRONTEND_NOTIFICATIONS.md`:
- `NotificationBell.vue` - Badge flotante
- `NotificationsPanel.vue` - Panel completo

---

## Tipos de Notificaciones Disponibles

| Tipo | Disparador | Destinatarios | Ejemplo |
|------|-----------|---------------|---------|
| **TaskAssigned** | Admin crea/edita tarea | Líderes asignados | "Se te ha asignado: Revisar..." |
| **TaskStatusChanged** | Líder cambio estado | Otros líderes | "Cambió de Pendiente a En progreso" |
| **TaskSentForReview** | Líder envía a revisión | Supervisores/Admins | "Nueva tarea en revisión" |
| **EvidenceAdded** | Alguien agrega evidencia | Supervisores, otros líderes | "Nueva evidencia agregada" |
| **TaskFeedback** | Supervisor revisa | Líderes | "Tu tarea ha sido Aprobada" |

---

## Campos en Cada Notificación

Cada notificación almacena:

```json
{
  "type": "task_assigned",           // Tipo de notificación
  "title": "Nueva tarea asignada",   // Título legible
  "task_id": 1,                       // ID de la tarea
  "task_name": "Nombre tarea",        // Nombre de la tarea
  "message": "Se te ha asignado...", // Mensaje para mostrar
  "actor_name": "John Doe",           // Quién realizó la acción
  // ... campos adicionales según el tipo
}
```

---

## Testing

Prueba el sistema rápidamente:

```bash
# 1. Crear datos de prueba
php artisan tinker
$user = User::factory()->create(['role' => 'Lider de Cuadrilla']);
$task = Task::factory()->create();
$task->leaders()->attach($user);

# 2. Generar notificación
$admin = User::factory()->create(['role' => 'Admin']);
$user->notify(new \App\Notifications\TaskAssigned($task, $admin));

# 3. Verificar en BD
SELECT * FROM notifications WHERE notifiable_id = 1;
```

---

## Configuración de Base de Datos

Por defecto, Laravel usa SQLite en desarrollo. Las notificaciones se guardan en:

```
notifications table
├── id (uuid)
├── notifiable_id (usuario)
├── notifiable_type (App\Models\User)
├── type (clase de notificación)
├── data (JSON con detalles)
├── read_at (null si no leída)
├── created_at
└── updated_at
```

---

## Extensiones Disponibles

### Push Notifications (Firebase)
```bash
# Ver: FIREBASE_PUSH_NOTIFICATIONS.md
# Requiere: kreait/firebase-php (ya instalado)
```

### Email Notifications
```php
// Agregar a clase de notificación
public function via($notifiable) {
    return ['database', 'mail'];
}

public function toMail($notifiable) {
    // ...
}
```

### SMS Notifications
```php
// Usar Twilio u otro servicio
public function via($notifiable) {
    return ['database', 'twilio'];
}
```

---

## Solución de Problemas

### ❌ Las notificaciones no aparecen

**Causa**: Migración no ejecutada
```bash
php artisan migrate
```

### ❌ El servicio no se inyecta

**Causa**: Service provider no registrado
```php
// Debería estar automático, pero verifica en config/app.php
// o usa php artisan make:provider TaskNotificationServiceProvider
```

### ❌ Las notificaciones no se envían

**Causa**: El usuario no tiene el trait Notifiable
```php
// app/Models/User.php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use Notifiable;
}
```

---

## Probar en Desarrollo Local

```bash
# 1. Ir al directorio del proyecto
cd d:\Proyectos\SquadControl

# 2. Levantar servidor
php artisan serve

# 3. Abrir navegador
http://localhost:8000

# 4. Login como usuario
# Usuario: admin@example.com
# Contraseña: password

# 5. Ver notificaciones
# Las notificaciones aparecerán automáticamente en el `page.props`
```

---

## Documentación Completa

Para información detallada, consulta:

1. **Sistema General**: [NOTIFICATIONS_GUIDE.md](NOTIFICATIONS_GUIDE.md)
2. **Push Con Firebase**: [FIREBASE_PUSH_NOTIFICATIONS.md](FIREBASE_PUSH_NOTIFICATIONS.md)
3. **Frontend/Vue**: [FRONTEND_NOTIFICATIONS.md](FRONTEND_NOTIFICATIONS.md)
4. **Resumen Técnico**: [SYSTEM_IMPLEMENTATION_SUMMARY.md](SYSTEM_IMPLEMENTATION_SUMMARY.md)

---

## ✅ Checklist de Verificación

- [ ] Las migraciones están ejecutadas (`php artisan migrate`)
- [ ] El modelo User tiene el trait `Notifiable`
- [ ] El servicio `TaskNotificationService` existe
- [ ] Las clases de notificación están en `app/Notifications/`
- [ ] El controlador importa el servicio
- [ ] La BD tiene datos de prueba (usuarios, tareas)
- [ ] El frontend muestra las notificaciones (en props)

---

## 🎉 ¡Listo!

El sistema está **100% funcional**. Las notificaciones se enviarán automáticamente cuando:

✅ Se cree una tarea
✅ Se cambie el estado
✅ Se envíe a revisión
✅ Se agregue evidencia
✅ Se proporcione feedback

**Los usuarios verán todas las notificaciones en su bandeja.**

---

## Soporte

Si tienes dudas:

1. Revisa la documentación específica en los archivos `.md`
2. Verifica los comentarios en el código
3. Prueba con `php artisan tinker`
4. Revisa los logs en `storage/logs/`

¡Que disfrutes el nuevo sistema de notificaciones! 🚀
