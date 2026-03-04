<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, LogOut, Menu } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { logout } from '@/routes';

defineProps<{ title?: string }>();

type NotificationItem = {
  id: string;
  type: string;
  data: Record<string, unknown>;
  created_at: string | null;
};

const page = usePage();
const currentPath = computed(() => {
  const url = page.url || '';
  return url.split('?')[0];
});

const notifications = computed(() => (page.props.taskNotifications as NotificationItem[] | undefined) ?? []);
const notificationsCount = computed(() => notifications.value.length);

const formatDate = (value: string | null) => (value ? new Date(value).toLocaleString('es-MX') : '');
const notificationTitle = (item: NotificationItem) => String(item.data.title || item.data.task_name || 'Notificación');
const notificationMessage = (item: NotificationItem) => String(item.data.message || item.data.comment || item.type);

const isActive = (path: string) =>
  currentPath.value === path || currentPath.value.startsWith(`${path}/`);

const handleLogout = () => {
  router.flushAll();
};
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <Head :title="title || 'Mis tareas'" />
    <header class="border-b border-sidebar-border/70">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <div class="text-lg font-semibold">SquadControl</div>

        <div class="flex items-center gap-3">
          <div class="hidden text-sm text-muted-foreground sm:block">Lider de cuadrilla</div>

          <div class="hidden items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs text-muted-foreground sm:inline-flex">
            <Bell class="h-4 w-4" />
            {{ notificationsCount }} notificaciones
          </div>

          <Link
            class="hidden sm:inline-flex"
            :href="logout()"
            @click="handleLogout"
            as="button"
          >
            <Button variant="outline" class="h-9 rounded-lg px-3">
              <LogOut class="mr-2 h-4 w-4" />
              Cerrar sesión
            </Button>
          </Link>

          <Sheet>
            <SheetTrigger as-child>
              <Button variant="outline" size="icon" class="h-9 w-9 rounded-lg">
                <Menu class="h-4 w-4" />
              </Button>
            </SheetTrigger>

            <SheetContent side="right" class="w-[280px] p-0">
              <SheetHeader class="border-b border-sidebar-border/70 px-5 py-4 text-left">
                <SheetTitle>Menú</SheetTitle>
              </SheetHeader>

              <nav class="grid gap-2 p-4">
                <Link
                  href="/mis-tareas"
                  class="rounded-md px-3 py-2 text-sm font-medium transition"
                  :class="isActive('/mis-tareas')
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                >
                  Mis tareas
                </Link>

                <Link
                  href="/checkin"
                  class="rounded-md px-3 py-2 text-sm font-medium transition"
                  :class="isActive('/checkin')
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                >
                  Checkin
                </Link>

                <Link
                  href="/mis-materiales"
                  class="rounded-md px-3 py-2 text-sm font-medium transition"
                  :class="isActive('/mis-materiales')
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                >
                  Materiales
                </Link>

                <Link
                  class="rounded-md px-3 py-2 text-left text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                  :href="logout()"
                  @click="handleLogout"
                  as="button"
                >
                  Cerrar sesión
                </Link>
              </nav>

              <div class="px-4 pb-4 text-xs text-muted-foreground">
                Rol: Lider de cuadrilla
              </div>

              <div class="border-t border-sidebar-border/70 px-4 py-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Notificaciones
                </div>

                <div v-if="notifications.length === 0" class="text-xs text-muted-foreground">
                  Sin notificaciones recientes.
                </div>

                <div v-else class="grid gap-2">
                  <div
                    v-for="item in notifications"
                    :key="item.id"
                    class="rounded-md border border-sidebar-border/70 p-2"
                  >
                    <p class="text-xs font-medium text-foreground">{{ notificationTitle(item) }}</p>
                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ notificationMessage(item) }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ formatDate(item.created_at) }}</p>
                  </div>
                </div>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </header>
    <main class="mx-auto w-full max-w-6xl px-4 py-6">
      <slot />
    </main>
  </div>
</template>
