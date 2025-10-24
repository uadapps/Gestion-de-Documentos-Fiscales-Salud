import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Brain,
    FileText,
    Calendar,
    MapPin,
    User,
    AlertCircle,
    CheckCircle,
    Clock,
    RefreshCw,
    Download,
    Eye
} from 'lucide-react';

interface AnalisisDocumento {
    id: string;
    documento: {
        nombre_detectado: string;
        tipo_documento_id: number;
        coincide_catalogo: boolean;
        criterio_coincidencia: string;
        descripcion: string;
        cumple_requisitos: boolean;
        observaciones: string;
    };
    metadatos: {
        folio_documento: string | null;
        entidad_emisora: string | null;
        nombre_perito: string | null;
        cedula_profesional: string | null;
        licencia: string | null;
        fecha_expedicion: string | null;
        vigencia_documento: string | null;
        dias_restantes_vigencia: number | null;
        lugar_expedicion: string | null;
    };
    estado_sistema: {
        requiere_vigencia: boolean;
        vigencia_meses: number | null;
        estado_calculado: string;
    };
    fecha_analisis: string;
    campus_id: string;
    archivo_nombre: string;
}

interface Props {
    analisis: AnalisisDocumento[];
    campus: any;
}

const AnalysisResult: React.FC<Props> = ({ analisis, campus }) => {
    const [selectedAnalisis, setSelectedAnalisis] = useState<AnalisisDocumento | null>(
        analisis.length > 0 ? analisis[0] : null
    );

    const getEstadoColor = (estado: string) => {
        switch (estado) {
            case 'vigente':
                return 'bg-green-100 text-green-800';
            case 'por_vencer':
                return 'bg-yellow-100 text-yellow-800';
            case 'vencido':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getEstadoIcon = (estado: string) => {
        switch (estado) {
            case 'vigente':
                return <CheckCircle className="w-4 h-4" />;
            case 'por_vencer':
                return <Clock className="w-4 h-4" />;
            case 'vencido':
                return <AlertCircle className="w-4 h-4" />;
            default:
                return <FileText className="w-4 h-4" />;
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'No especificada';
        return new Date(dateString).toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const reanalizar = async (archivoId: string) => {
        try {
            const response = await fetch('/documentos/analisis/reanalizar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ archivo_id: archivoId })
            });

            if (response.ok) {
                // Recargar la página para mostrar el nuevo análisis
                window.location.reload();
            }
        } catch (error) {
            console.error('Error en reanálisis:', error);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Documentos', href: '/documentos' },
                { title: 'Análisis IA', href: '/documentos/analisis' }
            ]}
        >
            <Head title="Análisis de Documentos IA" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                        <Brain className="inline w-8 h-8 mr-2 text-blue-600" />
                        Análisis Inteligente de Documentos
                    </h1>
                    <p className="text-muted-foreground">
                        Resultados del análisis automático con IA para el campus: <strong>{campus?.Campus}</strong>
                    </p>
                </div>

                <div className="grid lg:grid-cols-3 gap-6">
                    {/* Lista de documentos analizados */}
                    <div className="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Documentos Analizados</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {analisis.map((item) => (
                                        <div
                                            key={item.id}
                                            className={`p-3 rounded-lg border cursor-pointer transition-colors ${
                                                selectedAnalisis?.id === item.id
                                                    ? 'border-blue-500 bg-blue-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                            onClick={() => setSelectedAnalisis(item)}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-sm truncate">
                                                        {item.documento.nombre_detectado}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground truncate">
                                                        {item.archivo_nombre}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDate(item.fecha_analisis)}
                                                    </p>
                                                </div>
                                                <div className="ml-2">
                                                    <Badge
                                                        className={`text-xs ${getEstadoColor(item.estado_sistema.estado_calculado)}`}
                                                    >
                                                        {getEstadoIcon(item.estado_sistema.estado_calculado)}
                                                        <span className="ml-1">
                                                            {item.estado_sistema.estado_calculado}
                                                        </span>
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    {analisis.length === 0 && (
                                        <div className="text-center py-8 text-muted-foreground">
                                            <Brain className="w-12 h-12 mx-auto mb-3 opacity-50" />
                                            <p>No hay análisis disponibles</p>
                                            <p className="text-xs">Los documentos aparecerán aquí después de ser analizados</p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Detalles del análisis seleccionado */}
                    <div className="lg:col-span-2">
                        {selectedAnalisis ? (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-xl">
                                            {selectedAnalisis.documento.nombre_detectado}
                                        </CardTitle>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => reanalizar(selectedAnalisis.id)}
                                            >
                                                <RefreshCw className="w-4 h-4 mr-2" />
                                                Reanalizar
                                            </Button>
                                            <Button variant="outline" size="sm">
                                                <Eye className="w-4 h-4 mr-2" />
                                                Ver PDF
                                            </Button>
                                            <Button variant="outline" size="sm">
                                                <Download className="w-4 h-4 mr-2" />
                                                Descargar
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {/* Secciones usando divs simples */}
                                    <div className="space-y-6">
                                        {/* Información del Documento */}
                                        <div>
                                            <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
                                                <FileText className="w-5 h-5" />
                                                Información del Documento
                                            </h3>
                                            <div className="space-y-4">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Tipo Detectado</Label>
                                                        <p className="text-sm font-medium">
                                                            {selectedAnalisis.documento.nombre_detectado}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <Label>Coincide con Catálogo</Label>
                                                        <Badge
                                                            className={selectedAnalisis.documento.coincide_catalogo
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-red-100 text-red-800'
                                                            }
                                                        >
                                                            {selectedAnalisis.documento.coincide_catalogo ? 'Sí' : 'No'}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div>
                                                    <Label>Descripción</Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        {selectedAnalisis.documento.descripcion}
                                                    </p>
                                                </div>

                                                <div>
                                                    <Label>Cumple Requisitos</Label>
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            className={selectedAnalisis.documento.cumple_requisitos
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-red-100 text-red-800'
                                                            }
                                                        >
                                                            {selectedAnalisis.documento.cumple_requisitos ? (
                                                                <CheckCircle className="w-3 h-3 mr-1" />
                                                            ) : (
                                                                <AlertCircle className="w-3 h-3 mr-1" />
                                                            )}
                                                            {selectedAnalisis.documento.cumple_requisitos ? 'Cumple' : 'No cumple'}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                {selectedAnalisis.documento.observaciones && (
                                                    <div>
                                                        <Label>Observaciones</Label>
                                                        <p className="text-sm text-muted-foreground">
                                                            {selectedAnalisis.documento.observaciones}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Metadatos */}
                                        <div>
                                            <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
                                                <Calendar className="w-5 h-5" />
                                                Metadatos Extraídos
                                            </h3>
                                            <div className="space-y-4">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Folio</Label>
                                                        <p className="text-sm font-medium">
                                                            {selectedAnalisis.metadatos.folio_documento || 'No detectado'}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <Label>Entidad Emisora</Label>
                                                        <p className="text-sm font-medium">
                                                            {selectedAnalisis.metadatos.entidad_emisora || 'No detectada'}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Fecha de Expedición</Label>
                                                        <p className="text-sm font-medium flex items-center gap-2">
                                                            <Calendar className="w-4 h-4" />
                                                            {formatDate(selectedAnalisis.metadatos.fecha_expedicion)}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <Label>Vigencia</Label>
                                                        <p className="text-sm font-medium flex items-center gap-2">
                                                            <Calendar className="w-4 h-4" />
                                                            {formatDate(selectedAnalisis.metadatos.vigencia_documento)}
                                                        </p>
                                                    </div>
                                                </div>

                                                {selectedAnalisis.metadatos.lugar_expedicion && (
                                                    <div>
                                                        <Label>Lugar de Expedición</Label>
                                                        <p className="text-sm font-medium flex items-center gap-2">
                                                            <MapPin className="w-4 h-4" />
                                                            {selectedAnalisis.metadatos.lugar_expedicion}
                                                        </p>
                                                    </div>
                                                )}

                                                {selectedAnalisis.metadatos.nombre_perito && (
                                                    <div>
                                                        <Label>Nombre del Perito</Label>
                                                        <p className="text-sm font-medium flex items-center gap-2">
                                                            <User className="w-4 h-4" />
                                                            {selectedAnalisis.metadatos.nombre_perito}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Estado del Sistema */}
                                        <div>
                                            <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
                                                <Brain className="w-5 h-5" />
                                                Estado del Sistema
                                            </h3>
                                            <div className="space-y-4">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Estado Calculado</Label>
                                                        <Badge className={getEstadoColor(selectedAnalisis.estado_sistema.estado_calculado)}>
                                                            {getEstadoIcon(selectedAnalisis.estado_sistema.estado_calculado)}
                                                            <span className="ml-1">
                                                                {selectedAnalisis.estado_sistema.estado_calculado}
                                                            </span>
                                                        </Badge>
                                                    </div>
                                                    <div>
                                                        <Label>Requiere Vigencia</Label>
                                                        <p className="text-sm font-medium">
                                                            {selectedAnalisis.estado_sistema.requiere_vigencia ? 'Sí' : 'No'}
                                                        </p>
                                                    </div>
                                                </div>

                                                {selectedAnalisis.metadatos.dias_restantes_vigencia !== null && (
                                                    <div>
                                                        <Label>Días Restantes de Vigencia</Label>
                                                        <p className={`text-sm font-medium ${
                                                            selectedAnalisis.metadatos.dias_restantes_vigencia < 0
                                                                ? 'text-red-600'
                                                                : selectedAnalisis.metadatos.dias_restantes_vigencia <= 30
                                                                    ? 'text-orange-600'
                                                                    : 'text-green-600'
                                                        }`}>
                                                            {selectedAnalisis.metadatos.dias_restantes_vigencia} días
                                                        </p>
                                                    </div>
                                                )}

                                                <div>
                                                    <Label>Fecha de Análisis</Label>
                                                    <p className="text-sm font-medium">
                                                        {formatDate(selectedAnalisis.fecha_analisis)}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardContent className="flex items-center justify-center h-96">
                                    <div className="text-center text-muted-foreground">
                                        <Brain className="w-16 h-16 mx-auto mb-4 opacity-50" />
                                        <p className="text-lg">Selecciona un documento para ver su análisis</p>
                                        <p className="text-sm">Los detalles del análisis IA aparecerán aquí</p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default AnalysisResult;
