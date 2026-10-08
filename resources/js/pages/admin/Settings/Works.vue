<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type RoleRules = { can_create: boolean; requires_approval: boolean };

const props = defineProps<{ roles: Record<string, RoleRules> }>();

// Copia independiente (las props reactivas de Vue no se pueden pasar por structuredClone)
const form = useForm({ roles: JSON.parse(JSON.stringify(props.roles)) as Record<string, RoleRules> });

const descriptions: Record<string, string> = {
    Administrador: 'Sus obras quedan aprobadas. Siempre puede aprobar, rechazar y revertir decisiones.',
    Propietario: 'Solo para su propio inmueble.',
    Residente: 'Solo para el inmueble que tiene arrendado.',
    Vigilante: 'Útil cuando el contratista llega a portería sin obra registrada.',
};

function save() {
    form.put('/admin/settings/works', { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Configuración de obras" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-3 sm:p-6">
            <div>
                <h1 class="text-xl font-bold sm:text-2xl">Configuración de obras</h1>
                <p class="text-sm text-muted-foreground">
                    Define qué roles pueden registrar obras y cuáles necesitan aprobación.
                </p>
            </div>

            <p class="flex items-start gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                El superusuario y el administrador siempre pueden aprobar, rechazar, editar, suspender, cerrar y reabrir cualquier obra. Cada decisión queda en la bitácora de la obra.
            </p>

            <form @submit.prevent="save" class="flex flex-col gap-3">
                <div class="divide-y rounded-xl border bg-card shadow-sm">
                    <div
                        v-for="(rules, role) in form.roles"
                        :key="role"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold">{{ role }}</p>
                            <p class="text-xs text-muted-foreground">{{ descriptions[role] }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-4">
                            <label class="flex h-10 cursor-pointer items-center gap-2 text-sm">
                                <input v-model="rules.can_create" type="checkbox" class="h-5 w-5 accent-primary" />
                                Puede crear
                            </label>
                            <label
                                :class="[
                                    'flex h-10 cursor-pointer items-center gap-2 text-sm',
                                    !rules.can_create || role === 'Administrador' ? 'pointer-events-none opacity-40' : '',
                                ]"
                            >
                                <input
                                    v-model="rules.requires_approval"
                                    type="checkbox"
                                    class="h-5 w-5 accent-primary"
                                    :disabled="!rules.can_create || role === 'Administrador'"
                                />
                                Requiere aprobación
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <Button type="submit" class="h-11 px-6" :disabled="form.processing">Guardar configuración</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
