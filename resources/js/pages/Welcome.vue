<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Download } from 'lucide-vue-next';
import { dashboard, register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        canResetPassword: boolean;
    }>(),
    {
        canRegister: true,
        canResetPassword: true,
    },
);

const installPromptEvent = ref<BeforeInstallPromptEvent | null>(null);
const installFeedback = ref('');

const isIos = computed(() => {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /iPad|iPhone|iPod/.test(navigator.userAgent);
});

const isStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches;
});

const canPromptInstall = computed(
    () => Boolean(installPromptEvent.value) && !isStandalone.value,
);

const handleBeforeInstallPrompt = (event: Event) => {
    event.preventDefault();
    installPromptEvent.value = event as BeforeInstallPromptEvent;
};

const handleAppInstalled = () => {
    installFeedback.value = 'Aplicación instalada correctamente.';
    installPromptEvent.value = null;
};

const installApp = async () => {
    if (!installPromptEvent.value) {
        installFeedback.value = isIos.value
            ? 'En iPhone usa Compartir → Añadir a pantalla de inicio.'
            : 'La instalación directa no está disponible en este navegador. Usa el menú del navegador.';
        return;
    }

    await installPromptEvent.value.prompt();
    const choiceResult = await installPromptEvent.value.userChoice;

    installFeedback.value =
        choiceResult.outcome === 'accepted'
            ? 'Instalación aceptada. Terminando configuración…'
            : 'Instalación cancelada. Puedes volver a intentarlo cuando quieras.';
};

onMounted(() => {
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.removeEventListener('appinstalled', handleAppInstalled);
});
</script>

<template>
    <Head title="Inicio" />

    <div class="min-h-svh bg-background px-4 py-8 md:px-8 md:py-12">
        <div class="mx-auto w-full max-w-xl space-y-4">
            <section class="rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-background to-muted/30 p-5 md:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-sidebar-border/70 bg-background">
                            <AppLogoIcon class="size-7 fill-current text-foreground" />
                        </div>
                        <div>
                            <p class="text-base font-semibold tracking-tight">SquadControl</p>
                            <p class="text-xs text-muted-foreground">Acceso</p>
                        </div>
                    </div>

                    <Button
                        v-if="!isStandalone"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-9 w-9 rounded-full"
                        :title="canPromptInstall ? 'Instalar app' : 'Instalación no disponible en esta sesión'"
                        @click="installApp"
                    >
                        <Download class="h-4 w-4" />
                    </Button>
                </div>
            </section>

            <Card class="rounded-2xl border-sidebar-border/70">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-xl">Iniciar sesión</CardTitle>
                        <CardDescription>Usa tu cuenta.</CardDescription>
                    </CardHeader>
                    <CardContent>
                    <div v-if="$page.props.auth.user" class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Ya tienes una sesión activa.
                        </p>
                        <Button as-child class="w-full">
                            <Link :href="dashboard()">Ir al dashboard</Link>
                        </Button>
                    </div>

                    <Form
                        v-else
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="space-y-5"
                    >
                        <div class="grid gap-2">
                            <Label for="email">Correo</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="correo@empresa.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label for="password">Contraseña</Label>
                                <TextLink
                                    v-if="props.canResetPassword"
                                    :href="request()"
                                    class="text-sm"
                                >
                                    ¿Olvidaste tu contraseña?
                                </TextLink>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <Label for="remember" class="flex items-center gap-3 text-sm">
                            <Checkbox id="remember" name="remember" />
                            <span>Recordarme</span>
                        </Label>

                        <Button type="submit" class="w-full" :disabled="processing">
                            <Spinner v-if="processing" />
                            Entrar
                        </Button>

                        <p
                            v-if="props.canRegister"
                            class="text-center text-sm text-muted-foreground"
                        >
                            ¿No tienes cuenta?
                            <TextLink :href="register()">Regístrate</TextLink>
                        </p>
                    </Form>
                    </CardContent>
                </Card>

            <p v-if="installFeedback" class="px-1 text-xs text-muted-foreground">
                {{ installFeedback }}
            </p>
        </div>
    </div>
</template>
