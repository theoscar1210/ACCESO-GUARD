<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import type { Paginator } from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

interface Entry {
    id: number;
    full_name: string;
    cedula: string;
    apartment: string;
    type: string;
    vehicle: string;
    plate: string | null;
    entry_at: string;
    exit_at: string | null;
    is_inside: boolean;
    registered_by: string | null;
}

interface PaginatedEntries extends Paginator {
    data: Entry[];
}

interface Filters {
    date_from?: string;
    date_to?: string;
    type?: string;
    apartment?: string;
    search?: string;
}

const props = defineProps<{ entries: PaginatedEntries; filters: Filters }>();

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

// Filtros locales sincronizados
// "type" arranca en '' para que el select muestre "Todos los tipos" y no quede en blanco
const localFilters = ref<Filters>({ type: '', ...props.filters });

let filterTimeout: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get('/admin/entries', localFilters.value, {
            preserveState: true,
            replace: true,
        });
    }, 400);
}

watch(localFilters, applyFilters, { deep: true });

function clearFilters() {
    localFilters.value = { type: '' };
}

const hasFilters = () =>
    Object.values(localFilters.value).some((v) => v && v.toString().length > 0);
</script>

<template>
    <AppLayout>
        <Head title="Historial de Accesos" />

        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold">Historial de Accesos</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ entries.total.toLocaleString() }} registros en total
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="rounded-xl border bg-card p-3 shadow-sm sm:p-4">
                <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4 xl:grid-cols-6">
                    <!-- Búsqueda -->
                    <div class="relative col-span-2 xl:col-span-2">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            v-model="localFilters.search"
                            placeholder="Nombre o cédula..."
                            class="pl-9"
                        />
                    </div>

                    <!-- Destino -->
                    <Input
                        v-model="localFilters.apartment"
                        placeholder="Destino..."
                        title="Inmueble o administración"
                    />

                    <!-- Tipo -->
                    <select
                        v-model="localFilters.type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="propietario">Propietario</option>
                        <option value="residente">Residente</option>
                        <option value="autorizado">Autorizado</option>
                        <option value="visitante">Visitante</option>
                        <option value="proveedor">Proveedor</option>
                    </select>

                    <!-- Fechas -->
                    <label class="grid gap-0.5 text-xs text-muted-foreground">
                        Desde
                        <Input v-model="localFilters.date_from" type="date" class="text-foreground" />
                    </label>
                    <label class="grid gap-0.5 text-xs text-muted-foreground">
                        Hasta
                        <Input v-model="localFilters.date_to" type="date" class="text-foreground" />
                    </label>
                </div>
                <button
                    v-if="hasFilters()"
                    type="button"
                    @click="clearFilters"
                    class="mt-2 flex items-center gap-1 text-xs text-primary underline-offset-4 hover:underline"
                >
                    <X class="h-3.5 w-3.5" />
                    Limpiar filtros
                </button>
            </div>

            <!-- Móvil y tablet: tarjetas -->
            <div class="grid gap-2 sm:grid-cols-2 lg:hidden">
                <div
                    v-for="entry in entries.data"
                    :key="entry.id"
                    class="flex flex-col gap-1.5 rounded-xl border bg-card p-3 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ entry.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                CC {{ entry.cedula }} · {{ entry.apartment }}
                            </p>
                        </div>
                        <Badge :variant="typeVariant[entry.type]" class="shrink-0">
                            {{ typeLabel[entry.type] ?? entry.type }}
                        </Badge>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs">
                        <span class="text-muted-foreground">
                            {{ entry.entry_at }}
                            <template v-if="entry.exit_at"> → {{ entry.exit_at }}</template>
                        </span>
                        <span class="flex items-center gap-2">
                            <span v-if="entry.plate" class="font-mono font-semibold">
                                {{ vehicleLabel[entry.vehicle] ?? entry.vehicle }} {{ entry.plate }}
                            </span>
                            <span
                                v-if="entry.is_inside"
                                class="inline-flex items-center gap-1 font-semibold text-green-600"
                            >
                                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                Dentro
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-muted-foreground">
                                <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                Salió
                            </span>
                        </span>
                    </div>
                </div>
                <p
                    v-if="entries.data.length === 0"
                    class="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground sm:col-span-2"
                >
                    No se encontraron registros con los filtros actuales.
                </p>
            </div>

            <!-- PC: tabla -->
            <div class="hidden overflow-x-auto rounded-xl border bg-card shadow-sm lg:block">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nombre</th>
                            <th class="px-4 py-3 text-left font-medium">Cédula</th>
                            <th class="px-4 py-3 text-left font-medium">Destino</th>
                            <th class="px-4 py-3 text-left font-medium">Tipo</th>
                            <th class="px-4 py-3 text-left font-medium">Vehículo / Placa</th>
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
                            <td class="px-4 py-3">{{ entry.apartment }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="typeVariant[entry.type]">{{ typeLabel[entry.type] ?? entry.type }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-xs">
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
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">
                                No se encontraron registros con los filtros actuales.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="entries" />
        </div>
    </AppLayout>
</template>
