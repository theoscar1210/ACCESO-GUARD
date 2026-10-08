<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import type { Paginator } from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Historial de visitas al inmueble del usuario (propietario o residente).
 * Tarjetas en móvil y tabla desde tablet.
 */

export interface HistoryEntry {
    id: number;
    full_name: string;
    cedula: string;
    type: string;
    vehicle: string;
    plate: string | null;
    entry_at: string;
    exit_at: string | null;
    is_inside: boolean;
}

export interface HistoryFilters {
    type?: string;
    date_from?: string;
    date_to?: string;
}

const props = defineProps<{
    endpoint: string;
    entries: Paginator & { data: HistoryEntry[] };
    filters: HistoryFilters;
    apartment: string | null;
}>();

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    propietario: 'default',
    residente: 'default',
    autorizado: 'secondary',
    visitante: 'outline',
    proveedor: 'secondary',
};

const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    residente: 'Residente',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
    proveedor: 'Proveedor',
};

const vehicleLabel: Record<string, string> = {
    ninguno: '—',
    automovil: 'Auto',
    camioneta: 'Camioneta',
    moto: 'Moto',
    bicicleta: 'Bici',
};

// "type" arranca en '' para que el select muestre "Todos" y no quede en blanco
const localFilters = ref<HistoryFilters>({ type: '', ...props.filters });
let filterTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    localFilters,
    () => {
        if (filterTimeout) clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            router.get(props.endpoint, localFilters.value, {
                preserveState: true,
                replace: true,
            });
        }, 400);
    },
    { deep: true },
);
</script>

<template>
    <AppLayout>
        <Head title="Historial de Visitas" />

        <div class="flex flex-col gap-4 p-3 sm:gap-6 sm:p-6">
            <div>
                <h1 class="text-xl font-bold sm:text-2xl">Historial de Visitas</h1>
                <p class="text-sm text-muted-foreground">
                    <span v-if="apartment">Inmueble <strong>{{ apartment }}</strong> · </span>
                    {{ entries.total }} registro(s)
                </p>
            </div>

            <!-- Filtros: tipo arriba y fechas lado a lado en móvil -->
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
                <label class="col-span-2 grid gap-1 text-xs font-medium text-muted-foreground sm:w-44">
                    Tipo
                    <select
                        v-model="localFilters.type"
                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm sm:h-9"
                    >
                        <option value="">Todos</option>
                        <option value="propietario">Propietario</option>
                        <option value="residente">Residente</option>
                        <option value="autorizado">Autorizado</option>
                        <option value="visitante">Visitante</option>
                        <option value="proveedor">Proveedor</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                    Desde
                    <Input v-model="localFilters.date_from" type="date" class="w-full text-foreground sm:w-40" />
                </label>
                <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                    Hasta
                    <Input v-model="localFilters.date_to" type="date" class="w-full text-foreground sm:w-40" />
                </label>
            </div>

            <!-- Móvil: tarjetas -->
            <div class="grid gap-2 sm:hidden">
                <div
                    v-for="entry in entries.data"
                    :key="entry.id"
                    class="flex flex-col gap-1 rounded-xl border bg-card p-3 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ entry.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                CC {{ entry.cedula }}
                                <template v-if="entry.plate">
                                    · {{ vehicleLabel[entry.vehicle] ?? entry.vehicle }}
                                    <span class="font-mono font-semibold text-foreground">{{ entry.plate }}</span>
                                </template>
                            </p>
                        </div>
                        <Badge :variant="typeVariant[entry.type]" class="shrink-0">
                            {{ typeLabel[entry.type] ?? entry.type }}
                        </Badge>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-muted-foreground">
                            {{ entry.entry_at }}
                            <template v-if="entry.exit_at"> → {{ entry.exit_at }}</template>
                        </span>
                        <span v-if="entry.is_inside" class="inline-flex items-center gap-1 font-semibold text-green-600">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                            Dentro
                        </span>
                        <span v-else class="inline-flex items-center gap-1 text-muted-foreground">
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                            Salió
                        </span>
                    </div>
                </div>
                <p
                    v-if="entries.data.length === 0"
                    class="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground"
                >
                    No hay visitas registradas con los filtros actuales.
                </p>
            </div>

            <!-- Tablet y PC: tabla -->
            <div class="hidden overflow-x-auto rounded-xl border bg-card shadow-sm sm:block">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nombre</th>
                            <th class="px-4 py-3 text-left font-medium">Cédula</th>
                            <th class="px-4 py-3 text-left font-medium">Tipo</th>
                            <th class="hidden px-4 py-3 text-left font-medium lg:table-cell">Vehículo</th>
                            <th class="px-4 py-3 text-left font-medium">Ingreso</th>
                            <th class="px-4 py-3 text-left font-medium">Salida</th>
                            <th class="px-4 py-3 text-left font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ entry.full_name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ entry.cedula }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="typeVariant[entry.type]">{{ typeLabel[entry.type] ?? entry.type }}</Badge>
                            </td>
                            <td class="hidden px-4 py-3 text-xs lg:table-cell">
                                <span class="text-muted-foreground">{{ vehicleLabel[entry.vehicle] ?? entry.vehicle }}</span>
                                <span v-if="entry.plate" class="ml-1 font-mono font-semibold">{{ entry.plate }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ entry.entry_at }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ entry.exit_at ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="entry.is_inside"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-600"
                                >
                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                    Dentro
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                    Salió
                                </span>
                            </td>
                        </tr>
                        <tr v-if="entries.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                No hay visitas registradas con los filtros actuales.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="entries" />
        </div>
    </AppLayout>
</template>
