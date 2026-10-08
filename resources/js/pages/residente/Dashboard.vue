<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bell, Building2, CreditCard, History, Users } from 'lucide-vue-next';
import PushNotificationToggle from '@/components/PushNotificationToggle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';

const { auth } = usePage<{ auth: Auth }>().props;

defineProps<{
    stats: { inside_now: number; entries_today: number };
    unread: number;
    apartment: string | null;
}>();
</script>

<template>
    <AppLayout>
        <Head title="Mi Panel" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="page-header">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                    <h1 class="mt-1 text-2xl font-bold">Mi Panel</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Bienvenido, <span class="font-semibold text-foreground">{{ auth.user.first_name }}</span>
                    </p>
                </div>
                <PushNotificationToggle />
            </div>

            <!-- Alerta comunicados sin leer -->
            <Link v-if="unread > 0" href="/announcements"
                class="flex items-center gap-3 rounded-xl border border-amber-400/30 bg-amber-50 p-4 transition hover:bg-amber-100 dark:bg-amber-500/10 dark:hover:bg-amber-500/15">
                <Bell class="h-5 w-5 shrink-0 text-amber-500" />
                <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                    Tienes <strong>{{ unread }}</strong> comunicado{{ unread > 1 ? 's' : '' }} sin leer
                </p>
            </Link>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-2 sm:gap-4">
                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-teal-500/10 p-2 sm:block">
                        <Users class="h-4 w-4 text-teal-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">En el edificio ahora</p>
                    <p class="mt-1.5 text-3xl font-bold tracking-tight sm:text-4xl text-teal-600">{{ stats.inside_now }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">personas en tu unidad</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-blue-500/10 p-2 sm:block">
                        <History class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Ingresos hoy</p>
                    <p class="mt-1.5 text-3xl font-bold tracking-tight sm:text-4xl text-blue-600">{{ stats.entries_today }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">registros del día</p>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                <Link href="/carnet"
                    class="flex flex-col items-center gap-2 rounded-xl border-2 border-violet-200 p-3 text-center sm:flex-row sm:gap-4 sm:p-5 sm:text-left bg-gradient-to-br from-violet-50 to-transparent transition-all hover:border-violet-400 hover:shadow-[0_4px_24px_rgba(139,92,246,0.15)]">
                    <div class="rounded-xl bg-violet-600 p-3 shrink-0 shadow-[0_4px_12px_rgba(139,92,246,0.25)]">
                        <CreditCard class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="font-bold text-violet-900 dark:text-violet-300">Mi Carnet Digital</p>
                        <p class="hidden text-xs text-muted-foreground sm:block">Identifícate con QR</p>
                    </div>
                </Link>

                <Link href="/announcements"
                    class="relative flex flex-col items-center gap-2 rounded-xl border-2 border-amber-200 p-3 text-center sm:flex-row sm:gap-4 sm:p-5 sm:text-left bg-gradient-to-br from-amber-50 to-transparent transition-all hover:border-amber-400 hover:shadow-[0_4px_24px_rgba(245,158,11,0.15)]">
                    <div class="rounded-xl bg-amber-500 p-3 shrink-0 shadow-[0_4px_12px_rgba(245,158,11,0.25)]">
                        <Bell class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="font-bold text-amber-900 dark:text-amber-300">Comunicados</p>
                        <p class="hidden text-xs text-muted-foreground sm:block">Avisos de la administración</p>
                    </div>
                    <span v-if="unread > 0"
                        class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                        {{ unread }}
                    </span>
                </Link>
            </div>

            <!-- Info unidad -->
            <div v-if="apartment" class="flex items-center gap-3 rounded-xl border border-border bg-muted/30 px-5 py-4">
                <Building2 class="h-5 w-5 shrink-0 text-muted-foreground" />
                <div>
                    <p class="text-xs text-muted-foreground">Unidad asignada</p>
                    <p class="font-bold">{{ apartment }}</p>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-muted-foreground/20 px-5 py-4 text-center">
                <p class="text-sm text-muted-foreground">No tienes una unidad asignada aún.</p>
            </div>

        </div>
    </AppLayout>
</template>
