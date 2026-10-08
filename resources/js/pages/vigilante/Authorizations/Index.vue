<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Car, Home, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface Authorization {
    id: number;
    full_name: string;
    cedula: string;
    plate: string | null;
    vehicle: string | null;
    type: string;
    end_date: string | null;
    owner: string;
    property: string | null;
    observations: string | null;
}

const props = defineProps<{ authorizations: Authorization[] }>();

const search = ref('');

// Placas sin guiones ni espacios para que "abc-123" encuentre "ABC123"
const normalizePlate = (value: string) =>
    value.toUpperCase().replace(/[^A-Z0-9]/g, '');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    const plate = normalizePlate(search.value);

    return props.authorizations.filter(
        (a) =>
            a.full_name.toLowerCase().includes(term) ||
            a.cedula.includes(term) ||
            (a.property ?? '').toLowerCase().includes(term) ||
            (plate !== '' && !!a.plate && normalizePlate(a.plate).includes(plate)),
    );
});

const typeVariant: Record<string, 'default' | 'secondary'> = {
    visitante: 'secondary',
    autorizado: 'default',
};

const vehicleLabel: Record<string, string> = {
    automovil: 'Automóvil',
    camioneta: 'Camioneta',
    moto: 'Moto',
    bicicleta: 'Bicicleta',
};

const typeLabel: Record<string, string> = {
    visitante: 'Visitante',
    autorizado: 'Autorizado',
};
</script>

<template>
    <AppLayout>
        <Head title="Autorizaciones Activas" />

        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Autorizaciones Activas</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ authorizations.length }} autorización(es) vigente(s)
                    </p>
                </div>
            </div>

            <!-- Búsqueda -->
            <div class="relative w-full max-w-sm">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por nombre, cédula, inmueble o placa..."
                    :class="['flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground', search ? 'pr-8' : '']"
                />
                <button
                    v-if="search"
                    @click="search = ''"
                    class="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
                    title="Limpiar búsqueda"
                ><X class="h-4 w-4" /></button>
            </div>

            <!-- Vacío -->
            <div
                v-if="filtered.length === 0"
                class="rounded-xl border bg-card p-12 text-center text-muted-foreground shadow-sm"
            >
                No hay autorizaciones activas.
            </div>

            <!-- Lista -->
            <div v-else class="grid gap-3">
                <div
                    v-for="auth in filtered"
                    :key="auth.id"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{
                                auth.full_name
                            }}</span>
                            <Badge :variant="typeVariant[auth.type]">
                                {{ typeLabel[auth.type] }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            CC {{ auth.cedula }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-0.5 font-medium">
                                <Home class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ auth.property ?? 'Sin inmueble asignado' }}
                            </span>
                            <span
                                v-if="auth.plate || auth.vehicle"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5"
                            >
                                <Car class="h-3.5 w-3.5 text-muted-foreground" />
                                <span v-if="auth.vehicle" class="text-muted-foreground">{{ vehicleLabel[auth.vehicle] ?? auth.vehicle }}</span>
                                <span v-if="auth.plate" class="font-mono font-semibold tracking-wider">{{ auth.plate }}</span>
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Autoriza: {{ auth.owner }}
                        </p>
                        <p
                            v-if="auth.end_date"
                            class="text-sm text-muted-foreground"
                        >
                            Válida hasta: {{ auth.end_date }}
                        </p>
                        <p
                            v-if="auth.observations"
                            class="text-sm text-muted-foreground italic"
                        >
                            "{{ auth.observations }}"
                        </p>
                    </div>

                    <!-- Botón rápido de registrar ingreso -->
                    <Link
                        :href="`/vigilante/entries/create?cedula=${auth.cedula}`"
                        class="shrink-0 self-start"
                    >
                        <Button size="sm" variant="outline"
                            >Registrar ingreso</Button
                        >
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
