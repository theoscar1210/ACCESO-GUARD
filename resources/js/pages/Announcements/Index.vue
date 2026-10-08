<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, BellOff, CheckCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Announcement {
    id: number;
    title: string;
    body: string;
    created_at: string;
    read_at: string | null;
}

defineProps<{ announcements: Announcement[] }>();

const marking = ref<number | null>(null);

function markRead(id: number) {
    if (marking.value) return;
    marking.value = id;
    router.post(`/announcements/${id}/read`, {}, {
        preserveScroll: true,
        onFinish: () => { marking.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Comunicados" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <div class="page-header">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                <h1 class="mt-1 text-2xl font-bold">Comunicados</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">Avisos y notificaciones de la administración</p>
            </div>

            <div v-if="announcements.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-muted-foreground/20 py-16">
                <BellOff class="h-10 w-10 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No hay comunicados por ahora.</p>
            </div>

            <div v-else class="flex flex-col gap-3">
                <div v-for="a in announcements" :key="a.id"
                    :class="['overflow-hidden rounded-xl border bg-card shadow-sm transition-all', a.read_at ? 'border-border opacity-70' : 'border-amber-400/40 ring-1 ring-amber-400/20']">
                    <div class="flex items-start gap-3 p-4 sm:gap-4 sm:p-5">
                        <div :class="['mt-0.5 hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex', a.read_at ? 'bg-muted' : 'bg-amber-500/15']">
                            <Bell :class="['h-4 w-4', a.read_at ? 'text-muted-foreground' : 'text-amber-500']" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">{{ a.title }}</h3>
                                <span v-if="!a.read_at"
                                    class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-600">
                                    Nuevo
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground whitespace-pre-line">{{ a.body }}</p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <p class="text-xs text-muted-foreground/60">{{ a.created_at }}</p>
                                <button v-if="!a.read_at"
                                    :disabled="marking === a.id"
                                    @click="markRead(a.id)"
                                    class="flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-xs font-semibold transition hover:bg-muted disabled:opacity-50">
                                    <CheckCheck class="h-4 w-4" />
                                    Marcar leído
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
