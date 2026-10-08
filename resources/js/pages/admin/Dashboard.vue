<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    ClipboardList,
    HardHat,
    Shield,
    Users,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';

const { auth } = usePage<{ auth: Auth }>().props;

interface Stats {
    works_pending: number;
    works_current: number;
    tools_alert: number;
    material_exits_pending: number;
    users: number;
    properties: number;
    entries_today: number;
    inside: number;
    authorizations: number;
    by_type: { propietario: number; autorizado: number; visitante: number };
}
interface RecentEntry {
    id: number;
    full_name: string;
    apartment: string;
    type: string;
    entry_at: string;
    is_inside: boolean;
}
defineProps<{ stats: Stats; recent: RecentEntry[] }>();

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    propietario: 'default',
    residente: 'default',
    autorizado: 'secondary',
    visitante: 'outline',
    proveedor: 'secondary',
};
const typeLabel: Record<string, string> = {
    propietario: 'Propietario',
    autorizado: 'Autorizado',
    visitante: 'Visitante',
    proveedor: 'Proveedor',
    residente: 'Residente',
};
</script>

<template>
    <AppLayout>
        <Head title="Panel Administrador" />
        <div class="flex flex-col gap-5 p-4 sm:p-6">

            <!-- Header futurista -->
            <div class="page-header">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">ACCESO · GUARD</p>
                <h1 class="mt-1 text-2xl font-bold text-foreground">Panel de Administración</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Bienvenido, <span class="font-semibold text-foreground">{{ auth.user.first_name }}</span>
                </p>
            </div>

            <!-- Stats principales -->
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card stat-card-accent">
                    <div class="absolute right-4 top-4 rounded-xl bg-primary/15 p-2">
                        <Activity class="h-4 w-4 text-primary" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-primary">Dentro ahora</p>
                    <p class="mt-1.5 text-3xl font-bold sm:text-4xl tracking-tight text-primary">{{ stats.inside }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ stats.entries_today }} ingresos hoy</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-blue-500/10 p-2">
                        <Users class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Propietarios</p>
                    <p class="mt-1.5 text-3xl font-bold sm:text-4xl tracking-tight">{{ stats.by_type.propietario }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">dentro del edificio</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-green-500/10 p-2">
                        <Shield class="h-4 w-4 text-green-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Autorizados</p>
                    <p class="mt-1.5 text-3xl font-bold sm:text-4xl tracking-tight">{{ stats.by_type.autorizado }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">dentro del edificio</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 rounded-xl bg-amber-500/10 p-2">
                        <Users class="h-4 w-4 text-amber-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Visitantes</p>
                    <p class="mt-1.5 text-3xl font-bold sm:text-4xl tracking-tight">{{ stats.by_type.visitante }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">dentro del edificio</p>
                </div>
            </div>

            <!-- Stats secundarias -->
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-violet-500/10 p-2 sm:block">
                        <Users class="h-4 w-4 text-violet-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Usuarios registrados</p>
                    <p class="mt-1.5 text-2xl font-bold tracking-tight sm:text-3xl">{{ stats.users }}</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-teal-500/10 p-2 sm:block">
                        <Building2 class="h-4 w-4 text-teal-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Inmuebles</p>
                    <p class="mt-1.5 text-2xl font-bold tracking-tight sm:text-3xl">{{ stats.properties }}</p>
                </div>
                <div class="stat-card">
                    <div class="absolute right-4 top-4 hidden rounded-xl bg-emerald-500/10 p-2 sm:block">
                        <Shield class="h-4 w-4 text-emerald-500" />
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground"><span class="sm:hidden">Autoriz. activas</span><span class="hidden sm:inline">Autorizaciones activas</span></p>
                    <p class="mt-1.5 text-2xl font-bold tracking-tight sm:text-3xl">{{ stats.authorizations }}</p>
                </div>
            </div>

            <!-- Obras -->
            <Link href="/works" class="grid grid-cols-2 gap-2 rounded-xl border bg-card p-3 shadow-sm transition-colors hover:border-primary/40 sm:grid-cols-4 sm:gap-4 sm:p-4">
                <div class="flex items-center gap-2">
                    <HardHat class="h-5 w-5 shrink-0 text-primary" />
                    <div>
                        <p class="text-xl font-bold leading-none">{{ stats.works_current }}</p>
                        <p class="text-xs text-muted-foreground">obras vigentes</p>
                    </div>
                </div>
                <div>
                    <p :class="['text-xl font-bold leading-none', stats.works_pending ? 'text-amber-600' : '']">{{ stats.works_pending }}</p>
                    <p class="text-xs text-muted-foreground">por aprobar</p>
                </div>
                <div>
                    <p :class="['text-xl font-bold leading-none', stats.material_exits_pending ? 'text-amber-600' : '']">{{ stats.material_exits_pending }}</p>
                    <p class="text-xs text-muted-foreground">salidas de material por aprobar</p>
                </div>
                <div>
                    <p :class="['text-xl font-bold leading-none', stats.tools_alert ? 'text-red-600' : '']">{{ stats.tools_alert }}</p>
                    <p class="text-xs text-muted-foreground">herramientas sin cuadrar</p>
                </div>
            </Link>

            <!-- Accesos rápidos -->
            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <Link href="/admin/users"
                    class="flex flex-col items-center gap-2 rounded-xl border p-3 text-center sm:flex-row sm:gap-4 sm:p-4 sm:text-left border-violet-200 bg-gradient-to-br from-violet-50 to-transparent transition-all hover:border-violet-400 hover:shadow-[0_4px_20px_rgba(139,92,246,0.12)]"
                >
                    <div class="rounded-xl bg-violet-500 p-2.5 shrink-0"><Users class="h-5 w-5 text-white" /></div>
                    <div class="min-w-0">
                        <p class="text-sm leading-tight font-semibold sm:truncate sm:text-base">Gestionar Usuarios</p>
                        <p class="text-xs text-muted-foreground">{{ stats.users }} usuarios</p>
                    </div>
                </Link>
                <Link href="/admin/properties"
                    class="flex flex-col items-center gap-2 rounded-xl border p-3 text-center sm:flex-row sm:gap-4 sm:p-4 sm:text-left border-teal-200 bg-gradient-to-br from-teal-50 to-transparent transition-all hover:border-teal-400 hover:shadow-[0_4px_20px_rgba(20,184,166,0.12)]"
                >
                    <div class="rounded-xl bg-teal-500 p-2.5 shrink-0"><Building2 class="h-5 w-5 text-white" /></div>
                    <div class="min-w-0">
                        <p class="text-sm leading-tight font-semibold sm:truncate sm:text-base">Inmuebles</p>
                        <p class="text-xs text-muted-foreground">{{ stats.properties }} registrados</p>
                    </div>
                </Link>
                <Link href="/admin/entries"
                    class="flex flex-col items-center gap-2 rounded-xl border p-3 text-center sm:flex-row sm:gap-4 sm:p-4 sm:text-left border-primary/20 bg-gradient-to-br from-primary/5 to-transparent transition-all hover:border-primary/40 hover:shadow-[0_4px_20px_rgba(30,111,255,0.12)]"
                >
                    <div class="rounded-xl bg-primary p-2.5 shrink-0"><ClipboardList class="h-5 w-5 text-white" /></div>
                    <div class="min-w-0">
                        <p class="text-sm leading-tight font-semibold sm:truncate sm:text-base">Historial de Ingresos</p>
                        <p class="text-xs text-muted-foreground">{{ stats.entries_today }} hoy</p>
                    </div>
                </Link>
            </div>

            <!-- Tabla últimos ingresos -->
            <div class="overflow-hidden rounded-xl border border-primary/10 bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-primary/10 bg-primary/[0.03] px-5 py-4">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="h-4 w-4 text-primary" />
                        <h2 class="font-semibold">Últimos ingresos del día</h2>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">{{ recent.length }}</span>
                    </div>
                </div>
                <!-- Móvil: lista -->
                <ul class="divide-y sm:hidden">
                    <li v-for="e in recent" :key="e.id" class="flex items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ e.full_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ e.apartment }} · {{ e.entry_at }}
                                <span v-if="e.is_inside" class="font-medium text-emerald-600">· Dentro</span>
                            </p>
                        </div>
                        <Badge :variant="typeVariant[e.type]" class="shrink-0">{{ typeLabel[e.type] ?? e.type }}</Badge>
                    </li>
                    <li v-if="recent.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        No hay ingresos registrados hoy.
                    </li>
                </ul>

                <!-- Tablet y PC: tabla -->
                <div class="hidden overflow-x-auto sm:block">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Destino</th>
                                <th>Tipo</th>
                                <th>Hora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in recent" :key="e.id">
                                <td class="px-5 py-3 font-medium">{{ e.full_name }}</td>
                                <td class="px-5 py-3 text-muted-foreground">{{ e.apartment }}</td>
                                <td class="px-5 py-3">
                                    <Badge :variant="typeVariant[e.type]">{{ typeLabel[e.type] ?? e.type }}</Badge>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs">{{ e.entry_at }}</td>
                                <td class="px-5 py-3">
                                    <span v-if="e.is_inside" class="inline-flex items-center gap-1.5 font-medium text-emerald-600">
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                        Dentro
                                    </span>
                                    <span v-else class="text-muted-foreground">Salió</span>
                                </td>
                            </tr>
                            <tr v-if="recent.length === 0">
                                <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                                    No hay ingresos registrados hoy.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
