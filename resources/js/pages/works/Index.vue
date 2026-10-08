<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Calendar, HardHat, Plus, Users, Wrench } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { workStatusClass, workStatusLabel, workTypeLabel } from '@/lib/works';

interface WorkRow {
    id: number;
    title: string;
    property: string;
    contractor: string;
    type: string;
    status: string;
    is_current: boolean;
    start_date: string;
    end_date: string | null;
    workers_count: number;
    tools_inside: number;
    needs_attention: boolean;
}

const props = defineProps<{
    works: WorkRow[];
    filters: { status?: string };
    can_create: boolean;
}>();

const statusFilters = [
    { value: '', label: 'Todas' },
    { value: 'pendiente', label: 'Pendientes' },
    { value: 'aprobada', label: 'Aprobadas' },
    { value: 'suspendida', label: 'Suspendidas' },
    { value: 'cerrada', label: 'Cerradas' },
];

function filterBy(status: string) {
    router.get('/works', status ? { status } : {}, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Obras" />

        <div class="flex flex-col gap-4 p-3 sm:gap-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold sm:text-2xl">Obras</h1>
                    <p class="text-sm text-muted-foreground">Arreglos locativos y construcciones</p>
                </div>
                <Link v-if="can_create" href="/works/create">
                    <Button class="h-10"><Plus class="h-4 w-4" /> Nueva obra</Button>
                </Link>
            </div>

            <!-- Filtro por estado -->
            <div class="-mx-3 flex gap-1.5 overflow-x-auto px-3 sm:mx-0 sm:px-0">
                <button
                    v-for="f in statusFilters"
                    :key="f.value"
                    type="button"
                    @click="filterBy(f.value)"
                    :class="[
                        'h-9 shrink-0 rounded-full border px-3 text-sm whitespace-nowrap transition-colors',
                        (props.filters.status ?? '') === f.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                    ]"
                >
                    {{ f.label }}
                </button>
            </div>

            <div
                v-if="works.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                <HardHat class="h-10 w-10 opacity-30" />
                <p class="text-sm">No hay obras registradas.</p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="w in works"
                    :key="w.id"
                    :href="`/works/${w.id}`"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:border-primary/40"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ w.title }}</p>
                            <p class="truncate text-sm text-muted-foreground">{{ w.property }} · {{ w.contractor }}</p>
                        </div>
                        <span :class="['shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold', workStatusClass[w.status]]">
                            {{ workStatusLabel[w.status] ?? w.status }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span>{{ workTypeLabel[w.type] ?? w.type }}</span>
                        <span class="inline-flex items-center gap-1">
                            <Calendar class="h-3.5 w-3.5" />
                            {{ w.start_date }}{{ w.end_date ? ` → ${w.end_date}` : '' }}
                        </span>
                        <span class="inline-flex items-center gap-1"><Users class="h-3.5 w-3.5" /> {{ w.workers_count }}</span>
                        <span v-if="w.tools_inside" class="inline-flex items-center gap-1 font-medium text-foreground">
                            <Wrench class="h-3.5 w-3.5" /> {{ w.tools_inside }} dentro
                        </span>
                        <span v-if="w.is_current" class="inline-flex items-center gap-1 font-semibold text-emerald-600">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Vigente
                        </span>
                    </div>
                    <p
                        v-if="w.needs_attention"
                        class="flex items-center gap-1.5 rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700"
                    >
                        <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                        {{ w.tools_inside }} herramienta(s) dentro y la obra no está vigente
                    </p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
