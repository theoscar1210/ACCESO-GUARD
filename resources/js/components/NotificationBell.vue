<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck, Loader2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Campana de notificaciones dentro de la app (aprobaciones, retiros, comunicados…) */

interface Item {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string;
}

const page = usePage<{ notifications_unread?: number }>();
const unread = computed(() => page.props.notifications_unread ?? 0);

const open = ref(false);
const loading = ref(false);
const items = ref<Item[]>([]);
const root = ref<HTMLElement | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await fetch('/notifications', { headers: { Accept: 'application/json' } });
        const data: { items: Item[] } = await res.json();
        items.value = data.items;
    } finally {
        loading.value = false;
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value) load();
}

async function readAll() {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    await fetch('/notifications/read-all', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
    });
    items.value = items.value.map((i) => ({ ...i, read: true }));
    router.reload({ only: ['notifications_unread'] });
}

function onClickOutside(event: MouseEvent) {
    if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false;
}

// El contador se actualiza cada minuto sin recargar la pantalla
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    document.addEventListener('click', onClickOutside);
    timer = setInterval(() => router.reload({ only: ['notifications_unread'] }), 60_000);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    clearInterval(timer);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            @click="toggle"
            class="relative flex h-10 w-10 items-center justify-center rounded-md hover:bg-muted lg:h-9 lg:w-9"
            :aria-label="unread ? `${unread} notificaciones sin leer` : 'Notificaciones'"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unread"
                class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
            >{{ unread > 9 ? '9+' : unread }}</span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 flex max-h-[70dvh] w-[min(22rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-xl border bg-popover shadow-lg"
        >
            <div class="flex items-center justify-between border-b px-3 py-2">
                <p class="text-sm font-semibold">Notificaciones</p>
                <button
                    v-if="items.some((i) => !i.read)"
                    type="button"
                    @click="readAll"
                    class="flex h-8 items-center gap-1 rounded-md px-2 text-xs text-primary hover:bg-primary/10"
                >
                    <CheckCheck class="h-3.5 w-3.5" /> Marcar todas
                </button>
            </div>

            <div v-if="loading && items.length === 0" class="flex justify-center py-6">
                <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>

            <ul v-else class="divide-y overflow-y-auto">
                <li v-for="n in items" :key="n.id">
                    <a
                        :href="`/notifications/${n.id}/open`"
                        :class="['block px-3 py-2.5 hover:bg-muted/60', n.read ? 'opacity-70' : 'bg-primary/[0.04]']"
                    >
                        <p class="flex items-center gap-1.5 text-sm font-medium">
                            <span v-if="!n.read" class="h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                            {{ n.title }}
                        </p>
                        <p class="line-clamp-2 text-xs text-muted-foreground">{{ n.body }}</p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground/70">{{ n.at }}</p>
                    </a>
                </li>
                <li v-if="items.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No tienes notificaciones.</li>
            </ul>
        </div>
    </div>
</template>
