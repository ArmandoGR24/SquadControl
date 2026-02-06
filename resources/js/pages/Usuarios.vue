<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { usuarios } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Usuarios',
        href: usuarios().url,
    },
];
</script>
<template>
  <Head title="Mi Página" />
  
  <div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold">Mi Nueva Página</h1>
  </div>
</template>