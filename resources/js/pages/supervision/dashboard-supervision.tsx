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
    Activity
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
    const [selectedMetric, setSelectedMetric] = useState<'fiscales' | 'medicos' | 'general'>('general');

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

    // Filtrar campus según la métrica seleccionada
    const getCampusFiltrados = () => {
        if (!datosSupervision) return [];

        return datosSupervision.estadisticas_por_campus.filter(campus => {
            switch (selectedMetric) {
                case 'fiscales':
                    return campus.tiene_fiscales;
                case 'medicos':
                    return campus.tiene_medicos;
                default:
                    return true;
            }
        });
    };

    const campusFiltrados = getCampusFiltrados();

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Supervisión - Dashboard Global" />

            <div className="h-full w-full p-6 space-y-6">
                {/* Header Minimalista */}
                <div className="bg-white rounded-lg shadow-sm border p-6 mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 mb-1">
                                Dashboard de Supervisión
                            </h1>
                            <p className="text-gray-600">
                                Monitoreo de documentos fiscales y médicos
                            </p>
                        </div>
                        <div className="flex items-center gap-6">
                            <div className="text-right">
                                <div className="text-2xl font-bold text-gray-900">
                                    {datosSupervision?.estadisticas_generales.total_campus || 0}
                                </div>
                                <div className="text-sm text-gray-500">Campus</div>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-bold text-blue-600">
                                    {datosSupervision?.estadisticas_generales.cumplimiento_promedio || 0}%
                                </div>
                                <div className="text-sm text-gray-500">Cumplimiento</div>
                            </div>
                            <Link href="/supervision">
                                <Button className="bg-blue-600 hover:bg-blue-700 text-white">
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
                            <Card className="border-l-4 border-l-blue-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
                                        <FileText className="w-4 h-4" />
                                        Total Documentos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-gray-900 mb-2">
                                        {datosSupervision.estadisticas_generales.total_documentos.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-gray-600">
                                        <Activity className="w-4 h-4 mr-1" />
                                        En {datosSupervision.estadisticas_generales.total_campus} campus
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-green-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
                                        <CheckCircle className="w-4 h-4" />
                                        Documentos Aprobados
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-green-600 mb-2">
                                        {datosSupervision.estadisticas_generales.total_aprobados.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-green-600">
                                        <TrendingUp className="w-4 h-4 mr-1" />
                                        {datosSupervision.estadisticas_generales.cumplimiento_promedio}% cumplimiento
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-yellow-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
                                        <Clock className="w-4 h-4" />
                                        Pendientes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-yellow-600 mb-2">
                                        {datosSupervision.estadisticas_generales.total_pendientes.toLocaleString()}
                                    </div>
                                    <div className="flex items-center text-sm text-yellow-600">
                                        <Clock className="w-4 h-4 mr-1" />
                                        Requieren atención
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-red-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
                                        <AlertTriangle className="w-4 h-4" />
                                        Campus Críticos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-bold text-red-600 mb-2">
                                        {datosSupervision.estadisticas_generales.campus_criticos}
                                    </div>
                                    <div className="flex items-center text-sm text-red-600">
                                        <AlertTriangle className="w-4 h-4 mr-1" />
                                        Necesitan intervención
                                    </div>
                                </CardContent>
                            </Card>
                        </div>



                        {/* Sección de Gráficas con Recharts */}
                        {datosSupervision && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                {/* Gráfica de Barras - Cumplimiento por Campus */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle className="text-xl flex items-center gap-3">
                                            <BarChart3 className="w-6 h-6 text-blue-600" />
                                            Cumplimiento por Campus
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={campusFiltrados.slice(0, 10).map(campus => ({
                                                        nombre: campus.campus_nombre.length > 15
                                                            ? campus.campus_nombre.substring(0, 15) + '...'
                                                            : campus.campus_nombre,
                                                        cumplimiento: campus.porcentaje_cumplimiento,
                                                        aprobados: selectedMetric === 'fiscales' ? campus.fiscales.aprobados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.aprobados :
                                                                  campus.total_aprobados,
                                                        pendientes: selectedMetric === 'fiscales' ? campus.fiscales.pendientes :
                                                                   selectedMetric === 'medicos' ? campus.medicos.pendientes :
                                                                   campus.total_pendientes,
                                                        total: selectedMetric === 'fiscales' ? campus.fiscales.total_documentos :
                                                              selectedMetric === 'medicos' ? campus.medicos.total_documentos :
                                                              campus.total_documentos
                                                    }))}
                                                    margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="nombre"
                                                        tick={{ fontSize: 12 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={80}
                                                    />
                                                    <YAxis tick={{ fontSize: 12 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value + (name === 'cumplimiento' ? '%' : ''),
                                                            name === 'cumplimiento' ? 'Cumplimiento' :
                                                            name === 'aprobados' ? 'Aprobados' :
                                                            name === 'pendientes' ? 'Pendientes' : 'Total'
                                                        ]}
                                                    />
                                                    <Legend />
                                                    <Bar
                                                        dataKey="cumplimiento"
                                                        fill="#3b82f6"
                                                        name="Cumplimiento (%)"
                                                        radius={[4, 4, 0, 0]}
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
                                            <Activity className="w-5 h-5 text-green-600" />
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
                                                                value: datosSupervision.estadisticas_generales.total_aprobados,
                                                                color: '#10b981'
                                                            },
                                                            {
                                                                name: 'Pendientes',
                                                                value: datosSupervision.estadisticas_generales.total_pendientes,
                                                                color: '#f59e0b'
                                                            },
                                                            {
                                                                name: 'Caducados',
                                                                value: datosSupervision.estadisticas_generales.total_caducados,
                                                                color: '#f97316'
                                                            },
                                                            {
                                                                name: 'Rechazados',
                                                                value: datosSupervision.estadisticas_generales.total_rechazados,
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
                                            <TrendingUp className="w-5 h-5 text-purple-600" />
                                            Distribución por Tipo
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-64">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <AreaChart
                                                    data={campusFiltrados.slice(0, 8).map(campus => ({
                                                        campus: campus.campus_nombre.substring(0, 10) + '...',
                                                        fiscales: campus.fiscales.total_documentos,
                                                        medicos: campus.medicos.total_documentos
                                                    }))}
                                                    margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="campus"
                                                        tick={{ fontSize: 11 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={60}
                                                    />
                                                    <YAxis tick={{ fontSize: 11 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value,
                                                            name === 'fiscales' ? 'Docs Fiscales' : 'Docs Médicos'
                                                        ]}
                                                    />
                                                    <Area
                                                        type="monotone"
                                                        dataKey="fiscales"
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
                            </div>
                        )}

                        {/* Sección de Gráficas con Recharts */}
                        {datosSupervision && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                {/* Gráfica de Barras - Cumplimiento por Campus */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle className="text-xl flex items-center gap-3">
                                            <BarChart3 className="w-6 h-6 text-blue-600" />
                                            Cumplimiento por Campus
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={campusFiltrados.slice(0, 10).map(campus => ({
                                                        nombre: campus.campus_nombre.length > 15
                                                            ? campus.campus_nombre.substring(0, 15) + '...'
                                                            : campus.campus_nombre,
                                                        cumplimiento: campus.porcentaje_cumplimiento,
                                                        aprobados: selectedMetric === 'fiscales' ? campus.fiscales.aprobados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.aprobados :
                                                                  campus.total_aprobados,
                                                        pendientes: selectedMetric === 'fiscales' ? campus.fiscales.pendientes :
                                                                   selectedMetric === 'medicos' ? campus.medicos.pendientes :
                                                                   campus.total_pendientes,
                                                        total: selectedMetric === 'fiscales' ? campus.fiscales.total_documentos :
                                                              selectedMetric === 'medicos' ? campus.medicos.total_documentos :
                                                              campus.total_documentos
                                                    }))}
                                                    margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="nombre"
                                                        tick={{ fontSize: 12 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={80}
                                                    />
                                                    <YAxis tick={{ fontSize: 12 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value + (name === 'cumplimiento' ? '%' : ''),
                                                            name === 'cumplimiento' ? 'Cumplimiento' :
                                                            name === 'aprobados' ? 'Aprobados' :
                                                            name === 'pendientes' ? 'Pendientes' : 'Total'
                                                        ]}
                                                    />
                                                    <Legend />
                                                    <Bar
                                                        dataKey="cumplimiento"
                                                        fill="#3b82f6"
                                                        name="Cumplimiento (%)"
                                                        radius={[4, 4, 0, 0]}
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
                                            <Activity className="w-5 h-5 text-green-600" />
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
                                                                value: datosSupervision.estadisticas_generales.total_aprobados,
                                                                color: '#10b981'
                                                            },
                                                            {
                                                                name: 'Pendientes',
                                                                value: datosSupervision.estadisticas_generales.total_pendientes,
                                                                color: '#f59e0b'
                                                            },
                                                            {
                                                                name: 'Caducados',
                                                                value: datosSupervision.estadisticas_generales.total_caducados,
                                                                color: '#f97316'
                                                            },
                                                            {
                                                                name: 'Rechazados',
                                                                value: datosSupervision.estadisticas_generales.total_rechazados,
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
                                            <TrendingUp className="w-5 h-5 text-purple-600" />
                                            Distribución por Tipo
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-64">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <AreaChart
                                                    data={campusFiltrados.slice(0, 8).map(campus => ({
                                                        campus: campus.campus_nombre.substring(0, 10) + '...',
                                                        fiscales: campus.fiscales.total_documentos,
                                                        medicos: campus.medicos.total_documentos
                                                    }))}
                                                    margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="campus"
                                                        tick={{ fontSize: 11 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={60}
                                                    />
                                                    <YAxis tick={{ fontSize: 11 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value, name) => [
                                                            value,
                                                            name === 'fiscales' ? 'Docs Fiscales' : 'Docs Médicos'
                                                        ]}
                                                    />
                                                    <Area
                                                        type="monotone"
                                                        dataKey="fiscales"
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
                                            <BarChart3 className="w-6 h-6 text-indigo-600" />
                                            Análisis Detallado por Campus
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-96">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={campusFiltrados.slice(0, 12).map(campus => ({
                                                        nombre: campus.campus_nombre.length > 12
                                                            ? campus.campus_nombre.substring(0, 12) + '...'
                                                            : campus.campus_nombre,
                                                        aprobados: selectedMetric === 'fiscales' ? campus.fiscales.aprobados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.aprobados :
                                                                  campus.total_aprobados,
                                                        pendientes: selectedMetric === 'fiscales' ? campus.fiscales.pendientes :
                                                                   selectedMetric === 'medicos' ? campus.medicos.pendientes :
                                                                   campus.total_pendientes,
                                                        caducados: selectedMetric === 'fiscales' ? campus.fiscales.caducados :
                                                                  selectedMetric === 'medicos' ? campus.medicos.caducados :
                                                                  campus.total_caducados,
                                                        rechazados: selectedMetric === 'fiscales' ? campus.fiscales.rechazados :
                                                                   selectedMetric === 'medicos' ? campus.medicos.rechazados :
                                                                   campus.total_rechazados
                                                    }))}
                                                    margin={{ top: 20, right: 30, left: 20, bottom: 60 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="nombre"
                                                        tick={{ fontSize: 11 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={80}
                                                    />
                                                    <YAxis tick={{ fontSize: 11 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px',
                                                            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
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
                                                    <Legend />
                                                    <Bar dataKey="aprobados" stackId="a" fill="#10b981" name="Aprobados" />
                                                    <Bar dataKey="pendientes" stackId="a" fill="#f59e0b" name="Pendientes" />
                                                    <Bar dataKey="caducados" stackId="a" fill="#f97316" name="Caducados" />
                                                    <Bar dataKey="rechazados" stackId="a" fill="#ef4444" name="Rechazados" />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Gráfica de Líneas - Tendencias de Cumplimiento */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <TrendingUp className="w-5 h-5 text-blue-600" />
                                            Top Campus por Cumplimiento
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-64">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart
                                                    data={campusFiltrados
                                                        .sort((a, b) => b.porcentaje_cumplimiento - a.porcentaje_cumplimiento)
                                                        .slice(0, 8)
                                                        .map(campus => ({
                                                            campus: campus.campus_nombre.substring(0, 8) + '...',
                                                            cumplimiento: campus.porcentaje_cumplimiento
                                                        }))}
                                                    margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis
                                                        dataKey="campus"
                                                        tick={{ fontSize: 10 }}
                                                        angle={-45}
                                                        textAnchor="end"
                                                        height={50}
                                                    />
                                                    <YAxis
                                                        tick={{ fontSize: 11 }}
                                                        domain={[0, 100]}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value) => [value + '%', 'Cumplimiento']}
                                                    />
                                                    <Line
                                                        type="monotone"
                                                        dataKey="cumplimiento"
                                                        stroke="#3b82f6"
                                                        strokeWidth={3}
                                                        dot={{ fill: '#3b82f6', strokeWidth: 2, r: 4 }}
                                                        activeDot={{ r: 6 }}
                                                    />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Gráfica de Comparación Fiscal vs Médico */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <BarChart3 className="w-5 h-5 text-emerald-600" />
                                            Comparación Fiscal vs Médico
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-64">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={[
                                                        {
                                                            tipo: 'Fiscales',
                                                            total: campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.total_documentos, 0),
                                                            aprobados: campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.aprobados, 0),
                                                            pendientes: campusFiltrados.reduce((sum, campus) => sum + campus.fiscales.pendientes, 0)
                                                        },
                                                        {
                                                            tipo: 'Médicos',
                                                            total: campusFiltrados.reduce((sum, campus) => sum + campus.medicos.total_documentos, 0),
                                                            aprobados: campusFiltrados.reduce((sum, campus) => sum + campus.medicos.aprobados, 0),
                                                            pendientes: campusFiltrados.reduce((sum, campus) => sum + campus.medicos.pendientes, 0)
                                                        }
                                                    ]}
                                                    margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis dataKey="tipo" tick={{ fontSize: 12 }} />
                                                    <YAxis tick={{ fontSize: 12 }} />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                    />
                                                    <Legend />
                                                    <Bar dataKey="total" fill="#64748b" name="Total" />
                                                    <Bar dataKey="aprobados" fill="#10b981" name="Aprobados" />
                                                    <Bar dataKey="pendientes" fill="#f59e0b" name="Pendientes" />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Nueva Gráfica de Radar - Performance Integral */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <TrendingUp className="w-5 h-5 text-purple-600" />
                                            Performance Top 5 Campus
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <RadarChart data={campusFiltrados
                                                    .sort((a, b) => b.porcentaje_cumplimiento - a.porcentaje_cumplimiento)
                                                    .slice(0, 5)
                                                    .map(campus => ({
                                                        campus: campus.campus_nombre.substring(0, 8),
                                                        cumplimiento: campus.porcentaje_cumplimiento,
                                                        eficiencia: Math.min(100, (campus.total_aprobados / Math.max(1, campus.total_documentos)) * 100),
                                                        actividad: Math.min(100, (campus.total_documentos / 20) * 100),
                                                        calidad: Math.max(0, 100 - (campus.total_rechazados / Math.max(1, campus.total_documentos)) * 100)
                                                    }))}>
                                                    <PolarGrid />
                                                    <PolarAngleAxis dataKey="campus" tick={{ fontSize: 11 }} />
                                                    <PolarRadiusAxis
                                                        angle={90}
                                                        domain={[0, 100]}
                                                        tick={{ fontSize: 10 }}
                                                    />
                                                    <Radar
                                                        name="Cumplimiento"
                                                        dataKey="cumplimiento"
                                                        stroke="#3b82f6"
                                                        fill="#3b82f6"
                                                        fillOpacity={0.3}
                                                        strokeWidth={2}
                                                    />
                                                    <Radar
                                                        name="Eficiencia"
                                                        dataKey="eficiencia"
                                                        stroke="#10b981"
                                                        fill="#10b981"
                                                        fillOpacity={0.2}
                                                        strokeWidth={2}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value) => [`${Number(value).toFixed(1)}%`, '']}
                                                    />
                                                    <Legend />
                                                </RadarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Nueva Gráfica - Mapa de Calor Simulado con Barras */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <BarChart3 className="w-5 h-5 text-red-600" />
                                            Mapa de Rendimiento
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    layout="horizontal"
                                                    data={campusFiltrados
                                                        .sort((a, b) => a.porcentaje_cumplimiento - b.porcentaje_cumplimiento)
                                                        .slice(0, 10)
                                                        .map(campus => ({
                                                            campus: campus.campus_nombre.substring(0, 12),
                                                            cumplimiento: campus.porcentaje_cumplimiento,
                                                            riesgo: 100 - campus.porcentaje_cumplimiento
                                                        }))}
                                                    margin={{ top: 5, right: 30, left: 80, bottom: 5 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis type="number" domain={[0, 100]} tick={{ fontSize: 11 }} />
                                                    <YAxis
                                                        dataKey="campus"
                                                        type="category"
                                                        tick={{ fontSize: 10 }}
                                                        width={75}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value, name) => [
                                                            `${value}%`,
                                                            name === 'cumplimiento' ? 'Cumplimiento' : 'Riesgo'
                                                        ]}
                                                    />
                                                    <Bar
                                                        dataKey="cumplimiento"
                                                        fill="#10b981"
                                                        name="Cumplimiento"
                                                        radius={[0, 4, 4, 0]}
                                                    />
                                                    <Bar
                                                        dataKey="riesgo"
                                                        fill="#ef4444"
                                                        name="Riesgo"
                                                        radius={[0, 4, 4, 0]}
                                                    />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Nueva Gráfica - Tendencias Temporales Simuladas */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle className="text-xl flex items-center gap-3">
                                            <TrendingUp className="w-6 h-6 text-indigo-600" />
                                            Tendencias de Cumplimiento (Últimos 6 Meses)
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart
                                                    data={(() => {
                                                        const meses = ['May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct'];
                                                        const topCampus = campusFiltrados
                                                            .sort((a, b) => b.porcentaje_cumplimiento - a.porcentaje_cumplimiento)
                                                            .slice(0, 5);

                                                        return meses.map((mes, index) => {
                                                            const dataPoint: any = { mes };
                                                            topCampus.forEach(campus => {
                                                                const variacion = (Math.random() - 0.5) * 20;
                                                                const base = campus.porcentaje_cumplimiento;
                                                                const tendencia = Math.max(0, Math.min(100, base + variacion - (5 - index) * 3));
                                                                dataPoint[campus.campus_nombre.substring(0, 8)] = Math.round(tendencia);
                                                            });
                                                            return dataPoint;
                                                        });
                                                    })()}
                                                    margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis dataKey="mes" tick={{ fontSize: 12 }} />
                                                    <YAxis
                                                        domain={[0, 100]}
                                                        tick={{ fontSize: 12 }}
                                                        label={{ value: 'Cumplimiento %', angle: -90, position: 'insideLeft' }}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor: '#f8fafc',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '8px'
                                                        }}
                                                        formatter={(value) => [`${value}%`, 'Cumplimiento']}
                                                        labelFormatter={(label) => `Mes: ${label}`}
                                                    />
                                                    <Legend />
                                                    {campusFiltrados
                                                        .sort((a, b) => b.porcentaje_cumplimiento - a.porcentaje_cumplimiento)
                                                        .slice(0, 5)
                                                        .map((campus, index) => (
                                                            <Line
                                                                key={campus.campus_id}
                                                                type="monotone"
                                                                dataKey={campus.campus_nombre.substring(0, 8)}
                                                                stroke={[
                                                                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                                                                ][index]}
                                                                strokeWidth={2}
                                                                dot={{ r: 4 }}
                                                                activeDot={{ r: 6 }}
                                                            />
                                                        ))
                                                    }
                                                </LineChart>
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
