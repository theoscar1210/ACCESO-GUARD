<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Authorization {
    id: number;
    full_name: string;
    cedula: string;
    plate: string | null;
    type: string;
    status: string;
    start_date: string;
    end_date: string | null;
    observations: string | null;
}

defineProps<{ authorizations: Authorization[] }>();

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const pendingId = ref<number | null>(null);

function askDestroy(id: number) {
    pendingId.value = id;
    confirmDialog.value?.show();
}

function confirmDelete() {
    if (pendingId.value) {
        router.delete(`/residente/authorizations/${pendingId.value}`);
    }
}

const statusClass: Record<string, string> = {
    activo:   'bg-emerald-100 text-emerald-700',
    activa:   'bg-emerald-100 text-emerald-700',
    usado:    'bg-blue-100 text-blue-700',
    vencido:  'bg-red-100 text-red-700',
};
const statusLabel: Record<string, string> = {
    activo: 'Activa', activa: 'Activa',
    usado: 'Usada',
    vencido: 'Vencida',
};
const typeLabel: Record<string, string> = {
    visitante:  'Visitante',
    autorizado: 'Autorizado permanente',
};
const typeClass: Record<string, string> = {
    visitante:  'bg-amber-100 text-amber-700',
    autorizado: 'bg-green-100 text-green-700',
};
</script>

<template>
    <AppLayout>
        <Head title="Mis Autorizaciones" />
        <div class="flex flex-col gap-6 p-4 sm:p-6">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="page-header">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                    <h1 class="mt-1 text-2xl font-bold">Mis Autorizaciones</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">Gestiona los ingresos autorizados para tu unidad</p>
                </div>
                <Link href="/residente/authorizations/create"
                    class="shrink-0 self-start rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                    + Nueva Autorización
                </Link>
            </div>

            <!-- Vacío -->
            <div v-if="authorizations.length === 0"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-muted-foreground/20 py-16 text-center">
                <Shield class="h-10 w-10 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No tienes autorizaciones creadas.</p>
                <Link href="/residente/authorizations/create" class="text-sm text-primary underline">
                    Crear una ahora
                </Link>
            </div>

            <!-- Lista -->
            <div v-else class="overflow-hidden rounded-xl border border-primary/10 bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Tipo</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in authorizations" :key="a.id">
                                <td class="px-5 py-3 font-medium">{{ a.full_name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-muted-foreground">
                                    {{ a.cedula }}
                                    <span v-if="a.plate" class="mt-0.5 block font-semibold tracking-wider text-foreground">🚗 {{ a.plate }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', typeClass[a.type] ?? 'bg-muted text-muted-foreground']">
                                        {{ typeLabel[a.type] ?? a.type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-muted-foreground">
                                    {{ a.start_date }}
                                    <span v-if="a.end_date"> → {{ a.end_date }}</span>
                                    <span v-else class="italic"> · Sin vencimiento</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', statusClass[a.status] ?? 'bg-muted text-muted-foreground']">
                                        {{ statusLabel[a.status] ?? a.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <button v-if="a.status === 'activo'"
                                        @click="askDestroy(a.id)"
                                        class="text-xs text-destructive hover:underline">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>

    <ConfirmDialog
        ref="confirmDialog"
        title="¿Eliminar autorización?"
        description="Se eliminará esta autorización. Si la persona ya está dentro, no se verá afectada hasta su próximo ingreso."
        confirm-label="Sí, eliminar"
        @confirm="confirmDelete"
    />
</template>
