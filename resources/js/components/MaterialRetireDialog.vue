<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Car, Clock, PackageCheck, Search, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { materialReasonLabel } from '@/lib/works';

/**
 * Retiro de material autorizado (escombro, sobrantes, elementos de la casa).
 * Solo puede retirarlo una persona con ingreso activo; queda su nombre, cédula,
 * placa, fecha y hora, y se registra su salida en ese mismo momento.
 */

export interface RetirableExit {
    id: number;
    description: string;
    quantity: string;
    reason: string;
    house: string | null;
    work_title: string | null;
    approved_by: string | null;
    expires_at: string | null;
}

export interface InsidePerson {
    id: number;
    full_name: string;
    cedula: string;
    plate: string | null;
    apartment: string;
}

const props = defineProps<{ exits: RetirableExit[]; inside: InsidePerson[] }>();
const open = defineModel<boolean>('open', { required: true });

const exitId = ref<number | null>(null);
const search = ref('');
const form = useForm({ entry_id: null as number | null, plate: '' });

const normalize = (v: string) => v.toUpperCase().replace(/[^A-Z0-9]/g, '');

// Solo personas con ingreso activo (las que están dentro)
const people = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (term.length < 2) return [];
    const plate = normalize(search.value);
    return props.inside
        .filter(
            (p) =>
                p.full_name.toLowerCase().includes(term) ||
                p.cedula.includes(term) ||
                (!!p.plate && plate !== '' && normalize(p.plate).includes(plate)),
        )
        .slice(0, 6);
});

const person = computed(() => props.inside.find((p) => p.id === form.entry_id) ?? null);
const selectedExit = computed(() => props.exits.find((e) => e.id === exitId.value) ?? null);

function choose(p: InsidePerson) {
    form.entry_id = p.id;
    form.plate = p.plate ?? '';
    search.value = '';
}

watch(open, (isOpen) => {
    if (isOpen) {
        exitId.value = props.exits.length === 1 ? props.exits[0].id : null;
        search.value = '';
        form.reset();
        form.clearErrors();
    }
});

const errors = computed(() => form.errors as Record<string, string | undefined>);

function submit() {
    if (!exitId.value) return;
    form.transform((data) => ({ ...data, plate: data.plate.toUpperCase() }))
        .post(`/vigilante/material-exits/${exitId.value}/retire`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[92dvh] flex-col gap-3 p-4 sm:max-w-lg sm:p-6">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><PackageCheck class="h-5 w-5 text-emerald-600" /> Retiro de material</DialogTitle>
                <DialogDescription>Solo puede retirarlo una persona con ingreso registrado. Su salida se registra en este momento.</DialogDescription>
            </DialogHeader>

            <div class="-mx-1 flex min-h-0 flex-col gap-3 overflow-y-auto px-1">
                <!-- 1. Material autorizado -->
                <div class="flex flex-col gap-1.5">
                    <p class="text-xs font-semibold text-muted-foreground">1. Material autorizado</p>
                    <label
                        v-for="e in exits"
                        :key="e.id"
                        :class="[
                            'flex cursor-pointer items-start gap-2 rounded-lg border p-2.5 transition-colors',
                            exitId === e.id ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-400' : 'hover:bg-muted/50',
                        ]"
                    >
                        <input v-model="exitId" type="radio" :value="e.id" class="mt-1 h-4 w-4 accent-emerald-600" />
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="block truncate font-medium">{{ e.description }} · {{ e.quantity }}</span>
                            <span class="block truncate text-xs text-muted-foreground">
                                {{ e.house }} · {{ materialReasonLabel[e.reason] ?? e.reason }} · autorizó {{ e.approved_by }}
                            </span>
                            <span v-if="e.expires_at" class="flex items-center gap-1 text-[11px] text-amber-700">
                                <Clock class="h-3 w-3" /> Válida hasta {{ e.expires_at }}
                            </span>
                        </span>
                    </label>
                </div>

                <!-- 2. Persona que retira (con ingreso activo) -->
                <div v-if="selectedExit" class="flex flex-col gap-1.5">
                    <p class="text-xs font-semibold text-muted-foreground">2. Quién lo retira</p>

                    <div v-if="person" class="flex items-center justify-between gap-2 rounded-lg border border-primary/40 bg-primary/5 p-2.5">
                        <span class="flex min-w-0 items-center gap-2 text-sm">
                            <User class="h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ person.full_name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">CC {{ person.cedula }} · {{ person.apartment }}</span>
                            </span>
                        </span>
                        <button type="button" @click="form.entry_id = null" class="h-8 shrink-0 rounded-md px-2 text-xs text-primary hover:bg-primary/10">Cambiar</button>
                    </div>

                    <template v-else>
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <input
                                v-model="search"
                                type="text"
                                autocomplete="off"
                                placeholder="Cédula, nombre o placa de quien está dentro..."
                                class="flex h-10 w-full rounded-md border border-input bg-transparent py-1 pr-3 pl-9 text-sm shadow-sm"
                            />
                        </div>
                        <button
                            v-for="p in people"
                            :key="p.id"
                            type="button"
                            @click="choose(p)"
                            class="flex items-center justify-between gap-2 rounded-md border px-2.5 py-2 text-left text-sm hover:bg-muted/50"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ p.full_name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">CC {{ p.cedula }} · {{ p.apartment }}</span>
                            </span>
                            <span v-if="p.plate" class="shrink-0 font-mono text-xs font-semibold">{{ p.plate }}</span>
                        </button>
                        <p v-if="search.trim().length >= 2 && people.length === 0" class="text-xs text-amber-700">
                            Nadie dentro coincide. Si la persona aún no entró, registra primero su ingreso.
                        </p>
                    </template>
                    <InputError :message="errors.entry_id" class="text-xs" />
                </div>

                <!-- 3. Placa del vehículo con el que sale -->
                <div v-if="person" class="flex flex-col gap-1.5">
                    <label for="retire-plate" class="flex items-center gap-1 text-xs font-semibold text-muted-foreground">
                        <Car class="h-3.5 w-3.5" /> 3. Placa del vehículo
                    </label>
                    <input
                        id="retire-plate"
                        v-model="form.plate"
                        type="text"
                        maxlength="20"
                        placeholder="Sin vehículo"
                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 font-mono text-base tracking-widest uppercase shadow-sm placeholder:normal-case placeholder:tracking-normal"
                    />
                    <p class="text-[11px] text-muted-foreground">Viene del ingreso; corrígela si sale en otro vehículo.</p>
                </div>

                <InputError :message="errors.material_exit" class="text-xs" />
            </div>

            <DialogFooter class="flex-row gap-2 sm:gap-2">
                <Button variant="outline" class="h-11 shrink-0 px-4" @click="open = false">Cancelar</Button>
                <Button class="h-11 min-w-0 flex-1 bg-emerald-600 hover:bg-emerald-700" :disabled="!selectedExit || !person || form.processing" @click="submit">
                    <PackageCheck class="h-4 w-4 shrink-0" />
                    <span class="truncate">Registrar retiro y salida</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
