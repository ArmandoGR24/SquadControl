<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';

defineProps<{ title?: string }>();

const page = usePage();
const currentPath = computed(() => {
  const url = page.url || '';
  return url.split('?')[0];
});

const isActive = (path: string) =>
  currentPath.value === path || currentPath.value.startsWith(`${path}/`);
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <Head :title="title || 'Mis tareas'" />
    <header class="border-b border-sidebar-border/70">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <div class="text-lg font-semibold">SquadControl</div>

        <div class="flex items-center gap-3">
          <div class="hidden text-sm text-muted-foreground sm:block">Lider de cuadrilla</div>

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
              </nav>

              <div class="px-4 pb-4 text-xs text-muted-foreground">
                Rol: Lider de cuadrilla
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
