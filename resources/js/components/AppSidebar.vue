<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Clock, LayoutGrid, User, Bug, Package} from 'lucide-vue-next';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isDevMode = computed(() => Boolean((page.props.app as { isDevMode?: boolean } | undefined)?.isDevMode));
const isAdmin = computed(() => String(page.props.auth?.user?.role ?? '') === 'Admin');

const mainNavItems = computed<NavItem[]>(() => {
const items: NavItem[] = [
    {
        title: 'Tablero',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Tareas',
        href: '/tareas',
        icon: BookOpen,
    },
    {
        title: 'Materiales',
        href: '/materiales',
        icon: Package,
    },
    {
        title: 'Check-ins',
        href: '/checkins-admin',
        icon: Clock,
    }
];

if (isAdmin.value) {
    items.splice(1, 0, {
        title: 'Usuarios',
        href: '/usuarios',
        icon: User,
    });
}

if (isDevMode.value && isAdmin.value) {
    items.push({
        title: 'Diagnóstico FCM',
        href: '/fcm/debug',
        icon: Bug,
    });
}

return items;
});

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-14 sm:h-12">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
