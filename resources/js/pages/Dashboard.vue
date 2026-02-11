<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type DashboardStats = {
    activeTasks: number;
    inReviewTasks: number;
    completedTasks: number;
    checkinsToday: number;
};

const { stats } = defineProps<{ stats: DashboardStats }>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const statCards = [
    { label: 'Tareas activas', value: () => stats.activeTasks, tone: 'bg-amber-100 text-amber-800' },
    { label: 'En revision', value: () => stats.inReviewTasks, tone: 'bg-slate-100 text-slate-700' },
    { label: 'Completadas', value: () => stats.completedTasks, tone: 'bg-emerald-100 text-emerald-800' },
    { label: 'Checkins hoy', value: () => stats.checkinsToday, tone: 'bg-sky-100 text-sky-800' },
];

const formatCount = (value: number) => value.toLocaleString('es-MX');

const actions = [
    {
        title: 'Gestion de tareas',
        desc: 'Crea, asigna, agrega evidencias y controla estados.',
        href: '/tareas',
        badge: 'Operativo',
    },
    {
        title: 'Revision y aprobacion',
        desc: 'Aprueba o rechaza tareas en revision con motivo.',
        href: '/tareas',
        badge: 'Supervisor',
    },
    {
        title: 'Usuarios y roles',
        desc: 'Administra accesos, roles y estado de usuarios.',
        href: '/usuarios',
        badge: 'Admin/RH',
    },
    {
        title: 'Checkins administrativos',
        desc: 'Supervisa entradas y salidas del personal.',
        href: '/checkins-admin',
        badge: 'Control',
    },
    {
        title: 'Checkin personal',
        desc: 'Registro rapido de entrada y salida.',
        href: '/checkin',
        badge: 'Empleado',
    },
    {
        title: 'Mis tareas',
        desc: 'Consulta el detalle y reporta avances.',
        href: '/mis-tareas',
        badge: 'Lider',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 sm:p-6">
            <section class="rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-5 text-white sm:p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-300">
                            Control operativo
                        </p>
                        <h1 class="mt-2 text-3xl font-semibold md:text-4xl">
                            Centro de control SCUAD
                        </h1>
                        <p class="mt-2 text-sm text-slate-200">
                            Seguimiento de tareas, revision, evidencias y checkins en un solo lugar.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            href="/tareas"
                            class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20"
                        >
                            Ir a tareas
                        </Link>
                        <Link
                            href="/usuarios"
                            class="rounded-full border border-white/20 px-4 py-2 text-sm font-medium text-white hover:bg-white/10"
                        >
                            Administrar usuarios
                        </Link>
                    </div>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="stat in statCards"
                        :key="stat.label"
                        class="rounded-xl bg-white/10 p-4"
                    >
                        <p class="text-xs text-slate-300">{{ stat.label }}</p>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-2xl font-semibold">
                                {{ formatCount(stat.value()) }}
                            </span>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold', stat.tone]">
                                Hoy
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-[1.2fr,0.8fr]">
                <div class="rounded-2xl border border-sidebar-border/70 bg-background p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-foreground">Funciones principales</h2>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                            Demo funcional
                        </span>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <Link
                            v-for="action in actions"
                            :key="action.title"
                            :href="action.href"
                            class="group rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 transition hover:-translate-y-0.5 hover:bg-muted"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">
                                        {{ action.title }}
                                    </h3>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ action.desc }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-background px-2 py-1 text-[10px] font-semibold text-muted-foreground">
                                    {{ action.badge }}
                                </span>
                            </div>
                            <div class="mt-4 text-xs font-semibold text-primary">
                                Abrir modulo
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-sidebar-border/70 bg-background p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Flujo operativo</h2>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4">
                            <p class="text-xs font-semibold text-muted-foreground">1. Asignacion</p>
                            <p class="mt-1 text-sm text-foreground">
                                Admin crea tareas y asigna lideres de cuadrilla.
                            </p>
                        </div>
                        <div class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4">
                            <p class="text-xs font-semibold text-muted-foreground">2. Ejecucion</p>
                            <p class="mt-1 text-sm text-foreground">
                                Lideres reportan avances, evidencias y estado.
                            </p>
                        </div>
                        <div class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4">
                            <p class="text-xs font-semibold text-muted-foreground">3. Revision</p>
                            <p class="mt-1 text-sm text-foreground">
                                Supervisor aprueba o rechaza con motivo y guia multimedia.
                            </p>
                        </div>
                        <div class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4">
                            <p class="text-xs font-semibold text-muted-foreground">4. Cierre</p>
                            <p class="mt-1 text-sm text-foreground">
                                La tarea queda completada y el historial se actualiza.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
