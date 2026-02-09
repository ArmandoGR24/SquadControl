r<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title?: string }>();
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <Head :title="title || 'Mis tareas'" />
    <header class="border-b border-sidebar-border/70">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <div class="text-lg font-semibold">SquadControl</div>
        <div class="text-sm text-muted-foreground">Lider de cuadrilla</div>
      </div>
    </header>
    <main class="mx-auto w-full max-w-6xl px-4 py-6">
      <slot />
    </main>
  </div>
</template>
