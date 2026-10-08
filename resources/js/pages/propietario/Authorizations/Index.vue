<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
        router.delete(`/propietario/authorizations/${pendingId.value}`);
    }
}

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    activo: 'default',
    usado: 'secondary',
    vencido: 'outline',
};

const typeLabel: Record<string, string> = {
    visitante: 'Visitante',
    autorizado: 'Autorizado',
};
</script>

<template>
    <AppLayout>
        <Head title="Mis Autorizaciones" />

        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold">Mis Autorizaciones</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestiona las autorizaciones de ingreso para tus
                        visitantes
                    </p>
                </div>
                <Link href="/propietario/authorizations/create">
                    <Button>+ Nueva Autorización</Button>
                </Link>
            </div>

            <!-- Vacío -->
            <div
                v-if="authorizations.length === 0"
                class="rounded-xl border bg-card p-12 text-center text-muted-foreground shadow-sm"
            >
                No tienes autorizaciones creadas.
                <Link
                    href="/propietario/authorizations/create"
                    class="ml-1 text-primary underline"
                >
                    Crear una
                </Link>
            </div>

            <!-- Lista -->
            <div v-else class="grid gap-3">
                <div
                    v-for="auth in authorizations"
                    :key="auth.id"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{
                                auth.full_name
                            }}</span>
                            <Badge :variant="statusVariant[auth.status]">{{
                                auth.status
                            }}</Badge>
                            <Badge variant="outline">{{
                                typeLabel[auth.type]
                            }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            CC {{ auth.cedula }}
                            <span v-if="auth.plate">
                                · Placa
                                <span class="font-mono font-semibold tracking-wider">{{ auth.plate }}</span>
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Desde {{ auth.start_date }}
                            <span v-if="auth.end_date">
                                hasta {{ auth.end_date }}</span
                            >
                            <span v-else> · Sin fecha de vencimiento</span>
                        </p>
                        <p
                            v-if="auth.observations"
                            class="text-sm text-muted-foreground italic"
                        >
                            "{{ auth.observations }}"
                        </p>
                    </div>
                    <button
                        v-if="auth.status === 'activo'"
                        @click="askDestroy(auth.id)"
                        class="shrink-0 self-start rounded-md px-3 py-1.5 text-sm text-destructive transition-colors hover:bg-destructive/10"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        ref="confirmDialog"
        title="¿Eliminar autorización?"
        description="Se eliminará esta autorización. Si la persona ya tiene acceso activo, dejará de estar autorizada."
        confirm-label="Sí, eliminar"
        @confirm="confirmDelete"
    />
</template>
