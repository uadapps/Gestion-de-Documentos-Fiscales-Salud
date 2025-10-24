import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';
import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Upload, Shield, BarChart3, Users, AlertTriangle, TrendingUp, FileText, Clock, CheckCircle, XCircle } from 'lucide-react';
import DonutChart from '@/components/charts/DonutChart';
import SimpleBarChart from '@/components/charts/SimpleBarChart';
import TrendChart from '@/components/charts/TrendChart';

interface EstadisticasCampus {
    total_documentos: number;
    pendientes: number;
    aprobados: number;
    en_revision: number;
    caducados: number;
    rechazados: number;
    subidos: number;
}

interface EstadisticasSupervisor {
    total_campus: number;
    documentos_por_estado: EstadisticasCampus;
    cumplimiento_promedio: number;
    campus_criticos: number;
    usuarios_activos: number;
}

interface DocumentoPorTipo {
    tipo_documento: 'fiscales' | 'medicos';
    cantidad: number;
    aprobados: number;
}

interface ActividadReciente {
    documento_nombre: string;
    estado: string;
    updated_at: string;
    campus_nombre?: string;
    tipo_documento?: string;
}

interface CampusCumplimiento {
    ID_Campus: number;
    Campus: string;
    total_documentos: number;
    documentos_aprobados: number;
    porcentaje_cumplimiento: number;
}

interface DocumentoVencido {
    documento_nombre: string;
    fecha_vigencia_ia: string;
    estado: string;
    dias_restantes: number;
}

interface EstadisticasPorCampus {
    campus_id: number;
    campus_nombre: string;
    fiscales: EstadisticasCampus;
    medicos: EstadisticasCampus;
    total_documentos: number;
    total_aprobados: number;
    porcentaje_cumplimiento: number;
    tiene_fiscales: boolean;
    tiene_medicos: boolean;
}

interface DashboardData {
    tipo_usuario: 'campus' | 'supervisor';
    estadisticas: EstadisticasCampus | EstadisticasSupervisor;
    estadisticas_fiscales?: EstadisticasCampus;
    estadisticas_medicos?: EstadisticasCampus;
    estadisticas_por_campus?: EstadisticasPorCampus[];
    documentos_por_tipo?: DocumentoPorTipo[];
    actividad_reciente?: ActividadReciente[];
    documentos_vencidos?: DocumentoVencido[];
    cumplimiento_por_campus?: CampusCumplimiento[];
    alertas_criticas?: CampusCumplimiento[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user;

    const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Obtener los roles del usuario
    const roles = (user as any).roles || [];
    const userRoles = Array.isArray(roles) ? roles.map((role: any) =>
        role.rol || role.ID_Rol || role.nombre
    ) : [];

    // Verificar roles específicos
    const isRole13or14 = userRoles.some(role => role === '13' || role === '14');
    const isRole16 = userRoles.some(role => role === '16');

    // Cargar estadísticas del dashboard
    useEffect(() => {
        const cargarEstadisticas = async () => {
            try {
                setLoading(true);
                setError(null);

                const response = await fetch('/api/dashboard/estadisticas');

                if (!response.ok) {
                    throw new Error('Error al cargar estadísticas');
                }

                const data = await response.json();
                setDashboardData(data);
            } catch (err) {
                console.error('Error cargando estadísticas:', err);
                setError(err instanceof Error ? err.message : 'Error desconocido');
            } finally {
                setLoading(false);
            }
        };

        cargarEstadisticas();
    }, []);

    // Preparar datos para gráficas
    const prepararDatosGraficas = () => {
        if (!dashboardData) return null;

        if (dashboardData.tipo_usuario === 'campus') {
            const stats = dashboardData.estadisticas as EstadisticasCampus;
            const statsFiscales = dashboardData.estadisticas_fiscales;
            const statsMedicos = dashboardData.estadisticas_medicos;

            // Datos para gráfica de dona - Estados de documentos GENERALES
            const datosEstados = [
                { name: 'Aprobados', value: stats.aprobados, color: '#10b981' },
                { name: 'Pendientes', value: stats.pendientes, color: '#f59e0b' },
                { name: 'En Revisión', value: stats.en_revision, color: '#3b82f6' },
                { name: 'Caducados', value: stats.caducados, color: '#f97316' },
                { name: 'Rechazados', value: stats.rechazados, color: '#ef4444' }
            ].filter(item => item.value > 0);

            // Datos para gráfica de dona - Estados FISCALES
            const datosEstadosFiscales = statsFiscales ? [
                { name: 'Aprobados', value: statsFiscales.aprobados, color: '#10b981' },
                { name: 'Pendientes', value: statsFiscales.pendientes, color: '#f59e0b' },
                { name: 'En Revisión', value: statsFiscales.en_revision, color: '#3b82f6' },
                { name: 'Caducados', value: statsFiscales.caducados, color: '#f97316' },
                { name: 'Rechazados', value: statsFiscales.rechazados, color: '#ef4444' }
            ].filter(item => item.value > 0) : [];

            // Datos para gráfica de dona - Estados MÉDICOS
            const datosEstadosMedicos = statsMedicos ? [
                { name: 'Aprobados', value: statsMedicos.aprobados, color: '#10b981' },
                { name: 'Pendientes', value: statsMedicos.pendientes, color: '#f59e0b' },
                { name: 'En Revisión', value: statsMedicos.en_revision, color: '#3b82f6' },
                { name: 'Caducados', value: statsMedicos.caducados, color: '#f97316' },
                { name: 'Rechazados', value: statsMedicos.rechazados, color: '#ef4444' }
            ].filter(item => item.value > 0) : [];

            // Datos para gráfica de barras - Documentos por tipo
            const datosTipos = dashboardData.documentos_por_tipo?.map(tipo => ({
                name: tipo.tipo_documento === 'fiscales' ? 'Fiscales' : 'Médicos',
                value: tipo.cantidad,
                aprobados: tipo.aprobados
            })) || [];

            return {
                datosEstados,
                datosEstadosFiscales,
                datosEstadosMedicos,
                datosTipos,
                tieneFiscales: statsFiscales && statsFiscales.total_documentos > 0,
                tieneMedicos: statsMedicos && statsMedicos.total_documentos > 0
            };
        } else {
            const stats = dashboardData.estadisticas as EstadisticasSupervisor;
            const statsFiscales = dashboardData.estadisticas_fiscales;
            const statsMedicos = dashboardData.estadisticas_medicos;

            // Datos para gráfica de dona - Estados FISCALES (Supervisor)
            const datosEstadosFiscales = statsFiscales ? [
                { name: 'Aprobados', value: statsFiscales.aprobados, color: '#10b981' },
                { name: 'Pendientes', value: statsFiscales.pendientes, color: '#f59e0b' },
                { name: 'En Revisión', value: statsFiscales.en_revision, color: '#3b82f6' },
                { name: 'Caducados', value: statsFiscales.caducados, color: '#f97316' },
                { name: 'Rechazados', value: statsFiscales.rechazados, color: '#ef4444' }
            ].filter(item => item.value > 0) : [];

            // Datos para gráfica de dona - Estados MÉDICOS (Supervisor)
            const datosEstadosMedicos = statsMedicos ? [
                { name: 'Aprobados', value: statsMedicos.aprobados, color: '#10b981' },
                { name: 'Pendientes', value: statsMedicos.pendientes, color: '#f59e0b' },
                { name: 'En Revisión', value: statsMedicos.en_revision, color: '#3b82f6' },
                { name: 'Caducados', value: statsMedicos.caducados, color: '#f97316' },
                { name: 'Rechazados', value: statsMedicos.rechazados, color: '#ef4444' }
            ].filter(item => item.value > 0) : [];

            // Datos para cumplimiento por campus (top 10)
            const datosCumplimiento = dashboardData.cumplimiento_por_campus
                ?.slice(0, 10)
                .map(campus => ({
                    name: campus.Campus.length > 15 ? campus.Campus.substring(0, 15) + '...' : campus.Campus,
                    value: campus.porcentaje_cumplimiento
                })) || [];

            return {
                datosCumplimiento,
                datosEstadosFiscales,
                datosEstadosMedicos,
                tieneFiscales: statsFiscales && statsFiscales.aprobados + statsFiscales.pendientes + statsFiscales.en_revision + statsFiscales.caducados + statsFiscales.rechazados > 0,
                tieneMedicos: statsMedicos && statsMedicos.aprobados + statsMedicos.pendientes + statsMedicos.en_revision + statsMedicos.caducados + statsMedicos.rechazados > 0
            };
        }
    };

    const datosGraficas = prepararDatosGraficas();

    // Función para obtener el color del estado
    const getEstadoColor = (estado: string) => {
        switch (estado) {
            case 'aprobado':
                return 'text-green-600';
            case 'pendiente':
                return 'text-yellow-600';
            case 'en_revision':
                return 'text-blue-600';
            case 'rechazado':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    const getEstadoIcon = (estado: string) => {
        switch (estado) {
            case 'aprobado':
                return <CheckCircle className="w-4 h-4 text-green-500" />;
            case 'pendiente':
                return <Clock className="w-4 h-4 text-yellow-500" />;
            case 'subido':
                return <FileText className="w-4 h-4 text-blue-500" />;
            case 'rechazado':
                return <XCircle className="w-4 h-4 text-red-500" />;
            default:
                return <FileText className="w-4 h-4 text-gray-500" />;
        }
    };

    const formatearFecha = (fecha: string) => {
        return new Date(fecha).toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Loading State */}
                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="flex flex-col items-center gap-4">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                            <p className="text-muted-foreground">Cargando estadísticas...</p>
                        </div>
                    </div>
                )}

                {/* Error State */}
                {error && !loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">Error al cargar el dashboard</h3>
                            <p className="text-muted-foreground mb-4">{error}</p>
                            <Button onClick={() => window.location.reload()}>
                                Reintentar
                            </Button>
                        </div>
                    </div>
                )}

                {/* Dashboard Content */}
                {!loading && !error && dashboardData && (
                    <>
                        {/* Welcome Section - Diferente según el rol */}
                        <div className="mb-8">
                            {isRole16 ? (
                                <>
                                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                                        Panel de Supervisión - Documentos Fiscales
                                    </h1>
                                    <p className="text-muted-foreground">
                                        Monitorea el cumplimiento y estado de documentos fiscales en todos los campus
                                    </p>
                                </>
                            ) : (
                                <>
                                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                                        Panel de Campus - Gestión de Documentos
                                    </h1>
                                    <p className="text-muted-foreground">
                                        Gestiona, sube y visualiza todos los documentos fiscales y médicos requeridos para tu campus
                                    </p>
                                </>
                            )}
                        </div>
                        {/* Statistics Section - Campus Directors */}
                        {isRole13or14 && dashboardData.tipo_usuario === 'campus' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-4 mb-8">
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                                <Clock className="w-4 h-4 text-yellow-500" />
                                                Pendientes
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold mb-1">
                                                {(dashboardData.estadisticas as EstadisticasCampus).pendientes}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Documentos que requieren atención
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                                <CheckCircle className="w-4 h-4 text-green-500" />
                                                Aprobados
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold mb-1 text-green-600">
                                                {(dashboardData.estadisticas as EstadisticasCampus).aprobados}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Documentos verificados
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                                <FileText className="w-4 h-4" />
                                                Total
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold mb-1">
                                                {(dashboardData.estadisticas as EstadisticasCampus).total_documentos}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Total de documentos
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card className="cursor-pointer transition-colors hover:bg-muted/50">
                                        <CardContent className="p-6">
                                            <Link href="/documentos/upload" className="flex flex-col items-center text-center">
                                                <div className="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center mb-2">
                                                    <Upload className="w-4 h-4 text-primary" />
                                                </div>
                                                <h3 className="text-sm font-medium mb-1">Gestionar</h3>
                                                <p className="text-xs text-muted-foreground">
                                                    Subir documentos
                                                </p>
                                            </Link>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Charts Section - Campus - MEJORADO */}
                                <div className="grid gap-6 mb-8">
                                    {/* Vista consolidada más impactante */}
                                    {datosGraficas && (datosGraficas.datosEstados?.length > 0 || datosGraficas.datosEstadosFiscales?.length > 0 || datosGraficas.datosEstadosMedicos?.length > 0) && (
                                        <Card className="col-span-full">
                                            <CardHeader>
                                                <CardTitle className="text-xl flex items-center gap-3">
                                                    <BarChart3 className="w-6 h-6 text-blue-600" />
                                                    Resumen Visual de Documentos
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid gap-6 lg:grid-cols-3">
                                                    {/* Gráfica principal consolidada */}
                                                    <div className="lg:col-span-2">
                                                        {datosGraficas.datosEstados && datosGraficas.datosEstados.length > 0 ? (
                                                            <div className="space-y-4">
                                                                <h4 className="font-semibold text-gray-700">Estado General de Documentos</h4>
                                                                <SimpleBarChart
                                                                    data={datosGraficas.datosEstados.map(item => ({
                                                                        name: item.name,
                                                                        value: item.value
                                                                    }))}
                                                                    color="#3b82f6"
                                                                />
                                                            </div>
                                                        ) : (
                                                            <div className="grid gap-4 md:grid-cols-2">
                                                                {datosGraficas.tieneFiscales && datosGraficas.datosEstadosFiscales.length > 0 && (
                                                                    <div>
                                                                        <h4 className="font-semibold text-blue-700 mb-3 flex items-center gap-2">
                                                                            <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                                                                            Documentos Fiscales
                                                                        </h4>
                                                                        <DonutChart data={datosGraficas.datosEstadosFiscales} />
                                                                    </div>
                                                                )}
                                                                {datosGraficas.tieneMedicos && datosGraficas.datosEstadosMedicos.length > 0 && (
                                                                    <div>
                                                                        <h4 className="font-semibold text-green-700 mb-3 flex items-center gap-2">
                                                                            <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                                                                            Documentos Médicos
                                                                        </h4>
                                                                        <DonutChart data={datosGraficas.datosEstadosMedicos} />
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Panel de insights */}
                                                    <div className="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-lg p-4">
                                                        <h4 className="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                            <TrendingUp className="w-4 h-4 text-blue-600" />
                                                            Insights Rápidos
                                                        </h4>
                                                        <div className="space-y-3 text-sm">
                                                            <div className="flex items-center justify-between p-2 bg-white rounded">
                                                                <span className="text-gray-600">Total Documentos</span>
                                                                <span className="font-bold text-lg">
                                                                    {(dashboardData.estadisticas as EstadisticasCampus).total_documentos}
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center justify-between p-2 bg-white rounded">
                                                                <span className="text-gray-600">% Completado</span>
                                                                <span className="font-bold text-lg text-green-600">
                                                                    {(dashboardData.estadisticas as EstadisticasCampus).total_documentos > 0
                                                                        ? Math.round(((dashboardData.estadisticas as EstadisticasCampus).aprobados / (dashboardData.estadisticas as EstadisticasCampus).total_documentos) * 100)
                                                                        : 0
                                                                    }%
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center justify-between p-2 bg-white rounded">
                                                                <span className="text-gray-600">Pendientes</span>
                                                                <span className="font-bold text-lg text-yellow-600">
                                                                    {(dashboardData.estadisticas as EstadisticasCampus).pendientes}
                                                                </span>
                                                            </div>
                                                            <div className="mt-3 p-2 bg-white rounded">
                                                                <div className="text-xs text-gray-500 mb-1">Progreso General</div>
                                                                <div className="w-full bg-gray-200 rounded-full h-2">
                                                                    <div
                                                                        className="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-700"
                                                                        style={{
                                                                            width: `${(dashboardData.estadisticas as EstadisticasCampus).total_documentos > 0
                                                                                ? Math.round(((dashboardData.estadisticas as EstadisticasCampus).aprobados / (dashboardData.estadisticas as EstadisticasCampus).total_documentos) * 100)
                                                                                : 0
                                                                            }%`
                                                                        }}
                                                                    ></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>

                                {/* Sección Desglose por Campus - NUEVA CARACTERÍSTICA CREATIVA */}
                                {dashboardData.estadisticas_por_campus && dashboardData.estadisticas_por_campus.length > 0 && (
                                    <div className="mb-8">
                                        <div className="flex items-center gap-3 mb-6">
                                            <BarChart3 className="w-6 h-6 text-indigo-600" />
                                            <h2 className="text-xl font-bold">Desglose Detallado por Campus</h2>
                                            <div className="flex-1 h-px bg-gradient-to-r from-indigo-200 to-transparent"></div>
                                        </div>

                                        <div className="grid gap-6 lg:grid-cols-1 xl:grid-cols-2">
                                            {dashboardData.estadisticas_por_campus.map((campus) => (
                                                <Card key={campus.campus_id} className="border-l-4 border-l-indigo-500 hover:shadow-lg transition-shadow">
                                                    <CardHeader className="pb-3">
                                                        <div className="flex items-center justify-between">
                                                            <CardTitle className="text-lg font-semibold text-gray-800">
                                                                {campus.campus_nombre}
                                                            </CardTitle>
                                                            <div className="flex items-center gap-2">
                                                                <div className={`px-3 py-1 rounded-full text-xs font-medium ${
                                                                    campus.porcentaje_cumplimiento >= 80
                                                                        ? 'bg-green-100 text-green-800'
                                                                        : campus.porcentaje_cumplimiento >= 60
                                                                        ? 'bg-yellow-100 text-yellow-800'
                                                                        : 'bg-red-100 text-red-800'
                                                                }`}>
                                                                    {campus.porcentaje_cumplimiento}% cumplimiento
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <div className="space-y-4">
                                                            {/* Estadísticas rápidas */}
                                                            <div className="grid grid-cols-3 gap-3 text-center">
                                                                <div className="bg-gray-50 rounded-lg p-3">
                                                                    <div className="text-xl font-bold text-gray-900">
                                                                        {campus.total_documentos}
                                                                    </div>
                                                                    <div className="text-xs text-gray-600">Total</div>
                                                                </div>
                                                                <div className="bg-green-50 rounded-lg p-3">
                                                                    <div className="text-xl font-bold text-green-600">
                                                                        {campus.total_aprobados}
                                                                    </div>
                                                                    <div className="text-xs text-green-700">Aprobados</div>
                                                                </div>
                                                                <div className="bg-blue-50 rounded-lg p-3">
                                                                    <div className="text-xl font-bold text-blue-600">
                                                                        {campus.total_documentos - campus.total_aprobados}
                                                                    </div>
                                                                    <div className="text-xs text-blue-700">Pendientes</div>
                                                                </div>
                                                            </div>

                                                            {/* Desglose por tipo de documento */}
                                                            <div className="grid gap-3">
                                                                {campus.tiene_fiscales && (
                                                                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                                                        <div className="flex items-center gap-2 mb-2">
                                                                            <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                                                                            <span className="font-medium text-blue-800">Documentos Fiscales</span>
                                                                        </div>
                                                                        <div className="grid grid-cols-5 gap-2 text-sm">
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-green-600">{campus.fiscales.aprobados}</div>
                                                                                <div className="text-xs text-gray-600">Aprobados</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-yellow-600">{campus.fiscales.pendientes}</div>
                                                                                <div className="text-xs text-gray-600">Pendientes</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-blue-600">{campus.fiscales.subidos || 0}</div>
                                                                                <div className="text-xs text-gray-600">Subidos</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-orange-600">{campus.fiscales.caducados}</div>
                                                                                <div className="text-xs text-gray-600">Caducados</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-red-600">{campus.fiscales.rechazados}</div>
                                                                                <div className="text-xs text-gray-600">Rechazados</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {campus.tiene_medicos && (
                                                                    <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                                                                        <div className="flex items-center gap-2 mb-2">
                                                                            <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                                                                            <span className="font-medium text-green-800">Documentos Médicos</span>
                                                                        </div>
                                                                        <div className="grid grid-cols-5 gap-2 text-sm">
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-green-600">{campus.medicos.aprobados}</div>
                                                                                <div className="text-xs text-gray-600">Aprobados</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-yellow-600">{campus.medicos.pendientes}</div>
                                                                                <div className="text-xs text-gray-600">Pendientes</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-blue-600">{campus.medicos.subidos || 0}</div>
                                                                                <div className="text-xs text-gray-600">Subidos</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-orange-600">{campus.medicos.caducados}</div>
                                                                                <div className="text-xs text-gray-600">Caducados</div>
                                                                            </div>
                                                                            <div className="text-center">
                                                                                <div className="font-semibold text-red-600">{campus.medicos.rechazados}</div>
                                                                                <div className="text-xs text-gray-600">Rechazados</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {!campus.tiene_fiscales && !campus.tiene_medicos && (
                                                                    <div className="text-center py-4 text-gray-500 text-sm">
                                                                        No hay documentos registrados para este campus
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Barra de progreso visual */}
                                                            <div className="mt-3">
                                                                <div className="flex justify-between text-xs text-gray-600 mb-1">
                                                                    <span>Progreso de Cumplimiento</span>
                                                                    <span>{campus.porcentaje_cumplimiento}%</span>
                                                                </div>
                                                                <div className="w-full bg-gray-200 rounded-full h-2">
                                                                    <div
                                                                        className={`h-2 rounded-full transition-all duration-500 ${
                                                                            campus.porcentaje_cumplimiento >= 80
                                                                                ? 'bg-green-500'
                                                                                : campus.porcentaje_cumplimiento >= 60
                                                                                ? 'bg-yellow-500'
                                                                                : 'bg-red-500'
                                                                        }`}
                                                                        style={{ width: `${campus.porcentaje_cumplimiento}%` }}
                                                                    ></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Activity and Alerts - Campus - MEJORADO */}
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg flex items-center gap-2">
                                                <Clock className="w-5 h-5 text-blue-500" />
                                                Actividad Reciente
                                                {dashboardData.actividad_reciente && dashboardData.actividad_reciente.length > 0 && (
                                                    <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                                                        {dashboardData.actividad_reciente.length} eventos
                                                    </span>
                                                )}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-3 max-h-80 overflow-y-auto">
                                                {dashboardData.actividad_reciente && dashboardData.actividad_reciente.length > 0 ? (
                                                    dashboardData.actividad_reciente.slice(0, 8).map((actividad, index) => (
                                                        <div key={index} className="flex items-start gap-3 p-3 rounded-lg border hover:bg-gray-50 transition-colors">
                                                            <div className="flex-shrink-0 mt-0.5">
                                                                {getEstadoIcon(actividad.estado)}
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <div className="flex items-center gap-2 mb-1">
                                                                    <span className="text-sm font-medium text-gray-900 truncate">
                                                                        {actividad.documento_nombre}
                                                                    </span>
                                                                    {actividad.tipo_documento && (
                                                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                                                                            actividad.tipo_documento === 'MÉDICO'
                                                                                ? 'bg-green-100 text-green-700'
                                                                                : 'bg-blue-100 text-blue-700'
                                                                        }`}>
                                                                            {actividad.tipo_documento}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <div className="flex items-center justify-between">
                                                                    <span className={`text-xs px-2 py-1 rounded-full ${
                                                                        actividad.estado === 'aprobado' ? 'bg-green-100 text-green-700' :
                                                                        actividad.estado === 'pendiente' ? 'bg-yellow-100 text-yellow-700' :
                                                                        actividad.estado === 'rechazado' ? 'bg-red-100 text-red-700' :
                                                                        'bg-gray-100 text-gray-700'
                                                                    }`}>
                                                                        {actividad.estado.charAt(0).toUpperCase() + actividad.estado.slice(1)}
                                                                    </span>
                                                                    <span className="text-xs text-gray-500">
                                                                        {formatearFecha(actividad.updated_at)}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                ) : (
                                                    <div className="text-center py-12 text-muted-foreground">
                                                        <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                            <FileText className="w-8 h-8 text-gray-400" />
                                                        </div>
                                                        <p className="text-lg font-medium text-gray-600 mb-1">Sin actividad reciente</p>
                                                        <p className="text-sm text-gray-500">Los documentos procesados aparecerán aquí</p>
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {dashboardData.documentos_vencidos && dashboardData.documentos_vencidos.length > 0 && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg flex items-center gap-2">
                                                    <AlertTriangle className="w-5 h-5 text-yellow-500" />
                                                    Próximos a Vencer
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-3">
                                                    {dashboardData.documentos_vencidos.map((documento, index) => (
                                                        <div key={index} className="flex items-center justify-between p-3 rounded-lg border border-yellow-200 bg-yellow-50">
                                                            <div className="flex items-center gap-3">
                                                                <AlertTriangle className="w-4 h-4 text-yellow-500" />
                                                                <span className="text-sm">{documento.documento_nombre}</span>
                                                            </div>
                                                            <div className="text-right">
                                                                <div className="text-xs font-medium text-yellow-600">
                                                                    {documento.dias_restantes > 0
                                                                        ? `${documento.dias_restantes} días`
                                                                        : 'Vencido'
                                                                    }
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {new Date(documento.fecha_vigencia_ia).toLocaleDateString('es-ES')}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            </>
                        )}

                    </>
                )}
            </div>
        </AppLayout>
    );
}
