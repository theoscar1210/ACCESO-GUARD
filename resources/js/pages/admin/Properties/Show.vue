<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface Owner {
    id: number;
    full_name: string;
    cedula: string;
    since: string | null;
}

interface ActiveRental {
    id: number;
    tenant_id: number;
    tenant_name: string;
    tenant_cedula: string;
    start_date: string | null;
    end_date: string | null;
}

interface RentalHistory {
    id: number;
    tenant_name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
}

interface FamilyMember {
    id: number;
    full_name: string;
    cedula: string;
    phone: string | null;
    relationship: string;
    belongs_to: string;
    user_id: number;
}

interface AvailableUser {
    id: number;
    first_name: string;
    last_name: string;
    cedula: string;
}

interface PropertyData {
    id: number;
    number: string;
    block: string | null;
    type: string;
    description: string | null;
    owners: Owner[];
    active_rental: ActiveRental | null;
    rental_history: RentalHistory[];
}

const props = defineProps<{
    property: PropertyData;
    owners_available: AvailableUser[];
    tenants_available: AvailableUser[];
    family_members: FamilyMember[];
}>();

const activeTab = ref<'datos' | 'propietarios' | 'residente' | 'familiar'>('datos');

// Confirm dialog state
const confirmOwnerDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const confirmRentalDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const confirmFamilyDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const pendingOwnerId = ref<number | null>(null);
const pendingFamilyId = ref<number | null>(null);

// --- Edit property form ---
const editForm = useForm({
    number: props.property.number,
    block: props.property.block ?? '',
    type: props.property.type,
    description: props.property.description ?? '',
});

function saveEdit() {
    editForm.put(`/admin/properties/${props.property.id}`);
}

// --- Assign owner ---
const ownerForm = useForm({ user_id: '', since_date: '' });

function assignOwner() {
    ownerForm.post(`/admin/properties/${props.property.id}/assign-owner`, {
        onSuccess: () => ownerForm.reset(),
    });
}

function removeOwner(userId: number) {
    pendingOwnerId.value = userId;
    confirmOwnerDialog.value?.show();
}

function confirmRemoveOwner() {
    if (pendingOwnerId.value) {
        router.delete(`/admin/properties/${props.property.id}/owners/${pendingOwnerId.value}`);
    }
}

// --- Assign tenant ---
const tenantForm = useForm({ user_id: '', start_date: '', end_date: '' });

function assignTenant() {
    tenantForm.post(`/admin/properties/${props.property.id}/assign-tenant`, {
        onSuccess: () => tenantForm.reset(),
    });
}

function endRental() {
    confirmRentalDialog.value?.show();
}

function confirmEndRental() {
    router.post(`/admin/properties/${props.property.id}/end-rental`);
}

// --- Family member form ---
const familyForm = useForm({
    user_id: '',
    first_name: '',
    last_name: '',
    cedula: '',
    phone: '',
    relationship: 'otro',
});

function addFamilyMember() {
    familyForm.post('/admin/family-members', {
        onSuccess: () => familyForm.reset(),
    });
}

function removeFamilyMember(id: number) {
    pendingFamilyId.value = id;
    confirmFamilyDialog.value?.show();
}

function confirmRemoveFamily() {
    if (pendingFamilyId.value) {
        router.delete(`/admin/family-members/${pendingFamilyId.value}`);
    }
}

const relationshipLabel: Record<string, string> = {
    esposo: 'Esposo',
    esposa: 'Esposa',
    hijo: 'Hijo',
    hija: 'Hija',
    padre: 'Padre',
    madre: 'Madre',
    hermano: 'Hermano',
    hermana: 'Hermana',
    otro: 'Otro',
};

const typeLabel: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    local: 'Local',
};

// Combined list of users (owners + current tenant) for family member assignment
const relatedUsers = [
    ...props.property.owners.map(o => ({ id: o.id, label: `${o.full_name} (Propietario)` })),
    ...(props.property.active_rental
        ? [{ id: props.property.active_rental.tenant_id, label: `${props.property.active_rental.tenant_name} (Residente)` }]
        : []),
];
</script>

<template>
    <AppLayout>
        <Head :title="`Inmueble ${property.number}`" />
        <div class="p-4 sm:p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link href="/admin/properties" class="shrink-0 py-2 text-muted-foreground hover:text-foreground"
                    >← Volver</Link
                >
                <div>
                    <h1 class="text-2xl font-bold">
                        {{ property.block ? `${property.block} – ` : '' }}{{ property.number }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ typeLabel[property.type] ?? property.type }}
                        <span v-if="property.description"> · {{ property.description }}</span>
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <!-- Una sola fila; en móvil se desliza en vez de partirse en dos líneas -->
            <div class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b px-4 sm:mx-0 sm:gap-2 sm:px-0">
                <button
                    v-for="tab in (['datos', 'propietarios', 'residente', 'familiar'] as const)"
                    :key="tab"
                    class="shrink-0 px-3 py-2.5 text-sm font-medium whitespace-nowrap capitalize transition-colors sm:px-4"
                    :class="
                        activeTab === tab
                            ? 'border-b-2 border-primary text-primary'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = tab"
                >
                    {{
                        tab === 'datos'
                            ? 'Datos'
                            : tab === 'propietarios'
                              ? `Propietarios (${property.owners.length})`
                              : tab === 'residente'
                                ? 'Residente'
                                : `Núcleo Familiar (${family_members.length})`
                    }}
                </button>
            </div>

            <!-- Tab: Datos -->
            <div v-if="activeTab === 'datos'">
                <form
                    @submit.prevent="saveEdit"
                    class="max-w-xl space-y-4 rounded-xl border bg-card p-6 shadow-sm"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="number">Número *</Label>
                            <Input id="number" v-model="editForm.number" />
                            <p v-if="editForm.errors.number" class="text-xs text-destructive">
                                {{ editForm.errors.number }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="block">Bloque / Torre</Label>
                            <Input id="block" v-model="editForm.block" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="type">Tipo *</Label>
                        <select
                            id="type"
                            v-model="editForm.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="apartamento">Apartamento</option>
                            <option value="casa">Casa</option>
                            <option value="local">Local</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="description">Descripción</Label>
                        <Input id="description" v-model="editForm.description" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="editForm.reset()">
                            Restaurar
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Tab: Propietarios -->
            <div v-if="activeTab === 'propietarios'" class="space-y-6">
                <!-- Current owners list -->
                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="border-b px-4 py-3 font-medium">Propietarios actuales</div>
                    <div v-if="property.owners.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        Sin propietarios asignados.
                    </div>
                    <div
                        v-for="o in property.owners"
                        :key="o.id"
                        class="flex items-center justify-between border-b px-4 py-3 last:border-0"
                    >
                        <div>
                            <p class="font-medium">{{ o.full_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                CI: {{ o.cedula }}
                                <span v-if="o.since"> · Desde: {{ o.since }}</span>
                            </p>
                        </div>
                        <Button variant="destructive" size="sm" @click="removeOwner(o.id)">
                            Quitar
                        </Button>
                    </div>
                </div>

                <!-- Assign owner form -->
                <div class="max-w-lg rounded-xl border bg-card p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">Agregar propietario</h3>
                    <form @submit.prevent="assignOwner" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Propietario *</Label>
                            <select
                                v-model="ownerForm.user_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="">Seleccionar propietario</option>
                                <option
                                    v-for="u in owners_available"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.first_name }} {{ u.last_name }} ({{ u.cedula }})
                                </option>
                            </select>
                            <p v-if="ownerForm.errors.user_id" class="text-xs text-destructive">
                                {{ ownerForm.errors.user_id }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Fecha desde</Label>
                            <Input type="date" v-model="ownerForm.since_date" />
                        </div>
                        <div class="flex gap-3">
                            <Button type="button" variant="outline" @click="ownerForm.reset()">
                                Limpiar
                            </Button>
                            <Button type="submit" :disabled="ownerForm.processing || !ownerForm.user_id">
                                Asignar propietario
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab: Residente -->
            <div v-if="activeTab === 'residente'" class="space-y-6">
                <!-- Active rental -->
                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="border-b px-4 py-3 font-medium">Residente actual</div>
                    <div v-if="!property.active_rental" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        Sin residente activo (inmueble disponible o en propiedad directa).
                    </div>
                    <div v-else class="px-4 py-4 space-y-2">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-purple-600 dark:text-purple-400">
                                    {{ property.active_rental.tenant_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    CI: {{ property.active_rental.tenant_cedula }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Desde: {{ property.active_rental.start_date ?? '—' }}
                                    <span v-if="property.active_rental.end_date">
                                        · Hasta: {{ property.active_rental.end_date }}
                                    </span>
                                </p>
                            </div>
                            <Button variant="destructive" size="sm" @click="endRental">
                                Finalizar
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Assign tenant form -->
                <div class="max-w-lg rounded-xl border bg-card p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">Registrar arrendatario</h3>
                    <form @submit.prevent="assignTenant" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Residente *</Label>
                            <select
                                v-model="tenantForm.user_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="">Seleccionar residente</option>
                                <option
                                    v-for="u in tenants_available"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.first_name }} {{ u.last_name }} ({{ u.cedula }})
                                </option>
                            </select>
                            <p v-if="tenantForm.errors.user_id" class="text-xs text-destructive">
                                {{ tenantForm.errors.user_id }}
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>Fecha inicio *</Label>
                                <Input type="date" v-model="tenantForm.start_date" />
                                <p v-if="tenantForm.errors.start_date" class="text-xs text-destructive">
                                    {{ tenantForm.errors.start_date }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Fecha fin</Label>
                                <Input type="date" v-model="tenantForm.end_date" />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Button type="button" variant="outline" @click="tenantForm.reset()">
                                Limpiar
                            </Button>
                            <Button type="submit" :disabled="tenantForm.processing || !tenantForm.user_id || !tenantForm.start_date">
                                Registrar arrendatario
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Rental history -->
                <div v-if="property.rental_history.length > 0" class="rounded-xl border bg-card shadow-sm">
                    <div class="border-b px-4 py-3 font-medium text-sm text-muted-foreground">Historial de arrendamientos</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-2 text-left font-medium">Residente</th>
                                    <th class="px-4 py-2 text-left font-medium">Inicio</th>
                                    <th class="px-4 py-2 text-left font-medium">Fin</th>
                                    <th class="px-4 py-2 text-left font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in property.rental_history"
                                    :key="r.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-2">{{ r.tenant_name }}</td>
                                    <td class="px-4 py-2">{{ r.start_date }}</td>
                                    <td class="px-4 py-2">{{ r.end_date }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            :class="r.is_active
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-muted text-muted-foreground'"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ r.is_active ? 'Activo' : 'Finalizado' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Núcleo Familiar -->
            <div v-if="activeTab === 'familiar'" class="space-y-6">
                <!-- Family members list -->
                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="border-b px-4 py-3 font-medium">Miembros registrados</div>
                    <div v-if="family_members.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                        No hay familiares registrados para este inmueble.
                    </div>
                    <div
                        v-for="f in family_members"
                        :key="f.id"
                        class="flex items-center justify-between border-b px-4 py-3 last:border-0"
                    >
                        <div>
                            <p class="font-medium">{{ f.full_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                CI: {{ f.cedula }}
                                · {{ relationshipLabel[f.relationship] ?? f.relationship }}
                                · Familia de: {{ f.belongs_to }}
                                <span v-if="f.phone"> · Tel: {{ f.phone }}</span>
                            </p>
                        </div>
                        <Button variant="destructive" size="sm" @click="removeFamilyMember(f.id)">
                            Eliminar
                        </Button>
                    </div>
                </div>

                <!-- Add family member form -->
                <div v-if="relatedUsers.length > 0" class="max-w-lg rounded-xl border bg-card p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">Agregar familiar</h3>
                    <form @submit.prevent="addFamilyMember" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Pertenece a *</Label>
                            <select
                                v-model="familyForm.user_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="">Seleccionar persona</option>
                                <option v-for="u in relatedUsers" :key="u.id" :value="u.id">
                                    {{ u.label }}
                                </option>
                            </select>
                            <p v-if="familyForm.errors.user_id" class="text-xs text-destructive">
                                {{ familyForm.errors.user_id }}
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>Nombres *</Label>
                                <Input v-model="familyForm.first_name" />
                                <p v-if="familyForm.errors.first_name" class="text-xs text-destructive">
                                    {{ familyForm.errors.first_name }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Apellidos *</Label>
                                <Input v-model="familyForm.last_name" />
                                <p v-if="familyForm.errors.last_name" class="text-xs text-destructive">
                                    {{ familyForm.errors.last_name }}
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>Cédula *</Label>
                                <Input v-model="familyForm.cedula" />
                                <p v-if="familyForm.errors.cedula" class="text-xs text-destructive">
                                    {{ familyForm.errors.cedula }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Teléfono</Label>
                                <Input v-model="familyForm.phone" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Parentesco *</Label>
                            <select
                                v-model="familyForm.relationship"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option v-for="(label, val) in relationshipLabel" :key="val" :value="val">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="familyForm.errors.relationship" class="text-xs text-destructive">
                                {{ familyForm.errors.relationship }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Button type="button" variant="outline" @click="familyForm.reset()">
                                Limpiar
                            </Button>
                            <Button
                                type="submit"
                                :disabled="familyForm.processing || !familyForm.user_id || !familyForm.first_name || !familyForm.cedula"
                            >
                                Agregar familiar
                            </Button>
                        </div>
                    </form>
                </div>
                <div v-else class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Primero asigna un propietario o residente para poder registrar familiares.
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        ref="confirmOwnerDialog"
        title="¿Quitar propietario?"
        description="Se eliminará la asignación de este propietario al inmueble."
        confirm-label="Sí, quitar"
        @confirm="confirmRemoveOwner"
    />
    <ConfirmDialog
        ref="confirmRentalDialog"
        title="¿Finalizar arrendamiento?"
        description="El residente actual perderá acceso al inmueble. Esta acción no se puede deshacer."
        confirm-label="Sí, finalizar"
        @confirm="confirmEndRental"
    />
    <ConfirmDialog
        ref="confirmFamilyDialog"
        title="¿Eliminar familiar?"
        description="Se eliminará este miembro del núcleo familiar. Su acceso como autorizado dejará de funcionar."
        confirm-label="Sí, eliminar"
        @confirm="confirmRemoveFamily"
    />
</template>
