<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRightLeft,
    Camera,
    FileDown,
    Package,
    PackageCheck,
    Trash2,
    Truck,
    UserPlus,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { WorkItemInfo } from '@/lib/works';
import {
    materialReasonLabel,
    materialStatusClass,
    materialStatusLabel,
    movementLabel,
    workLogLabel,
    workStatusClass,
    workStatusLabel,
    workTypeLabel,
} from '@/lib/works';

interface WorkData {
    id: number;
    title: string;
    type: string;
    status: string;
    is_current: boolean;
    property_id: number;
    property: string;
    contractor_company: string | null;
    contractor_name: string;
    contractor_document: string | null;
    contractor_phone: string | null;
    start_date: string;
    end_date: string | null;
    schedule: string | null;
    description: string | null;
    created_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
}

interface Worker {
    id: number;
    full_name: string;
    cedula: string;
    phone: string | null;
    tools: WorkItemInfo[];
}

interface Movement {
    id: number;
    item: string;
    serial: string | null;
    direction: string;
    quantity: number;
    is_transfer: boolean;
    owner: string | null;
    mover: string | null;
    registered_by: string | null;
    notes: string | null;
    photo_url: string | null;
    at: string;
}

interface MaterialExitRow {
    id: number;
    description: string;
    quantity: string;
    reason: string;
    status: string;
    notes: string | null;
    requested_by: string | null;
    approved_by: string | null;
    expires_at: string | null;
    executed_by: string | null;
    taken_by: string | null;
    taken_cedula: string | null;
    exit_plate: string | null;
    created_at: string;
    executed_at: string | null;
}

const props = defineProps<{
    work: WorkData;
    workers: Worker[];
    materials: WorkItemInfo[];
    movements: Movement[];
    logs: { id: number; action: string; notes: string | null; user: string; at: string }[];
    can_decide: boolean;
    can_manage_workers: boolean;
    properties: { id: number; label: string }[];
    material_exits: MaterialExitRow[];
    can_approve_material_exit: boolean;
}>();

type Tab = 'inventario' | 'trabajadores' | 'movimientos' | 'material' | 'datos' | 'bitacora';
// Las notificaciones de material llegan con ?tab=material
const initialTab = new URLSearchParams(window.location.search).get('tab') as Tab | null;
const tab = ref<Tab>(initialTab ?? 'inventario');
const tabs: { key: Tab; label: string }[] = [
    { key: 'inventario', label: 'Inventario' },
    { key: 'trabajadores', label: 'Trabajadores' },
    { key: 'movimientos', label: 'Movimientos' },
    { key: 'material', label: 'Salida de material' },
    { key: 'datos', label: 'Datos' },
    { key: 'bitacora', label: 'Bitácora' },
];

const toolsInside = computed(() =>
    props.workers.reduce((sum, w) => sum + w.tools.reduce((s, t) => s + t.quantity, 0), 0),
);

// Herramientas dentro de una obra cerrada, suspendida o vencida: hay que cuadrarlas
const needsAttention = computed(() => toolsInside.value > 0 && !props.work.is_current);

const pendingMaterialExits = computed(() => props.material_exits.filter((m) => m.status === 'pendiente').length);

// ── Acta en PDF ──
const today = new Date().toISOString().split('T')[0];

// ── Salidas de material ──
const materialForm = useForm({ description: '', quantity: '', reason: 'sobrante', notes: '' });

function requestMaterialExit() {
    materialForm.post(`/works/${props.work.id}/material-exits`, {
        preserveScroll: true,
        onSuccess: () => materialForm.reset(),
    });
}

function decideMaterialExit(id: number, action: 'aprobar' | 'rechazar') {
    router.post(`/works/${props.work.id}/material-exits/${id}/decision`, { action }, { preserveScroll: true });
}

// ── Decisiones (administrador y superusuario) ──
const decisionNotes = ref('');
const decisions = computed(() => {
    const s = props.work.status;
    const all = [
        { action: 'aprobar', label: 'Aprobar', show: ['pendiente', 'rechazada', 'suspendida'].includes(s), variant: 'default' },
        { action: 'rechazar', label: 'Rechazar', show: s === 'pendiente' || s === 'aprobada', variant: 'destructive' },
        { action: 'suspender', label: 'Suspender', show: s === 'aprobada', variant: 'outline' },
        { action: 'cerrar', label: 'Cerrar obra', show: s !== 'cerrada', variant: 'outline' },
        { action: 'reabrir', label: 'Reabrir', show: s === 'cerrada', variant: 'default' },
    ] as const;
    return all.filter((d) => d.show);
});

function decide(action: string) {
    router.post(
        `/works/${props.work.id}/decision`,
        { action, notes: decisionNotes.value || null },
        { preserveScroll: true, onSuccess: () => (decisionNotes.value = '') },
    );
}

// ── Trabajadores ──
const workerForm = useForm({ first_name: '', last_name: '', cedula: '', phone: '' });

function addWorker() {
    workerForm.post(`/works/${props.work.id}/workers`, {
        preserveScroll: true,
        onSuccess: () => workerForm.reset(),
    });
}

function removeWorker(worker: Worker) {
    if (!confirm(`¿Quitar a ${worker.full_name} de la obra?`)) return;
    router.delete(`/works/${props.work.id}/workers/${worker.id}`, { preserveScroll: true });
}

// ── Edición de datos ──
const editForm = useForm({
    property_id: props.work.property_id,
    title: props.work.title,
    type: props.work.type,
    contractor_company: props.work.contractor_company ?? '',
    contractor_name: props.work.contractor_name,
    contractor_document: props.work.contractor_document ?? '',
    contractor_phone: props.work.contractor_phone ?? '',
    start_date: props.work.start_date,
    end_date: props.work.end_date ?? '',
    schedule: props.work.schedule ?? '',
    description: props.work.description ?? '',
});

function saveWork() {
    editForm.put(`/works/${props.work.id}`, { preserveScroll: true });
}

// Errores de acciones sin formulario propio (decisiones, quitar trabajador)
const page = usePage();
const pageErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
</script>

<template>
    <AppLayout>
        <Head :title="work.title" />

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-3 sm:p-6">
            <!-- Encabezado -->
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold sm:text-2xl">{{ work.title }}</h1>
                        <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', workStatusClass[work.status]]">
                            {{ workStatusLabel[work.status] ?? work.status }}
                        </span>
                        <span v-if="work.is_current" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Vigente
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ work.property }} · {{ workTypeLabel[work.type] ?? work.type }} ·
                        {{ work.contractor_company || work.contractor_name }}
                    </p>
                </div>
                <Link href="/works" class="flex shrink-0 items-center gap-1 py-2 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" /> Obras
                </Link>
            </div>

            <!-- Alerta: herramientas sin cuadrar en una obra que ya no está vigente -->
            <p
                v-if="needsAttention"
                class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                Quedan {{ toolsInside }} herramienta(s) dentro y la obra no está vigente. Hay que registrar su salida o reabrir la obra.
            </p>

            <!-- Acta en PDF -->
            <div class="flex flex-wrap gap-2">
                <a :href="`/works/${work.id}/acta?date=${today}`" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm hover:bg-muted">
                    <FileDown class="h-4 w-4" /> Acta de hoy
                </a>
                <a :href="`/works/${work.id}/acta`" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm hover:bg-muted">
                    <FileDown class="h-4 w-4" /> Acta completa
                </a>
            </div>

            <!-- Decisiones: administrador y superusuario -->
            <section v-if="can_decide" class="flex flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm sm:flex-row sm:items-center">
                <Input v-model="decisionNotes" placeholder="Motivo o nota (opcional)" class="sm:flex-1" />
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="d in decisions"
                        :key="d.action"
                        :variant="d.variant"
                        class="h-10 flex-1 sm:flex-none"
                        @click="decide(d.action)"
                    >
                        {{ d.label }}
                    </Button>
                </div>
                <InputError :message="pageErrors.action" />
            </section>

            <!-- Pestañas -->
            <div class="-mx-3 flex gap-1 overflow-x-auto border-b px-3 sm:mx-0 sm:px-0">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    @click="tab = t.key"
                    :class="[
                        'shrink-0 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                        tab === t.key ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ t.label }}
                    <span v-if="t.key === 'inventario' && toolsInside" class="ml-1 rounded-full bg-primary/10 px-1.5 text-xs text-primary">{{ toolsInside }}</span>
                    <span v-if="t.key === 'trabajadores'" class="ml-1 text-xs text-muted-foreground">({{ workers.length }})</span>
                    <span v-if="t.key === 'material' && pendingMaterialExits" class="ml-1 rounded-full bg-amber-100 px-1.5 text-xs text-amber-800">{{ pendingMaterialExits }}</span>
                </button>
            </div>

            <!-- Inventario por trabajador + materiales -->
            <div v-if="tab === 'inventario'" class="flex flex-col gap-3">
                <div
                    v-if="toolsInside === 0 && materials.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No hay herramientas ni materiales dentro de la casa.
                </div>

                <section
                    v-for="w in workers.filter((x) => x.tools.length)"
                    :key="w.id"
                    class="rounded-xl border bg-card shadow-sm"
                >
                    <div class="flex items-center gap-2 border-b px-4 py-2.5">
                        <Wrench class="h-4 w-4 text-muted-foreground" />
                        <p class="font-semibold">{{ w.full_name }}</p>
                        <span class="text-xs text-muted-foreground">CC {{ w.cedula }}</span>
                    </div>
                    <ul class="divide-y">
                        <li v-for="t in w.tools" :key="t.id" class="flex items-center gap-3 px-4 py-2">
                            <a v-if="t.photo_url" :href="t.photo_url" target="_blank" class="shrink-0">
                                <img :src="t.photo_url" alt="" class="h-11 w-11 rounded-md border object-cover" />
                            </a>
                            <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md border bg-muted">
                                <Camera class="h-4 w-4 text-muted-foreground/50" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ t.name }}</span>
                                <span v-if="t.serial" class="block truncate font-mono text-xs text-muted-foreground">S/N {{ t.serial }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold">×{{ t.quantity }}</span>
                        </li>
                    </ul>
                </section>

                <section v-if="materials.length" class="rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center gap-2 border-b px-4 py-2.5">
                        <Package class="h-4 w-4 text-muted-foreground" />
                        <p class="font-semibold">Material en obra</p>
                    </div>
                    <ul class="divide-y">
                        <li v-for="m in materials" :key="m.id" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <span class="min-w-0 truncate">{{ m.name }} <span class="text-xs text-muted-foreground">· {{ m.owner }}</span></span>
                            <span class="shrink-0 font-semibold">×{{ m.quantity }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Trabajadores -->
            <div v-if="tab === 'trabajadores'" class="flex flex-col gap-3">
                <ul class="divide-y rounded-xl border bg-card shadow-sm">
                    <li v-for="w in workers" :key="w.id" class="flex items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ w.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                CC {{ w.cedula }}<template v-if="w.phone"> · {{ w.phone }}</template>
                                <template v-if="w.tools.length"> · {{ w.tools.length }} herramienta(s) dentro</template>
                            </p>
                        </div>
                        <Button
                            v-if="can_manage_workers"
                            variant="ghost"
                            class="h-9 w-9 shrink-0 px-0 text-destructive"
                            :title="`Quitar a ${w.full_name}`"
                            @click="removeWorker(w)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </li>
                    <li v-if="workers.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">Sin trabajadores.</li>
                </ul>
                <InputError :message="pageErrors.worker" />

                <form
                    v-if="can_manage_workers"
                    @submit.prevent="addWorker"
                    class="grid grid-cols-2 gap-2 rounded-xl border bg-card p-3 shadow-sm sm:grid-cols-[1fr_1fr_1fr_1fr_auto] sm:items-start"
                >
                    <div class="grid gap-0.5">
                        <Input v-model="workerForm.first_name" placeholder="Nombres *" />
                        <InputError :message="workerForm.errors.first_name" class="text-xs" />
                    </div>
                    <div class="grid gap-0.5">
                        <Input v-model="workerForm.last_name" placeholder="Apellidos *" />
                        <InputError :message="workerForm.errors.last_name" class="text-xs" />
                    </div>
                    <div class="grid gap-0.5">
                        <Input v-model="workerForm.cedula" placeholder="Cédula *" inputmode="numeric" class="font-mono" />
                        <InputError :message="workerForm.errors.cedula" class="text-xs" />
                    </div>
                    <Input v-model="workerForm.phone" placeholder="Teléfono" inputmode="tel" />
                    <Button type="submit" class="col-span-2 h-10 sm:col-span-1" :disabled="workerForm.processing">
                        <UserPlus class="h-4 w-4" /> Agregar
                    </Button>
                </form>
            </div>

            <!-- Movimientos -->
            <div v-if="tab === 'movimientos'">
                <ul class="divide-y rounded-xl border bg-card shadow-sm">
                    <li v-for="m in movements" :key="m.id" class="flex items-center gap-3 px-4 py-2.5">
                        <a v-if="m.photo_url" :href="m.photo_url" target="_blank" class="shrink-0">
                            <img :src="m.photo_url" alt="" class="h-10 w-10 rounded-md border object-cover" />
                        </a>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">
                                <span
                                    :class="[
                                        'font-semibold',
                                        m.direction === 'ingreso' ? 'text-emerald-700' : m.direction === 'salida' ? 'text-blue-700' : 'text-muted-foreground',
                                    ]"
                                >{{ movementLabel[m.direction] ?? m.direction }}</span>
                                {{ m.item }} ×{{ m.quantity }}
                                <span v-if="m.serial" class="font-mono text-xs text-muted-foreground">S/N {{ m.serial }}</span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                <template v-if="m.is_transfer">
                                    <ArrowRightLeft class="inline h-3 w-3 text-amber-600" />
                                    <span class="font-medium text-amber-700">Traspaso:</span> ingresó {{ m.owner }}, salió con {{ m.mover }}
                                </template>
                                <template v-else-if="m.notes">
                                    <Truck class="inline h-3 w-3 text-orange-600" /> {{ m.notes }}
                                </template>
                                <template v-else>{{ m.mover ?? m.owner }}</template>
                                · {{ m.at }}<template v-if="m.registered_by"> · {{ m.registered_by }}</template>
                            </p>
                        </div>
                    </li>
                    <li v-if="movements.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        Aún no hay movimientos de herramientas.
                    </li>
                </ul>
            </div>

            <!-- Salidas de material (sobrantes, escombros, devoluciones) -->
            <div v-if="tab === 'material'" class="flex flex-col gap-3">
                <p class="text-xs text-muted-foreground">
                    Nada sale de la casa sin autorización. La aprueban el propietario de la casa, el administrador o el superusuario, y vale 48 horas.
                    Al solicitarla se les avisa por la app, push, correo y WhatsApp; en portería solo la puede retirar alguien con ingreso registrado.
                </p>

                <ul class="divide-y rounded-xl border bg-card shadow-sm">
                    <li v-for="m in material_exits" :key="m.id" class="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ m.description }} · {{ m.quantity }}
                                <span class="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs font-normal">{{ materialReasonLabel[m.reason] ?? m.reason }}</span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                Solicitó {{ m.requested_by }} · {{ m.created_at }}
                                <template v-if="m.approved_by"> · {{ m.status === 'rechazada' ? 'rechazó' : 'aprobó' }} {{ m.approved_by }}</template>
                                <template v-if="m.status === 'aprobada' && m.expires_at"> · válida hasta {{ m.expires_at }}</template>
                            </p>
                            <!-- Retiro: quién, cédula, placa, fecha y hora -->
                            <p v-if="m.status === 'ejecutada'" class="mt-0.5 text-xs">
                                Retiró <span class="font-medium">{{ m.taken_by }}</span> · CC {{ m.taken_cedula }}
                                <template v-if="m.exit_plate"> · placa <span class="font-mono font-semibold">{{ m.exit_plate }}</span></template>
                                · {{ m.executed_at }}<template v-if="m.executed_by"> · guarda {{ m.executed_by }}</template>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', materialStatusClass[m.status]]">
                                {{ materialStatusLabel[m.status] ?? m.status }}
                            </span>
                            <template v-if="can_approve_material_exit && m.status === 'pendiente'">
                                <Button class="h-9" @click="decideMaterialExit(m.id, 'aprobar')">Aprobar</Button>
                                <Button variant="outline" class="h-9" @click="decideMaterialExit(m.id, 'rechazar')">Rechazar</Button>
                            </template>
                        </div>
                    </li>
                    <li v-if="material_exits.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        No hay salidas de material solicitadas.
                    </li>
                </ul>
                <InputError :message="pageErrors.material_exit" />

                <form @submit.prevent="requestMaterialExit" class="grid grid-cols-2 gap-2 rounded-xl border bg-card p-3 shadow-sm sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-start">
                    <div class="col-span-2 grid gap-0.5 sm:col-span-1">
                        <Input v-model="materialForm.description" placeholder="Material (ej: Escombro de demolición) *" />
                        <InputError :message="materialForm.errors.description" class="text-xs" />
                    </div>
                    <div class="grid gap-0.5">
                        <Input v-model="materialForm.quantity" placeholder="Cantidad (ej: 6 bultos) *" />
                        <InputError :message="materialForm.errors.quantity" class="text-xs" />
                    </div>
                    <select v-model="materialForm.reason" class="flex h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm shadow-sm">
                        <option v-for="(label, value) in materialReasonLabel" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <Button type="submit" class="col-span-2 h-10 sm:col-span-1" :disabled="materialForm.processing">
                        <PackageCheck class="h-4 w-4" />
                        {{ can_approve_material_exit ? 'Autorizar salida' : 'Solicitar salida' }}
                    </Button>
                </form>
            </div>

            <!-- Datos -->
            <div v-if="tab === 'datos'">
                <form
                    v-if="can_decide"
                    @submit.prevent="saveWork"
                    class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2"
                >
                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="e_title">Descripción corta</Label>
                        <Input id="e_title" v-model="editForm.title" />
                        <InputError :message="editForm.errors.title" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_property">Inmueble</Label>
                        <select id="e_property" v-model="editForm.property_id" class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_type">Tipo</Label>
                        <select id="e_type" v-model="editForm.type" class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option v-for="(label, value) in workTypeLabel" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_company">Empresa</Label>
                        <Input id="e_company" v-model="editForm.contractor_company" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_name">Responsable</Label>
                        <Input id="e_name" v-model="editForm.contractor_name" />
                        <InputError :message="editForm.errors.contractor_name" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_doc">Cédula / NIT</Label>
                        <Input id="e_doc" v-model="editForm.contractor_document" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_phone">Teléfono</Label>
                        <Input id="e_phone" v-model="editForm.contractor_phone" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_start">Inicio</Label>
                        <Input id="e_start" v-model="editForm.start_date" type="date" />
                        <InputError :message="editForm.errors.start_date" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="e_end">Fin</Label>
                        <Input id="e_end" v-model="editForm.end_date" type="date" />
                        <InputError :message="editForm.errors.end_date" />
                    </div>
                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="e_schedule">Horario permitido</Label>
                        <Input id="e_schedule" v-model="editForm.schedule" />
                    </div>
                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="e_desc">Detalle</Label>
                        <textarea id="e_desc" v-model="editForm.description" rows="2" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <Button type="submit" class="h-10" :disabled="editForm.processing">Guardar cambios</Button>
                    </div>
                </form>

                <dl v-else class="grid gap-3 rounded-xl border bg-card p-4 text-sm shadow-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted-foreground">Responsable</dt><dd>{{ work.contractor_name }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Empresa</dt><dd>{{ work.contractor_company || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Cédula / NIT</dt><dd>{{ work.contractor_document || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Teléfono</dt><dd>{{ work.contractor_phone || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Fechas</dt><dd>{{ work.start_date }} → {{ work.end_date || 'sin fin' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Horario</dt><dd>{{ work.schedule || '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-foreground">Detalle</dt><dd class="whitespace-pre-line">{{ work.description || '—' }}</dd></div>
                </dl>
            </div>

            <!-- Bitácora de decisiones -->
            <div v-if="tab === 'bitacora'">
                <p class="mb-2 text-xs text-muted-foreground">
                    Creada por {{ work.created_by ?? '—' }}
                    <template v-if="work.decided_by"> · última decisión: {{ work.decided_by }} ({{ work.decided_at }})</template>
                </p>
                <ul class="divide-y rounded-xl border bg-card shadow-sm">
                    <li v-for="l in logs" :key="l.id" class="px-4 py-2.5 text-sm">
                        <p><span class="font-medium">{{ l.user }}</span> · {{ workLogLabel[l.action] ?? l.action }}</p>
                        <p class="text-xs text-muted-foreground">{{ l.at }}<template v-if="l.notes"> · {{ l.notes }}</template></p>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
