<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    Car,
    CheckCircle2,
    HardHat,
    History,
    Loader2,
    MessageSquarePlus,
    User,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EntryItemRow } from '@/components/WorkItemsInput.vue';
import WorkItemsInput from '@/components/WorkItemsInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { WorkItemInfo } from '@/lib/works';

interface PropertyOption {
    number: string;
    label: string;
    type: string;
}

interface CurrentWork {
    id: number;
    title: string;
    property_number: string;
    property: string;
}

const props = defineProps<{ properties: PropertyOption[]; current_works: CurrentWork[] }>();

const form = useForm({
    first_name: '',
    last_name: '',
    cedula: '',
    apartment: '',
    type: 'visitante',
    vehicle: 'ninguno',
    plate: '',
    observations: '',
    work_worker_id: null as number | null,
    // Proveedor: obra a la que entrega y su empresa
    work_id: null as number | null,
    supplier_company: '',
    items: [] as EntryItemRow[],
});

const isSupplier = computed(() => form.type === 'proveedor');

// El destino de una entrega es la casa de la obra
watch(
    () => form.work_id,
    (id) => {
        const work = props.current_works.find((w) => w.id === id);
        if (work) form.apartment = work.property_number;
    },
);

// Los ítems de trabajador y los de proveedor no se mezclan
watch(
    () => form.type,
    (type, previous) => {
        if (type === 'proveedor' || previous === 'proveedor') form.items = [];
    },
);

interface AuthorizationInfo {
    type: string;
    plate: string | null;
    vehicle: string | null;
    end_date: string | null;
}

interface WorkInfo {
    worker_id: number;
    worker_name: string;
    work_id: number;
    title: string;
    company: string;
    property_number: string;
    property: string;
    status: string;
    is_current: boolean;
    tools_inside: WorkItemInfo[];
    reentry_items: WorkItemInfo[];
}

interface LookupResult {
    work?: WorkInfo | null;
    supplier?: { company: string | null; work_id: number | null } | null;
    first_name: string;
    last_name: string;
    apartment: string | null;
    to_administration?: boolean;
    vehicle?: string | null;
    type: string;
    known_in_system: boolean;
    authorization: AuthorizationInfo | null;
}

interface PlateLookupResult extends LookupResult {
    cedula: string;
    plate: string | null;
    source: 'entry' | 'authorization';
    last_entry_at: string | null;
}

// Opción "Administración" del selector de destino
const ADMINISTRATION = '__administracion__';

const authorization = ref<AuthorizationInfo | null>(null);
const noAuthorization = ref(false);
const lookupTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const plateTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const searching = ref(false);
const searchingPlate = ref(false);
const lookupDone = ref(false);
const knownInSystem = ref(false);
// Obra del trabajador (si la cédula pertenece a un trabajador de obra)
const workInfo = ref<WorkInfo | null>(null);
// De dónde salieron los datos al buscar por placa (para avisar al guarda)
const plateFill = ref<{ source: 'entry' | 'authorization'; date: string | null } | null>(null);
// Evita que rellenar la cédula desde la placa dispare otra búsqueda que pise los datos
let skipCedulaLookup = false;

function applyLookupResult(data: LookupResult) {
    form.first_name = data.first_name ?? form.first_name;
    form.last_name = data.last_name ?? form.last_name;
    // Solo se precarga si el inmueble sigue registrado (el selector no admite otros valores)
    if (data.apartment && props.properties.some((p) => p.number === data.apartment)) {
        form.apartment = data.apartment;
    } else if (data.to_administration) {
        form.apartment = ADMINISTRATION;
    }
    if (data.vehicle) {
        form.vehicle = data.vehicle;
    }
    form.type = data.type ?? form.type;
    authorization.value = data.authorization ?? null;
    noAuthorization.value = !data.authorization && data.type === 'visitante';
    knownInSystem.value = data.known_in_system ?? false;
    lookupDone.value = true;
    applyWork(data.work ?? null);

    // Proveedor recurrente: se recuerda su empresa y la obra de la última entrega
    if (data.supplier) {
        form.supplier_company = data.supplier.company ?? form.supplier_company;
        if (data.supplier.work_id && props.current_works.some((w) => w.id === data.supplier?.work_id)) {
            form.work_id = data.supplier.work_id;
        }
    }
}

// Solo una obra aprobada y vigente permite registrar herramientas
function applyWork(work: WorkInfo | null) {
    if (workInfo.value?.worker_id !== work?.worker_id) form.items = [];
    workInfo.value = work;
    form.work_worker_id = work?.is_current ? work.worker_id : null;
}

async function lookup(cedula: string) {
    if (cedula.length < 3) {
        lookupDone.value = false;
        noAuthorization.value = false;
        authorization.value = null;
        knownInSystem.value = false;
        return;
    }
    searching.value = true;
    try {
        const res = await fetch(
            `/vigilante/entries/lookup?cedula=${encodeURIComponent(cedula)}`,
        );
        const data: LookupResult | null = await res.json();
        if (data) {
            applyLookupResult(data);
        } else {
            authorization.value = null;
            noAuthorization.value = false;
            knownInSystem.value = false;
            lookupDone.value = true;
        }
    } finally {
        searching.value = false;
    }
}

async function lookupPlate(plate: string) {
    if (plate.length < 3) return;
    searchingPlate.value = true;
    try {
        const res = await fetch(
            `/vigilante/entries/lookup-plate?plate=${encodeURIComponent(plate)}`,
        );
        const data: PlateLookupResult | null = await res.json();
        if (data && data.cedula) {
            if (form.cedula !== data.cedula) {
                skipCedulaLookup = true;
                form.cedula = data.cedula;
            }
            applyLookupResult(data);
            plateFill.value = { source: data.source, date: data.last_entry_at };
        } else {
            plateFill.value = null;
        }
    } finally {
        searchingPlate.value = false;
    }
}

watch(
    () => form.cedula,
    (val) => {
        if (lookupTimeout.value) clearTimeout(lookupTimeout.value);
        if (skipCedulaLookup) {
            skipCedulaLookup = false;
            return;
        }
        authorization.value = null;
        noAuthorization.value = false;
        lookupDone.value = false;
        knownInSystem.value = false;
        applyWork(null);
        lookupTimeout.value = setTimeout(() => lookup(val), 500);
    },
);

watch(
    () => form.plate,
    (val) => {
        if (plateTimeout.value) clearTimeout(plateTimeout.value);
        plateFill.value = null;

        // Con placa hay que indicar el tipo de vehículo; sin placa vuelve a peatón
        if (val && form.vehicle === 'ninguno') form.vehicle = '';
        if (!val && form.vehicle === '') form.vehicle = 'ninguno';

        if (!val || val.length < 3) return;
        plateTimeout.value = setTimeout(() => lookupPlate(val), 600);
    },
);

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const cedula = params.get('cedula');
    if (cedula) {
        form.cedula = cedula;
        lookup(cedula);
    }
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            apartment: data.apartment === ADMINISTRATION ? '' : data.apartment,
            to_administration: data.apartment === ADMINISTRATION,
            // Solo se envían ítems de trabajadores o proveedores de una obra vigente; la vista previa no viaja
            items: data.work_worker_id || (data.type === 'proveedor' && data.work_id)
                ? data.items.map(({ item_id, name, serial, kind, quantity, photo }) => ({ item_id, name, serial, kind, quantity, photo }))
                : [],
            work_id: data.type === 'proveedor' ? data.work_id : null,
            supplier_company: data.type === 'proveedor' ? data.supplier_company : null,
        }))
        .post('/vigilante/entries');
}

// En móvil se usan etiquetas cortas para que los 5 tipos quepan en una fila
const typeOptions = [
    { value: 'propietario', label: 'Propietario', short: 'Propiet.', color: 'blue' },
    { value: 'residente', label: 'Residente', short: 'Resid.', color: 'purple' },
    { value: 'autorizado', label: 'Autorizado', short: 'Autoriz.', color: 'green' },
    { value: 'visitante', label: 'Visitante', short: 'Visita', color: 'amber' },
    { value: 'proveedor', label: 'Proveedor', short: 'Proveed.', color: 'orange' },
];

const typeButtonClass = (value: string, color: string) => {
    const active = form.type === value;
    const map: Record<string, string> = {
        blue: active
            ? 'border-blue-400 bg-blue-50 text-blue-800 ring-2 ring-blue-300'
            : 'border-input hover:border-blue-300 hover:bg-blue-50/50',
        purple: active
            ? 'border-purple-400 bg-purple-50 text-purple-800 ring-2 ring-purple-300'
            : 'border-input hover:border-purple-300 hover:bg-purple-50/50',
        green: active
            ? 'border-green-400 bg-green-50 text-green-800 ring-2 ring-green-300'
            : 'border-input hover:border-green-300 hover:bg-green-50/50',
        amber: active
            ? 'border-amber-400 bg-amber-50 text-amber-800 ring-2 ring-amber-300'
            : 'border-input hover:border-amber-300 hover:bg-amber-50/50',
        orange: active
            ? 'border-orange-400 bg-orange-50 text-orange-800 ring-2 ring-orange-300'
            : 'border-input hover:border-orange-300 hover:bg-orange-50/50',
    };
    return map[color];
};

function clearForm() {
    form.reset();
    form.clearErrors();
    authorization.value = null;
    noAuthorization.value = false;
    lookupDone.value = false;
    knownInSystem.value = false;
    plateFill.value = null;
    showObservations.value = false;
    applyWork(null);
}

// Errores con claves que no son campos directos del formulario (active_entry, items.0.name…)
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const activeEntryError = computed(() => formErrors.value.active_entry);

const propertyTypeLabel: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    local: 'Local',
};

// "Apartamento 101", pero "Casa-01" en vez de "Casa Casa-01"
function propertyOptionLabel(p: PropertyOption): string {
    const type = propertyTypeLabel[p.type] ?? p.type;

    return p.label.toLowerCase().includes(type.toLowerCase())
        ? p.label
        : `${type} ${p.label}`;
}

// La observación queda oculta tras un enlace para que el formulario quepa sin scroll
const showObservations = ref(false);
watch(
    () => form.observations || form.errors.observations,
    (value) => {
        if (value) showObservations.value = true;
    },
);

const typeShortLabel: Record<string, string> = {
    visitante: 'visitante',
    autorizado: 'permanente',
};
</script>

<template>
    <AppLayout>
        <Head title="Registrar Ingreso" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-2.5 p-3 sm:p-4">
            <!-- Encabezado -->
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-lg font-bold sm:text-xl">Registrar ingreso</h1>
                <Link
                    href="/vigilante/entries"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Monitor
                </Link>
            </div>

            <!-- Ingreso duplicado: bloquea el registro, se muestra destacado pero compacto -->
            <div
                v-if="activeEntryError"
                class="flex items-start gap-2 rounded-lg border-2 border-red-300 bg-red-50 px-3 py-2 text-xs text-red-900 sm:text-sm"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                <p><span class="font-bold">Ingreso duplicado.</span> {{ activeEntryError }}</p>
            </div>

            <!-- Estado de la persona: etiquetas de una línea en vez de cajas grandes -->
            <div
                v-if="workInfo || plateFill || (lookupDone && (knownInSystem || authorization || noAuthorization))"
                class="flex flex-wrap gap-1.5 text-xs"
            >
                <span
                    v-if="workInfo"
                    :class="[
                        'inline-flex max-w-full items-center gap-1 truncate rounded-full px-2 py-0.5 font-medium',
                        workInfo.is_current ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800',
                    ]"
                >
                    <HardHat class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">
                        Obra {{ workInfo.property }} · {{ workInfo.title }}
                        <template v-if="!workInfo.is_current">
                            · {{ workInfo.status === 'pendiente' ? 'pendiente de aprobación' : workInfo.status === 'aprobada' ? 'fuera de fechas' : workInfo.status }}: no puede ingresar herramientas
                        </template>
                    </span>
                </span>
                <span
                    v-if="plateFill?.source === 'entry'"
                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-800"
                >
                    <History class="h-3.5 w-3.5" />
                    Último ingreso {{ plateFill.date }}
                </span>
                <span
                    v-else-if="plateFill?.source === 'authorization'"
                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800"
                >
                    <Car class="h-3.5 w-3.5" />
                    Placa autorizada
                </span>
                <span
                    v-if="lookupDone && knownInSystem"
                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-800"
                >
                    <BadgeCheck class="h-3.5 w-3.5" />
                    Registrado
                </span>
                <span
                    v-if="authorization"
                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    Autorizado {{ typeShortLabel[authorization.type] ?? authorization.type }}
                    · {{ authorization.end_date ? `hasta ${authorization.end_date}` : 'sin vencimiento' }}
                    <span v-if="authorization.plate" class="font-mono font-semibold">· {{ authorization.plate }}</span>
                </span>
                <span
                    v-if="noAuthorization && lookupDone && !knownInSystem && form.cedula.length >= 3"
                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800"
                >
                    <AlertCircle class="h-3.5 w-3.5" />
                    Sin autorización previa
                </span>
            </div>

            <form
                @submit.prevent="submit"
                autocomplete="off"
                novalidate
                class="grid grid-cols-2 gap-x-3 gap-y-2.5 rounded-xl border bg-card p-3 shadow-sm sm:p-4 lg:grid-cols-4"
            >
                <!-- Placa: con solo la placa se carga el último ingreso -->
                <div class="grid content-start gap-1">
                    <Label for="plate" class="flex items-center gap-1 text-xs">
                        <Car class="h-3.5 w-3.5 text-muted-foreground" />
                        Placa
                    </Label>
                    <div class="relative">
                        <Input
                            id="plate"
                            v-model="form.plate"
                            placeholder="ABC-123"
                            autofocus
                            maxlength="20"
                            class="pr-8 font-mono text-base tracking-widest uppercase placeholder:tracking-normal"
                            @input="form.plate = (form.plate ?? '').toUpperCase()"
                        />
                        <Loader2
                            v-if="searchingPlate"
                            class="absolute top-2.5 right-2.5 h-4 w-4 animate-spin text-muted-foreground"
                        />
                    </div>
                    <InputError :message="form.errors.plate" class="text-xs" />
                </div>

                <!-- Tipo de vehículo -->
                <div class="grid content-start gap-1">
                    <Label for="vehicle" class="text-xs">Vehículo *</Label>
                    <select
                        id="vehicle"
                        v-model="form.vehicle"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm"
                    >
                        <option value="" disabled>Tipo...</option>
                        <option value="ninguno" :disabled="!!form.plate">A pie</option>
                        <option value="automovil">Automóvil</option>
                        <option value="camioneta">Camioneta</option>
                        <option value="moto">Moto</option>
                        <option value="bicicleta">Bicicleta</option>
                    </select>
                    <InputError :message="form.errors.vehicle" class="text-xs" />
                </div>

                <!-- Cédula -->
                <div class="grid content-start gap-1">
                    <Label for="cedula" class="flex items-center gap-1 text-xs">
                        <User class="h-3.5 w-3.5 text-muted-foreground" />
                        Cédula *
                    </Label>
                    <div class="relative">
                        <Input
                            id="cedula"
                            v-model="form.cedula"
                            placeholder="Número"
                            inputmode="numeric"
                            class="pr-8 font-mono text-base"
                        />
                        <Loader2
                            v-if="searching"
                            class="absolute top-2.5 right-2.5 h-4 w-4 animate-spin text-muted-foreground"
                        />
                    </div>
                    <InputError :message="form.errors.cedula" class="text-xs" />
                </div>

                <!-- Destino: casa, apartamento o administración -->
                <div class="grid content-start gap-1">
                    <Label for="apartment" class="text-xs">Destino *</Label>
                    <select
                        id="apartment"
                        v-model="form.apartment"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm"
                    >
                        <option value="" disabled>Destino...</option>
                        <option :value="ADMINISTRATION">Administración</option>
                        <optgroup v-if="properties.length" label="Casas y apartamentos">
                            <option
                                v-for="p in properties"
                                :key="p.number"
                                :value="p.number"
                            >
                                {{ propertyOptionLabel(p) }}
                            </option>
                        </optgroup>
                    </select>
                    <InputError :message="form.errors.apartment" class="text-xs" />
                </div>

                <!-- Nombres y apellidos -->
                <div class="grid content-start gap-1 lg:col-span-2">
                    <Label for="first_name" class="text-xs">Nombres *</Label>
                    <Input id="first_name" v-model="form.first_name" placeholder="Nombres" />
                    <InputError :message="form.errors.first_name" class="text-xs" />
                </div>
                <div class="grid content-start gap-1 lg:col-span-2">
                    <Label for="last_name" class="text-xs">Apellidos *</Label>
                    <Input id="last_name" v-model="form.last_name" placeholder="Apellidos" />
                    <InputError :message="form.errors.last_name" class="text-xs" />
                </div>

                <!-- Tipo de persona: 4 botones en una fila -->
                <div class="col-span-2 grid gap-1 lg:col-span-4">
                    <Label class="text-xs">Tipo de persona *</Label>
                    <div class="grid grid-cols-5 gap-1">
                        <button
                            v-for="opt in typeOptions"
                            :key="opt.value"
                            type="button"
                            @click="form.type = opt.value"
                            :class="[
                                'h-10 truncate rounded-lg border-2 px-1 text-xs font-semibold transition-all sm:text-sm',
                                typeButtonClass(opt.value, opt.color),
                            ]"
                        >
                            <span class="sm:hidden">{{ opt.short }}</span>
                            <span class="hidden sm:inline">{{ opt.label }}</span>
                        </button>
                    </div>
                    <InputError :message="form.errors.type" class="text-xs" />
                </div>

                <!-- Proveedor (ferretería, depósito…): entrega material a una obra vigente -->
                <div v-if="isSupplier" class="col-span-2 flex flex-col gap-2 lg:col-span-4">
                    <p v-if="current_works.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        No hay obras aprobadas y vigentes: no se puede registrar una entrega de material.
                    </p>
                    <div v-else class="grid grid-cols-2 gap-x-3 gap-y-2">
                        <div class="grid content-start gap-1">
                            <Label for="work_id" class="flex items-center gap-1 text-xs">
                                <HardHat class="h-3.5 w-3.5 text-muted-foreground" /> Obra *
                            </Label>
                            <select
                                id="work_id"
                                v-model="form.work_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm"
                            >
                                <option :value="null" disabled>Obra...</option>
                                <option v-for="w in current_works" :key="w.id" :value="w.id">{{ w.property }} · {{ w.title }}</option>
                            </select>
                            <InputError :message="formErrors.work_id" class="text-xs" />
                        </div>
                        <div class="grid content-start gap-1">
                            <Label for="supplier_company" class="text-xs">Empresa *</Label>
                            <Input id="supplier_company" v-model="form.supplier_company" placeholder="Ej: Ferretería El Tornillo" />
                            <InputError :message="formErrors.supplier_company" class="text-xs" />
                        </div>
                    </div>
                    <WorkItemsInput
                        v-if="form.work_id"
                        v-model="form.items"
                        material-only
                        :errors="formErrors"
                    />
                    <InputError :message="formErrors.items" class="text-xs" />
                </div>

                <!-- Trabajador de obra vigente: herramientas y materiales que ingresa -->
                <div v-if="workInfo?.is_current && !isSupplier" class="col-span-2 lg:col-span-4">
                    <WorkItemsInput
                        v-model="form.items"
                        :tools-inside="workInfo.tools_inside"
                        :reentry-items="workInfo.reentry_items"
                        :errors="formErrors"
                    />
                    <InputError :message="formErrors.work_worker_id ?? formErrors.items" class="text-xs" />
                </div>

                <!-- Observación (opcional, oculta tras un enlace) -->
                <div class="col-span-2 lg:col-span-4">
                    <Input
                        v-if="showObservations"
                        id="observations"
                        v-model="form.observations"
                        placeholder="Observación (opcional)"
                    />
                    <button
                        v-else
                        type="button"
                        @click="showObservations = true"
                        class="flex items-center gap-1 text-xs text-primary underline-offset-4 hover:underline"
                    >
                        <MessageSquarePlus class="h-3.5 w-3.5" />
                        Agregar observación
                    </button>
                    <InputError :message="form.errors.observations" class="text-xs" />
                </div>

                <!-- Acciones -->
                <div class="col-span-2 flex gap-2 lg:col-span-4">
                    <Button type="button" variant="outline" class="h-11" @click="clearForm">
                        Limpiar
                    </Button>
                    <Button type="submit" class="h-11 flex-1 text-base" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ form.processing ? 'Registrando...' : 'Registrar ingreso' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

