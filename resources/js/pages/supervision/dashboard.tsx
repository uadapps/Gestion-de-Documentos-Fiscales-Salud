import React, { useEffect, useState, useMemo, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    FileText,
    AlertTriangle,
    CheckCircle,
    Clock,
    TrendingUp,
    TrendingDown,
    Eye,
    Users,
    Building,
    Calendar,
    BarChart3,
    PieChart
} from 'lucide-react';interface EstadisticasGenerales {
    totalDocumentos: number;
    documentosAprobados: number;
    documentosPendientes: number;
    documentosRechazados: number;
    usuariosActivos: number;
    campusConectados: number;
}

interface DatosSemaforo {
    campus: string;
    estado: 'excelente' | 'bueno' | 'advertencia' | 'critico';
    cumplimiento: number;
    documentosTotal: number;
    documentosVigentes: number;
    documentosCaducados: number;
    documentosVencidos: number;
    usuariosActivos: number;
    id_campus: string;
    uniqueKey?: string; // Añadir clave única opcional
    campusHash?: string; // Añadir hash del campus
}

interface TendenciaMensual {
    mes: string;
    aprobados: number;
    pendientes: number;
    rechazados: number;
}

interface SupervisionDashboardProps {
    estadisticasGenerales: EstadisticasGenerales;
    datosSemaforo: DatosSemaforo[];
    tendenciasMensuales: TendenciaMensual[];
}

// Contador global para keys únicas
let globalKeyCounter = 0;

// Función para generar hash consistente del nombre del campus
const generateCampusHash = (campusName: string, campusId: string): string => {
    // Asegurar que los valores sean strings
    const name = String(campusName);
    const id = String(campusId);

    const input = `${name}-${id}`;
    let hash = 0;
    for (let i = 0; i < input.length; i++) {
        const char = input.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    const result = Math.abs(hash).toString(16).substring(0, 8);

    console.log(`🔗 generateCampusHash: "${input}" → ${result}`);

    return result;
};

// Datos de ejemplo para el gráfico de pie (se mantienen para la visualización)
const datosGraficoPieDefault = [
    { name: 'Aprobados', value: 71.5, colorClass: 'bg-emerald-500', textClass: 'text-emerald-600 dark:text-emerald-400' },
    { name: 'Pendientes', value: 18.8, colorClass: 'bg-amber-500', textClass: 'text-amber-600 dark:text-amber-400' },
    { name: 'Rechazados', value: 9.7, colorClass: 'bg-red-500', textClass: 'text-red-600 dark:text-red-400' }
];

// Componente de skeleton para las tarjetas de campus
const CampusCardSkeleton = () => (
    <div className="p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 animate-pulse">
        <div className="flex items-center justify-between mb-3">
            <div className="h-4 bg-gray-300 dark:bg-gray-600 rounded w-24"></div>
            <div className="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
        </div>

        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div className="h-6 bg-gray-300 dark:bg-gray-600 rounded w-16"></div>
            </div>

            <div>
                <div className="flex justify-between text-xs mb-1">
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-16"></div>
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-8"></div>
                </div>
                <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div className="h-full bg-gray-300 dark:bg-gray-600 rounded-full w-1/2"></div>
                </div>
            </div>

            <div className="text-xs space-y-1">
                <div className="flex justify-between">
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-8"></div>
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-6"></div>
                </div>
                <div className="flex justify-between">
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-12"></div>
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-6"></div>
                </div>
                <div className="flex justify-between">
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-10"></div>
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-6"></div>
                </div>
                <div className="flex justify-between">
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-14"></div>
                    <div className="h-3 bg-gray-300 dark:bg-gray-600 rounded w-6"></div>
                </div>
            </div>
        </div>
    </div>
);

// Componente de skeleton para las tarjetas de estadísticas
const StatCardSkeleton = () => (
    <Card>
        <CardContent className="p-4">
            <div className="flex items-center gap-2 animate-pulse">
                <div className="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div>
                    <div className="h-4 bg-gray-300 dark:bg-gray-600 rounded w-16 mb-2"></div>
                    <div className="h-8 bg-gray-300 dark:bg-gray-600 rounded w-12"></div>
                </div>
            </div>
        </CardContent>
    </Card>
);

    const getEstadoSemaforo = (estado: string) => {
        switch (estado) {
            case 'excelente':
                return {
                    icon: CheckCircle,
                    label: 'Excelente',
                    color: 'bg-emerald-500 dark:bg-emerald-400',
                    textColor: 'text-emerald-700 dark:text-emerald-300',
                    bgColor: 'bg-emerald-50 dark:bg-emerald-950/20',
                    borderColor: 'border-emerald-200 dark:border-emerald-800',
                };
            case 'bueno':
                return {
                    icon: CheckCircle,
                    label: 'Bueno',
                    color: 'bg-blue-500 dark:bg-blue-400',
                    textColor: 'text-blue-700 dark:text-blue-300',
                    bgColor: 'bg-blue-50 dark:bg-blue-950/20',
                    borderColor: 'border-blue-200 dark:border-blue-800',
                };
            case 'advertencia':
                return {
                    icon: AlertTriangle,
                    label: 'Advertencia',
                    color: 'bg-amber-500 dark:bg-amber-400',
                    textColor: 'text-amber-700 dark:text-amber-300',
                    bgColor: 'bg-amber-50 dark:bg-amber-950/20',
                    borderColor: 'border-amber-200 dark:border-amber-800',
                };
            case 'critico':
            default:
                return {
                    icon: AlertTriangle,
                    label: 'Crítico',
                    color: 'bg-red-500 dark:bg-red-400',
                    textColor: 'text-red-700 dark:text-red-300',
                    bgColor: 'bg-red-50 dark:bg-red-950/20',
                    borderColor: 'border-red-200 dark:border-red-800',
                };
        }
    };

export default function SupervisionDashboard({
    estadisticasGenerales: initialEstadisticas,
    datosSemaforo: initialSemaforo,
    tendenciasMensuales: initialTendencias
}: SupervisionDashboardProps) {
    const [dashboardData, setDashboardData] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Usar datos iniciales del servidor como fallback
    const [estadisticasGenerales, setEstadisticasGenerales] = useState(initialEstadisticas);
    const [datosSemaforo, setDatosSemaforo] = useState(initialSemaforo);
    const [tendenciasMensuales, setTendenciasMensuales] = useState(initialTendencias);

    // Calcular distribución en tiempo real
    const calcularDistribucion = () => {
        const total = estadisticasGenerales.totalDocumentos;
        if (total === 0) {
            return datosGraficoPieDefault;
        }

        return [
            {
                name: 'Aprobados',
                value: parseFloat(((estadisticasGenerales.documentosAprobados / total) * 100).toFixed(1)),
                colorClass: 'bg-emerald-500',
                textClass: 'text-emerald-600 dark:text-emerald-400'
            },
            {
                name: 'Pendientes',
                value: parseFloat(((estadisticasGenerales.documentosPendientes / total) * 100).toFixed(1)),
                colorClass: 'bg-amber-500',
                textClass: 'text-amber-600 dark:text-amber-400'
            },
            {
                name: 'Rechazados',
                value: parseFloat(((estadisticasGenerales.documentosRechazados / total) * 100).toFixed(1)),
                colorClass: 'bg-red-500',
                textClass: 'text-red-600 dark:text-red-400'
            }
        ];
    };

    const datosGraficoPie = calcularDistribucion();

    useEffect(() => {
        const fetchSupervisionData = async () => {
            try {
                setLoading(true);
                const response = await fetch('/api/supervision/estadisticas', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('📊 Datos de supervisión recibidos:', data);

                setDashboardData(data);

                // Procesar datos para supervisión usando la estructura del SupervisionController
                if (data.tipo_usuario === 'supervisor') {
                    const estadisticasSupervision = procesarEstadisticasSupervision(data);
                    // Usar estadisticas_por_campus que devuelve el SupervisionController
                    const semaforoSupervision = procesarDatosSemaforo(data.estadisticas_por_campus || []);

                    setEstadisticasGenerales(estadisticasSupervision);
                    setDatosSemaforo(semaforoSupervision);
                }                setError(null);
            } catch (err) {
                console.error('❌ Error cargando datos de supervisión:', err);
                setError('Error al cargar datos de supervisión');
                // Mantener datos iniciales del servidor
            } finally {
                setLoading(false);
            }
        };

        fetchSupervisionData();

        // Actualizar cada 5 minutos
        const interval = setInterval(fetchSupervisionData, 5 * 60 * 1000);
        return () => clearInterval(interval);
    }, []);

    const procesarEstadisticasSupervision = (data: any) => {
        // Manejar ambas estructuras: la anterior y la nueva del SupervisionController
        if (data.estadisticas_por_campus && Array.isArray(data.estadisticas_por_campus)) {
            // Nueva estructura del SupervisionController
            const totalDocumentos = data.estadisticas_por_campus.reduce((total: number, campus: any) =>
                total + (campus.total_documentos || 0), 0);

            const totalAprobados = data.estadisticas_por_campus.reduce((total: number, campus: any) =>
                total + (campus.total_aprobados || 0), 0);

            const totalCaducados = data.estadisticas_por_campus.reduce((total: number, campus: any) =>
                total + (campus.total_caducados || 0), 0);

            const totalRechazados = data.estadisticas_por_campus.reduce((total: number, campus: any) =>
                total + ((campus.fiscales?.rechazados || 0) + (campus.medicos?.rechazados || 0)), 0);

            // Calcular pendientes correctamente: Total - Vigentes - Caducados - Rechazados
            const totalPendientes = Math.max(0, totalDocumentos - totalAprobados - totalCaducados - totalRechazados);

            return {
                totalDocumentos,
                documentosAprobados: totalAprobados,
                documentosPendientes: totalPendientes,
                documentosRechazados: totalRechazados,
                usuariosActivos: data.estadisticas?.usuarios_activos || 0,
                campusConectados: data.estadisticas_por_campus.length,
            };
        } else {
            // Estructura anterior (fallback)
            const estadisticas = data.estadisticas || {};
            const fiscales = data.estadisticas_fiscales || {};
            const medicos = data.estadisticas_medicos || {};

            const totalDocumentos = (fiscales.total_documentos || fiscales.total || 0) + (medicos.total_documentos || medicos.total || 0);
            const totalAprobados = (fiscales.aprobados || 0) + (medicos.aprobados || 0);
            const totalCaducados = (fiscales.caducados || 0) + (medicos.caducados || 0);
            const totalRechazados = (fiscales.rechazados || 0) + (medicos.rechazados || 0);

            // Calcular pendientes correctamente: Total - Vigentes - Caducados - Rechazados
            const totalPendientes = Math.max(0, totalDocumentos - totalAprobados - totalCaducados - totalRechazados);

            return {
                totalDocumentos,
                documentosAprobados: totalAprobados,
                documentosPendientes: totalPendientes,
                documentosRechazados: totalRechazados,
                usuariosActivos: estadisticas.usuarios_activos || 0,
                campusConectados: estadisticas.total_campus || 0,
            };
        }
    };    const procesarDatosSemaforo = (campusData: any[]) => {
        if (!campusData || !Array.isArray(campusData)) {
            return [];
        }

        // PRIMERO: Filtrar duplicados ANTES de procesar
        const campusUnicos = campusData.filter((campus, index, array) => {
            const id = String(campus.campus_id || campus.id);
            const nombre = campus.campus_nombre || campus.nombre;

            // Buscar la primera ocurrencia con el mismo ID Y nombre
            return array.findIndex(c => {
                const cId = String(c.campus_id || c.id);
                const cNombre = c.campus_nombre || c.nombre;
                return cId === id && cNombre === nombre;
            }) === index;
        });

        console.log('� Campus procesados:', campusData.length, '→ Campus únicos:', campusUnicos.length);

        if (campusData.length !== campusUnicos.length) {
            console.warn(`⚠️ Se eliminaron ${campusData.length - campusUnicos.length} campus duplicados`);
        }

        // SEGUNDO: Procesar los datos únicos con identificador único
        const resultados = campusUnicos.map((campus: any, index: number) => {
            globalKeyCounter++; // Incrementar contador global
            const campusId = String(campus.campus_id || campus.id || 'unknown');
            const campusIdFormatted = campusId.padStart(2, '0'); // Formato "01", "02", etc.
            const campusNombre = campus.campus_nombre || campus.nombre || 'Campus desconocido';
            const uniqueKey = `campus-${globalKeyCounter}-${campusIdFormatted}`;
            const campusHash = generateCampusHash(campusNombre, campusId);

            // Debug: Verificar que el hash se genera correctamente
            console.log(`🔗 Campus: ${campusNombre} (ID: ${campusId}) → Hash: ${campusHash}`);

            const cumplimiento = campus.porcentaje_cumplimiento || campus.cumplimiento || 0;
            let estado: 'excelente' | 'bueno' | 'advertencia' | 'critico' = 'critico';

            if (cumplimiento >= 90) estado = 'excelente';
            else if (cumplimiento >= 80) estado = 'bueno';
            else if (cumplimiento >= 70) estado = 'advertencia';

            const vigentes = campus.total_vigentes || campus.total_aprobados || 0;
            const caducados = campus.total_caducados || 0;

            return {
                campus: campusNombre,
                estado,
                cumplimiento,
                documentosTotal: campus.total_documentos || campus.documentos_total || 0,
                documentosVigentes: vigentes,
                documentosCaducados: caducados,
                documentosVencidos: Math.max(0, (campus.total_documentos || 0) - vigentes),
                usuariosActivos: campus.usuarios_activos || 1,
                id_campus: campusIdFormatted, // Usar ID formateado
                campusHash, // Añadir hash del campus
                uniqueKey // Añadir la clave única al objeto
            };
        });

        console.log(`📊 Datos del semáforo procesados:`, resultados.map(r => ({
            campus: r.campus,
            id: r.id_campus,
            hash: r.campusHash
        })));

        return resultados;
    };

    // Memorizar los datos del semáforo para evitar re-procesamiento
    const datosSemaforoMemoizados = useMemo(() => {
        return [...datosSemaforo]; // Crear una copia nueva
    }, [datosSemaforo.length]);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Supervisión', href: '/supervision' }
            ]}
        >
            <Head title="Dashboard de Supervisión" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight mb-2">
                            Dashboard de Supervisión
                        </h1>
                        <p className="text-muted-foreground">
                            Monitoreo en tiempo real del estado de documentos  por campus
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {loading && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                                Actualizando...
                            </div>
                        )}
                        {error && (
                            <div className="flex items-center gap-2 text-sm text-red-600">
                                <AlertTriangle className="w-4 h-4" />
                                {error}
                            </div>
                        )}

                    </div>
                </div>

                {/* Métricas principales */}
                <div className="grid gap-4 md:grid-cols-6">
                    {loading ? (
                        // Mostrar skeletons mientras carga
                        <>
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                        </>
                    ) : (
                        <>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <FileText className="w-4 h-4 text-blue-600" />
                                        <div>
                                            <p className="text-sm font-medium">Total Documentos</p>
                                            <p className="text-2xl font-bold">{estadisticasGenerales.totalDocumentos.toLocaleString()}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                        <div>
                                            <p className="text-sm font-medium">Vigentes</p>
                                            <p className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                                {(dashboardData as any)?.documentosVigentes || estadisticasGenerales.documentosAprobados}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <Clock className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                        <div>
                                            <p className="text-sm font-medium">Pendientes</p>
                                            <p className="text-2xl font-bold text-amber-700 dark:text-amber-300">{estadisticasGenerales.documentosPendientes}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="w-4 h-4 text-orange-600 dark:text-orange-400" />
                                        <div>
                                            <p className="text-sm font-medium">Caducados</p>
                                            <p className="text-2xl font-bold text-orange-700 dark:text-orange-300">
                                                {(dashboardData as any)?.documentosCaducados || 0}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400" />
                                        <div>
                                            <p className="text-sm font-medium">Rechazados</p>
                                            <p className="text-2xl font-bold text-red-700 dark:text-red-300">{estadisticasGenerales.documentosRechazados}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-2">
                                        <Building className="w-4 h-4 text-indigo-600" />
                                        <div>
                                            <p className="text-sm font-medium">Campus Activos</p>
                                            <p className="text-2xl font-bold">{datosSemaforoMemoizados.length}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>

                {/* Semáforo por Campus */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building className="w-5 h-5" />
                            Estado por Campus - Semáforo de Cumplimiento
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            // Mostrar skeletons mientras carga
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {Array.from({ length: 8 }, (_, index) => (
                                    <CampusCardSkeleton key={`skeleton-${index}`} />
                                ))}
                            </div>
                        ) : datosSemaforoMemoizados.length === 0 ? (
                            <div className="text-center py-8">
                                <Building className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                                <p className="text-muted-foreground">No hay campus disponibles para mostrar</p>
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {datosSemaforoMemoizados.map((campus, index) => {
                                    const estado = getEstadoSemaforo(campus.estado);
                                const IconComponent = estado.icon;

                                return (
                                    <Link
                                        key={(campus as any).uniqueKey || `fallback-${index}-${campus.id_campus}`}
                                        href={`/supervision/${(campus as any).campusHash || 'unknown'}`}
                                        className="block"
                                        onClick={(e) => {
                                            const hash = (campus as any).campusHash;
                                            console.log(`🔗 Navegando a campus ${campus.campus} con hash: ${hash}`);

                                            // Verificar que el hash sea válido
                                            if (!hash || hash === 'unknown') {
                                                e.preventDefault();
                                                console.error('❌ Hash inválido para campus:', campus);
                                                alert(`Error: No se pudo generar hash para ${campus.campus}`);
                                                return;
                                            }

                                            console.log(`✅ Navegando a URL: /supervision/${hash}`);
                                        }}
                                    >
                                        <div
                                            className={`p-4 rounded-lg border-2 ${estado.bgColor} ${estado.borderColor} transition-all hover:shadow-lg hover:scale-105 cursor-pointer`}
                                        >
                                        <div className="flex items-center justify-between mb-3">
                                            <div>
                                                <h3 className="font-semibold text-sm">{campus.campus}</h3>
                                            </div>
                                            <div className={`w-4 h-4 rounded-full ${estado.color} animate-pulse`}></div>
                                        </div>

                                        <div className="space-y-3">
                                            <div className="flex items-center gap-2">
                                                <IconComponent className={`w-4 h-4 ${estado.textColor}`} />
                                                <Badge className={`${estado.bgColor} ${estado.textColor} hover:${estado.bgColor}`}>
                                                    {estado.label}
                                                </Badge>
                                            </div>

                                            <div>
                                                <div className="flex justify-between text-xs mb-1">
                                                    <span>Cumplimiento</span>
                                                    <span className="font-medium">{campus.cumplimiento}%</span>
                                                </div>
                                                <Progress
                                                    value={campus.cumplimiento}
                                                    className="h-2"
                                                />
                                            </div>

                                            <div className="text-xs space-y-1">
                                                <div className="flex justify-between">
                                                    <span>Total:</span>
                                                    <span className="font-medium">{campus.documentosTotal}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Vigentes:</span>
                                                    <span className="font-medium text-green-600 dark:text-green-400">
                                                        {campus.documentosVigentes}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Caducados:</span>
                                                    <span className="font-medium text-orange-600 dark:text-orange-400">
                                                        {campus.documentosCaducados}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Pendientes:</span>
                                                    <span className={`font-medium ${campus.documentosVencidos > 20 ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400'}`}>
                                                        {campus.documentosVencidos}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </Link>
                                );
                            })}
                            </div>
                        )}
                    </CardContent>
                </Card>


            </div>
        </AppLayout>
    );
}
