<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type EventKey =
    | 'task_published'
    | 'task_assigned'
    | 'task_status_changed'
    | 'task_review_requested'
    | 'task_review_decision'
    | 'task_feedback'
    | 'evidence_added'
    | 'task_completed'
    | 'checkin_registered'
    | 'checkout_registered';

type Props = {
    eventSettings: Record<EventKey, boolean>;
    recipientSettings: Record<EventKey, string[]>;
    eventLabels: Record<EventKey, string>;
    roleOptions: string[];
};

const props = defineProps<Props>();

const orderedEvents: EventKey[] = [
    'task_published',
    'task_assigned',
    'task_status_changed',
    'task_review_requested',
    'task_review_decision',
    'task_feedback',
    'evidence_added',
    'task_completed',
    'checkin_registered',
    'checkout_registered',
];

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configuración de notificaciones',
        href: '/settings/notifications',
    },
];

const form = useForm({
    events: { ...props.eventSettings },
    recipients: orderedEvents.reduce((accumulator, eventKey) => {
        accumulator[eventKey] = [...(props.recipientSettings[eventKey] ?? [])];
        return accumulator;
    }, {} as Record<EventKey, string[]>),
});

const submit = () => {
    form.put('/settings/notifications', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configuración de notificaciones" />

        <h1 class="sr-only">Configuración de notificaciones</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Notificaciones"
                    description="Configura qué eventos enviar y a qué roles se notifican"
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <div
                        v-for="eventKey in orderedEvents"
                        :key="eventKey"
                        class="rounded-lg border border-sidebar-border/70 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-foreground">
                                {{ eventLabels[eventKey] }}
                            </p>
                            <label class="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                <input
                                    v-model="form.events[eventKey]"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary"
                                />
                                Activar
                            </label>
                        </div>

                        <div class="mt-4 space-y-2">
                            <p class="text-xs text-muted-foreground">Destinatarios</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="role in roleOptions"
                                    :key="`${eventKey}-${role}`"
                                    class="inline-flex items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        v-model="form.recipients[eventKey]"
                                        type="checkbox"
                                        :value="role"
                                        class="h-4 w-4 rounded border-input text-primary"
                                    />
                                    <span>{{ role }}</span>
                                </label>
                            </div>
                        </div>

                        <InputError :message="form.errors[`events.${eventKey}`]" class="mt-2" />
                        <InputError :message="form.errors[`recipients.${eventKey}`]" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing">Guardar configuración</Button>
                        <p v-if="form.recentlySuccessful" class="text-sm text-emerald-600">
                            Configuración actualizada.
                        </p>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
