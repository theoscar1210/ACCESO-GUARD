<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const form = useForm({
    number: '',
    block: '',
    type: 'apartamento',
    description: '',
});

function submit() {
    form.post('/admin/properties');
}
</script>

<template>
    <AppLayout>
        <Head title="Nuevo Inmueble" />
        <div class="mx-auto max-w-xl p-4 sm:p-6">
            <div class="mb-6 flex items-center gap-4">
                <Link href="/admin/properties" class="shrink-0 py-2 text-muted-foreground hover:text-foreground"
                    >← Volver</Link
                >
                <div>
                    <h1 class="text-2xl font-bold">Nuevo Inmueble</h1>
                    <p class="text-sm text-muted-foreground">Registrar apartamento, casa o local</p>
                </div>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-5 rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="number">Número *</Label>
                        <Input id="number" v-model="form.number" placeholder="Ej: 101, A-302" />
                        <p v-if="form.errors.number" class="text-xs text-destructive">
                            {{ form.errors.number }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="block">Bloque / Torre</Label>
                        <Input id="block" v-model="form.block" placeholder="Ej: Torre A" />
                        <p v-if="form.errors.block" class="text-xs text-destructive">
                            {{ form.errors.block }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="type">Tipo *</Label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="apartamento">Apartamento</option>
                        <option value="casa">Casa</option>
                        <option value="local">Local</option>
                    </select>
                    <p v-if="form.errors.type" class="text-xs text-destructive">
                        {{ form.errors.type }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="description">Descripción</Label>
                    <Input
                        id="description"
                        v-model="form.description"
                        placeholder="Notas adicionales"
                    />
                    <p v-if="form.errors.description" class="text-xs text-destructive">
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link href="/admin/properties">
                        <Button type="button" variant="outline">Cancelar</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Guardando...' : 'Crear Inmueble' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
