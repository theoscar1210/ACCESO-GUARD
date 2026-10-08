<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface Owner {
    id: number;
    full_name: string;
}

interface Tenant {
    id: number;
    full_name: string;
    since: string;
}

interface Property {
    id: number;
    number: string;
    block: string | null;
    type: string;
    description: string | null;
    owners: Owner[];
    tenant: Tenant | null;
}

defineProps<{ properties: Property[] }>();

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const pendingDeleteId = ref<number | null>(null);

function askDestroy(id: number) {
    pendingDeleteId.value = id;
    confirmDialog.value?.show();
}

function confirmDelete() {
    if (pendingDeleteId.value) {
        router.delete(`/admin/properties/${pendingDeleteId.value}`);
    }
}

const typeLabel: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    local: 'Local',
};
</script>

<template>
    <AppLayout>
        <Head title="Inmuebles" />
        <div class="p-4 sm:p-6">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Inmuebles</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestión de propiedades del conjunto
                    </p>
                </div>
                <Link href="/admin/properties/create">
                    <Button>+ Nuevo Inmueble</Button>
                </Link>
            </div>

            <!-- Móvil y tablet: tarjetas -->
            <div class="grid gap-2 sm:grid-cols-2 lg:hidden">
                <div
                    v-for="p in properties"
                    :key="p.id"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ p.number }}
                                <span v-if="p.block" class="font-normal text-muted-foreground">· {{ p.block }}</span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                Propietario:
                                <span class="text-foreground">{{ p.owners.length ? p.owners.map(o => o.full_name).join(', ') : 'Sin asignar' }}</span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                Residente:
                                <span :class="p.tenant ? 'text-purple-600 dark:text-purple-400' : ''">{{ p.tenant?.full_name ?? '—' }}</span>
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                            {{ typeLabel[p.type] ?? p.type }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="`/admin/properties/${p.id}`" class="flex-1">
                            <Button variant="outline" class="h-10 w-full">Ver</Button>
                        </Link>
                        <Button variant="destructive" class="h-10 flex-1" @click="askDestroy(p.id)">
                            Eliminar
                        </Button>
                    </div>
                </div>
                <p
                    v-if="properties.length === 0"
                    class="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground sm:col-span-2"
                >
                    No hay inmuebles registrados.
                </p>
            </div>

            <!-- PC: tabla -->
            <div class="hidden overflow-x-auto rounded-xl border bg-card shadow-sm lg:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="px-4 py-3 text-left font-medium">Número</th>
                            <th class="px-4 py-3 text-left font-medium">Bloque</th>
                            <th class="px-4 py-3 text-left font-medium">Tipo</th>
                            <th class="px-4 py-3 text-left font-medium">Propietario(s)</th>
                            <th class="px-4 py-3 text-left font-medium">Residente</th>
                            <th class="px-4 py-3 text-left font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in properties"
                            :key="p.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-semibold">{{ p.number }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ p.block ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                    {{ typeLabel[p.type] ?? p.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="p.owners.length === 0" class="text-muted-foreground">Sin asignar</span>
                                <span v-else>{{ p.owners.map(o => o.full_name).join(', ') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="!p.tenant" class="text-muted-foreground">—</span>
                                <span v-else class="text-purple-600 dark:text-purple-400">
                                    {{ p.tenant.full_name }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Link :href="`/admin/properties/${p.id}`">
                                        <Button variant="outline" size="sm">Ver</Button>
                                    </Link>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="askDestroy(p.id)"
                                    >Eliminar</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="properties.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No hay inmuebles registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        ref="confirmDialog"
        title="¿Eliminar inmueble?"
        description="Se eliminará el inmueble y todos sus datos asociados. Esta acción no se puede deshacer."
        confirm-label="Sí, eliminar"
        @confirm="confirmDelete"
    />
</template>
