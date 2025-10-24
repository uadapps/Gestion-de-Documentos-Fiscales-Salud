import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutGrid,
    Upload,
    Calendar,
    BarChart3,
    Activity,
    LigatureIcon
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user;

    // Obtener los roles del usuario
    const roles = (user as any).roles || [];
    const userRoles = Array.isArray(roles) ? roles.map((role: any) =>
        role.rol || role.ID_Rol || role.nombre
    ) : [];

    // Verificar roles específicos
    const isRole13or14 = userRoles.some(role => role === '13' || role === '14');
    const isRole16 = userRoles.some(role => role === '16');

    // Construir menú dinámicamente según el rol
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        // Opciones para roles 13 y 14 (gestión de documentos)
        ...(isRole13or14 ? [
            {
                title: 'Gestionar Documentos',
                href: '/documentos/upload',
                icon: Upload,
            },
        ] : []),
        // Opción para rol 16 (supervisión)
        ...(isRole16 ? [
            {
                title: 'Semáforo',
                href: '/supervision',
                icon: Activity,
            },
        ] : []),
    ];

    const footerNavItems: NavItem[] = [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
