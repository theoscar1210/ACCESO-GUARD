<script setup lang="ts">
import { ArrowRightLeft, Camera, ChevronDown, Loader2, LogOut, PackageCheck, Wrench } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { WorkItemInfo } from '@/lib/works';

/**
 * Al registrar la salida de trabajadores de obra: qué pasa con sus herramientas.
 * Cada herramienta propia necesita decisión (sale / queda en la casa); las de
 * compañeros de la misma casa pueden salir con él y quedan como traspaso.
 */

export interface ToolMove {
    entry_id: number;
    item_id: number;
    action: 'sale' | 'queda';
    quantity?: number;
}

export interface MaterialExitTake {
    entry_id: number;
    id: number;
}

type ToolItem = WorkItemInfo & { owner_cedula: string | null };

interface MaterialExitInfo {
    id: number;
    description: string;
    quantity: string;
    reason: string;
    approved_by: string | null;
}

interface WorkerTools {
    entry_id: number;
    full_name: string;
    cedula: string;
    own: { item: ToolItem; action: 'sale' | 'queda'; quantity: number }[];
    others: { item: ToolItem; take: boolean; quantity: number }[];
    materials: { exit: MaterialExitInfo; take: boolean }[];
    showOthers: boolean;
}

const props = defineProps<{
    workers: { id: number; full_name: string; cedula: string }[];
    processing: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [moves: ToolMove[], materialExits: MaterialExitTake[]] }>();

const reasonLabel: Record<string, string> = {
    sobrante: 'Sobrante',
    escombro: 'Escombro',
    devolucion: 'Devolución',
    otro: 'Otro',
};

const loading = ref(false);
const groups = ref<WorkerTools[]>([]);

watch(open, async (isOpen) => {
    if (!isOpen) return;
    loading.value = true;
    try {
        const exitingCedulas = new Set(props.workers.map((w) => w.cedula));
        groups.value = await Promise.all(
            props.workers.map(async (w) => {
                const res = await fetch(`/vigilante/exits/${w.id}/tools`, { headers: { Accept: 'application/json' } });
                const data: { own: ToolItem[]; others: ToolItem[]; material_exits: MaterialExitInfo[] } = await res.json();
                return {
                    entry_id: w.id,
                    full_name: w.full_name,
                    cedula: w.cedula,
                    own: data.own.map((item) => ({ item, action: 'sale' as const, quantity: item.quantity })),
                    // Las de compañeros que también salen ahora las decide cada dueño
                    others: data.others
                        .filter((item) => !exitingCedulas.has(item.owner_cedula ?? ''))
                        .map((item) => ({ item, take: false, quantity: item.quantity })),
                    materials: (data.material_exits ?? []).map((exit) => ({ exit, take: false })),
                    showOthers: false,
                };
            }),
        );
    } finally {
        loading.value = false;
    }
});

// Una herramienta ajena solo puede llevársela un trabajador a la vez
const takenBy = computed(() => {
    const map = new Map<number, number>();
    for (const g of groups.value) {
        for (const o of g.others) if (o.take) map.set(o.item.id, g.entry_id);
    }
    return map;
});

// Una salida de material la lleva una sola persona
const materialTakenBy = computed(() => {
    const map = new Map<number, number>();
    for (const g of groups.value) {
        for (const m of g.materials) if (m.take) map.set(m.exit.id, g.entry_id);
    }
    return map;
});

const leavingCount = computed(() =>
    groups.value.reduce(
        (sum, g) =>
            sum +
            g.own.filter((o) => o.action === 'sale').length +
            g.others.filter((o) => o.take).length +
            g.materials.filter((m) => m.take).length,
        0,
    ),
);

function confirm() {
    const materialExits: MaterialExitTake[] = groups.value.flatMap((g) =>
        g.materials.filter((m) => m.take).map((m) => ({ entry_id: g.entry_id, id: m.exit.id })),
    );
    const moves: ToolMove[] = [];
    for (const g of groups.value) {
        for (const o of g.own) {
            moves.push(
                o.action === 'sale'
                    ? { entry_id: g.entry_id, item_id: o.item.id, action: 'sale', quantity: o.quantity }
                    : { entry_id: g.entry_id, item_id: o.item.id, action: 'queda' },
            );
        }
        for (const o of g.others) {
            if (o.take) moves.push({ entry_id: g.entry_id, item_id: o.item.id, action: 'sale', quantity: o.quantity });
        }
    }
    emit('confirm', moves, materialExits);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[92dvh] flex-col gap-3 p-4 sm:max-w-lg sm:p-6">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><Wrench class="h-5 w-5 text-primary" /> Salida de obra</DialogTitle>
                <DialogDescription>Compara con la foto, indica qué herramientas salen o quedan y marca el material autorizado que se llevan.</DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>

            <div v-else class="-mx-1 flex min-h-0 flex-col gap-3 overflow-y-auto px-1">
                <section v-for="g in groups" :key="g.entry_id" class="flex flex-col gap-2">
                    <p class="text-sm font-semibold">{{ g.full_name }}</p>

                    <p v-if="g.own.length === 0 && g.materials.length === 0" class="text-xs text-muted-foreground">No tiene herramientas propias dentro.</p>

                    <!-- Material con salida aprobada por el propietario o el admin -->
                    <div v-if="g.materials.length" class="flex flex-col gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50/50 p-2">
                        <p class="flex items-center gap-1.5 text-xs font-semibold text-emerald-800">
                            <PackageCheck class="h-3.5 w-3.5" /> Material autorizado para salir
                        </p>
                        <label
                            v-for="m in g.materials"
                            :key="m.exit.id"
                            :class="[
                                'flex items-center gap-2 rounded-md p-1.5',
                                materialTakenBy.has(m.exit.id) && materialTakenBy.get(m.exit.id) !== g.entry_id
                                    ? 'pointer-events-none opacity-40'
                                    : 'cursor-pointer hover:bg-emerald-100/60',
                            ]"
                        >
                            <input v-model="m.take" type="checkbox" class="h-5 w-5 accent-emerald-600" />
                            <span class="min-w-0 flex-1 text-xs">
                                <span class="block truncate font-medium text-foreground">{{ m.exit.description }} · {{ m.exit.quantity }}</span>
                                <span class="block truncate text-muted-foreground">
                                    {{ reasonLabel[m.exit.reason] ?? m.exit.reason }} · autorizó {{ m.exit.approved_by ?? '—' }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div v-for="o in g.own" :key="o.item.id" class="flex items-center gap-2 rounded-lg border p-2">
                        <img v-if="o.item.photo_url" :src="o.item.photo_url" alt="" class="h-14 w-14 shrink-0 rounded-md border object-cover" />
                        <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border bg-muted">
                            <Camera class="h-4 w-4 text-muted-foreground/50" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ o.item.name }}</span>
                            <span class="block truncate font-mono text-xs text-muted-foreground">
                                {{ o.item.serial ? `S/N ${o.item.serial}` : 'Sin serial' }} · dentro ×{{ o.item.quantity }}
                            </span>
                            <input
                                v-if="o.action === 'sale' && o.item.quantity > 1"
                                v-model.number="o.quantity"
                                type="number"
                                min="1"
                                :max="o.item.quantity"
                                class="mt-1 h-8 w-20 rounded-md border px-2 text-sm"
                                aria-label="Cantidad que sale"
                            />
                        </span>
                        <div class="flex shrink-0 flex-col gap-1">
                            <button
                                type="button"
                                @click="o.action = 'sale'"
                                :class="['h-8 rounded-md px-3 text-xs font-semibold', o.action === 'sale' ? 'bg-blue-600 text-white' : 'border hover:bg-muted']"
                            >Sale</button>
                            <button
                                type="button"
                                @click="o.action = 'queda'"
                                :class="['h-8 rounded-md px-3 text-xs font-semibold', o.action === 'queda' ? 'bg-slate-700 text-white' : 'border hover:bg-muted']"
                            >Queda</button>
                        </div>
                    </div>

                    <!-- Herramientas de compañeros de la misma casa (traspaso) -->
                    <div v-if="g.others.length" class="rounded-lg border border-dashed">
                        <button
                            type="button"
                            @click="g.showOthers = !g.showOthers"
                            class="flex h-10 w-full items-center justify-between px-3 text-left text-xs font-medium text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5"><ArrowRightLeft class="h-3.5 w-3.5" /> Herramientas de compañeros de la casa ({{ g.others.length }})</span>
                            <ChevronDown :class="['h-4 w-4 transition-transform', g.showOthers ? 'rotate-180' : '']" />
                        </button>
                        <div v-if="g.showOthers" class="flex flex-col gap-1.5 border-t p-2">
                            <label
                                v-for="o in g.others"
                                :key="o.item.id"
                                :class="[
                                    'flex items-center gap-2 rounded-md p-1.5',
                                    takenBy.has(o.item.id) && takenBy.get(o.item.id) !== g.entry_id ? 'pointer-events-none opacity-40' : 'cursor-pointer hover:bg-muted/50',
                                ]"
                            >
                                <input v-model="o.take" type="checkbox" class="h-5 w-5 accent-amber-600" />
                                <img v-if="o.item.photo_url" :src="o.item.photo_url" alt="" class="h-10 w-10 rounded border object-cover" />
                                <span class="min-w-0 flex-1 text-xs">
                                    <span class="block truncate font-medium text-foreground">{{ o.item.name }} ×{{ o.item.quantity }}</span>
                                    <span class="block truncate text-muted-foreground">Ingresó {{ o.item.owner }}</span>
                                </span>
                            </label>
                            <p class="px-1.5 text-[11px] text-amber-700">Si sale con {{ g.full_name }}, queda registrado como traspaso.</p>
                        </div>
                    </div>
                </section>
            </div>

            <DialogFooter class="flex-row gap-2 sm:gap-2">
                <Button variant="outline" class="h-11 shrink-0 px-4" @click="open = false">Cancelar</Button>
                <Button class="h-11 min-w-0 flex-1" :disabled="loading || processing" @click="confirm">
                    <LogOut class="h-4 w-4 shrink-0" />
                    <span class="truncate">Confirmar salida</span>
                    <span
                        v-if="leavingCount"
                        class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-white/20 px-1.5 text-xs"
                        :title="`${leavingCount} herramienta(s) salen`"
                    ><Wrench class="h-3 w-3" />{{ leavingCount }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
