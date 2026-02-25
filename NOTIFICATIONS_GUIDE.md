# Sistema de Notificaciones de Tareas

## Descripción General

Se ha implementado un sistema completo de notificaciones para mantener a los usuarios informados sobre cambios en el estado de las tareas y feedback de revisiones. El sistema utiliza las notificaciones de Laravel almacenadas en la base de datos.

## Notificaciones Implementadas

### 1. **TaskAssigned** - Tarea Asignada
**Cuándo se dispara:**
- Cuando un administrador o supervisor crea una nueva tarea asignándola a líderes
- Cuando se edita una tarea y se agregan nuevos líderes

**Información incluida:**
- Nombre de la tarea
- Estado inicial
- Quién asignó la tarea

**Destinatarios:** Los líderes asignados a la tarea

---

### 2. **TaskStatusChanged** - Estado de Tarea Actualizado
**Cuándo se dispara:**
- Cuando cambia el estado de una tarea (excepto cuando se envía a revisión)
- En los métodos `update()` y `updateStatus()`

**Estados disponibles:**
- Pendiente
- En progreso
- En revisión
- Completada

**Información incluida:**
- Nombre de la tarea
- Estado anterior y nuevo
- Comentario del cambio (si lo hay)
- Quién realizó el cambio

**Destinatarios:** 
- Todos los líderes asignados a la tarea
- Supervisores y admins (cuando se notifica desde updateStatus)

---

### 3. **TaskSentForReview** - Tarea Enviada para Revisión
**Cuándo se dispara:**
- Cuando un líder cambia el estado de la tarea a "En revisión"

**Información incluida:**
- Nombre de la tarea
- Estado (En revisión)
- Quién envió para revisión

**Destinatarios:** Supervisores y Administradores

---

### 4. **TaskReviewDecision** - Decisión de Revisión
**Cuándo se dispara:**
- Cuando un supervisor/admin aprueba o rechaza una tarea en revisión

**Decisiones posibles:**
- Aceptada (tarea completada)
- Rechazada (devuelta a "En progreso")

**Información incluida:**
- Nombre de la tarea
- Decisión (Aceptada/Rechazada)
- Comentario de la revisión
- Quién realizó la revisión

**Destinatarios:** Todos los líderes asignados a la tarea

---

### 5. **TaskFeedback** - Feedback de Revisión (Nueva)
**Cuándo se dispara:**
- Cuando un supervisor/admin proporciona feedback en la revisión

**Estados de feedback:**
- `approved` - Aprobada
- `rejected` - Rechazada
- `changes_requested` - Cambios solicitados

**Información incluida:**
- Nombre de la tarea
- Tipo de feedback
- Comentario detallado
- Quién proporcionó el feedback

**Destinatarios:** Todos los líderes asignados a la tarea

---

### 6. **EvidenceAdded** - Nueva Evidencia Agregada
**Cuándo se dispara:**
- Cuando cualquier usuario agrega evidencia a una tarea

**Información incluida:**
- Nombre de la tarea
- Comentario de la evidencia
- Quién agregó la evidencia

**Destinatarios:**
- Supervisores y Administradores
- Otros líderes asignados a la tarea (no el que subió)

---

## Flujo de Notificaciones por Proceso

### Creación de Tarea
```
1. Admin/Supervisor crea tarea → TaskAssigned (a líderes)
```

### Progreso de Tarea
```
1. Líder actualiza estado → TaskStatusChanged (a otros líderes)
2. Se agrega evidencia → EvidenceAdded (a supervisores y otros líderes)
3. Líder envía a revisión → TaskSentForReview (a supervisores/admins)
```

### Revisión y Feedback
```
1. Supervisor revisa → TaskFeedback (a líderes)
2. Supervisor revisa → TaskReviewDecision (a líderes) [para compatibilidad]
3. Aplica cambios → TaskStatusChanged (a otros líderes)
```

## Estructura de Datos en Notificaciones

Todas las notificaciones se almacenan en la tabla `notifications` con los siguientes campos clave:

```php
[
    'type' => 'tipo_de_notificacion', // task_assigned, task_status_changed, etc.
    'title' => 'Título legible',
    'task_id' => ID_DE_LA_TAREA,
    'task_name' => 'Nombre de la Tarea',
    'message' => 'Mensaje descriptivo para el usuario',
    // ... campos específicos según el tipo
]
```

## Cómo Consumir las Notificaciones en el Frontend

Las notificaciones están disponibles en:

```javascript
// Obtener todas las notificaciones del usuario autenticado
GET /api/notifications

// Marcar una notificación como leída
POST /api/notifications/{id}/read

// Marcar todas como leídas
POST /api/notifications/read-all

// Eliminar una notificación
DELETE /api/notifications/{id}
```

Ejemplo de uso en Vue/Inertia:

```vue
<script setup>
import { usePage } from '@inertiajs/vue3'

const notifications = computed(() => {
  return usePage().props.auth.user.notifications
})

const getNotificationColor = (type) => {
  const colors = {
    'task_assigned': 'blue',
    'task_status_changed': 'purple',
    'task_review_requested': 'orange',
    'task_feedback': 'red',
    'evidence_added': 'green'
  }
  return colors[type] || 'gray'
}
</script>

<template>
  <div class="notifications">
    <div 
      v-for="notification in notifications" 
      :key="notification.id"
      :class="`bg-${getNotificationColor(notification.data.type)}`"
    >
      <h4>{{ notification.data.title }}</h4>
      <p>{{ notification.data.message }}</p>
      <p v-if="notification.data.comment" class="comment">
        {{ notification.data.comment }}
      </p>
      <time>{{ notification.created_at }}</time>
    </div>
  </div>
</template>
```

## Configuración de Push Notifications (Opcional)

Para agregar soporte de push notifications con Firebase, actualiza las clases de notificación para incluir el canal `firebase`:

```php
public function via(object $notifiable): array
{
    return ['database', 'firebase']; // Agregar firebase
}

public function toFirebase(object $notifiable): array
{
    return [
        'title' => $this->task->name,
        'body' => $this->getMessage(),
        'data' => $this->toDatabase($notifiable)
    ];
}
```

## Mejoras Futuras

1. **Push Notifications**: Integrar notificaciones push con FCM (Firebase Cloud Messaging)
2. **Email Notifications**: Agregar notificaciones por correo para eventos críticos
3. **Preferencias de Notificación**: Permitir a usuarios elegir qué notificaciones reciben
4. **Agregación**: Agrupar notificaciones similares en un período de tiempo
5. **Leer/No leer**: Sistema de marca como leído/no leído
6. **Filtrado**: Permitir filtrar notificaciones por tipo, tarea o usuario

## Modelos de Datos Relacionados

- **Task**: Modelo de tarea con relación a líderes
- **User**: Modelo de usuario con trait `Notifiable`
- **TaskStatusHistory**: Registro histórico de cambios de estado
- **TaskEvidence**: Evidencia adjunta a tareas
- **notifications** (tabla): Almacena todas las notificaciones de usuarios
