<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Car, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    plate: string | null;
    observations: string | null;
    entry_at: string;
}

const props = defineProps<{ inside: Entry[] }>();

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

function toggle(id: number) {
    const idx = selected.value.indexOf(id);
    if (idx === -1) selected.value.push(id);
    else selected.value.splice(idx, 1);
}

function toggleAll() {
    if (selected.value.length === filtered.value.length) {
        selected.value = [];
    } else {
        selected.value = filtered.value.map((e) => e.id);
    }
}

function submit() {
    form.entry_ids = selected.value;
    form.post('/vigilante/exits', {
        onSuccess: () => {
            selected.value = [];
            search.value = '';
        },
    });
}

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    propietario: 'default',
    autorizado: 'secondary',
    visitante: 'outline',
};

const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    residente: 'Residente',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
};

const vehicleLabel: Record<string, string> = {
    ninguno: '',
    automovil: '🚗',
    camioneta: '🚙',
    moto: '🏍',
    bicicleta: '🚲',
};
</script>

<template>
    <AppLayout>
        <Head title="Registrar Salidas" />

        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold">Registrar Salidas</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ inside.length }} persona(s) dentro del edificio
                    </p>
                </div>
                <Button
                    @click="submit"
                    :disabled="selected.length === 0 || form.processing"
                    class="min-w-36"
                >
                    Registrar salida
                    <span v-if="selected.length > 0" class="ml-1"
                        >({{ selected.length }})</span
                    >
                </Button>
            </div>

            <!-- Sin personas dentro -->
            <div
                v-if="inside.length === 0"
                class="rounded-xl border bg-card p-12 text-center text-muted-foreground shadow-sm"
            >
                No hay personas dentro del edificio en este momento.
            </div>

            <template v-else>
                <!-- Búsqueda (nombre, cédula, destino o placa) y seleccionar todos -->
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:max-w-sm">
                        <input
                            v-model="search"
                            type="text"
                            autocomplete="off"
                            placeholder="Buscar por nombre, cédula, destino o placa..."
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 pr-9 text-sm shadow-sm placeholder:text-muted-foreground"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="clearSearch"
                            class="absolute top-2 right-2.5 text-muted-foreground hover:text-foreground"
                            aria-label="Limpiar búsqueda"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex gap-3">
                    <button
                        @click="toggleAll"
                        class="text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {{
                            selected.length === filtered.length
                                ? 'Deseleccionar todos'
                                : 'Seleccionar todos'
                        }}
                    </button>
                    <button
                        v-if="selected.length > 0"
                        @click="selected = []"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >Limpiar selección</button>
                    </div>
                </div>

                <!-- Placa encontrada: ocupantes ya seleccionados -->
                <div
                    v-if="plateMatches.length > 0"
                    class="flex max-w-lg flex-col gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="flex items-center gap-2">
                        <Car class="h-4 w-4 shrink-0" />
                        <span>
                            Vehículo <span class="font-mono font-bold">{{ plateMatches[0].plate }}</span>:
                            {{ plateMatches.length }} ocupante(s) seleccionado(s)
                        </span>
                    </span>
                    <Button size="sm" :disabled="form.processing" @click="submit">
                        Registrar salida
                    </Button>
                </div>
                <p
                    v-else-if="search && filtered.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nadie dentro coincide con «{{ search }}».
                </p>

                <!-- Lista de personas dentro -->
                <div class="grid gap-2">
                    <div
                        v-for="entry in filtered"
                        :key="entry.id"
                        @click="toggle(entry.id)"
                        :class="[
                            'flex cursor-pointer items-center gap-4 rounded-xl border bg-card p-4 shadow-sm transition-colors',
                            selected.includes(entry.id)
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted/30',
                        ]"
                    >
                        <!-- Checkbox -->
                        <div
                            :class="[
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition-colors',
                                selected.includes(entry.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground',
                            ]"
                        >
                            <svg
                                v-if="selected.includes(entry.id)"
                                class="h-3 w-3"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="3"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>

                        <!-- Info -->
                        <div class="flex flex-1 items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ entry.full_name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    CC {{ entry.cedula }} · Destino {{ entry.apartment }}
                                </p>
                                <p v-if="entry.plate || (entry.vehicle && entry.vehicle !== 'ninguno')" class="text-xs text-muted-foreground">
                                    <span v-if="entry.vehicle && entry.vehicle !== 'ninguno'">
                                        {{ vehicleLabel[entry.vehicle] ?? entry.vehicle }}
                                    </span>
                                    <span v-if="entry.plate" class="ml-1 font-mono font-semibold tracking-wider">{{ entry.plate }}</span>
                                </p>
                                <p v-if="entry.observations" class="mt-0.5 text-xs italic text-muted-foreground">
                                    "{{ entry.observations }}"
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <Badge :variant="typeVariant[entry.type]">
                                    {{ typeLabel[entry.type] ?? entry.type }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    Ingresó {{ entry.entry_at }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones (opcional) -->
                <div v-if="selected.length > 0" class="grid max-w-lg gap-1.5">
                    <label class="text-sm font-medium"
                        >Observaciones (opcional)</label
                    >
                    <textarea
                        v-model="form.observations"
                        rows="2"
                        placeholder="Observaciones sobre la salida..."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground"
                    />
                </div>
            </template>
        </div>
    </AppLayout>
</template>
