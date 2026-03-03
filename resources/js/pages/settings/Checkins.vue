<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type CheckinSettings = {
    minimum_checkout_hours: number;
    require_location: boolean;
    allow_leader_bulk_actions: boolean;
    allow_leader_include_self: boolean;
    max_targets_per_action: number;
};

const props = defineProps<{
    settings: CheckinSettings;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configuración de check-ins',
        href: '/settings/checkins',
    },
];

const form = useForm<CheckinSettings>({
    minimum_checkout_hours: props.settings.minimum_checkout_hours,
    require_location: props.settings.require_location,
    allow_leader_bulk_actions: props.settings.allow_leader_bulk_actions,
    allow_leader_include_self: props.settings.allow_leader_include_self,
    max_targets_per_action: props.settings.max_targets_per_action,
});

const submit = () => {
    form.put('/settings/checkins', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configuración de check-ins" />

        <h1 class="sr-only">Configuración de check-ins</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Check-ins"
                    description="Configura reglas operativas del reloj checador desde la interfaz"
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="minimum_checkout_hours">Tiempo mínimo para registrar salida (horas)</Label>
                        <Input
                            id="minimum_checkout_hours"
                            type="number"
                            min="1"
                            max="24"
                            v-model.number="form.minimum_checkout_hours"
                        />
                        <InputError :message="form.errors.minimum_checkout_hours" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="max_targets_per_action">Máximo de usuarios por operación de líder</Label>
                        <Input
                            id="max_targets_per_action"
                            type="number"
                            min="1"
                            max="100"
                            v-model.number="form.max_targets_per_action"
                        />
                        <InputError :message="form.errors.max_targets_per_action" />
                    </div>

                    <label class="flex items-center gap-3 rounded-md border border-sidebar-border/70 p-3">
                        <input
                            v-model="form.require_location"
                            type="checkbox"
                            class="h-4 w-4 rounded border-input"
                        />
                        <span class="text-sm text-foreground">Requerir ubicación GPS para entrada/salida</span>
                    </label>
                    <InputError :message="form.errors.require_location" />

                    <label class="flex items-center gap-3 rounded-md border border-sidebar-border/70 p-3">
                        <input
                            v-model="form.allow_leader_bulk_actions"
                            type="checkbox"
                            class="h-4 w-4 rounded border-input"
                        />
                        <span class="text-sm text-foreground">Permitir a líderes registrar check-ins de varios usuarios</span>
                    </label>
                    <InputError :message="form.errors.allow_leader_bulk_actions" />

                    <label class="flex items-center gap-3 rounded-md border border-sidebar-border/70 p-3">
                        <input
                            v-model="form.allow_leader_include_self"
                            type="checkbox"
                            class="h-4 w-4 rounded border-input"
                        />
                        <span class="text-sm text-foreground">Permitir que el líder se incluya en operaciones masivas</span>
                    </label>
                    <InputError :message="form.errors.allow_leader_include_self" />

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing">Guardar configuración</Button>
                        <p v-if="form.recentlySuccessful" class="text-sm text-emerald-600">
                            Configuración guardada.
                        </p>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
