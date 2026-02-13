<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { useEcho } from '@laravel/echo-vue';
import { useToast, POSITION } from "vue-toastification";
import { onMounted } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const toast = useToast();

// --- FUNCIÓN GENÉRICA PARA MOSTRAR NOTIFICACIONES ---
const handleNotification = async (titulo: string, mensaje: string, tipo: 'success' | 'error' | 'info' = 'success') => {
    // 1. Mostrar Toast Visual
    (toast as any)[tipo](mensaje, {
        position: POSITION.TOP_CENTER,
        timeout: 4000
    });

    // 2. Notificación de Sistema (Nativa)
    if (Notification.permission === "granted") {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
            registration.showNotification(titulo, {
                body: mensaje,
                icon: "/icon-192x192.png",
                vibrate: [200, 100, 200],
                tag: "pwa-notification" 
            } as any);
        }
    }
};

// --- ESCUCHA DE MÚLTIPLES EVENTOS ---

// Evento 1: Tareas o Notificaciones Generales
useEcho('task-notifications', 'notificationsTask', (data: { mensaje: string }) => {
    handleNotification("Nueva Tarea", data.mensaje, 'info');
});

// Evento 2: Check-In de Usuarios
useEcho('task-notifications', 'CheckInEvent', (data: { mensaje: string }) => {
    handleNotification("Check-In Registrado", data.mensaje, 'success');
});

// Evento 3: Alertas de Error (Ejemplo)
useEcho('task-notifications', 'SystemError', (data: { mensaje: string }) => {
    handleNotification("¡Alerta de Sistema!", data.mensaje, 'error');
});


onMounted(() => {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
});
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>