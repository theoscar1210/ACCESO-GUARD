<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, Plus, Trash2, UserPlus } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { workTypeLabel } from '@/lib/works';

const props = defineProps<{
    properties: { id: number; label: string }[];
    requires_approval: boolean;
}>();

const emptyWorker = () => ({ first_name: '', last_name: '', cedula: '', phone: '' });

const form = useForm({
    // Propietario/residente solo tienen su inmueble: queda preseleccionado
    property_id: props.properties.length === 1 ? props.properties[0].id : ('' as number | ''),
    title: '',
    type: 'locativa',
    contractor_company: '',
    contractor_name: '',
    contractor_document: '',
    contractor_phone: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    schedule: '',
    description: '',
    workers: [emptyWorker()],
});

function addWorker() {
    form.workers.push(emptyWorker());
}

function removeWorker(index: number) {
    form.workers.splice(index, 1);
}

function workerError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`workers.${index}.${field}`];
}

function submit() {
    form.post('/works');
}
</script>

<template>
    <AppLayout>
        <Head title="Nueva obra" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-3 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold sm:text-2xl">Nueva obra</h1>
                <Link href="/works" class="flex items-center gap-1 py-2 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" /> Obras
                </Link>
            </div>

            <p
                v-if="requires_approval"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                La obra quedará pendiente hasta que el administrador la apruebe. Mientras tanto no se podrán registrar herramientas.
            </p>

            <p v-if="properties.length === 0" class="rounded-lg border bg-card p-4 text-sm text-muted-foreground">
                No tienes un inmueble asignado, por eso no puedes registrar obras.
            </p>

            <form v-else @submit.prevent="submit" class="flex flex-col gap-4">
                <!-- Datos de la obra -->
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2">
                    <h2 class="font-semibold sm:col-span-2">Datos de la obra</h2>

                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="title">Descripción corta *</Label>
                        <Input id="title" v-model="form.title" placeholder="Ej: Remodelación de cocina" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-1">
                        <Label for="property_id">Inmueble *</Label>
                        <select
                            id="property_id"
                            v-model="form.property_id"
                            :disabled="properties.length === 1"
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm disabled:opacity-70"
                        >
                            <option value="" disabled>Selecciona...</option>
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                        <InputError :message="form.errors.property_id" />
                    </div>

                    <div class="grid gap-1">
                        <Label>Tipo *</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="(label, value) in workTypeLabel"
                                :key="value"
                                type="button"
                                @click="form.type = value"
                                :class="[
                                    'h-10 rounded-md border-2 px-2 text-sm font-medium transition-colors',
                                    form.type === value ? 'border-primary bg-primary/5 text-primary' : 'border-input hover:bg-muted',
                                ]"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-1">
                        <Label for="start_date">Inicio *</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="end_date">Fin <span class="text-muted-foreground">(opcional)</span></Label>
                        <Input id="end_date" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="schedule">Horario permitido <span class="text-muted-foreground">(opcional)</span></Label>
                        <Input id="schedule" v-model="form.schedule" placeholder="Ej: Lunes a sábado 8:00 a 17:00" />
                    </div>

                    <div class="grid gap-1 sm:col-span-2">
                        <Label for="description">Detalle <span class="text-muted-foreground">(opcional)</span></Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                        />
                    </div>
                </section>

                <!-- Contratista -->
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2">
                    <h2 class="font-semibold sm:col-span-2">Contratista</h2>
                    <div class="grid gap-1">
                        <Label for="contractor_company">Empresa <span class="text-muted-foreground">(opcional)</span></Label>
                        <Input id="contractor_company" v-model="form.contractor_company" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="contractor_name">Responsable *</Label>
                        <Input id="contractor_name" v-model="form.contractor_name" />
                        <InputError :message="form.errors.contractor_name" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="contractor_document">Cédula / NIT</Label>
                        <Input id="contractor_document" v-model="form.contractor_document" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="contractor_phone">Teléfono</Label>
                        <Input id="contractor_phone" v-model="form.contractor_phone" inputmode="tel" />
                    </div>
                </section>

                <!-- Trabajadores -->
                <section class="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold">Trabajadores ({{ form.workers.length }})</h2>
                        <Button type="button" variant="outline" class="h-9" @click="addWorker">
                            <UserPlus class="h-4 w-4" /> Agregar
                        </Button>
                    </div>
                    <InputError :message="form.errors.workers" />

                    <div
                        v-for="(w, i) in form.workers"
                        :key="i"
                        class="grid grid-cols-2 gap-2 rounded-lg border bg-muted/30 p-3 sm:grid-cols-[1fr_1fr_1fr_1fr_auto] sm:items-start"
                    >
                        <div class="grid gap-0.5">
                            <Input v-model="w.first_name" placeholder="Nombres *" />
                            <InputError :message="workerError(i, 'first_name')" class="text-xs" />
                        </div>
                        <div class="grid gap-0.5">
                            <Input v-model="w.last_name" placeholder="Apellidos *" />
                            <InputError :message="workerError(i, 'last_name')" class="text-xs" />
                        </div>
                        <div class="grid gap-0.5">
                            <Input v-model="w.cedula" placeholder="Cédula *" inputmode="numeric" class="font-mono" />
                            <InputError :message="workerError(i, 'cedula')" class="text-xs" />
                        </div>
                        <Input v-model="w.phone" placeholder="Teléfono" inputmode="tel" />
                        <Button
                            v-if="form.workers.length > 1"
                            type="button"
                            variant="ghost"
                            class="col-span-2 h-9 text-destructive sm:col-span-1 sm:w-9 sm:px-0"
                            @click="removeWorker(i)"
                        >
                            <Trash2 class="h-4 w-4" /><span class="sm:hidden">Quitar</span>
                        </Button>
                    </div>
                </section>

                <div class="flex gap-2">
                    <Link href="/works" class="shrink-0">
                        <Button type="button" variant="outline" class="h-11">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="h-11 flex-1 text-base" :disabled="form.processing">
                        <Plus class="h-4 w-4" /> Registrar obra
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
