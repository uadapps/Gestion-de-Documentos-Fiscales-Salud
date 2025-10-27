import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import {
    FileText,
    Calendar,
    Download,
    AlertTriangle,
    CheckCircle,
    Clock,
    XCircle,
    Building,
    Activity,
    Eye
} from 'lucide-react';

interface Campus {
    ID_Campus: string;
    Campus: string;
    Activo: boolean;
    [key: string]: any;
}

interface Documento {
    id: number;
    nombre: string;
    tipo: string;
    estado: 'aprobado' | 'pendiente' | 'vencido' | 'rechazado';
    fecha_subida: string;
    fecha_vencimiento: string | null;
    fecha_aprobacion: string | null;
    usuario: string;
    tamano: string;
    observaciones: string | null;
    folio?: string;
    lugar_expedicion?: string;
    fecha_expedicion?: string;
    carrera?: string;
    puede_descargar?: boolean;
    url_descarga?: string | null;
    url_ver?: string | null;
}

interface DocumentoAgrupado {
    tipo: string;
    total: number;
    aprobados: number;
    vencidos: number;
    pendientes: number;
    rechazados: number;
    documentos: Documento[];
}

interface Estadisticas {
    documentos: {
        total: number;
        aprobados: number;
        pendientes: number;
        vencidos: number;
        cumplimiento: number;
    };
}

interface DetallesCampusProps {
    campus: Campus;
    documentos: Documento[];
    documentos_agrupados: DocumentoAgrupado[];
    estadisticas: Estadisticas;
    [key: string]: any;
}

export default function DetallesCampus() {
    const { campus, documentos, documentos_agrupados, estadisticas } = usePage<any>().props;
    const [isLoading, setIsLoading] = useState(false);

    // Debug temporal - Ver qué datos llegan
/*     console.log('Datos recibidos en DetallesCampus:', {
        campus,
        documentos,
        documentos_agrupados,
        estadisticas
    });
 */
    const breadcrumbItems: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/supervision' },
        { title: 'Supervisión', href: '/supervision' },
        { title: campus?.Campus || 'Campus', href: '#' }
    ];

    const handleDownload = async (documento: Documento) => {
        if (documento.url_descarga && documento.puede_descargar) {
          /*   console.log('Descargando:', documento.url_descarga); */

            try {
                // Usar fetch para obtener el archivo como blob
                const response = await fetch(documento.url_descarga);
                const blob = await response.blob();

                // Crear URL del blob y enlace para descarga
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${documento.nombre || 'documento'}.pdf`;

                // Forzar descarga
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Limpiar URL del blob
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Error al descargar:', error);
                // Fallback al método anterior si falla
                const link = document.createElement('a');
                link.href = documento.url_descarga;
                link.download = documento.nombre || 'documento.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        } else {
           /*  console.log('No se puede descargar:', {
                tiene_url: !!documento.url_descarga,
                puede_descargar: documento.puede_descargar
            }); */
        }
    };

    const handleView = (documento: Documento) => {
/*         console.log('handleView llamado:', {
            documento: documento,
            url_ver: documento.url_ver
        }); */

        if (documento.url_ver) {
          /*   console.log('Abriendo URL de vista:', documento.url_ver); */
            window.open(documento.url_ver, '_blank');
        } else {
        /*     console.log('No tiene URL de vista'); */
        }
    };

    const getEstadoBadge = (estado: string) => {
        const variants = {
            aprobado: 'default',
            pendiente: 'secondary',
            vencido: 'destructive',
            rechazado: 'destructive'
        };

        const colors = {
            aprobado: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 border-green-200 dark:border-green-800',
            pendiente: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
            vencido: 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 border-orange-200 dark:border-orange-800',
            rechazado: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 border-red-200 dark:border-red-800'
        };

        return (
            <Badge className={colors[estado as keyof typeof colors] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'}>
                {estado.charAt(0).toUpperCase() + estado.slice(1)}
            </Badge>
        );
    };

    const getEstadoIcon = (estado: string) => {
        switch (estado) {
            case 'aprobado':
                return <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />;
            case 'pendiente':
                return <Clock className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />;
            case 'vencido':
                return <AlertTriangle className="h-4 w-4 text-orange-600 dark:text-orange-400" />;
            case 'rechazado':
                return <XCircle className="h-4 w-4 text-red-600 dark:text-red-400" />;
            default:
                return <FileText className="h-4 w-4 text-gray-600 dark:text-gray-400" />;
        }
    };

    const normalizarTexto = (texto: string) => {
        if (!texto) return texto;
        return texto.replace(/fiscal/gi, 'legal').toLowerCase();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title={`Campus ${campus?.Campus || 'Campus'} - Supervisión`} />

            <div className="h-full w-full p-6 pl-8 pr-8 space-y-6">
                {/* Campus Info - Header principal */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                    <div className="flex items-center space-x-4">
                        <Building className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Campus {campus?.Campus || 'Campus'}</h1>
                            <p className="text-gray-600 dark:text-gray-400">Supervisión de documentos</p>
                        </div>
                    </div>
                </div>

                {/* Estadísticas Cards - Solo documentos */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Documentos</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{estadisticas?.documentos?.total || documentos?.length || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                {estadisticas?.documentos?.pendientes || 0} pendientes
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Documentos Aprobados</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600 dark:text-green-400">{estadisticas?.documentos?.aprobados || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                {estadisticas?.documentos?.cumplimiento || 0}% cumplimiento
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Documentos Vencidos</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600 dark:text-red-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">{estadisticas?.documentos?.vencidos || 0}</div>
                            <p className="text-xs text-red-600 dark:text-red-400">
                                Requieren atención
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Documentos agrupados por tipo */}
                <div className="space-y-8">
                    {documentos_agrupados && documentos_agrupados.length > 0 ? (
                        documentos_agrupados.map((grupo: DocumentoAgrupado) => (
                            <Card key={grupo.tipo} className="shadow-sm border border-gray-200 dark:border-gray-700">
                                <CardHeader className="bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 py-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-3">
                                            <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                                <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                                                    Tipo: {normalizarTexto(grupo.tipo)}
                                                </CardTitle>
                                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {grupo.total} {grupo.total === 1 ? 'documento' : 'documentos'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <Badge variant="outline" className="text-xs font-medium">
                                                {grupo.total}
                                            </Badge>
                                            <Badge className="bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800 text-xs">
                                                {grupo.aprobados}
                                            </Badge>
                                            <Badge className="bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800 text-xs">
                                                {grupo.pendientes}
                                            </Badge>
                                            {grupo.vencidos > 0 && (
                                                <Badge className="bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800 text-xs">
                                                    {grupo.vencidos}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-6">
                                    <div className="overflow-hidden">
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead className="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Documento
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Estado
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Vigencia
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Usuario
                                                        </th>
                                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Acciones
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {grupo.documentos.map((documento: Documento) => (
                                                        <tr key={documento.id} className="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                            <td className="px-6 py-4">
                                                                <div className="flex items-center space-x-3">
                                                                    {getEstadoIcon(documento.estado)}
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                                            {normalizarTexto(documento.tipo)}
                                                                        </p>
                                                                        {documento.carrera && (
                                                                            <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                                                {documento.carrera}
                                                                            </p>
                                                                        )}
                                                                        {documento.observaciones && (
                                                                            <div className="mt-1 text-xs text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 px-2 py-1 rounded">
                                                                                <span className="font-medium">Observaciones:</span> {documento.observaciones}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4">
                                                                {getEstadoBadge(documento.estado)}
                                                            </td>
                                                            <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                                {documento.fecha_vencimiento || 'Sin vencimiento'}
                                                            </td>
                                                            <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                                {documento.usuario || ''}
                                                            </td>
                                                            <td className="px-6 py-4 text-right">
                                                                <div className="flex justify-end space-x-2">
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => documento.url_ver ? handleView(documento) : null}
                                                                        disabled={!documento.url_ver}
                                                                        className={`h-8 w-8 p-0 ${!documento.url_ver ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100 hover:text-blue-700'}`}
                                                                        title="Ver documento"
                                                                    >
                                                                        <Eye className="h-4 w-4" />
                                                                    </Button>

                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => documento.url_descarga ? handleDownload(documento) : null}
                                                                        disabled={!documento.puede_descargar || !documento.url_descarga}
                                                                        className={`h-8 w-8 p-0 ${(!documento.puede_descargar || !documento.url_descarga) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-100 hover:text-green-700'}`}
                                                                        title="Descargar documento"
                                                                    >
                                                                        <Download className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        // Fallback si no hay documentos agrupados, mostrar documentos normales
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <FileText className="h-5 w-5 mr-2 text-blue-600 dark:text-blue-400" />
                                    Documentos del Campus
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {documentos && documentos.length > 0 ? (
                                    <div className="space-y-3">
                                        {documentos.map((documento: any) => (
                                            <div key={documento.id} className="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <div className="flex items-center space-x-3 flex-1">
                                                    {getEstadoIcon(documento.estado)}
                                                    <div className="flex-1">
                                                        <p className="font-medium text-sm text-gray-900 dark:text-gray-100">{normalizarTexto(documento.tipo)}</p>
                                                        <div className="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            <span>Archivo: {documento.nombre}</span>
                                                            <span>Subido: {documento.fecha_subida}</span>
                                                            <span>Usuario: {documento.usuario || ''}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-3">
                                                    {getEstadoBadge(documento.estado)}
                                                    <div className="flex space-x-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => documento.url_ver ? handleView(documento) : null}
                                                            disabled={!documento.url_ver}
                                                            className={`h-8 px-3 ${!documento.url_ver ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                        >
                                                            <Eye className="h-4 w-4 mr-1" />
                                                            Ver {!documento.url_ver && '(N/A)'}
                                                        </Button>

                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => documento.url_descarga ? handleDownload(documento) : null}
                                                            disabled={!documento.puede_descargar || !documento.url_descarga}
                                                            className={`h-8 px-3 ${(!documento.puede_descargar || !documento.url_descarga) ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                        >
                                                            <Download className="h-4 w-4 mr-1" />
                                                            Descargar {(!documento.puede_descargar || !documento.url_descarga) && '(N/A)'}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <FileText className="h-12 w-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                                        <p className="text-gray-500 dark:text-gray-400">No hay documentos disponibles</p>
                                        <p className="text-sm text-gray-400 dark:text-gray-500">Los documentos aparecerán aquí cuando estén disponibles</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
