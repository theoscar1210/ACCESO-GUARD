<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Car,
    Check,
    ChevronLeft,
    ChevronRight,
    LogOut,
    MessageSquare,
    PackageCheck,
    Search,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ExitToolsDialog from '@/components/ExitToolsDialog.vue';
import type { MaterialExitTake, ToolMove } from '@/components/ExitToolsDialog.vue';
import MaterialRetireDialog from '@/components/MaterialRetireDialog.vue';
import type { RetirableExit } from '@/components/MaterialRetireDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFitToViewport } from '@/composables/useFitToViewport';
import AppLayout from '@/layouts/AppLayout.vue';

interface Entry {
    id: number;
    full_name: string;
    cedula: string;
    apartment: string;
    type: string;
    vehicle: string;
    plate: string | null;
    observations: string | null;
    entry_at: string;
    work: { title: string; property: string; is_supplier: boolean; tools_inside: number; material_exits: number } | null;
}

const props = defineProps<{ inside: Entry[]; material_exits: RetirableExit[] }>();

// Retiro de material autorizado por cualquier persona con ingreso activo
const retireOpen = ref(false);

const selected = ref<number[]>([]);

const form = useForm({
    entry_ids: [] as number[],
    observations: '',
});

const search = ref('');

// Compara placas ignorando mayúsculas, guiones y espacios ("abc-123" = "ABC123")
const normalizePlate = (value: string) =>
    value.toUpperCase().replace(/[^A-Z0-9]/g, '');

const plateQuery = computed(() => normalizePlate(search.value));

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.inside.filter(
        (e) =>
            e.full_name.toLowerCase().includes(term) ||
            e.cedula.includes(term) ||
            e.apartment.toLowerCase().includes(term) ||
            (plateQuery.value !== '' &&
                !!e.plate &&
                normalizePlate(e.plate).includes(plateQuery.value)),
    );
});

// Ocupantes del vehículo cuya placa coincide exactamente con lo buscado
const plateMatches = computed(() =>
    plateQuery.value.length >= 3
        ? props.inside.filter(
              (e) => e.plate && normalizePlate(e.plate) === plateQuery.value,
          )
        : [],
);

// Al escribir una placa completa se seleccionan todos sus ocupantes para la salida
watch(plateMatches, (matches) => {
    if (matches.length > 0) {
        selected.value = matches.map((e) => e.id);
    }
});

function clearSearch() {
    search.value = '';
    selected.value = [];
}

// ── Sin scroll: la pantalla ocupa el alto visible y caben tantas filas como permita ──
const root = ref<HTMLElement | null>(null);
const { height: rootHeight } = useFitToViewport(root);

const ROW_HEIGHT = 56; // h-14
const ROW_GAP = 6; // gap-1.5
const MAX_PER_PAGE = 10;

const listEl = ref<HTMLElement | null>(null);
const listHeight = ref(0);
let observer: ResizeObserver | null = null;

// La lista puede aparecer después (p. ej. si al cargar no había nadie dentro)
watch(
    listEl,
    (el) => {
        observer?.disconnect();
        if (!el) return;
        observer = new ResizeObserver(([entry]) => {
            listHeight.value = entry.contentRect.height;
        });
        observer.observe(el);
    },
    { flush: 'post' },
);

onBeforeUnmount(() => observer?.disconnect());

const perPage = computed(() => {
    if (listHeight.value === 0) return MAX_PER_PAGE;
    const fits = Math.floor((listHeight.value + ROW_GAP) / (ROW_HEIGHT + ROW_GAP));

    return Math.min(MAX_PER_PAGE, Math.max(1, fits));
});

// Paginación local: la búsqueda sigue recorriendo a todos los que están dentro
const page = ref(1);

const lastPage = computed(() =>
    Math.max(1, Math.ceil(filtered.value.length / perPage.value)),
);

const paged = computed(() =>
    filtered.value.slice(
        (page.value - 1) * perPage.value,
        page.value * perPage.value,
    ),
);

const rangeFrom = computed(() =>
    filtered.value.length === 0 ? 0 : (page.value - 1) * perPage.value + 1,
);
const rangeTo = computed(() =>
    Math.min(page.value * perPage.value, filtered.value.length),
);

// Números de página con "…" cuando hay muchas: 1 … 4 5 6 … 12
const pageItems = computed<(number | '…')[]>(() => {
    const total = lastPage.value;
    if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);

    const current = page.value;
    const items: (number | '…')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    if (start > 2) items.push('…');
    for (let i = start; i <= end; i++) items.push(i);
    if (end < total - 1) items.push('…');
    items.push(total);

    return items;
});

function goToPage(n: number) {
    page.value = Math.min(Math.max(1, n), lastPage.value);
}

// Al buscar se vuelve a la primera página; si la lista se achica (tras registrar
// salidas o al girar el celular) no se queda en una página vacía
watch(search, () => {
    page.value = 1;
});
watch(lastPage, (last) => {
    if (page.value > last) page.value = last;
});

function toggle(id: number) {
    const idx = selected.value.indexOf(id);
    if (idx === -1) selected.value.push(id);
    else selected.value.splice(idx, 1);
}

const allSelected = computed(
    () =>
        filtered.value.length > 0 &&
        selected.value.length === filtered.value.length,
);

function toggleAll() {
    selected.value = allSelected.value ? [] : filtered.value.map((e) => e.id);
}

// ── Herramientas de obra: si sale un trabajador con herramientas dentro, se decide qué sale ──
const toolsDialogOpen = ref(false);

const selectedWorkers = computed(() =>
    props.inside
        .filter((e) => selected.value.includes(e.id) && e.work)
        .map((e) => ({
            id: e.id,
            full_name: e.full_name,
            cedula: e.cedula,
            pending: (e.work?.tools_inside ?? 0) + (e.work?.material_exits ?? 0),
        })),
);

function submit() {
    // Herramientas propias dentro o material autorizado para la casa: hay que decidir antes
    if (selectedWorkers.value.some((w) => w.pending > 0)) {
        toolsDialogOpen.value = true;
        return;
    }
    send([], []);
}

function send(toolMoves: ToolMove[], materialExits: MaterialExitTake[]) {
    form
        .transform((data) => ({ ...data, entry_ids: selected.value, tool_moves: toolMoves, material_exits: materialExits }))
        .post('/vigilante/exits', {
            preserveScroll: true,
            onSuccess: () => {
                toolsDialogOpen.value = false;
                selected.value = [];
                search.value = '';
                form.reset('observations');
            },
        });
}

const toolMovesError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors.tool_moves ?? errors.material_exits;
});

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    propietario: 'default',
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
    automovil: '🚗',
    camioneta: '🚙',
    moto: '🏍',
    bicicleta: '🚲',
};
</script>

<template>
    <AppLayout>
        <Head title="Registrar Salidas" />

        <div
            ref="root"
            :style="{ height: rootHeight }"
            class="flex flex-col gap-2 overflow-hidden p-3 sm:gap-3 sm:p-4"
        >
            <!-- Encabezado -->
            <div class="flex items-center justify-between gap-3">
                <h1 class="min-w-0 truncate text-lg font-bold sm:text-xl">
                    Salidas
                    <span class="ml-1 text-sm font-normal text-muted-foreground">
                        {{ inside.length }} dentro
                    </span>
                </h1>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="material_exits.length"
                        type="button"
                        @click="retireOpen = true"
                        class="inline-flex h-9 items-center gap-1 rounded-md bg-emerald-600 px-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        title="Retiro de material autorizado"
                    >
                        <PackageCheck class="h-4 w-4" /> Retiro ({{ material_exits.length }})
                    </button>
                    <button
                        v-if="filtered.length > 0"
                        type="button"
                        @click="toggleAll"
                        class="text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {{ allSelected ? 'Quitar' : `Todos (${filtered.length})` }}
                    </button>
                </div>
            </div>

            <!-- Sin personas dentro -->
            <div
                v-if="inside.length === 0"
                class="flex flex-1 items-center justify-center rounded-xl border bg-card p-6 text-center text-muted-foreground shadow-sm"
            >
                No hay personas dentro del edificio en este momento.
            </div>

            <template v-else>
                <!-- Búsqueda por nombre, cédula, destino o placa -->
                <div class="relative">
                    <Search class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        autocomplete="off"
                        placeholder="Nombre, cédula, destino o placa..."
                        class="flex h-10 w-full rounded-md border border-input bg-transparent py-1 pr-9 pl-9 text-sm shadow-sm placeholder:text-muted-foreground"
                    />
                    <button
                        v-if="search"
                        type="button"
                        @click="clearSearch"
                        class="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
                        aria-label="Limpiar búsqueda"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Placa encontrada / sin resultados (una sola línea) -->
                <p
                    v-if="plateMatches.length > 0"
                    class="flex items-center gap-2 truncate rounded-md border border-green-200 bg-green-50 px-3 py-1.5 text-sm text-green-800"
                >
                    <Car class="h-4 w-4 shrink-0" />
                    <span class="truncate">
                        Vehículo <span class="font-mono font-bold">{{ plateMatches[0].plate }}</span>:
                        {{ plateMatches.length }} ocupante(s) seleccionado(s)
                    </span>
                </p>
                <p
                    v-else-if="search && filtered.length === 0"
                    class="truncate px-1 text-sm text-muted-foreground"
                >
                    Nadie dentro coincide con «{{ search }}».
                </p>

                <!-- Lista: ocupa el espacio restante, sin scroll -->
                <div ref="listEl" class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-hidden">
                    <button
                        v-for="entry in paged"
                        :key="entry.id"
                        type="button"
                        @click="toggle(entry.id)"
                        :class="[
                            'flex h-14 shrink-0 items-center gap-3 rounded-lg border bg-card px-3 text-left shadow-sm transition-colors',
                            selected.includes(entry.id)
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted/40',
                        ]"
                    >
                        <span
                            :class="[
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition-colors',
                                selected.includes(entry.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground',
                            ]"
                        >
                            <Check v-if="selected.includes(entry.id)" class="h-3 w-3" stroke-width="3" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-semibold">{{ entry.full_name }}</span>
                                <span v-if="entry.observations" :title="entry.observations" class="shrink-0">
                                    <MessageSquare class="h-3.5 w-3.5 text-muted-foreground" />
                                </span>
                                <span
                                    v-if="entry.work?.tools_inside"
                                    :title="`Obra ${entry.work.property}: ${entry.work.tools_inside} herramienta(s) a su nombre`"
                                    class="inline-flex shrink-0 items-center gap-0.5 rounded bg-amber-100 px-1 text-[10px] font-bold text-amber-800"
                                >
                                    <Wrench class="h-3 w-3" />{{ entry.work.tools_inside }}
                                </span>
                                <span
                                    v-if="entry.work?.material_exits"
                                    :title="`Hay ${entry.work.material_exits} salida(s) de material autorizada(s) en ${entry.work.property}`"
                                    class="inline-flex shrink-0 items-center gap-0.5 rounded bg-emerald-100 px-1 text-[10px] font-bold text-emerald-800"
                                >
                                    <PackageCheck class="h-3 w-3" />{{ entry.work.material_exits }}
                                </span>
                            </span>
                            <span class="block truncate text-xs text-muted-foreground">
                                {{ entry.cedula }} · {{ entry.apartment }}
                                <template v-if="entry.plate">
                                    · {{ vehicleLabel[entry.vehicle] ?? '' }}
                                    <span class="font-mono font-semibold tracking-wider text-foreground">{{ entry.plate }}</span>
                                </template>
                            </span>
                        </span>

                        <span class="flex shrink-0 flex-col items-end gap-0.5">
                            <Badge :variant="typeVariant[entry.type]" class="px-1.5 py-0 text-[10px]">
                                {{ typeLabel[entry.type] ?? entry.type }}
                            </Badge>
                            <span class="text-[11px] text-muted-foreground">{{ entry.entry_at }}</span>
                        </span>
                    </button>
                </div>

                <!-- Paginación -->
                <div
                    v-if="filtered.length > 0"
                    class="flex items-center justify-between gap-2 text-xs sm:text-sm"
                >
                    <span class="text-muted-foreground">
                        {{ rangeFrom }}–{{ rangeTo }} de {{ filtered.length }}
                    </span>
                    <nav v-if="lastPage > 1" class="flex items-center gap-0.5" aria-label="Paginación">
                        <button
                            type="button"
                            :disabled="page === 1"
                            @click="goToPage(page - 1)"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-md transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            aria-label="Página anterior"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <template v-for="(item, i) in pageItems" :key="`${item}-${i}`">
                            <span
                                v-if="item === '…'"
                                class="inline-flex h-9 min-w-6 items-center justify-center text-muted-foreground"
                            >…</span>
                            <button
                                v-else
                                type="button"
                                @click="goToPage(item)"
                                :aria-current="item === page ? 'page' : undefined"
                                :class="[
                                    'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-1 transition-colors',
                                    item === page
                                        ? 'bg-primary font-semibold text-primary-foreground'
                                        : 'hover:bg-muted',
                                ]"
                            >
                                {{ item }}
                            </button>
                        </template>
                        <button
                            type="button"
                            :disabled="page === lastPage"
                            @click="goToPage(page + 1)"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-md transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            aria-label="Página siguiente"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>
                    </nav>
                </div>

                <p v-if="toolMovesError" class="truncate text-xs font-medium text-destructive">{{ toolMovesError }}</p>

                <!-- Acción: siempre visible abajo, al alcance del pulgar -->
                <div class="flex items-center gap-2 border-t pt-2">
                    <input
                        v-model="form.observations"
                        type="text"
                        placeholder="Observación (opcional)"
                        class="flex h-11 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm placeholder:text-muted-foreground"
                    />
                    <Button
                        @click="submit"
                        :disabled="selected.length === 0 || form.processing"
                        class="h-11 shrink-0 px-4"
                    >
                        <LogOut class="h-4 w-4" />
                        Registrar salida
                        <span v-if="selected.length > 0">({{ selected.length }})</span>
                    </Button>
                </div>
            </template>
        </div>

        <MaterialRetireDialog v-model:open="retireOpen" :exits="material_exits" :inside="inside" />

        <ExitToolsDialog
            v-model:open="toolsDialogOpen"
            :workers="selectedWorkers"
            :processing="form.processing"
            @confirm="send"
        />
    </AppLayout>
</template>
