<script setup lang="ts">
import { Camera, Loader2, Package, Plus, RotateCcw, Wrench, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { compressImage } from '@/lib/compressImage';
import type { WorkItemInfo } from '@/lib/works';

/**
 * Herramientas y materiales que ingresa un trabajador de obra.
 * Cada fila puede llevar una foto tomada con la cámara (opcional).
 */

export interface EntryItemRow {
    key: number;
    item_id: number | null;
    name: string;
    serial: string;
    kind: 'herramienta' | 'material';
    quantity: number;
    photo: File | null;
    preview: string | null;
}

const props = withDefaults(
    defineProps<{
        toolsInside?: WorkItemInfo[];
        reentryItems?: WorkItemInfo[];
        errors: Record<string, string | undefined>;
        // Entregas de proveedores: solo material, sin "repetir"
        materialOnly?: boolean;
    }>(),
    { toolsInside: () => [], reentryItems: () => [], materialOnly: false },
);

const rows = defineModel<EntryItemRow[]>({ required: true });

let nextKey = 1;
const compressing = ref<number | null>(null);

function addRow() {
    rows.value.push({
        key: nextKey++,
        item_id: null,
        name: '',
        serial: '',
        kind: props.materialOnly ? 'material' : 'herramienta',
        quantity: 1,
        photo: null,
        preview: null,
    });
}

// "Repetir": vuelve a ingresar una herramienta que el trabajador ya trajo antes
function addReentry(item: WorkItemInfo) {
    if (rows.value.some((r) => r.item_id === item.id)) return;
    rows.value.push({
        key: nextKey++,
        item_id: item.id,
        name: item.name,
        serial: item.serial ?? '',
        kind: item.kind,
        quantity: 1,
        photo: null,
        preview: item.photo_url,
    });
}

function addAllReentries() {
    props.reentryItems.forEach(addReentry);
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

async function onPhoto(row: EntryItemRow, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    compressing.value = row.key;
    try {
        row.photo = await compressImage(file);
        row.preview = URL.createObjectURL(row.photo);
    } finally {
        compressing.value = null;
    }
}

function error(index: number, field: string) {
    return props.errors[`items.${index}.${field}`];
}

const pendingReentries = () => props.reentryItems.filter((i) => !rows.value.some((r) => r.item_id === i.id));
</script>

<template>
    <div class="flex flex-col gap-2 rounded-lg border border-dashed border-primary/40 bg-primary/[0.03] p-3">
        <div class="flex items-center justify-between gap-2">
            <p class="flex items-center gap-1.5 text-sm font-semibold">
                <Package v-if="materialOnly" class="h-4 w-4 text-primary" />
                <Wrench v-else class="h-4 w-4 text-primary" />
                {{ materialOnly ? 'Material que entrega' : 'Herramientas y materiales que ingresa' }}
            </p>
            <button type="button" @click="addRow" class="flex h-9 items-center gap-1 rounded-md px-2 text-sm font-medium text-primary hover:bg-primary/10">
                <Plus class="h-4 w-4" /> Ítem
            </button>
        </div>

        <!-- Lo que ya tiene dentro a su nombre -->
        <p v-if="toolsInside.length" class="text-xs text-muted-foreground">
            Ya tiene dentro: <span class="text-foreground">{{ toolsInside.map((t) => `${t.name}${t.quantity > 1 ? ` ×${t.quantity}` : ''}`).join(', ') }}</span>
        </p>

        <!-- Repetir herramientas que ya trajo antes -->
        <div v-if="pendingReentries().length" class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                @click="addAllReentries"
                class="inline-flex h-8 items-center gap-1 rounded-full bg-primary px-3 text-xs font-semibold text-primary-foreground"
            >
                <RotateCcw class="h-3.5 w-3.5" /> Repetir todo ({{ pendingReentries().length }})
            </button>
            <button
                v-for="item in pendingReentries()"
                :key="item.id"
                type="button"
                @click="addReentry(item)"
                class="inline-flex h-8 max-w-full items-center gap-1 truncate rounded-full border bg-background px-3 text-xs hover:bg-muted"
            >
                + {{ item.name }}<span v-if="item.serial" class="font-mono text-muted-foreground"> {{ item.serial }}</span>
            </button>
        </div>

        <!-- Filas -->
        <div v-for="(row, i) in rows" :key="row.key" class="flex items-start gap-2 rounded-md border bg-background p-2">
            <!-- Foto: abre la cámara trasera en el celular -->
            <label
                class="relative flex h-14 w-14 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-md border bg-muted"
                :title="row.preview ? 'Cambiar foto' : 'Tomar foto (opcional)'"
            >
                <img v-if="row.preview" :src="row.preview" alt="" class="h-full w-full object-cover" />
                <Loader2 v-else-if="compressing === row.key" class="h-5 w-5 animate-spin text-muted-foreground" />
                <Camera v-else class="h-5 w-5 text-muted-foreground" />
                <input type="file" accept="image/*" capture="environment" class="sr-only" @change="onPhoto(row, $event)" />
            </label>

            <div class="grid min-w-0 flex-1 grid-cols-6 gap-1.5">
                <Input
                    v-model="row.name"
                    :disabled="row.item_id !== null"
                    :placeholder="materialOnly ? 'Material * (ej: Cemento 50kg)' : 'Herramienta o material *'"
                    class="col-span-6 h-9 sm:col-span-3"
                />
                <Input
                    v-model="row.serial"
                    :disabled="row.item_id !== null"
                    placeholder="Serial / marca"
                    class="col-span-3 h-9 font-mono uppercase sm:col-span-2"
                />
                <Input v-model.number="row.quantity" type="number" min="1" inputmode="numeric" class="col-span-3 h-9 sm:col-span-1" />
                <div v-if="!materialOnly" class="col-span-6 flex gap-1.5">
                    <button
                        v-for="kind in (['herramienta', 'material'] as const)"
                        :key="kind"
                        type="button"
                        :disabled="row.item_id !== null"
                        @click="row.kind = kind"
                        :class="[
                            'flex h-8 flex-1 items-center justify-center gap-1 rounded-md border text-xs font-medium capitalize transition-colors disabled:opacity-60',
                            row.kind === kind ? 'border-primary bg-primary/10 text-primary' : 'hover:bg-muted',
                        ]"
                    >
                        <Wrench v-if="kind === 'herramienta'" class="h-3.5 w-3.5" />
                        <Package v-else class="h-3.5 w-3.5" />
                        {{ kind }}
                    </button>
                </div>
                <p v-if="error(i, 'name') || error(i, 'quantity') || error(i, 'photo')" class="col-span-6 text-xs text-destructive">
                    {{ error(i, 'name') ?? error(i, 'quantity') ?? error(i, 'photo') }}
                </p>
            </div>

            <button type="button" @click="removeRow(i)" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted" aria-label="Quitar ítem">
                <X class="h-4 w-4" />
            </button>
        </div>

        <p v-if="rows.length === 0" class="text-xs text-muted-foreground">
            {{ materialOnly
                ? 'Agrega cada material que entrega el proveedor. La foto (de la factura o del material) es opcional.'
                : 'Si no ingresa herramientas ni materiales, no agregues ítems. La foto es opcional.' }}
        </p>
    </div>
</template>
