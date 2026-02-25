# Consumo de Notificaciones en el Frontend (Vue/Inertia)

## Descripción

Esta guía muestra cómo acceder y mostrar las notificaciones de tareas en el frontend usando Vue 3 con Inertia.js.

## 1. Obtener las Notificaciones del Usuario

Las notificaciones están disponibles en las props del componente a través de `usePage()`:

```vue
<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const notifications = computed(() => {
  return page.props.auth?.user?.notifications || []
})

const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.read_at).length
})
</script>

<template>
  <div>
    <p>Tienes {{ unreadCount }} notificaciones no leídas</p>
  </div>
</template>
```

## 2. Componente de Panel de Notificaciones

Crea un componente para mostrar todas las notificaciones:

```vue
<!-- resources/js/Components/NotificationsPanel.vue -->

<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { format } from 'date-fns'
import { es } from 'date-fns/locale'

const page = usePage()
const notifications = computed(() => page.props.auth?.user?.notifications || [])
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)

const notificationIcons = {
  'task_assigned': '📋',
  'task_status_changed': '🔄',
  'task_review_requested': '👁️',
  'task_feedback': '💬',
  'task_review_decision': '✅',
  'evidence_added': '📎',
}

const notificationColors = {
  'task_assigned': 'bg-blue-50 border-blue-200',
  'task_status_changed': 'bg-purple-50 border-purple-200',
  'task_review_requested': 'bg-orange-50 border-orange-200',
  'task_feedback': 'bg-red-50 border-red-200',
  'task_review_decision': 'bg-green-50 border-green-200',
  'evidence_added': 'bg-cyan-50 border-cyan-200',
}

const markAsRead = (notificationId) => {
  router.post(`/notifications/${notificationId}/read`, {}, { preserveScroll: true })
}

const markAllAsRead = () => {
  router.post('/notifications/read-all', {}, { preserveScroll: true })
}

const deleteNotification = (notificationId) => {
  router.delete(`/notifications/${notificationId}`, { preserveScroll: true })
}

const getRelativeTime = (date) => {
  return format(new Date(date), 'PPP p', { locale: es })
}

const handleNotificationClick = (notification) => {
  if (!notification.read_at) {
    markAsRead(notification.id)
  }
  
  // Navegar a la tarea relacionada
  if (notification.data?.task_id) {
    router.get(`/tareas/${notification.data.task_id}`)
  }
}
</script>

<template>
  <div class="notifications-panel">
    <!-- Encabezado -->
    <div class="flex items-center justify-between p-4 border-b">
      <div>
        <h3 class="text-lg font-semibold">Notificaciones</h3>
        <p v-if="unreadCount > 0" class="text-sm text-gray-600">
          {{ unreadCount }} no leída{{ unreadCount !== 1 ? 's' : '' }}
        </p>
      </div>
      <button
        v-if="unreadCount > 0"
        @click="markAllAsRead"
        class="text-sm text-blue-600 hover:text-blue-700 font-medium"
      >
        Marcar todas como leídas
      </button>
    </div>

    <!-- Lista de Notificaciones -->
    <div class="max-h-96 overflow-y-auto">
      <div v-if="notifications.length === 0" class="p-8 text-center text-gray-500">
        <p>No tienes notificaciones</p>
      </div>

      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="[
          'border-l-4 p-4 cursor-pointer transition hover:bg-gray-50',
          notification.read_at ? 'bg-gray-50 border-l-gray-300' : 'bg-white border-l-blue-500',
          notificationColors[notification.data.type] || 'bg-gray-50 border-gray-200',
        ]"
        @click="handleNotificationClick(notification)"
      >
        <!-- Contenedor Principal -->
        <div class="flex items-start justify-between">
          <!-- Icono y Contenido -->
          <div class="flex-1">
            <div class="flex items-start gap-3">
              <span class="text-2xl" :title="notification.data.type">
                {{ notificationIcons[notification.data.type] || '📌' }}
              </span>

              <div class="flex-1 min-w-0">
                <!-- Título -->
                <h4 class="font-semibold text-sm text-gray-900">
                  {{ notification.data.title || notification.data.type }}
                </h4>

                <!-- Mensaje Principal -->
                <p class="text-sm text-gray-700 mt-1">
                  {{ notification.data.message || notification.data.task_name }}
                </p>

                <!-- Información de la Tarea -->
                <div v-if="notification.data.task_name" class="mt-2 p-2 bg-white bg-opacity-50 rounded">
                  <p class="text-xs font-medium text-gray-600">
                    📌 {{ notification.data.task_name }}
                  </p>
                </div>

                <!-- Detalles Específicos por Tipo -->
                <div v-if="notification.data.comment" class="mt-2 p-2 bg-white bg-opacity-50 rounded border-l-2 border-gray-300">
                  <p class="text-xs text-gray-600">
                    <strong>Comentario:</strong> {{ notification.data.comment }}
                  </p>
                </div>

                <!-- Estado Changes -->
                <div v-if="notification.data.previous_status && notification.data.new_status" class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                  <span class="px-2 py-1 bg-red-100 text-red-700 rounded">
                    {{ notification.data.previous_status }}
                  </span>
                  <span>→</span>
                  <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
                    {{ notification.data.new_status }}
                  </span>
                </div>

                <!-- Decision/Feedback -->
                <div v-if="notification.data.decision || notification.data.feedback_label" class="mt-2">
                  <span :class="[
                    'text-xs font-semibold px-2 py-1 rounded',
                    (notification.data.decision === 'Aceptada' || notification.data.feedback_label === 'Aprobada')
                      ? 'bg-green-100 text-green-700'
                      : 'bg-red-100 text-red-700'
                  ]">
                    {{ notification.data.decision || notification.data.feedback_label }}
                  </span>
                </div>

                <!-- Actor/Usuario que realizó la acción -->
                <div v-if="notification.data.actor_name || notification.data.uploaded_by_name" class="mt-2 text-xs text-gray-500">
                  <p>Por: <strong>{{ notification.data.actor_name || notification.data.uploaded_by_name }}</strong></p>
                </div>

                <!-- Fecha -->
                <p class="text-xs text-gray-500 mt-2">
                  {{ getRelativeTime(notification.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Acciones -->
          <div class="ml-4 flex flex-col gap-1">
            <button
              v-if="!notification.read_at"
              @click.stop="markAsRead(notification.id)"
              class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded transition"
              title="Marcar como leído"
            >
              ✓
            </button>
            <button
              @click.stop="deleteNotification(notification.id)"
              class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded transition"
              title="Eliminar"
            >
              ✕
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notifications-panel {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: white;
}
</style>
```

## 3. Componente Reducido (Badge de Notificaciones)

Para usar en el menú principal:

```vue
<!-- resources/js/Components/NotificationBell.vue -->

<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import NotificationsPanel from './NotificationsPanel.vue'

const page = usePage()
const showPanel = ref(false)

const notifications = computed(() => page.props.auth?.user?.notifications || [])
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)
</script>

<template>
  <div class="relative">
    <!-- Botón -->
    <button
      @click="showPanel = !showPanel"
      class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition"
      :title="`${unreadCount} notificaciones`"
    >
      <!-- Icono de Campana -->
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
      </svg>

      <!-- Badge de Notificaciones No Leídas -->
      <span
        v-if="unreadCount > 0"
        class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Panel Flotante -->
    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="showPanel"
        class="absolute right-0 mt-2 w-96 shadow-lg rounded-lg z-50"
      >
        <NotificationsPanel />
      </div>
    </transition>

    <!-- Click fuera para cerrar -->
    <div
      v-if="showPanel"
      class="fixed inset-0 z-40"
      @click="showPanel = false"
    ></div>
  </div>
</template>
```

## 4. Layout con Notificaciones

Integra el bell en tu layout principal:

```vue
<!-- resources/js/Layouts/AuthenticatedLayout.vue -->

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import NotificationBell from '@/Components/NotificationBell.vue'

const page = usePage()
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <div class="flex items-center">
            <h1 class="text-xl font-bold">SquadControl</h1>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-4">
            <!-- Notification Bell -->
            <NotificationBell />

            <!-- User Menu -->
            <div class="text-sm text-gray-600">
              {{ page.props.auth.user.name }}
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Content -->
    <main class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <slot></slot>
      </div>
    </main>
  </div>
</template>
```

## 5. Echo para Actualizaciones en Tiempo Real (Opcional)

Si quieres actualizaciones en tiempo real:

```vue
<script setup>
import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Echo from 'laravel-echo'

const page = usePage()

let echo = null

onMounted(() => {
  // Pusher o socket.io configuration
  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  })

  // Escuchar notificaciones
  window.Echo.private(`App.Models.User.${page.props.auth.user.id}`)
    .notification((notification) => {
      console.log('Nueva notificación:', notification)
      // Actualizar UI aquí
    })
})

onUnmounted(() => {
  window.Echo?.disconnect()
})
</script>
```

## 6. Estilos CSS Adicionales

```css
/* Animación para notificaciones nuevas */
@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.notification-enter-active {
  animation: slideIn 0.3s ease-out;
}

/* Toast para notificaciones */
.notification-toast {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  max-width: 400px;
  z-index: 9999;
}
```

## 7. Rutas Necesarias en Laravel

Asegúrate de tener estas rutas en `routes/web.php`:

```php
Route::middleware(['auth'])->group(function () {
    // Notificaciones
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});
```

## 8. Controlador de Notificaciones

Crea `app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'notifications' => $request->user()->notifications,
        ]);
    }

    public function markAsRead($id, Request $request)
    {
        $notification = $request->user()->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
        }

        return redirect()->back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications->each->markAsRead();
        return redirect()->back();
    }

    public function destroy($id, Request $request)
    {
        $request->user()->notifications()->find($id)?->delete();
        return redirect()->back();
    }
}
```

## 9. Tips de Diseño

- **Colores**: Usa colores diferentes por tipo de notificación
- **Iconos**: Usa emojis o SVGs para identificar tipos
- **Agrupación**: Agrupa notificaciones por tarea
- **Animaciones**: Agrega transiciones suaves al marcar como leído
- **Mobile**: Diseña responsivo para móviles

## 10. Ejemplo Completo de Uso

```vue
<template>
  <AuthenticatedLayout>
    <div class="grid grid-cols-4 gap-4">
      <!-- Sidebar con Notificaciones -->
      <aside class="col-span-1">
        <NotificationsPanel />
      </aside>

      <!-- Main Content -->
      <main class="col-span-3">
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-2xl font-bold mb-4">Mis Tareas</h2>
          <!-- Tu contenido aquí -->
        </div>
      </main>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import NotificationsPanel from '@/Components/NotificationsPanel.vue'
</script>
```

## Conclusión

Con estos componentes, tendrás un sistema de notificaciones completo y totalmente integrado en tu aplicación Vue/Inertia. Las notificaciones se mostrarán en tiempo real, permitiendo a los usuarios mantenerse informados de todos los cambios en sus tareas.
