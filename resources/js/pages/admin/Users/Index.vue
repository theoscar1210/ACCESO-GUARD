<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

interface User {
    id: number;
    full_name: string;
    username: string;
    email: string;
    cedula: string;
    phone: string;
    role: string;
    created_at: string;
    can_manage: boolean;
}

const props = defineProps<{ users: User[] }>();

const search = ref('');
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const pendingDelete = ref<{ id: number; name: string } | null>(null);

const filtered = () =>
    props.users.filter((u) => {
        const q = search.value.toLowerCase();
        return (
            u.full_name.toLowerCase().includes(q) ||
            u.username.toLowerCase().includes(q) ||
            u.cedula.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q)
        );
    });

const roleBadge: Record<string, string> = {
    Superusuario: 'bg-slate-900 text-white',
    Administrador: 'bg-red-100 text-red-800',
    Vigilante: 'bg-blue-100 text-blue-800',
    Propietario: 'bg-green-100 text-green-800',
    Residente: 'bg-amber-100 text-amber-800',
};

function askDestroy(id: number, name: string) {
    pendingDelete.value = { id, name };
    confirmDialog.value?.show();
}

function confirmDelete() {
    if (pendingDelete.value) {
        router.delete(`/admin/users/${pendingDelete.value.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Usuarios" />
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold">Usuarios</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestión de cuentas del sistema
                    </p>
                </div>
                <Link href="/admin/users/create">
                    <Button>+ Nuevo usuario</Button>
                </Link>
            </div>

            <div class="relative w-full sm:max-w-sm">
                <Input
                    v-model="search"
                    placeholder="Buscar por nombre, usuario, cédula o correo..."
                    :class="search ? 'pr-8' : ''"
                />
                <button
                    v-if="search"
                    @click="search = ''"
                    class="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
                    title="Limpiar búsqueda"
                ><X class="h-4 w-4" /></button>
            </div>

            <!-- Móvil y tablet: tarjetas -->
            <div class="grid gap-2 sm:grid-cols-2 lg:hidden">
                <div
                    v-for="u in filtered()"
                    :key="u.id"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ u.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ u.username }} · CC {{ u.cedula }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">{{ u.email }}</p>
                        </div>
                        <span
                            :class="[
                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                roleBadge[u.role] ?? 'bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ u.role }}
                        </span>
                    </div>
                    <div v-if="u.can_manage" class="flex gap-2">
                        <Link :href="`/admin/users/${u.id}/edit`" class="flex-1">
                            <Button variant="outline" class="h-10 w-full">Editar</Button>
                        </Link>
                        <Button
                            variant="destructive"
                            class="h-10 flex-1"
                            @click="askDestroy(u.id, u.full_name)"
                        >
                            Eliminar
                        </Button>
                    </div>
                </div>
                <p
                    v-if="filtered().length === 0"
                    class="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground sm:col-span-2"
                >
                    No se encontraron usuarios.
                </p>
            </div>

            <!-- PC: tabla -->
            <div class="hidden overflow-x-auto rounded-xl border bg-card shadow-sm lg:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Nombre
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Usuario
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Cédula
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Correo
                            </th>
                            <th class="px-4 py-3 text-left font-medium">Rol</th>
                            <th class="hidden px-4 py-3 text-left font-medium 2xl:table-cell">
                                Creado
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in filtered()"
                            :key="u.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ u.full_name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ u.username }}
                            </td>
                            <td class="px-4 py-3">{{ u.cedula }}</td>
                            <td class="px-4 py-3">{{ u.email }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        roleBadge[u.role] ??
                                            'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ u.role }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground 2xl:table-cell">
                                {{ u.created_at }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="u.can_manage" class="flex gap-2">
                                    <Link :href="`/admin/users/${u.id}/edit`">
                                        <Button variant="outline" size="sm"
                                            >Editar</Button
                                        >
                                    </Link>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="askDestroy(u.id, u.full_name)"
                                    >
                                        Eliminar
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filtered().length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                No se encontraron usuarios.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        ref="confirmDialog"
        :title="`¿Eliminar usuario?`"
        :description="pendingDelete ? `Se eliminará permanentemente a «${pendingDelete.name}». Esta acción no se puede deshacer.` : ''"
        confirm-label="Sí, eliminar"
        @confirm="confirmDelete"
    />
</template>
