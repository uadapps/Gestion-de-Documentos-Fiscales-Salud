import React, { useState } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem, type SharedData } from '@/types';
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
    Eye,
    Save,
    Loader2,
    ChevronDown,
    ChevronRight
} from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';

interface Campus {
    ID_Campus: string;
    Campus: string;
    Activo: boolean;
    [key: string]: any;
}

interface Documento {
    id: number;
    sdi_id?: number; // ID de la tabla sug_documentos_informacion
    nombre: string;
    tipo: string;
    estado: 'aprobado' | 'pendiente' | 'vencido' | 'rechazado';
    fecha_subida: string;
    fecha_vencimiento: string | null;
    fecha_aprobacion: string | null;
    usuario: string;
    actualizado_por?: string;
    tamano: string;
    observaciones: string | null;
    observaciones_archivo: string | null;
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
        rechazados?: number;
        cumplimiento: number;
    };
}

interface EstadisticasSP {
    resumen_por_tipo?: Array<{
        campus_id: string;
        nombre_campus: string;
        tipo_documento: string;
        Vigentes: number;
        Caducados: number;
        Rechazados: number;
        Pendientes: number;
        Total: number;
    }>;
    total_general?: {
        campus_id: string;
        nombre_campus: string;
        Vigentes: number;
        Caducados: number;
        Rechazados: number;
        Pendientes: number;
        Total: number;
    };
    resumen_por_carrera?: any[];
}

interface DetallesCampusProps {
    campus: Campus;
    documentos: Documento[];
    documentos_agrupados: DocumentoAgrupado[];
    estadisticas: Estadisticas;
    estadisticas_sp?: EstadisticasSP;
    [key: string]: any;
}

export default function DetallesCampus() {
    const { campus, documentos, documentos_agrupados, estadisticas, estadisticas_sp, auth } = usePage<any>().props;
    const [isLoading, setIsLoading] = useState(false);
    const [editingDocuments, setEditingDocuments] = useState<{ [key: number]: { estado?: string; fecha_vencimiento?: string } }>({});
    const [documentErrors, setDocumentErrors] = useState<{ [key: number]: string }>({});
    const [savingDocuments, setSavingDocuments] = useState<{ [key: number]: boolean }>({});
    const [successDocuments, setSuccessDocuments] = useState<{ [key: number]: boolean }>({});
    const [expandedDocuments, setExpandedDocuments] = useState<{ [key: string]: boolean }>({});

    // Obtener roles del usuario autenticado
    const user = auth?.user;
    const roles = (user as any)?.roles || [];
    const userRoles = Array.isArray(roles) ? roles.map((role: any) =>
        role.rol || role.ID_Rol || role.nombre
    ) : [];
    const isRole20 = userRoles.some((role: any) => role === '20' || role === 20);

    // Usar las estadísticas del SP si están disponibles, si no calcular localmente
    const totalDocumentosReales = estadisticas_sp?.total_general?.Total
        || documentos_agrupados?.reduce((total: number, grupo: DocumentoAgrupado) => {
            return total + (grupo.documentos?.length || 0);
        }, 0)
        || 0;

    const documentosAprobados = estadisticas_sp?.total_general?.Vigentes || estadisticas?.documentos?.aprobados || 0;
    const documentosVencidos = estadisticas_sp?.total_general?.Caducados || estadisticas?.documentos?.vencidos || 0;
    const documentosPendientes = estadisticas_sp?.total_general?.Pendientes || estadisticas?.documentos?.pendientes || 0;
    const cumplimiento = totalDocumentosReales > 0
        ? Math.round((documentosAprobados / totalDocumentosReales) * 100 * 10) / 10
        : 0;

    // Función para obtener estadísticas correctas por tipo desde el SP
    const getEstadisticasPorTipo = (tipoDocumento: string) => {
        if (!estadisticas_sp?.resumen_por_tipo) {
            return null;
        }

        // Normalizar el tipo para comparar
        const tipoNormalizado = tipoDocumento.toUpperCase();
        return estadisticas_sp.resumen_por_tipo.find(
            (resumen: any) => resumen.tipo_documento?.toUpperCase() === tipoNormalizado
        );
    };

    // Corregir los totales de documentos_agrupados con las estadísticas del SP
    const documentosAgrupadosCorregidos = documentos_agrupados?.map((grupo: DocumentoAgrupado) => {
        const estadisticasTipo = getEstadisticasPorTipo(grupo.tipo);

        if (estadisticasTipo) {
            return {
                ...grupo,
                total: estadisticasTipo.Total || grupo.total,
                aprobados: estadisticasTipo.Vigentes || grupo.aprobados,
                vencidos: estadisticasTipo.Caducados || grupo.vencidos,
                pendientes: estadisticasTipo.Pendientes || grupo.pendientes,
                rechazados: estadisticasTipo.Rechazados || grupo.rechazados || 0
            };
        }

        return grupo;
    }) || [];

    // Función para agrupar documentos por tipo + carrera (para MEDICINA)
    const agruparDocumentosPorTipo = (documentos: Documento[]) => {
        const grupos = new Map<string, Documento[]>();

        documentos.forEach(doc => {
            // Si tiene carrera, agrupar por tipo + carrera
            // Si no tiene carrera, agrupar solo por tipo
            const key = doc.carrera
                ? `${doc.tipo.toLowerCase().trim()}-${doc.carrera.toLowerCase().trim()}`
                : doc.tipo.toLowerCase().trim();

            if (!grupos.has(key)) {
                grupos.set(key, []);
            }
            grupos.get(key)!.push(doc);
        });

        return grupos;
    };

    // Función para toggle expandir/colapsar documentos agrupados
    const toggleExpandDocumento = (tipoGrupo: string, tipoDocumento: string) => {
        const key = `${tipoGrupo}-${tipoDocumento}`;
        setExpandedDocuments(prev => ({
            ...prev,
            [key]: !prev[key]
        }));
    };

    // Debug temporal - Ver qué datos llegan
/*     console.log('Datos recibidos en DetallesCampus:', {
        campus,
        documentos,
        documentos_agrupados,
        estadisticas,
        estadisticas_sp,
        totalDocumentosReales,
        documentosAprobados,
        documentosVencidos,
        documentosPendientes,
        cumplimiento
    });
 */
    const breadcrumbItems: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/supervision' },
        { title: 'Supervisión', href: '/supervision' },
        { title: campus?.Campus || 'Campus', href: '#' }
    ];

    const handleDownload = async (documento: Documento) => {
        if (documento.url_descarga && documento.puede_descargar) {
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

    const handleEstadoChange = (documentoId: number, nuevoEstado: string, fechaVencimiento?: string) => {
        setEditingDocuments(prev => ({
            ...prev,
            [documentoId]: {
                ...prev[documentoId],
                estado: nuevoEstado
            }
        }));
    };

    const handleFechaVencimientoChange = (documentoId: number, nuevaFecha: string) => {
        setEditingDocuments(prev => ({
            ...prev,
            [documentoId]: {
                ...prev[documentoId],
                fecha_vencimiento: nuevaFecha
            }
        }));
    };

    const handleSaveChanges = async (documentoId: number) => {
        const changes = editingDocuments[documentoId];
        if (!changes) return;

        // Limpiar mensaje de error previo para este documento
        setDocumentErrors(prev => {
            const newErrors = { ...prev };
            delete newErrors[documentoId];
            return newErrors;
        });

        // Buscar el documento para obtener su fecha original
        let documento: Documento | undefined;
        if (documentos_agrupados) {
            for (const grupo of documentos_agrupados) {
                documento = grupo.documentos.find((doc: Documento) => (doc.sdi_id || doc.id) === documentoId);
                if (documento) break;
            }
        }

        // Validación: no se puede aprobar con fecha vencida
        if (changes.estado === 'aprobado') {
            const fechaAValidar = changes.fecha_vencimiento || documento?.fecha_vencimiento;

            console.log('Validando documento:', {
                documentoId,
                estado: changes.estado,
                fechaAValidar,
                fechaOriginal: documento?.fecha_vencimiento,
                fechaCambio: changes.fecha_vencimiento
            });

            if (fechaAValidar) {
                const fechaActual = new Date();
                fechaActual.setHours(0, 0, 0, 0);
                const fechaVenc = new Date(fechaAValidar);

                console.log('Comparando fechas:', {
                    fechaVenc: fechaVenc.toISOString(),
                    fechaActual: fechaActual.toISOString(),
                    esVencida: fechaVenc < fechaActual
                });

                if (fechaVenc < fechaActual) {
                    setDocumentErrors(prev => ({
                        ...prev,
                        [documentoId]: 'No se puede aprobar con fecha vencida'
                    }));
                    setTimeout(() => {
                        setDocumentErrors(prev => {
                            const newErrors = { ...prev };
                            delete newErrors[documentoId];
                            return newErrors;
                        });
                    }, 5000);
                    return;
                }
            }
        }

        try {
            setSavingDocuments(prev => ({ ...prev, [documentoId]: true }));

            const response = await fetch(`/supervision/actualizar-documento/${documentoId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    estado: changes.estado,
                    fecha_vencimiento: changes.fecha_vencimiento
                })
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar el documento en el estado local
                if (documento) {
                    documento.estado = data.documento.estado;
                    documento.fecha_vencimiento = data.documento.fecha_vencimiento;
                }

                // Limpiar el estado de edición
                setEditingDocuments(prev => {
                    const newState = { ...prev };
                    delete newState[documentoId];
                    return newState;
                });

                // Mostrar éxito
                setSuccessDocuments(prev => ({ ...prev, [documentoId]: true }));
                setTimeout(() => {
                    setSuccessDocuments(prev => {
                        const newState = { ...prev };
                        delete newState[documentoId];
                        return newState;
                    });
                }, 3000);

                // Recargar los datos sin refrescar la página
                router.reload({ only: ['documentos_agrupados', 'estadisticas'] });
            } else {
                setDocumentErrors(prev => ({
                    ...prev,
                    [documentoId]: data.message || 'Error al guardar'
                }));
            }
        } catch (error) {
            console.error('Error al guardar cambios:', error);
            setDocumentErrors(prev => ({
                ...prev,
                [documentoId]: 'Error de conexión'
            }));
        } finally {
            setSavingDocuments(prev => {
                const newState = { ...prev };
                delete newState[documentoId];
                return newState;
            });
        }
    };

    const hasChanges = (documentoId: number) => {
        return editingDocuments[documentoId] !== undefined;
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
                            <div className="text-2xl font-bold">{totalDocumentosReales}</div>
                            <p className="text-xs text-muted-foreground">
                                {documentosPendientes} pendientes
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Documentos Aprobados</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600 dark:text-green-400">{documentosAprobados}</div>
                            <p className="text-xs text-muted-foreground">
                                {cumplimiento}% cumplimiento
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Documentos Vencidos</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600 dark:text-red-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">{documentosVencidos}</div>
                            <p className="text-xs text-red-600 dark:text-red-400">
                                Requieren atención
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Documentos agrupados por tipo */}
                <div className="space-y-8">
                    {documentosAgrupadosCorregidos && documentosAgrupadosCorregidos.length > 0 ? (
                        documentosAgrupadosCorregidos.map((grupo: DocumentoAgrupado) => (
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
                                                            Capturado por
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Actualizado por
                                                        </th>
                                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                            Acciones
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {(() => {
                                                        // Agrupar documentos por tipo
                                                        const gruposDocumentos = agruparDocumentosPorTipo(grupo.documentos);
                                                        const filas: JSX.Element[] = [];

                                                        gruposDocumentos.forEach((docs, tipoDoc) => {
                                                            const expandKey = `${grupo.tipo}-${tipoDoc}`;
                                                            const isExpanded = expandedDocuments[expandKey] || false;
                                                            const documentoPrincipal = docs[0]; // Mostrar el primero
                                                            const tieneMultiples = docs.length > 1;

                                                            // Fila principal
                                                            filas.push(
                                                                <tr key={`main-${expandKey}`} className="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                                    <td className="px-6 py-4">
                                                                        <div className="flex items-center space-x-3">
                                                                            {tieneMultiples && (
                                                                                <button
                                                                                    onClick={() => toggleExpandDocumento(grupo.tipo, tipoDoc)}
                                                                                    className="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                                                                                >
                                                                                    {isExpanded ? (
                                                                                        <ChevronDown className="h-4 w-4 text-gray-600 dark:text-gray-400" />
                                                                                    ) : (
                                                                                        <ChevronRight className="h-4 w-4 text-gray-600 dark:text-gray-400" />
                                                                                    )}
                                                                                </button>
                                                                            )}
                                                                            {getEstadoIcon(documentoPrincipal.estado)}
                                                                            <div className="min-w-0 flex-1">
                                                                                <div className="flex items-center gap-2">
                                                                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                                                        {normalizarTexto(documentoPrincipal.tipo)}
                                                                                    </p>
                                                                                    {tieneMultiples && (
                                                                                        <Badge variant="outline" className="text-xs">
                                                                                            {docs.length} archivos
                                                                                        </Badge>
                                                                                    )}
                                                                                </div>
                                                                                {documentoPrincipal.carrera && (
                                                                                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                                                        {documentoPrincipal.carrera}
                                                                                    </p>
                                                                                )}
                                                                                {documentoPrincipal.observaciones && (
                                                                                    <div className="mt-1 text-xs text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 px-2 py-1 rounded">
                                                                                        <span className="font-medium">Observaciones:</span> {documentoPrincipal.observaciones}
                                                                                    </div>
                                                                                )}
                                                                                {documentoPrincipal.observaciones_archivo && (
                                                                                    <div className="mt-1 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded">
                                                                                        <span className="font-medium">Obs. Archivo:</span> {documentoPrincipal.observaciones_archivo}
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-6 py-4">
                                                                        {isRole20 ? (
                                                                            <Select
                                                                                value={editingDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id]?.estado || documentoPrincipal.estado}
                                                                                onValueChange={(value) => handleEstadoChange(documentoPrincipal.sdi_id || documentoPrincipal.id, value, documentoPrincipal.fecha_vencimiento || undefined)}
                                                                            >
                                                                                <SelectTrigger className="w-[140px]">
                                                                                    <SelectValue />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    <SelectItem value="aprobado">Aprobado</SelectItem>
                                                                                    <SelectItem value="pendiente">Pendiente</SelectItem>
                                                                                    <SelectItem value="vencido">Vencido</SelectItem>
                                                                                    <SelectItem value="rechazado">Rechazado</SelectItem>
                                                                                </SelectContent>
                                                                            </Select>
                                                                        ) : (
                                                                            getEstadoBadge(documentoPrincipal.estado)
                                                                        )}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                                        <div className="flex flex-col space-y-1">
                                                                            {isRole20 ? (
                                                                                <Input
                                                                                    type="date"
                                                                                    value={editingDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id]?.fecha_vencimiento || documentoPrincipal.fecha_vencimiento || ''}
                                                                                    onChange={(e) => handleFechaVencimientoChange(documentoPrincipal.sdi_id || documentoPrincipal.id, e.target.value)}
                                                                                    className="w-[160px]"
                                                                                />
                                                                            ) : (
                                                                                documentoPrincipal.fecha_vencimiento || 'Sin vencimiento'
                                                                            )}
                                                                            {documentErrors[documentoPrincipal.sdi_id || documentoPrincipal.id] && (
                                                                                <div className="flex items-center space-x-1 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded animate-in fade-in duration-200">
                                                                                    <AlertTriangle className="h-3 w-3" />
                                                                                    <span>{documentErrors[documentoPrincipal.sdi_id || documentoPrincipal.id]}</span>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                                        {documentoPrincipal.usuario || '-'}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                                        {documentoPrincipal.actualizado_por || '-'}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-right">
                                                                        <div className="flex justify-end items-center space-x-2">
                                                                            {isRole20 && hasChanges(documentoPrincipal.sdi_id || documentoPrincipal.id) && !successDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id] && (
                                                                                <Button
                                                                                    variant="default"
                                                                                    size="sm"
                                                                                    onClick={() => handleSaveChanges(documentoPrincipal.sdi_id || documentoPrincipal.id)}
                                                                                    disabled={savingDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id]}
                                                                                    className="h-8 px-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white shadow-md hover:shadow-lg transition-all duration-200"
                                                                                    title="Guardar cambios"
                                                                                >
                                                                                    {savingDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id] ? (
                                                                                        <>
                                                                                            <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                                                                                            <span className="text-xs">Guardando...</span>
                                                                                        </>
                                                                                    ) : (
                                                                                        <>
                                                                                            <Save className="h-4 w-4 mr-1" />
                                                                                            <span className="text-xs font-medium">Guardar</span>
                                                                                        </>
                                                                                    )}
                                                                                </Button>
                                                                            )}
                                                                            {successDocuments[documentoPrincipal.sdi_id || documentoPrincipal.id] && (
                                                                                <div className="flex items-center space-x-1 text-green-600 dark:text-green-400 animate-in fade-in duration-300">
                                                                                    <CheckCircle className="h-5 w-5" />
                                                                                    <span className="text-sm font-medium">Guardado</span>
                                                                                </div>
                                                                            )}
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                onClick={() => documentoPrincipal.url_ver ? handleView(documentoPrincipal) : null}
                                                                                disabled={!documentoPrincipal.url_ver}
                                                                                className={`h-8 w-8 p-0 ${!documentoPrincipal.url_ver ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100 hover:text-blue-700'}`}
                                                                                title="Ver documento"
                                                                            >
                                                                                <Eye className="h-4 w-4" />
                                                                            </Button>

                                                                            <Button
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                onClick={() => documentoPrincipal.url_descarga ? handleDownload(documentoPrincipal) : null}
                                                                                disabled={!documentoPrincipal.puede_descargar || !documentoPrincipal.url_descarga}
                                                                                className={`h-8 w-8 p-0 ${(!documentoPrincipal.puede_descargar || !documentoPrincipal.url_descarga) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-100 hover:text-green-700'}`}
                                                                                title="Descargar documento"
                                                                            >
                                                                                <Download className="h-4 w-4" />
                                                                            </Button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            );

                                                            // Filas expandidas (archivos adicionales)
                                                            if (isExpanded && tieneMultiples) {
                                                                docs.slice(1).forEach((doc, idx) => {
                                                                    filas.push(
                                                                        <tr key={`${expandKey}-${idx}`} className="bg-gray-50 dark:bg-gray-800/50">
                                                                            <td className="px-6 py-3 pl-16">
                                                                                <div className="flex flex-col space-y-1">
                                                                                    <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                                                                                        <FileText className="h-3 w-3" />
                                                                                        <span className="text-xs">Archivo {idx + 2}</span>
                                                                                        {doc.carrera && <span className="text-xs">• {doc.carrera}</span>}
                                                                                    </div>
                                                                                    {doc.observaciones && (
                                                                                        <div className="text-xs text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 px-2 py-1 rounded">
                                                                                            <span className="font-medium">Observaciones:</span> {doc.observaciones}
                                                                                        </div>
                                                                                    )}
                                                                                    {doc.observaciones_archivo && (
                                                                                        <div className="text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded">
                                                                                            <span className="font-medium">Observaciones Archivo:</span> {doc.observaciones_archivo}
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                            </td>
                                                                            <td className="px-6 py-3">
                                                                                {getEstadoBadge(doc.estado)}
                                                                            </td>
                                                                            <td className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                                                {doc.fecha_vencimiento || '-'}
                                                                            </td>
                                                                            <td className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                                                {doc.usuario || '-'}
                                                                            </td>
                                                                            <td className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                                                {doc.actualizado_por || '-'}
                                                                            </td>
                                                                            <td className="px-6 py-3 text-right">
                                                                                <div className="flex justify-end items-center space-x-2">
                                                                                    <Button
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={() => doc.url_ver ? handleView(doc) : null}
                                                                                        disabled={!doc.url_ver}
                                                                                        className="h-7 w-7 p-0"
                                                                                    >
                                                                                        <Eye className="h-3 w-3" />
                                                                                    </Button>
                                                                                    <Button
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={() => doc.url_descarga ? handleDownload(doc) : null}
                                                                                        disabled={!doc.puede_descargar || !doc.url_descarga}
                                                                                        className="h-7 w-7 p-0"
                                                                                    >
                                                                                        <Download className="h-3 w-3" />
                                                                                    </Button>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    );
                                                                });
                                                            }
                                                        });

                                                        return filas;
                                                    })()}
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
                                            <div key={documento.sdi_id || `doc-${documento.id}`} className="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
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
