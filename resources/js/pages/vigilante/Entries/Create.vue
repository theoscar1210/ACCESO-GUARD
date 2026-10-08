<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    BadgeCheck,
    CheckCircle2,
    Loader2,
    User,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';

interface PropertyOption {
    number: string;
    label: string;
    type: string;
}

const props = defineProps<{ properties: PropertyOption[] }>();

const { auth } = usePage<{ auth: Auth }>().props;

const form = useForm({
    first_name: '',
    last_name: '',
    cedula: '',
    apartment: '',
    type: 'visitante',
    vehicle: 'ninguno',
    plate: '',
    observations: '',
});

interface AuthorizationInfo {
    type: string;
    plate: string | null;
    end_date: string | null;
}

interface LookupResult {
    first_name: string;
    last_name: string;
    apartment: string | null;
    type: string;
    known_in_system: boolean;
    authorization: AuthorizationInfo | null;
}

const authorization = ref<AuthorizationInfo | null>(null);
const noAuthorization = ref(false);
const lookupTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const plateTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const searching = ref(false);
const searchingPlate = ref(false);
const lookupDone = ref(false);
const knownInSystem = ref(false);

function applyLookupResult(data: LookupResult) {
    form.first_name = data.first_name ?? form.first_name;
    form.last_name = data.last_name ?? form.last_name;
    // Solo se precarga si el inmueble sigue registrado (el selector no admite otros valores)
    if (data.apartment && props.properties.some((p) => p.number === data.apartment)) {
        form.apartment = data.apartment;
    }
    form.type = data.type ?? form.type;
    authorization.value = data.authorization ?? null;
    noAuthorization.value = !data.authorization && data.type === 'visitante';
    knownInSystem.value = data.known_in_system ?? false;
    lookupDone.value = true;
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
        const data: (LookupResult & { cedula: string }) | null =
            await res.json();
        if (data) {
            form.cedula = data.cedula;
            applyLookupResult(data);
        }
    } finally {
        searchingPlate.value = false;
    }
}

watch(
    () => form.cedula,
    (val) => {
        if (lookupTimeout.value) clearTimeout(lookupTimeout.value);
        authorization.value = null;
        noAuthorization.value = false;
        lookupDone.value = false;
        knownInSystem.value = false;
        lookupTimeout.value = setTimeout(() => lookup(val), 500);
    },
);

watch(
    () => form.plate,
    (val) => {
        if (!val || val.length < 3) return;
        if (plateTimeout.value) clearTimeout(plateTimeout.value);
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

// Opción "Administración" del selector de destino
const ADMINISTRATION = '__administracion__';

function submit() {
    form
        .transform((data) => ({
            ...data,
            apartment: data.apartment === ADMINISTRATION ? '' : data.apartment,
            to_administration: data.apartment === ADMINISTRATION,
        }))
        .post('/vigilante/entries');
}

const typeOptions = [
    { value: 'propietario', label: 'Propietario', color: 'blue' },
    { value: 'residente', label: 'Residente', color: 'purple' },
    { value: 'autorizado', label: 'Autorizado', color: 'green' },
    { value: 'visitante', label: 'Visitante', color: 'amber' },
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
    };
    return map[color];
};

const needsPlate = computed(() => form.vehicle !== 'ninguno');

// Error devuelto por el servidor que no corresponde a un campo del formulario
const activeEntryError = computed(
    () => (form.errors as Record<string, string | undefined>).active_entry,
);

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
</script>

<template>
    <AppLayout>
        <Head title="Registrar Ingreso" />

        <div class="mx-auto max-w-2xl p-4 sm:p-6">
            <div class="mb-6 flex flex-col gap-1">
                <Link href="/vigilante/entries" class="text-sm text-muted-foreground hover:text-foreground">← Monitor de Ingresos</Link>
                <h1 class="text-2xl font-bold">Registrar Ingreso</h1>
                <p class="text-sm text-muted-foreground">
                    Vigilante: {{ auth.user.first_name }}
                    {{ auth.user.last_name }}
                </p>
            </div>

            <!-- Alerta: INGRESO ACTIVO (bloqueo prominente) -->
            <div
                v-if="activeEntryError"
                class="mb-4 flex items-start gap-3 rounded-xl border-2 border-red-300 bg-red-50 px-4 py-4 text-sm text-red-900"
            >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <div>
                    <p class="font-bold text-base">Ingreso duplicado</p>
                    <p class="mt-0.5 text-red-700">{{ activeEntryError }}</p>
                </div>
            </div>

            <!-- Persona conocida en el sistema -->
            <Transition name="alert">
                <div
                    v-if="knownInSystem && lookupDone"
                    class="mb-4 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                >
                    <BadgeCheck class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                    <p class="font-semibold">
                        Persona registrada en el sistema
                    </p>
                </div>
            </Transition>

            <!-- Alerta: AUTORIZACIÓN ACTIVA -->
            <Transition name="alert">
                <div
                    v-if="authorization"
                    class="mb-4 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    <CheckCircle2
                        class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                    />
                    <div>
                        <p class="font-semibold">
                            Autorización activa encontrada
                        </p>
                        <p class="text-green-700">
                            Tipo:
                            <span class="font-medium">{{
                                authorization.type
                            }}</span>
                            <span v-if="authorization.end_date">
                                &mdash; Válida hasta:
                                <span class="font-medium">{{
                                    authorization.end_date
                                }}</span></span
                            >
                            <span v-else>
                                &mdash; Sin fecha de vencimiento</span
                            >
                        </p>
                        <p v-if="authorization.plate" class="text-green-700">
                            Vehículo autorizado:
                            <span class="font-mono font-semibold tracking-wider">{{ authorization.plate }}</span>
                        </p>
                    </div>
                </div>
            </Transition>

            <!-- Alerta: SIN AUTORIZACIÓN (solo para visitantes no conocidos) -->
            <Transition name="alert">
                <div
                    v-if="
                        noAuthorization &&
                        lookupDone &&
                        !knownInSystem &&
                        form.cedula.length >= 3
                    "
                    class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    <AlertCircle
                        class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                    />
                    <div>
                        <p class="font-semibold">Sin autorización previa</p>
                        <p class="text-amber-700">
                            Esta persona no tiene autorización activa. Puedes
                            registrar el ingreso de todas formas.
                        </p>
                    </div>
                </div>
            </Transition>

            <form
                @submit.prevent="submit"
                autocomplete="off"
                novalidate
                class="space-y-5 rounded-xl border bg-card p-6 shadow-sm"
            >
                <!-- Cédula con indicador de búsqueda -->
                <div class="grid gap-1.5">
                    <Label for="cedula">Cédula *</Label>
                    <div class="relative">
                        <Input
                            id="cedula"
                            v-model="form.cedula"
                            placeholder="Número de cédula"
                            autocomplete="off"
                            class="pr-28 font-mono text-base"
                        />
                        <span
                            v-if="searching"
                            class="absolute top-2.5 right-3 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Loader2 class="h-3 w-3 animate-spin" />
                            Consultando...
                        </span>
                        <span
                            v-else-if="lookupDone && knownInSystem"
                            class="absolute top-2.5 right-3 flex items-center gap-1 text-xs font-medium text-blue-600"
                        >
                            <User class="h-3 w-3" />
                            Registrado
                        </span>
                        <span
                            v-else-if="lookupDone && authorization"
                            class="absolute top-2.5 right-3 text-xs font-medium text-green-600"
                        >
                            ✓ Autorizado
                        </span>
                    </div>
                    <InputError :message="form.errors.cedula" class="text-xs" />
                </div>

                <!-- Nombres y apellidos -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="first_name">Nombres *</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            placeholder="Nombres"
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="last_name">Apellidos *</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            placeholder="Apellidos"
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <!-- Destino: casa, apartamento o administración -->
                <div class="grid gap-1.5">
                    <Label for="apartment">Destino *</Label>
                    <select
                        id="apartment"
                        v-model="form.apartment"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="" disabled>Selecciona el destino...</option>
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
                    <InputError :message="form.errors.apartment" />
                </div>

                <!-- Tipo — 4 botones visuales -->
                <div class="grid gap-2">
                    <Label>Tipo de persona *</Label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="opt in typeOptions"
                            :key="opt.value"
                            type="button"
                            @click="form.type = opt.value"
                            :class="[
                                'flex flex-col items-center rounded-lg border-2 py-3 text-sm font-semibold transition-all',
                                typeButtonClass(opt.value, opt.color),
                            ]"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <InputError :message="form.errors.type" />
                </div>

                <!-- Vehículo -->
                <div class="grid gap-1.5">
                    <Label for="vehicle">Vehículo</Label>
                    <select
                        id="vehicle"
                        v-model="form.vehicle"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="ninguno">Ninguno / Peatón</option>
                        <option value="automovil">Automóvil</option>
                        <option value="camioneta">Camioneta</option>
                        <option value="moto">Moto</option>
                        <option value="bicicleta">Bicicleta</option>
                    </select>
                    <InputError :message="form.errors.vehicle" />
                </div>

                <!-- Placa — aparece cuando tiene vehículo -->
                <Transition name="alert">
                    <div v-if="needsPlate" class="grid gap-1.5">
                        <Label for="plate">Placa del vehículo</Label>
                        <div class="relative">
                            <Input
                                id="plate"
                                v-model="form.plate"
                                placeholder="Ej: ABC-123"
                                class="font-mono tracking-widest uppercase"
                                @input="
                                    form.plate = (
                                        form.plate ?? ''
                                    ).toUpperCase()
                                "
                            />
                            <span
                                v-if="searchingPlate"
                                class="absolute top-2.5 right-3 flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Loader2 class="h-3 w-3 animate-spin" />
                                buscando...
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Si la placa está en una autorización o tiene ingresos previos, los datos del conductor y el destino se completarán automáticamente.
                        </p>
                        <InputError :message="form.errors.plate" />
                    </div>
                </Transition>

                <!-- Observaciones -->
                <div class="grid gap-1.5">
                    <Label for="observations">Observaciones</Label>
                    <textarea
                        id="observations"
                        v-model="form.observations"
                        rows="2"
                        placeholder="Observaciones opcionales..."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground"
                    />
                    <InputError :message="form.errors.observations" />
                </div>

                <div class="flex gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="flex-1"
                    @click="form.reset(); authorization = null; noAuthorization = false; lookupDone = false; knownInSystem = false;"
                >Limpiar</Button>
                <Button
                    type="submit"
                    class="flex-1"
                    size="lg"
                    :disabled="form.processing"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    {{
                        form.processing ? 'Registrando...' : 'Registrar Ingreso'
                    }}
                </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.alert-enter-active,
.alert-leave-active {
    transition: all 0.2s ease;
}
.alert-enter-from,
.alert-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
