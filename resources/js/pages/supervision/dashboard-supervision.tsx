import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import {
    BarChart3,
    TrendingUp,
    Shield,
    FileText,
    AlertTriangle,
    CheckCircle,
    Clock,
    Activity,
    Filter
} from 'lucide-react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    LineChart,
    Line,
    Area,
    AreaChart,
    RadarChart,
    PolarGrid,
    PolarAngleAxis,
    PolarRadiusAxis,
    Radar
} from 'recharts';

// Interfaces para datos de supervisión
interface EstadisticasCampus {
    campus_id: number;
    campus_nombre: string;
    total_documentos: number;
    total_aprobados: number;
    total_caducados: number;
    total_pendientes: number;
    total_rechazados: number;
    porcentaje_cumplimiento: number;
    tiene_fiscales: boolean;
    tiene_medicos: boolean;
    fiscales: {
        total_documentos: number;
        aprobados: number;
        pendientes: number;
        caducados: number;
        rechazados: number;
    };
    medicos: {
        total_documentos: number;
        aprobados: number;
        pendientes: number;
        caducados: number;
        rechazados: number;
    };
}

interface DatosSupervision {
    estadisticas_generales: {
        total_campus: number;
        total_documentos: number;
        total_aprobados: number;
        total_pendientes: number;
        total_caducados: number;
        total_rechazados: number;
        cumplimiento_promedio: number;
        campus_criticos: number;
        usuarios_activos: number;
    };
    estadisticas_por_campus: EstadisticasCampus[];
    campus_alertas: Array<{
        campus_nombre: string;
        tipo_alerta: 'critico' | 'advertencia' | 'info';
        mensaje: string;
        documentos_afectados: number;
    }>;
    tendencias: Array<{
        campus_id: number;
        campus_nombre: string;
        tendencia: 'subiendo' | 'bajando' | 'estable';
        cambio_porcentual: number;
    }>;
}

export default function DashboardSupervision() {
    const { datosSupervision } = usePage<{ datosSupervision: DatosSupervision }>().props;
    const [selectedMetric, setSelectedMetric] = useState<'legales' | 'medicos' | 'general'>('general');
    const [excludeCID, setExcludeCID] = useState<boolean>(false);

    const breadcrumbItems: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/' },

    ];

    // Función para determinar el color del estado del campus
    const getEstadoCampusColor = (cumplimiento: number) => {
        if (cumplimiento >= 90) return 'bg-green-500 text-white';
        if (cumplimiento >= 80) return 'bg-blue-500 text-white';
        if (cumplimiento >= 60) return 'bg-yellow-500 text-white';
        return 'bg-red-500 text-white';
    };

    // Filtrar campus según la métrica seleccionada y exclusión de CID
    const getCampusFiltrados = () => {
        if (!datosSupervision) return [];

        let campusFiltrados = datosSupervision.estadisticas_por_campus.filter(campus => {
            // Filtrar por CID si está activado
            if (excludeCID && campus.campus_nombre.toUpperCase().includes('CID')) {
                return false;
            }

            // Filtrar por tipo de documento
            switch (selectedMetric) {
                case 'legales':
                    return campus.tiene_fiscales;
                case 'medicos':
                    return campus.tiene_medicos;
                default:
                    return true;
            }
        });

        // Eliminar duplicados basados en campus_id
        const campusUnicos = campusFiltrados.filter((campus, index, self) =>
            index === self.findIndex((c) => c.campus_id === campus.campus_id)
        );

        return campusUnicos;
    };

    const campusFiltrados = getCampusFiltrados();

    // Calcular campus críticos desde el frontend (campus únicos con cumplimiento < 60%)
    const campusCriticos = campusFiltrados.filter(campus => campus.porcentaje_cumplimiento < 60).length;

    // Aplicar límite de campus para las gráficas - ahora siempre mostrar todos
    const getCampusParaGraficas = () => {
        return [...campusFiltrados].sort((a, b) => a.campus_nombre.localeCompare(b.campus_nombre));
    };

    const campusParaGraficas = getCampusParaGraficas();

    // Para la gráfica detallada, siempre mostrar todos los campus filtrados
    const getCampusParaDetallada = () => {
        return [...campusFiltrados].sort((a, b) => a.campus_nombre.localeCompare(b.campus_nombre));
    };

    const campusParaDetallada = getCampusParaDetallada();

    // Calcular altura dinámica según la cantidad de campus (más compacta)
    const getGraficaHeight = () => {
        const cantidad = campusParaGraficas.length;
        // Altura más compacta: 30px por campus + margen
        const alturaPorCampus = 35;
        const alturaMinima = 400;
        const alturaMaxima = 1000;

        const alturaCalculada = 150 + (cantidad * alturaPorCampus);
        return Math.min(Math.max(alturaCalculada, alturaMinima), alturaMaxima);
    };

    // Calcular altura dinámica para mostrar TODOS los campus con texto legible
    const getGraficaDetalladaHeight = () => {
        const cantidad = campusParaDetallada.length;
        // Altura aumentada para acomodar texto más grande y márgenes
        const alturaPorCampus = 25; // Aumentado para el texto más grande
        const alturaMinima = 500;
        const alturaBase =70; // Aumentado para los márgenes más grandes
        // Sin altura máxima para que muestre todos los campus
        const alturaCalculada = alturaBase + (cantidad * alturaPorCampus);
        console.log('Campus total:', cantidad, 'Altura calculada:', alturaCalculada);
        return Math.max(alturaCalculada, alturaMinima);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Supervisión - Dashboard Global" />

            <div className="h-full w-full p-6 space-y-6">
                {/* Header Minimalista */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-1">
                                Dashboard de Supervisión
                            </h1>
                            <p className="text-gray-600 dark:text-gray-400">
                                Monitoreo de documentos legales y médicos
                            </p>
                        </div>
                        <div className="flex items-center gap-6">
                            <div className="text-right">
                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {campusFiltrados.length}
                                </div>
                                <div className="text-sm text-gray-500 dark:text-gray-400">Campus</div>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {datosSupervision?.estadisticas_generales.cumplimiento_promedio || 0}%
                                </div>
                                <div className="text-sm text-gray-500 dark:text-gray-400">Cumplimiento</div>
                            </div>
                            <Link href="/supervision">
                                <Button className="bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white">
                                    <Shield className="w-4 h-4 mr-2" />
                                    Ver Semáforo
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Dashboard Content */}
                {datosSupervision && (
                    <>


                        {/* Métricas Principales */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <Card className="border-l-4 border-l-blue-500 dark:border-l-blue-400">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                        <FileText className="w-4 h-4" />
                                        Total Documentos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                        {datosSupervision.estadisticas_generales.total_documentos.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <Activity className="w-4 h-4 mr-1" />
                                        En {campusFiltrados.length} campus
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-green-500 dark:border-l-green-400">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                        <CheckCircle className="w-4 h-4" />
                                        Documentos Aprobados
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">
                                        {datosSupervision.estadisticas_generales.total_aprobados.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-green-600 dark:text-green-400">
                                        <TrendingUp className="w-4 h-4 mr-1" />
                                        {datosSupervision.estadisticas_generales.cumplimiento_promedio}% cumplimiento
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-yellow-500 dark:border-l-yellow-400">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                        <Clock className="w-4 h-4" />
                                        Pendientes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mb-2">
                                        {datosSupervision.estadisticas_generales.total_pendientes.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-yellow-600 dark:text-yellow-400">
                                        <Clock className="w-4 h-4 mr-1" />
                                        Requieren atención
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-red-500 dark:border-l-red-400">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                        <AlertTriangle className="w-4 h-4" />
                                        Campus Críticos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-red-600 dark:text-red-400 mb-2">
                                        {campusCriticos}
                                    </div>
                                    <div className="flex items-center text-sm text-red-600 dark:text-red-400">
                                        <AlertTriangle className="w-4 h-4 mr-1" />
                                        Necesitan intervención
                                    </div>
                                </CardContent>
                            </Card>
                        </div>



                        {/* Sección de Gráficas con Recharts */}
                        {datosSupervision && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                {/* Selector de Tipo de Documento */}
                                <Card className="lg:col-span-2 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950 dark:to-purple-950 border-blue-200 dark:border-blue-800">
                                    <CardContent className="pt-6">
                                        <div className="flex items-center justify-between gap-4">
                                            <div className="flex items-center gap-3">
                                                <BarChart3 className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                                <div>
                                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Filtrar por Tipo de Documento</h3>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">Selecciona qué documentos visualizar en las gráficas</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <Button
                                                    onClick={() => setSelectedMetric('general')}
                                                    className={`${
                                                        selectedMetric === 'general'
                                                            ? 'bg-blue-600 hover:bg-blue-700 text-white'
                                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    }`}
                                                >
                                                    <Activity className="w-4 h-4 mr-2" />
                                                    General
                                                </Button>
                                                <Button
                                                    onClick={() => setSelectedMetric('legales')}
                                                    className={`${
                                                        selectedMetric === 'legales'
                                                            ? 'bg-indigo-600 hover:bg-indigo-700 text-white'
                                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    }`}
                                                >
                                                    <Shield className="w-4 h-4 mr-2" />
                                                    Legales
                                                </Button>
                                                <Button
                                                    onClick={() => setSelectedMetric('medicos')}
                                                    className={`${
                                                        selectedMetric === 'medicos'
                                                            ? 'bg-green-600 hover:bg-green-700 text-white'
                                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    }`}
                                                >
                                                    <FileText className="w-4 h-4 mr-2" />
                                                    Médicos
                                                </Button>

                                                <div className="mx-3 h-6 w-px bg-gray-300 dark:bg-gray-600"></div>
                                                <Button
                                                    onClick={() => setExcludeCID(!excludeCID)}
                                                    className={`${
                                                        excludeCID
                                                            ? 'bg-gray-600 hover:bg-gray-700 text-white'
                                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    }`}
                                                >
                                                    <Filter className="w-4 h-4 mr-2" />
                                                    {excludeCID ? 'Incluir CID' : 'Excluir CID'}
                                                </Button>

                                                <div className="ml-4 text-sm text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700">
                                                    Mostrando: <span className="font-bold text-blue-600 dark:text-blue-400">
                                                        {selectedMetric === 'general' ? 'Todos' : selectedMetric === 'legales' ? 'Documentos Legales' : 'Documentos Médicos'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Gráfica de Barras - Cumplimiento por Campus */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle className="text-xl flex items-center gap-3">
                                            <BarChart3 className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                            Cumplimiento por Campus
                                            <Badge className="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 ml-2">
                                                {selectedMetric === 'general' ? 'General' : selectedMetric === 'legales' ? 'Legales' : 'Médicos'}
                                            </Badge>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div style={{ height: `${getGraficaHeight()}px` }}>
                                            <ResponsiveContainer width="100%" height="100%">
                                                    <BarChart
                                                        data={campusParaGraficas.map(campus => {
                                                            const aprobados = selectedMetric === 'legales' ? campus.fiscales.aprobados :
                                                                             selectedMetric === 'medicos' ? campus.medicos.aprobados :
                                                                             campus.total_aprobados;
                                                            const pendientes = selectedMetric === 'legales' ? campus.fiscales.pendientes :
                                                                              selectedMetric === 'medicos' ? campus.medicos.pendientes :
                                                                              campus.total_pendientes;
                                                            const total = selectedMetric === 'legales' ? campus.fiscales.total_documentos :
                                                                         selectedMetric === 'medicos' ? campus.medicos.total_documentos :
                                                                         campus.total_documentos;
                                                            return {
                                                                nombre: campus.campus_nombre.length > 20
                                                                    ? campus.campus_nombre.substring(0, 20) + '...'
                                                                    : campus.campus_nombre,
                                                                aprobados,
                                                                pendientes,
                                                                total
                                                            };
                                                        })}
                                                        layout="horizontal"
                                                        margin={{ top: 20, right: 30, left: 20, bottom: 80 }}
                                                    >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" className="dark:stroke-gray-700" />
                                                        <XAxis
                                                            dataKey="nombre"
                                                            tick={{ fontSize: 13, fill: 'currentColor' }}
                                                            className="text-gray-700 dark:text-gray-300"
                                                            angle={-45}
                                                            textAnchor="end"
                                                            height={80}
                                                            interval={0}
                                                        />
                                                        <YAxis
                                                            type="number"
                                                            tick={{ fontSize: 12, fill: 'currentColor' }}
                                                            className="text-gray-700 dark:text-gray-300"
                                                            domain={[0, 'dataMax + 5']}
                                                        />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px',
                                                            color: '#1f2937'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value,
                                                            name === 'aprobados' ? 'Aprobados' :
                                                            name === 'pendientes' ? 'Pendientes' : 'Total'
                                                        ]}
                                                    />
                                                    <Legend wrapperStyle={{ color: 'currentColor' }} className="text-gray-700 dark:text-gray-300" />
                                                    <Bar
                                                        dataKey="aprobados"
                                                        fill="#10b981"
                                                        name="Aprobados"
                                                        radius={[0, 4, 4, 0]}
                                                    />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Gráfica de Dona - Estados Generales */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <Activity className="w-5 h-5 text-green-600 dark:text-green-400" />
                                            Estados de Documentos
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-64">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <PieChart>
                                                    <Pie
                                                        data={[
                                                            {
                                                                name: 'Aprobados',
                                                                value: selectedMetric === 'legales'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.aprobados, 0)
                                                                    : selectedMetric === 'medicos'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.medicos.aprobados, 0)
                                                                    : datosSupervision.estadisticas_generales.total_aprobados,
                                                                color: '#10b981'
                                                            },
                                                            {
                                                                name: 'Pendientes',
                                                                value: selectedMetric === 'legales'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.pendientes, 0)
                                                                    : selectedMetric === 'medicos'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.medicos.pendientes, 0)
                                                                    : datosSupervision.estadisticas_generales.total_pendientes,
                                                                color: '#f59e0b'
                                                            },
                                                            {
                                                                name: 'Caducados',
                                                                value: selectedMetric === 'legales'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.caducados, 0)
                                                                    : selectedMetric === 'medicos'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.medicos.caducados, 0)
                                                                    : datosSupervision.estadisticas_generales.total_caducados,
                                                                color: '#f97316'
                                                            },
                                                            {
                                                                name: 'Rechazados',
                                                                value: selectedMetric === 'legales'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.rechazados, 0)
                                                                    : selectedMetric === 'medicos'
                                                                    ? campusFiltrados.reduce((sum, campus) => sum + campus.medicos.rechazados, 0)
                                                                    : datosSupervision.estadisticas_generales.total_rechazados,
                                                                color: '#ef4444'
                                                            }
                                                        ].filter(item => item.value > 0)}
                                                        cx="50%"
                                                        cy="50%"
                                                        innerRadius={40}
                                                        outerRadius={80}
                                                        paddingAngle={2}
                                                        dataKey="value"
                                                    >
                                                        {[
                                                            { color: '#10b981' },
                                                            { color: '#f59e0b' },
                                                            { color: '#f97316' },
                                                            { color: '#ef4444' }
                                                        ].map((entry, index) => (
                                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                                        ))}
                                                    </Pie>
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value) => [value.toLocaleString(), 'Documentos']}
                                                    />
                                                    <Legend />
                                                </PieChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Gráfica de Área - Distribución por Tipo */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <TrendingUp className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                            Distribución por Tipo
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div style={{ height: `${Math.max(400, getGraficaHeight() * 0.5)}px` }}>
                                            <ResponsiveContainer width="100%" height="100%">
                                                <AreaChart
                                                    data={campusParaGraficas.map(campus => ({
                                                        campus: campus.campus_nombre.substring(0, 10) + '...',
                                                        legales: campus.fiscales.total_documentos,
                                                        medicos: campus.medicos.total_documentos
                                                    }))}
                                                    margin={{ top: 10, right: 30, left: 0, bottom: 100 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" className="dark:stroke-gray-700" />
                                                    <XAxis
                                                        dataKey="campus"
                                                        tick={{ fontSize: 11, fill: 'currentColor' }}
                                                        className="text-gray-700 dark:text-gray-300"
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={80}
                                                        interval={0}
                                                    />
                                                    <YAxis tick={{ fontSize: 12, fill: 'currentColor' }} className="text-gray-700 dark:text-gray-300" />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px',
                                                            color: '#1f2937'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value,
                                                            name === 'legales' ? 'Docs Legales' : 'Docs Médicos'
                                                        ]}
                                                    />
                                                    <Area
                                                        type="monotone"
                                                        dataKey="legales"
                                                        stackId="1"
                                                        stroke="#3b82f6"
                                                        fill="#3b82f6"
                                                        fillOpacity={0.6}
                                                    />
                                                    <Area
                                                        type="monotone"
                                                        dataKey="medicos"
                                                        stackId="1"
                                                        stroke="#10b981"
                                                        fill="#10b981"
                                                        fillOpacity={0.6}
                                                    />
                                                    <Legend />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                                {/* Gráfica de Barras Apiladas - Análisis Detallado */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle className="text-xl flex items-center gap-3">
                                            <BarChart3 className="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                            Análisis Detallado por Campus
                                            <Badge className="bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 ml-2">
                                                {selectedMetric === 'general' ? 'General' : selectedMetric === 'legales' ? 'Legales' : 'Médicos'}
                                            </Badge>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div style={{ height: `${getGraficaDetalladaHeight()}px` }}>
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={campusParaDetallada.map(campus => ({
                                                        nombre: campus.campus_nombre.length > 25
                                                            ? campus.campus_nombre.substring(0, 25) + '...'
                                                            : campus.campus_nombre,
                                                        aprobados: selectedMetric === 'legales' ? campus.fiscales.aprobados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.aprobados :
                                                                  campus.total_aprobados,
                                                        pendientes: selectedMetric === 'legales' ? campus.fiscales.pendientes :
                                                                   selectedMetric === 'medicos' ? campus.medicos.pendientes :
                                                                   campus.total_pendientes,
                                                        caducados: selectedMetric === 'legales' ? campus.fiscales.caducados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.caducados :
                                                                  campus.total_caducados,
                                                        rechazados: selectedMetric === 'legales' ? campus.fiscales.rechazados :
                                                                   selectedMetric === 'medicos' ? campus.medicos.rechazados :
                                                                   campus.total_rechazados
                                                    }))}
                                                    layout="vertical"
                                                    margin={{ top: 15, right: 30, left: 120, bottom: 15 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" className="dark:stroke-gray-700" />
                                                    <XAxis
                                                        type="number"
                                                        tick={{ fontSize: 13, fill: 'currentColor' }}
                                                        className="text-gray-700 dark:text-gray-300"
                                                    />
                                                    <YAxis
                                                        type="category"
                                                        dataKey="nombre"
                                                        tick={{ fontSize: 13, fill: 'currentColor' }}
                                                        className="text-gray-700 dark:text-gray-300"
                                                        width={180}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px',
                                                            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                                                            color: '#1f2937'
                                                        }}
                                                        formatter={(value, name, props) => {
                                                            const campusData = props.payload;
                                                            const numValue = Number(value);
                                                            const total = Number(campusData.aprobados) + Number(campusData.pendientes) + Number(campusData.caducados) + Number(campusData.rechazados);
                                                            const porcentaje = total > 0 ? ((numValue / total) * 100).toFixed(1) : '0';

                                                            // Usar el name que viene de la barra directamente
                                                            const estadoNombre = name || (
                                                                props.dataKey === 'aprobados' ? 'Aprobados' :
                                                                props.dataKey === 'pendientes' ? 'Pendientes' :
                                                                props.dataKey === 'caducados' ? 'Caducados' : 'Rechazados'
                                                            );

                                                            return [
                                                                `${value} documentos (${porcentaje}%)`,
                                                                estadoNombre
                                                            ];
                                                        }}
                                                        labelFormatter={(label) => `Campus: ${label}`}
                                                        itemSorter={(item) => {
                                                            const order = ['aprobados', 'pendientes', 'caducados', 'rechazados'];
                                                            return order.indexOf(String(item.dataKey || ''));
                                                        }}
                                                    />
                                                    <Legend wrapperStyle={{ color: 'currentColor' }} className="text-gray-700 dark:text-gray-300" />
                                                    <Bar dataKey="aprobados" stackId="a" fill="#10b981" name="Aprobados" />
                                                    <Bar dataKey="pendientes" stackId="a" fill="#f59e0b" name="Pendientes" />
                                                    <Bar dataKey="caducados" stackId="a" fill="#f97316" name="Caducados" />
                                                    <Bar dataKey="rechazados" stackId="a" fill="#ef4444" name="Rechazados" />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
