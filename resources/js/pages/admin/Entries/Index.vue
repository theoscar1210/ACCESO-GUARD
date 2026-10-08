<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

interface PaginatedEntries {
    data: Entry[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
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
    autorizado: 'secondary',
    visitante: 'outline',
};

const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
};

const vehicleLabel: Record<string, string> = {
    ninguno: '—',
    automovil: 'Auto',
    camioneta: 'Camioneta',
    moto: 'Moto',
    bicicleta: 'Bici',
};

// Filtros locales sincronizados
const localFilters = ref<Filters>({ ...props.filters });

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
    localFilters.value = {};
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
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5"
                >
                    <!-- Búsqueda -->
                    <div class="relative sm:col-span-2 xl:col-span-1">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            v-model="localFilters.search"
                            placeholder="Nombre o cédula..."
                            class="pl-9"
                        />
                    </div>

                    <!-- Apartamento -->
                    <Input
                        v-model="localFilters.apartment"
                        placeholder="Destino (inmueble o administración)..."
                    />

                    <!-- Tipo -->
                    <select
                        v-model="localFilters.type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="propietario">Propietario</option>
                        <option value="autorizado">Autorizado</option>
                        <option value="visitante">Visitante</option>
                    </select>

                    <!-- Fechas -->
                    <Input
                        v-model="localFilters.date_from"
                        type="date"
                        placeholder="Desde"
                    />
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="localFilters.date_to"
                            type="date"
                            placeholder="Hasta"
                        />
                        <Button
                            v-if="hasFilters()"
                            @click="clearFilters"
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                            title="Limpiar filtros"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Nombre
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Cédula
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Apto
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Tipo
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Vehículo / Placa
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Ingreso
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Salida
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Estado
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ entry.full_name }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-muted-foreground"
                            >
                                {{ entry.cedula }}
                            </td>
                            <td class="px-4 py-3">{{ entry.apartment }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="typeVariant[entry.type]">{{
                                    typeLabel[entry.type] ?? entry.type
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="text-muted-foreground">{{
                                    vehicleLabel[entry.vehicle] ?? entry.vehicle
                                }}</span>
                                <span
                                    v-if="entry.plate"
                                    class="ml-1 font-mono font-semibold"
                                    >{{ entry.plate }}</span
                                >
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ entry.entry_at }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-muted-foreground"
                            >
                                {{ entry.exit_at ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="entry.is_inside"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-600"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-green-500"
                                    ></span>
                                    Dentro
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-slate-300"
                                    ></span>
                                    Salió
                                </span>
                            </td>
                        </tr>
                        <tr v-if="entries.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                No se encontraron registros con los filtros
                                actuales.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="entries.last_page > 1"
                class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground">
                    Mostrando {{ entries.from }}–{{ entries.to }} de
                    {{ entries.total.toLocaleString() }} registros
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="link in entries.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            :class="[
                                'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition-colors',
                                link.active
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : 'hover:bg-muted',
                            ]"
                        >
                            <ChevronLeft
                                v-if="link.label === '&laquo; Previous'"
                                class="h-4 w-4"
                            />
                            <ChevronRight
                                v-else-if="link.label === 'Next &raquo;'"
                                class="h-4 w-4"
                            />
                            <span v-else>{{ link.label }}</span>
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground opacity-50"
                        >
                            <ChevronLeft
                                v-if="link.label === '&laquo; Previous'"
                                class="h-4 w-4"
                            />
                            <ChevronRight
                                v-else-if="link.label === 'Next &raquo;'"
                                class="h-4 w-4"
                            />
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
