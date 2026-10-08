<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    History,
    Shield,
    Users,
} from 'lucide-vue-next';
import PushNotificationToggle from '@/components/PushNotificationToggle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';

const { auth } = usePage<{ auth: Auth }>().props;

interface Stats {
    authorizations_active: number;
    authorizations_total: number;
    inside_now: number;
}
interface Authorization {
    id: number;
    full_name: string;
    cedula: string;
    type: string;
    status: string;
    start_date: string;
    end_date: string;
}
defineProps<{ stats: Stats; authorizations: Authorization[] }>();

const statusClass: Record<string, string> = {
    activo: 'bg-emerald-100 text-emerald-700',
    activa: 'bg-emerald-100 text-emerald-700',
    usado: 'bg-blue-100 text-blue-700',
    usada: 'bg-blue-100 text-blue-700',
    vencido: 'bg-red-100 text-red-700',
    expirada: 'bg-red-100 text-red-700',
};
const statusLabel: Record<string, string> = {
    activo: 'Activa',
    activa: 'Activa',
    usado: 'Usada',
    usada: 'Usada',
    vencido: 'Vencida',
    expirada: 'Vencida',
};
const typeLabel: Record<string, string> = {
    autorizado: 'Autorizado',
    visitante: 'Visitante',
};
const typeClass: Record<string, string> = {
    autorizado: 'bg-green-100 text-green-700',
    visitante: 'bg-amber-100 text-amber-700',
};
</script>

<template>
    <AppLayout>
        <Head title="Mi Panel" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <!-- Header futurista -->
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

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <div class="stat-card stat-card-accent">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-primary/15 p-2 sm:block">
                        <Shield class="h-4 w-4 text-primary" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-primary"><span class="sm:hidden">Activas</span><span class="hidden sm:inline">Autorizaciones activas</span></p>
                    <p class="mt-1.5 text-3xl font-bold tracking-tight sm:text-4xl text-primary">{{ stats.authorizations_active }}</p>
                    <p class="mt-1 hidden text-xs text-muted-foreground sm:block">vigentes en este momento</p>
                </div>

                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-blue-500/10 p-2 sm:block">
                        <CheckCircle2 class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground"><span class="sm:hidden">Total</span><span class="hidden sm:inline">Total autorizaciones</span></p>
                    <p class="mt-1.5 text-3xl font-bold tracking-tight sm:text-4xl">{{ stats.authorizations_total }}</p>
                    <p class="mt-1 hidden text-xs text-muted-foreground sm:block">historial completo</p>
                </div>

                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-teal-500/10 p-2 sm:block">
                        <Users class="h-4 w-4 text-teal-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">En el edificio</p>
                    <p class="mt-1.5 text-3xl font-bold tracking-tight sm:text-4xl text-teal-600">{{ stats.inside_now }}</p>
                    <p class="mt-1 hidden text-xs text-muted-foreground sm:block">relacionadas a tu unidad</p>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                <Link href="/propietario/authorizations/create"
                    class="flex flex-col items-center gap-2 rounded-xl border-2 p-3 text-center sm:flex-row sm:gap-4 sm:p-5 sm:text-left border-primary/25 bg-gradient-to-br from-primary/6 to-transparent transition-all hover:border-primary/50 hover:shadow-[0_4px_24px_rgba(30,111,255,0.15)]"
                >
                    <div class="rounded-xl bg-primary p-3 shrink-0 shadow-[0_4px_12px_rgba(30,111,255,0.25)]">
                        <Shield class="h-5 w-5 text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold">+ Nueva Autorización</p>
                        <p class="hidden text-xs text-muted-foreground sm:block">Autoriza el ingreso de un visitante</p>
                    </div>
                </Link>
                <Link href="/propietario/history"
                    class="flex flex-col items-center gap-2 rounded-xl border-2 p-3 text-center sm:flex-row sm:gap-4 sm:p-5 sm:text-left border-slate-200 bg-gradient-to-br from-slate-50 to-transparent transition-all hover:border-slate-400 hover:shadow-[0_4px_24px_rgba(100,116,139,0.12)]"
                >
                    <div class="rounded-xl bg-slate-600 p-3 shrink-0 shadow-[0_4px_12px_rgba(71,85,105,0.2)]">
                        <History class="h-5 w-5 text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900">Historial de Visitas</p>
                        <p class="hidden text-xs text-muted-foreground sm:block">Ver ingresos a tu unidad</p>
                    </div>
                </Link>
            </div>

            <!-- Tabla autorizaciones -->
            <div class="overflow-hidden rounded-xl border border-primary/10 bg-card shadow-sm">
                <div class="flex items-center justify-between gap-2 border-b border-primary/10 bg-primary/[0.03] px-4 py-3 sm:px-5 sm:py-4">
                    <div class="flex items-center gap-2">
                        <Shield class="h-4 w-4 text-primary" />
                        <h2 class="font-semibold">Mis autorizaciones</h2>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">{{ authorizations.length }}</span>
                    </div>
                    <Link href="/propietario/authorizations/create"
                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    >
                        + Nueva
                    </Link>
                </div>
                <!-- Móvil: lista -->
                <ul class="divide-y sm:hidden">
                    <li v-for="a in authorizations" :key="a.id" class="flex items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ a.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ typeLabel[a.type] ?? a.type }} · {{ a.start_date }} → {{ a.end_date || 'Sin vencimiento' }}
                            </p>
                        </div>
                        <span :class="['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold', statusClass[a.status] ?? 'bg-muted text-muted-foreground']">
                            {{ statusLabel[a.status] ?? a.status }}
                        </span>
                    </li>
                    <li v-if="authorizations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        No tienes autorizaciones.
                        <Link href="/propietario/authorizations/create" class="inline-block py-2 text-primary underline">Crea una ahora</Link>
                    </li>
                </ul>

                <!-- Tablet y PC: tabla -->
                <div class="hidden overflow-x-auto sm:block">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Tipo</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in authorizations" :key="a.id">
                                <td class="px-5 py-3 font-medium">{{ a.full_name }}</td>
                                <td class="px-5 py-3 text-muted-foreground font-mono text-xs">{{ a.cedula }}</td>
                                <td class="px-5 py-3">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', typeClass[a.type] ?? 'bg-muted text-muted-foreground']">
                                        {{ typeLabel[a.type] ?? a.type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-muted-foreground">
                                    {{ a.start_date }} <span class="text-muted-foreground/50">→</span> {{ a.end_date || 'Sin vencimiento' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', statusClass[a.status] ?? 'bg-muted text-muted-foreground']">
                                        {{ statusLabel[a.status] ?? a.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="authorizations.length === 0">
                                <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                                    No tienes autorizaciones.
                                    <Link href="/propietario/authorizations/create" class="text-primary underline ml-1">Crea una ahora</Link>.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
