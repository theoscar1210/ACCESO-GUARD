<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ClipboardList, LogIn, Shield, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface Entry {
    id: number;
    full_name: string;
    cedula: string;
    apartment: string;
    type: string;
    vehicle: string;
    entry_at: string;
    is_inside: boolean;
    registered_by: string | null;
}
interface Stats {
    total: number;
    inside: number;
    propietario: number;
    autorizado: number;
    visitante: number;
}

const props = defineProps<{ entries: Entry[]; stats: Stats }>();
const search = ref('');

const filtered = computed(() =>
    props.entries.filter(
        (e) =>
            e.full_name.toLowerCase().includes(search.value.toLowerCase()) ||
            e.cedula.includes(search.value) ||
            e.apartment.includes(search.value),
    ),
);

const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    residente: 'Residente',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
    proveedor: 'Proveedor',
};
const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    propietario: 'default',
    autorizado: 'secondary',
    visitante: 'outline',
    proveedor: 'secondary',
};
</script>

<template>
    <AppLayout>
        <Head title="Monitor de Ingresos" />

        <div class="flex flex-col gap-5 p-4 sm:p-6">
            <!-- Header -->
            <div class="page-header">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 class="text-2xl font-bold">Monitor de Ingresos</h1>
                    <Link href="/vigilante/entries/create">
                        <Button class="w-full sm:w-auto"><LogIn class="mr-2 h-4 w-4" /> Registrar Ingreso</Button>
                    </Link>
                </div>
                <p class="mt-0.5 text-sm text-muted-foreground">Ingresos registrados hoy</p>
            </div>

            <!-- Stats mini -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="stat-card stat-card-accent">
                    <div class="absolute right-3 top-3 rounded-lg bg-primary/15 p-1.5">
                        <Activity class="h-3.5 w-3.5 text-primary" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-primary">Dentro ahora</p>
                    <p class="mt-1 text-3xl font-bold text-primary">{{ stats.inside }}</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-3 top-3 rounded-lg bg-blue-500/10 p-1.5">
                        <Users class="h-3.5 w-3.5 text-blue-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground">Propietarios</p>
                    <p class="mt-1 text-3xl font-bold">{{ stats.propietario }}</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-3 top-3 rounded-lg bg-green-500/10 p-1.5">
                        <Shield class="h-3.5 w-3.5 text-green-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground">Autorizados</p>
                    <p class="mt-1 text-3xl font-bold">{{ stats.autorizado }}</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-3 top-3 rounded-lg bg-amber-500/10 p-1.5">
                        <Users class="h-3.5 w-3.5 text-amber-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground">Visitantes</p>
                    <p class="mt-1 text-3xl font-bold">{{ stats.visitante }}</p>
                </div>
            </div>

            <!-- Búsqueda -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por nombre, cédula o apartamento..."
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary sm:max-w-sm"
                />
                <p class="text-xs text-muted-foreground shrink-0">
                    {{ filtered.length }} de {{ entries.length }} registro(s)
                </p>
            </div>

            <!-- Tabla -->
            <div class="overflow-hidden rounded-xl border border-primary/10 bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b border-primary/10 bg-primary/[0.03] px-5 py-3">
                    <ClipboardList class="h-4 w-4 text-primary" />
                    <span class="text-sm font-semibold">Registros del día</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th class="hidden sm:table-cell">Apto</th>
                                <th>Tipo</th>
                                <th class="hidden md:table-cell">Hora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in filtered" :key="entry.id">
                                <td class="px-4 py-3 font-medium">{{ entry.full_name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ entry.cedula }}</td>
                                <td class="hidden px-4 py-3 sm:table-cell">{{ entry.apartment }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="typeVariant[entry.type]">{{ typeLabel[entry.type] ?? entry.type }}</Badge>
                                </td>
                                <td class="hidden px-4 py-3 font-mono text-xs md:table-cell">{{ entry.entry_at }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="entry.is_inside" class="inline-flex items-center gap-1.5 font-medium text-emerald-600">
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                        Dentro
                                    </span>
                                    <span v-else class="text-muted-foreground">Salió</span>
                                </td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    No hay ingresos registrados hoy.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
