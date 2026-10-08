<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Send } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

const form = useForm({
    title: '',
    body: '',
    target: 'all',
    send_push: true,
});

function submit() {
    form.post('/admin/announcements');
}
</script>

<template>
    <AppLayout>
        <Head title="Nuevo Comunicado" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <div class="flex items-center gap-3">
                <Link href="/admin/announcements"
                    class="flex items-center gap-1.5 py-2 text-sm text-muted-foreground transition hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" />
                    Volver
                </Link>
            </div>

            <div class="page-header">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                <h1 class="mt-1 text-2xl font-bold">Nuevo Comunicado</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">El mensaje será enviado a los usuarios seleccionados</p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 max-w-xl">

                <!-- Título -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold">Título</label>
                    <input v-model="form.title" type="text" placeholder="Ej: Mantenimiento programado"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- Mensaje -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold">Mensaje</label>
                    <textarea v-model="form.body" rows="5" placeholder="Escribe el contenido del comunicado..."
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 resize-none" />
                    <p v-if="form.errors.body" class="text-xs text-red-500">{{ form.errors.body }}</p>
                </div>

                <!-- Dirigido a -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold">Dirigido a</label>
                    <select v-model="form.target"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="all">Todos (Propietarios y Residentes)</option>
                        <option value="propietario">Solo Propietarios</option>
                        <option value="residente">Solo Residentes</option>
                    </select>
                </div>

                <!-- Push notification toggle -->
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-muted/30 p-4 transition hover:bg-muted/50">
                    <div class="relative">
                        <input type="checkbox" v-model="form.send_push" class="sr-only peer" />
                        <div class="h-5 w-9 rounded-full bg-border peer-checked:bg-primary transition" />
                        <div class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition peer-checked:translate-x-4" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Enviar notificación push</p>
                        <p class="text-xs text-muted-foreground">Llegará al celular de los usuarios con la app instalada</p>
                    </div>
                    <Bell class="ml-auto h-4 w-4 text-muted-foreground" />
                </label>

                <div class="flex gap-3 pt-2">
                    <Link href="/admin/announcements"
                        class="rounded-lg border border-border bg-background px-4 py-2 text-sm font-semibold transition hover:bg-muted">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white shadow transition hover:opacity-90 disabled:opacity-50">
                        <Send class="h-4 w-4" />
                        {{ form.processing ? 'Enviando...' : 'Enviar Comunicado' }}
                    </button>
                </div>
            </form>

        </div>
    </AppLayout>
</template>
