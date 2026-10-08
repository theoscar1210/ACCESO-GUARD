<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRightLeft,
    ClipboardList,
    LogIn,
    LogOut,
    Shield,
    Users,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';

const { auth } = usePage<{ auth: Auth }>().props;

interface Stats {
    entries_today: number;
    inside: number;
    exits_today: number;
    authorizations: number;
}
interface InsideEntry {
    id: number;
    full_name: string;
    apartment: string;
    type: string;
    entry_at: string;
}
defineProps<{ stats: Stats; inside: InsideEntry[] }>();

const typeBadge: Record<string, string> = {
    propietario: 'bg-blue-100 text-blue-700',
    residente: 'bg-purple-100 text-purple-700',
    autorizado: 'bg-green-100 text-green-700',
    visitante: 'bg-amber-100 text-amber-700',
    proveedor: 'bg-orange-100 text-orange-700',
    contratista: 'bg-orange-100 text-orange-700',
};
const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    residente: 'Residente',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
    proveedor: 'Proveedor',
    contratista: 'Contratista',
};
</script>

<template>
    <AppLayout>
        <Head title="Panel Vigilante" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <!-- Header futurista -->
            <div class="page-header">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                <h1 class="mt-1 text-2xl font-bold">Panel de Vigilancia</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Bienvenido, <span class="font-semibold text-foreground">{{ auth.user.first_name }}</span>
                    &mdash;
                    {{ new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }) }}
                </p>
            </div>

            <!-- Stat cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card stat-card-accent">
                    <div class="absolute right-4 top-4 rounded-xl bg-primary/15 p-2">
                        <Activity class="h-4 w-4 text-primary" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-primary">Dentro ahora</p>
                    <p class="mt-1.5 text-4xl font-bold tracking-tight text-primary">{{ stats.inside }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">personas en el edificio</p>
                </div>

                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-emerald-500/10 p-2">
                        <LogIn class="h-4 w-4 text-emerald-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Ingresos hoy</p>
                    <p class="mt-1.5 text-4xl font-bold tracking-tight text-emerald-600">{{ stats.entries_today }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">registrados durante el día</p>
                </div>

                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-slate-400/10 p-2">
                        <LogOut class="h-4 w-4 text-slate-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Salidas hoy</p>
                    <p class="mt-1.5 text-4xl font-bold tracking-tight text-slate-600">{{ stats.exits_today }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">registradas durante el día</p>
                </div>

                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-blue-500/10 p-2">
                        <Shield class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Autorizaciones</p>
                    <p class="mt-1.5 text-4xl font-bold tracking-tight text-blue-600">{{ stats.authorizations }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">activas vigentes</p>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div>
                <h2 class="mb-3 text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground">Acciones rápidas</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Link href="/vigilante/entries/create"
                        class="relative overflow-hidden flex items-center gap-4 rounded-xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-transparent p-5 transition-all hover:border-emerald-400 hover:shadow-[0_4px_24px_rgba(16,185,129,0.18)]"
                    >
                        <div class="rounded-xl bg-emerald-500 p-3 shrink-0 shadow-[0_4px_12px_rgba(16,185,129,0.3)]">
                            <LogIn class="h-6 w-6 text-white" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-emerald-900">Registrar Ingreso</p>
                            <p class="text-xs text-emerald-700">Visitante, propietario o contratista</p>
                        </div>
                    </Link>

                    <Link href="/vigilante/exits"
                        class="relative overflow-hidden flex items-center gap-4 rounded-xl border-2 border-slate-200 bg-gradient-to-br from-slate-50 to-transparent p-5 transition-all hover:border-slate-400 hover:shadow-[0_4px_24px_rgba(100,116,139,0.15)]"
                    >
                        <div class="rounded-xl bg-slate-600 p-3 shrink-0 shadow-[0_4px_12px_rgba(71,85,105,0.25)]">
                            <LogOut class="h-6 w-6 text-white" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">Registrar Salida</p>
                            <p class="text-xs text-slate-600">{{ stats.inside }} persona(s) dentro</p>
                        </div>
                    </Link>

                    <Link href="/vigilante/authorizations"
                        class="relative overflow-hidden flex items-center gap-4 rounded-xl border-2 border-primary/25 bg-gradient-to-br from-primary/6 to-transparent p-5 transition-all hover:border-primary/50 hover:shadow-[0_4px_24px_rgba(30,111,255,0.18)]"
                    >
                        <div class="rounded-xl bg-primary p-3 shrink-0 shadow-[0_4px_12px_rgba(30,111,255,0.3)]">
                            <Shield class="h-6 w-6 text-white" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-blue-900">Autorizaciones</p>
                            <p class="text-xs text-blue-700">{{ stats.authorizations }} activa(s)</p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Personas dentro ahora -->
            <div class="overflow-hidden rounded-xl border border-primary/10 bg-card shadow-sm">
                <div class="flex flex-col gap-2 border-b border-primary/10 bg-primary/[0.03] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <ArrowRightLeft class="h-4 w-4 text-primary" />
                        <h2 class="font-semibold">Personas dentro ahora</h2>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">{{ inside.length }}</span>
                    </div>
                    <Link href="/vigilante/entries" class="flex items-center gap-1 text-sm text-primary hover:underline">
                        <ClipboardList class="h-3.5 w-3.5" />
                        Ver historial completo
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Apto</th>
                                <th>Tipo</th>
                                <th>Ingresó</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in inside" :key="e.id">
                                <td class="px-5 py-3 font-medium">{{ e.full_name }}</td>
                                <td class="px-5 py-3 text-muted-foreground">{{ e.apartment }}</td>
                                <td class="px-5 py-3">
                                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', typeBadge[e.type] ?? 'bg-muted text-muted-foreground']">
                                        {{ typeLabel[e.type] ?? e.type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-muted-foreground">{{ e.entry_at }}</td>
                            </tr>
                            <tr v-if="inside.length === 0">
                                <td colspan="4" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                        <Users class="h-8 w-8 opacity-25" />
                                        <span>No hay personas dentro del edificio en este momento.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
