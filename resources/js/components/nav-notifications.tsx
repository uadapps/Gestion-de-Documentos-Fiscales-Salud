import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { Bell, MessageSquare, AlertCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { router } from '@inertiajs/react';

interface Observacion {
    id: number;
    campus_id: number;
    documento_informacion_id: number | null;
    tipo_observacion: string;
    observacion: string;
    estatus: string;
    creado_por: number;
    creado_en: string;
    campus_nombre?: string;
    documento_nombre?: string;
}

export function NavNotifications() {
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const [observacionesPendientes, setObservacionesPendientes] = useState<Observacion[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);

    // Cargar observaciones pendientes
    const cargarObservacionesPendientes = async () => {
        setIsLoading(true);
        try {
            const response = await fetch('/observaciones/pendientes/todas');
            const data = await response.json();
            setObservacionesPendientes(data.observaciones || []);
        } catch (error) {
            console.error('Error cargando observaciones pendientes:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Cargar al montar y cada 2 minutos
    useEffect(() => {
        cargarObservacionesPendientes();
        const interval = setInterval(cargarObservacionesPendientes, 120000); // 2 minutos

        // Escuchar evento personalizado para recargar observaciones
        const handleReloadObservations = () => {
            cargarObservacionesPendientes();
        };
        window.addEventListener('observaciones-updated', handleReloadObservations);

        return () => {
            clearInterval(interval);
            window.removeEventListener('observaciones-updated', handleReloadObservations);
        };
    }, []);

    // Recargar cuando se abre el dropdown
    const handleOpenChange = (open: boolean) => {
        setIsOpen(open);
        if (open) {
            cargarObservacionesPendientes();
        }
    };

    const handleVerObservacion = (obs: Observacion) => {
        // Guardar el campus_id en sessionStorage para que la página lo detecte
        sessionStorage.setItem('campus_from_notification', obs.campus_id.toString());
        if (obs.documento_informacion_id) {
            sessionStorage.setItem('highlight_doc_from_notification', obs.documento_informacion_id.toString());
        }

        // Navegar sin parámetros en la URL
        router.visit('/documentos/upload', {
            preserveState: false,
            preserveScroll: false
        });
        setIsOpen(false);
    };

    const totalPendientes = observacionesPendientes.length;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent relative"
                            data-test="notifications-button"
                        >
                            <div className="flex items-center gap-3 w-full">
                                <div className="relative">
                                    <Bell className="size-5" />
                                    {totalPendientes > 0 && (
                                        <Badge
                                            className="absolute -top-2 -right-2 h-5 min-w-5 flex items-center justify-center bg-red-500 text-white text-xs p-0 px-1"
                                            variant="destructive"
                                        >
                                            {totalPendientes > 99 ? '99+' : totalPendientes}
                                        </Badge>
                                    )}
                                </div>
                                {state === 'expanded' && (
                                    <span className="flex-1 text-left font-medium">
                                        Notificaciones
                                    </span>
                                )}
                            </div>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-80 rounded-lg p-0"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                    >
                        <div className="flex items-center justify-between border-b p-4">
                            <h3 className="font-semibold text-base">Notificaciones</h3>
                            {totalPendientes > 0 && (
                                <Badge variant="secondary" className="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    {totalPendientes} pendiente{totalPendientes !== 1 ? 's' : ''}
                                </Badge>
                            )}
                        </div>

                        <div className="max-h-[400px] overflow-y-auto">
                            {isLoading ? (
                                <div className="flex items-center justify-center py-8">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                                </div>
                            ) : observacionesPendientes.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 px-4 text-center">
                                    <Bell className="h-12 w-12 text-muted-foreground/50 mb-2" />
                                    <p className="text-sm text-muted-foreground">
                                        No tienes observaciones pendientes
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {observacionesPendientes.map((obs) => (
                                        <button
                                            key={obs.id}
                                            onClick={() => handleVerObservacion(obs)}
                                            className="w-full text-left p-4 hover:bg-accent transition-colors"
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className="flex-shrink-0 mt-1">
                                                    {obs.documento_informacion_id ? (
                                                        <MessageSquare className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                                    ) : (
                                                        <AlertCircle className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                    )}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <Badge
                                                            variant="outline"
                                                            className={obs.documento_informacion_id
                                                                ? "bg-purple-100 text-purple-700 border-purple-300 dark:bg-purple-900/30 dark:text-purple-400"
                                                                : "bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/30 dark:text-blue-400"
                                                            }
                                                        >
                                                            {obs.documento_informacion_id ? 'Documento' : 'Campus'}
                                                        </Badge>
                                                        {obs.campus_nombre && (
                                                            <span className="text-xs text-muted-foreground truncate">
                                                                {obs.campus_nombre}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {obs.documento_nombre && (
                                                        <p className="text-sm font-medium mb-1 truncate">
                                                            {obs.documento_nombre}
                                                        </p>
                                                    )}
                                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                                        {obs.observacion}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {new Date(obs.creado_en).toLocaleDateString('es-MX', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            hour: '2-digit',
                                                            minute: '2-digit'
                                                        })}
                                                    </p>
                                                </div>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {totalPendientes > 0 && (
                            <div className="border-t p-2">
                                <Button
                                    variant="ghost"
                                    className="w-full text-xs"
                                    onClick={() => {
                                        router.visit('/documentos/upload');
                                        setIsOpen(false);
                                    }}
                                >
                                    Ver todas las notificaciones
                                </Button>
                            </div>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
