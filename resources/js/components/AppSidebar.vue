<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    ClipboardList,
    CreditCard,
    FileText,
    HardHat,
    History,
    LayoutGrid,
    LogIn,
    LogOut,
    Settings,
    Shield,
    Users,
} from 'lucide-vue-next';

import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { Auth, NavItem } from '@/types';

const { auth } = usePage<{ auth: Auth }>().props;
const role = auth?.user?.role ?? '';

const works: NavItem = { title: 'Obras', href: '/works', icon: HardHat };

const adminItems: NavItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutGrid },
    { title: 'Usuarios', href: '/admin/users', icon: Users },
    { title: 'Ingresos', href: '/admin/entries', icon: ClipboardList },
    { title: 'Inmuebles', href: '/admin/properties', icon: Building2 },
    works,
    { title: 'Comunicados', href: '/admin/announcements', icon: Bell },
];

const navByRole: Record<string, NavItem[]> = {
    // Superusuario: todo lo del administrador, la portería y la configuración
    Superusuario: [
        ...adminItems,
        { title: 'Registrar Ingreso', href: '/vigilante/entries/create', icon: LogIn },
        { title: 'Registrar Salida', href: '/vigilante/exits', icon: LogOut },
        { title: 'Configuración', href: '/admin/settings/works', icon: Settings },
    ],
    Administrador: adminItems,
    Vigilante: [
        { title: 'Dashboard', href: '/vigilante/dashboard', icon: LayoutGrid },
        { title: 'Registrar Ingreso', href: '/vigilante/entries/create', icon: LogIn },
        { title: 'Monitor de Ingresos', href: '/vigilante/entries', icon: ClipboardList },
        { title: 'Registrar Salida', href: '/vigilante/exits', icon: LogOut },
        works,
        { title: 'Autorizaciones', href: '/vigilante/authorizations', icon: Shield },
        { title: 'Reportes', href: '/vigilante/reports', icon: FileText },
    ],
    Propietario: [
        { title: 'Dashboard', href: '/propietario/dashboard', icon: LayoutGrid },
        { title: 'Mi Carnet', href: '/carnet', icon: CreditCard },
        { title: 'Mis Autorizaciones', href: '/propietario/authorizations', icon: Shield },
        works,
        { title: 'Historial', href: '/propietario/history', icon: History },
        { title: 'Comunicados', href: '/announcements', icon: Bell },
    ],
    Residente: [
        { title: 'Dashboard', href: '/residente/dashboard', icon: LayoutGrid },
        { title: 'Mi Carnet', href: '/carnet', icon: CreditCard },
        { title: 'Mis Autorizaciones', href: '/residente/authorizations', icon: Shield },
        works,
        { title: 'Historial', href: '/residente/history', icon: History },
        { title: 'Comunicados', href: '/announcements', icon: Bell },
    ],
};

const mainNavItems: NavItem[] = navByRole[role] ?? [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
