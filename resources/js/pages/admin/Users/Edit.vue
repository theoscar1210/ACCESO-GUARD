<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface UserData {
    id: number;
    first_name: string;
    last_name: string;
    cedula: string;
    phone: string;
    username: string;
    email: string;
    role: string;
}

const props = defineProps<{ user: UserData; roles: string[] }>();

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    cedula: props.user.cedula,
    phone: props.user.phone ?? '',
    username: props.user.username,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role ?? '',
});

function submit() {
    form.put(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <AppLayout>
        <Head title="Editar Usuario" />
        <div class="mx-auto max-w-2xl p-4 sm:p-6">
            <div class="mb-6 flex items-center gap-4">
                <Link
                    href="/admin/users"
                    class="shrink-0 py-2 text-muted-foreground hover:text-foreground"
                    >← Volver</Link
                >
                <div>
                    <h1 class="text-2xl font-bold">Editar Usuario</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ user.first_name }} {{ user.last_name }}
                    </p>
                </div>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-5 rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="first_name">Nombres *</Label>
                        <Input id="first_name" v-model="form.first_name" />
                        <p
                            v-if="form.errors.first_name"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.first_name }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="last_name">Apellidos *</Label>
                        <Input id="last_name" v-model="form.last_name" />
                        <p
                            v-if="form.errors.last_name"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.last_name }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cedula">Cédula *</Label>
                        <Input id="cedula" v-model="form.cedula" />
                        <p
                            v-if="form.errors.cedula"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.cedula }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="phone">Teléfono</Label>
                        <Input id="phone" v-model="form.phone" />
                        <p
                            v-if="form.errors.phone"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <!-- Rol -->
                <div class="grid gap-1.5">
                    <Label for="role">Rol *</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="">Seleccionar rol</option>
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                    <p v-if="form.errors.role" class="text-xs text-destructive">
                        {{ form.errors.role }}
                    </p>
                </div>


                <div class="grid gap-1.5">
                    <Label for="username">Usuario *</Label>
                    <Input id="username" v-model="form.username" />
                    <p
                        v-if="form.errors.username"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.username }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="email">Correo electrónico *</Label>
                    <Input id="email" type="email" v-model="form.email" />
                    <p
                        v-if="form.errors.email"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label
                        >Contraseña
                        <span class="text-muted-foreground"
                            >(dejar en blanco para no cambiar)</span
                        ></Label
                    >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Input
                                id="password"
                                type="password"
                                v-model="form.password"
                                placeholder="Nueva contraseña"
                            />
                            <p
                                v-if="form.errors.password"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Input
                                id="password_confirmation"
                                type="password"
                                v-model="form.password_confirmation"
                                placeholder="Confirmar contraseña"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link href="/admin/users">
                        <Button type="button" variant="outline"
                            >Cancelar</Button
                        >
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing ? 'Guardando...' : 'Guardar cambios'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

