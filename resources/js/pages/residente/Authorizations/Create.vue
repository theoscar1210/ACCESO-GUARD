<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const form = useForm({
    first_name:   '',
    last_name:    '',
    cedula:       '',
    plate:        '',
    vehicle:      '',
    type:         'visitante',
    start_date:   new Date().toISOString().split('T')[0],
    end_date:     '',
    observations: '',
});

function submit() {
    form.post('/residente/authorizations');
}
</script>

<template>
    <AppLayout>
        <Head title="Nueva Autorización" />
        <div class="mx-auto max-w-2xl p-4 sm:p-6">

            <div class="mb-6 flex flex-col gap-1">
                <Link href="/residente/authorizations" class="text-sm text-muted-foreground hover:text-foreground">
                    ← Mis Autorizaciones
                </Link>
                <h1 class="text-2xl font-bold">Nueva Autorización</h1>
                <p class="text-sm text-muted-foreground">Autoriza el ingreso de una persona a tu unidad</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5 rounded-xl border bg-card p-6 shadow-sm">

                <!-- Nombres y apellidos -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="first_name">Nombres *</Label>
                        <Input id="first_name" v-model="form.first_name" placeholder="Nombres" />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="last_name">Apellidos *</Label>
                        <Input id="last_name" v-model="form.last_name" placeholder="Apellidos" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <!-- Cédula y tipo -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cedula">Cédula *</Label>
                        <Input id="cedula" v-model="form.cedula" placeholder="Número de cédula" />
                        <InputError :message="form.errors.cedula" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="type">Tipo *</Label>
                        <select id="type" v-model="form.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm">
                            <option value="visitante">Visitante</option>
                            <option value="autorizado">Autorizado permanente</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>

                <!-- Placa y tipo de vehículo -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="plate">
                            Placa del vehículo
                            <span class="text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input id="plate" v-model="form.plate" placeholder="Ej: ABC-123" maxlength="20"
                            class="font-mono tracking-widest uppercase placeholder:normal-case placeholder:tracking-normal"
                            @input="form.plate = form.plate.toUpperCase()" />
                        <InputError :message="form.errors.plate" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="vehicle">
                            Tipo de vehículo
                            <span v-if="form.plate" class="text-destructive">*</span>
                        </Label>
                        <select id="vehicle" v-model="form.vehicle"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm">
                            <option value="">Sin vehículo / A pie</option>
                            <option value="automovil">Automóvil</option>
                            <option value="camioneta">Camioneta</option>
                            <option value="moto">Moto</option>
                            <option value="bicicleta">Bicicleta</option>
                        </select>
                        <InputError :message="form.errors.vehicle" />
                    </div>
                </div>
                <p class="-mt-3 text-xs text-muted-foreground">Si la persona llega a pie, deja la placa vacía.</p>

                <!-- Fechas -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="start_date">Fecha de inicio *</Label>
                        <Input id="start_date" type="date" v-model="form.start_date" />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="end_date">
                            Fecha de vencimiento
                            <span class="text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input id="end_date" type="datetime-local" v-model="form.end_date" />
                        <InputError :message="form.errors.end_date" />
                        <p class="text-xs text-muted-foreground">Vacío = sin vencimiento</p>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="grid gap-1.5">
                    <Label for="observations">Observaciones</Label>
                    <textarea id="observations" v-model="form.observations" rows="2"
                        placeholder="Ej: Personal de mantenimiento, familiar, etc."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground" />
                    <InputError :message="form.errors.observations" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :disabled="form.processing">Crear Autorización</Button>
                    <Button type="button" variant="outline" @click="form.reset()">Limpiar</Button>
                    <Link href="/residente/authorizations">
                        <Button type="button" variant="ghost">Cancelar</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
