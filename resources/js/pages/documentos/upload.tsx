import React, { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfFetch } from '@/lib/csrf';
import {
    Upload,
    FileText,
    Calendar,
    CheckCircle,
    AlertCircle,
    Clock,
    X,
    Plus,
    Download,
    Eye,
    Trash2,
    Search,
    Filter,
    Brain,
    Loader2
} from 'lucide-react';

interface Campus {
    ID_Campus: string;
    Campus: string;
    Activo: boolean;
}

interface CarreraMedica {
    ID_Especialidad: number;
    Descripcion: string;
    TipoUniv: number;
    DescripcionPlan: string;
}

interface DocumentoRequerido {
    id: string;
    concepto: string;
    descripcion: string;
    fechaLimite: string;
    estado: 'pendiente' | 'subido' | 'vigente' | 'caducado' | 'rechazado';
    archivos?: ArchivoSubido[];
    obligatorio: boolean;
    categoria?: string;
    carreraId?: string; // ID de la carrera médica (ID_Especialidad) - nvarchar en BD
    carreraNombre?: string; // Nombre de la carrera médica
    documentoOriginalId?: string; // ID original del documento en BD
    uniqueKey?: string; // Key único para React cuando hay múltiples instancias
}

interface ArchivoSubido {
    id: string;
    file_hash_sha256?: string;
    nombre: string;
    tamaño: number | null | undefined;
    fechaSubida: string;
    estado: 'subiendo' | 'procesando' | 'analizando' | 'completado' | 'error' | 'rechazado';
    progreso: number;
    mensaje?: string; // Para mostrar mensajes específicos
    validacionIA?: {
        coincide: boolean;
        porcentaje?: number;
        razon?: string;
        accion?: string;
    };
    // Información extraída del documento por IA (puede ser imprecisa)
    fechaExpedicion?: string;
    vigenciaDocumento?: string;
    diasRestantesVigencia?: number;
    // Información de la base de datos (fuente de verdad)
    metadata?: {
        folio_documento?: string;
        fecha_expedicion?: string;
        vigencia_documento?: string;
        lugar_expedicion?: string;
        // 🔧 Estado real de la tabla de BD (no del JSON)
        estado_bd?: string;
    };
}

// Datos de ejemplo para Campus
const documentosRequeridos: DocumentoRequerido[] = [
    {
        id: '1',
        concepto: 'Cédula de Identificación Fiscal',
        descripcion: 'RFC actualizado del campus con homoclave',
        fechaLimite: '2024-12-15',
        estado: 'subido',
        obligatorio: true,
        archivos: [
            {
                id: '1',
                nombre: 'rfc_campus_2024.pdf',
                tamaño: 1456789,
                fechaSubida: '2024-10-15',
                estado: 'completado',
                progreso: 100
            }
        ]
    },
    {
        id: '2',
        concepto: 'Comprobante de Domicilio',
        descripcion: 'Comprobante de domicilio fiscal no mayor a 3 meses',
        fechaLimite: '2024-11-30',
        estado: 'pendiente',
        obligatorio: true,
        archivos: []
    },
    {
        id: '3',
        concepto: 'Estados Financieros',
        descripcion: 'Balance general y estado de resultados del ejercicio fiscal anterior',
        fechaLimite: '2024-12-31',
        estado: 'pendiente',
        obligatorio: true,
        archivos: []
    },
    {
        id: '4',
        concepto: 'Constancia de Situación Fiscal',
        descripcion: 'Constancia actualizada del SAT con régimen fiscal vigente',
        fechaLimite: '2024-11-20',
        estado: 'pendiente',
        obligatorio: true,
        archivos: []
    },
    {
        id: '5',
        concepto: 'Opinión de Cumplimiento',
        descripcion: 'Opinión positiva de cumplimiento de obligaciones fiscales',
        fechaLimite: '2024-12-10',
        estado: 'vigente',
        obligatorio: false,
        archivos: [
            {
                id: '2',
                nombre: 'opinion_cumplimiento_2024.pdf',
                tamaño: 987654,
                fechaSubida: '2024-10-10',
                estado: 'completado',
                progreso: 100
            }
        ]
    },
    {
        id: '6',
        concepto: 'Declaraciones Anuales',
        descripcion: 'Declaraciones anuales de los últimos 3 ejercicios fiscales',
        fechaLimite: '2024-12-20',
        estado: 'pendiente',
        obligatorio: true,
        archivos: []
    }
];

const getEstadoColor = (estado: string) => {
    switch (estado) {
        case 'pendiente':
            return 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800';
        case 'subido':
            return 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 border-blue-200 dark:border-blue-800';
        case 'vigente':
            return 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 border-green-200 dark:border-green-800';
        case 'caducado':
            return 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 border-orange-200 dark:border-orange-800';
        case 'rechazado':
            return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 border-red-200 dark:border-red-800';
        default:
            return 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    }
};

const getEstadoIcon = (estado: string) => {
    switch (estado) {
        case 'pendiente':
            return <Clock className="w-4 h-4" />;
        case 'subido':
            return <Upload className="w-4 h-4" />;
        case 'vigente':
            return <CheckCircle className="w-4 h-4" />;
        case 'caducado':
            return <Calendar className="w-4 h-4" />;
        case 'rechazado':
            return <AlertCircle className="w-4 h-4" />;
        case 'subiendo':
            return <Upload className="w-4 h-4 text-blue-600 dark:text-blue-400 animate-pulse" />;
        case 'procesando':
            return <Loader2 className="w-4 h-4 text-yellow-600 dark:text-yellow-400 animate-spin" />;
        case 'analizando':
            return <Brain className="w-4 h-4 text-purple-600 dark:text-purple-400 animate-pulse" />;
        case 'completado':
            return <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />;
        case 'error':
            return <AlertCircle className="w-4 h-4 text-red-600 dark:text-red-400" />;
        default:
            return <FileText className="w-4 h-4" />;
    }
};

const formatFileSize = (bytes: number | undefined | null) => {
    if (!bytes || bytes === 0 || isNaN(bytes)) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString: string | undefined | null) => {
    if (!dateString) return 'Fecha no disponible';
    try {
        // Manejar diferentes formatos de fecha que pueden venir del backend
        let date: Date;

        // Si ya es una fecha ISO (YYYY-MM-DD) o completa (YYYY-MM-DDTHH:mm:ss)
        if (dateString.includes('-')) {
            // Para fechas YYYY-MM-DD, crear la fecha de manera más explícita para evitar problemas de zona horaria
            if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const [year, month, day] = dateString.split('-');
                date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
            } else {
                date = new Date(dateString);
            }
        }
        // Si es un timestamp
        else if (!isNaN(Number(dateString))) {
            date = new Date(Number(dateString));
        }
        // Otros formatos
        else {
            date = new Date(dateString);
        }

        if (isNaN(date.getTime())) return 'Fecha inválida';

        return date.toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (error) {
        console.error('Error formateando fecha:', dateString, error);
        return 'Fecha inválida';
    }
};

// Nueva función para mostrar información de vigencia del documento
const formatDocumentInfo = (archivo: ArchivoSubido) => {
    const expedicion = archivo.fechaExpedicion;
    const vigencia = archivo.vigenciaDocumento;
    const diasRestantes = archivo.diasRestantesVigencia;

    // Si no hay información del documento, mostrar fecha de subida
    if (!expedicion && !vigencia) {
        return `${formatFileSize(archivo.tamaño || 0)} • ${formatDate(archivo.fechaSubida)}`;
    }

    let info = formatFileSize(archivo.tamaño || 0);

    // Mostrar la fecha de expedición como fecha principal
    if (expedicion) {
        info += ` • ${formatDate(expedicion)}`;
    }

    return info;
};

// Nueva función para obtener información de vigencia para el indicador
const getVigenciaInfo = (archivo: ArchivoSubido) => {
    const vigencia = archivo.vigenciaDocumento;
    const diasRestantes = archivo.diasRestantesVigencia;

    if (!vigencia || vigencia === '2099-12-31') {
        return {
            texto: 'Sin vencimiento',
            estado: 'sin_vencimiento'
        };
    }

    if (diasRestantes !== null && diasRestantes !== undefined) {
        if (diasRestantes > 30) {
            return {
                texto: `${formatDate(vigencia)} (${diasRestantes}d)`,
                estado: 'vigente'
            };
        } else if (diasRestantes > 0) {
            return {
                texto: `${formatDate(vigencia)} (${diasRestantes}d)`,
                estado: 'por_vencer'
            };
        } else if (diasRestantes === 0) {
            return {
                texto: `${formatDate(vigencia)} (vence hoy)`,
                estado: 'vence_hoy'
            };
        } else {
            return {
                texto: `${formatDate(vigencia)} (vencido ${Math.abs(diasRestantes)}d)`,
                estado: 'vencido'
            };
        }
    }

    // Si no hay días restantes pero sí vigencia
    return {
        texto: formatDate(vigencia),
        estado: 'vigente'
    };
};

// Función para obtener la fecha correcta a mostrar en el panel
const getFechaVigenciaDocumento = (documento: DocumentoRequerido) => {
    // Debug temporal: Ver exactamente qué datos tenemos
    // console.log('🔍 Documento completo:', {
    //     concepto: documento.concepto,
    //     estado: documento.estado,
    //     archivos: documento.archivos
    // });

    // if (documento.archivos && documento.archivos.length > 0) {
    //     documento.archivos.forEach((archivo, index) => {
    //         console.log(`📄 Archivo ${index + 1}:`, {
    //             nombre: archivo.nombre,
    //             estado: archivo.estado,
    //             fechaExpedicion: archivo.fechaExpedicion,
    //             vigenciaDocumento: archivo.vigenciaDocumento,
    //             diasRestantesVigencia: archivo.diasRestantesVigencia,
    //             validacionIA: archivo.validacionIA
    //         });
    //     });
    // }

    // Buscar archivo aprobado que tenga información de vigencia
    const archivoAprobado = documento.archivos?.find(archivo =>
        archivo.estado === 'completado' &&
        (archivo.metadata?.vigencia_documento || archivo.vigenciaDocumento)
    );

    if (archivoAprobado) {
        // 🎯 PRIORIDAD: Usar vigencia de la BD (metadata) sobre la del JSON de IA
        const fechaVigenciaStr = archivoAprobado.metadata?.vigencia_documento || archivoAprobado.vigenciaDocumento;

        if (fechaVigenciaStr) {
            // Calcular días restantes en tiempo real
            const fechaVigencia = new Date(fechaVigenciaStr);
            const hoy = new Date();
            const diffTime = fechaVigencia.getTime() - hoy.getTime();
            const diasRestantes = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            // const fuente = archivoAprobado.metadata?.vigencia_documento ? 'BD (metadata)' : 'IA (JSON)';
            // console.log(`✅ Usando vigencia desde ${fuente}:`, fechaVigenciaStr, 'Días calculados:', diasRestantes);

            return {
                fecha: fechaVigenciaStr,
                diasRestantes: diasRestantes,
                esVigenciaReal: true
            };
        }
    }

    // console.log('⚠️ No hay archivos con vigencia IA, usando fechaLimite:', documento.fechaLimite);

    // Si no hay vigencia IA, usar fechaLimite como fallback
    return {
        fecha: documento.fechaLimite,
        diasRestantes: null,
        esVigenciaReal: false
    };
}; interface DocumentUploadPageProps {
    campusDelDirector: Campus[];
    documentosRequeridos: DocumentoRequerido[];
    campusSeleccionado: Campus | null;
    error?: string;
}

const DocumentUploadPage: React.FC<DocumentUploadPageProps> = ({
    campusDelDirector,
    documentosRequeridos: documentosIniciales,
    campusSeleccionado: campusInicial,
    error
}) => {
    // 🐛 DEBUG - Log inicial de props
    // console.log('=== PROPS RECIBIDAS AL CARGAR COMPONENTE ===');
    // console.log('campusDelDirector:', campusDelDirector?.length || 0, campusDelDirector);
    // console.log('documentosIniciales:', documentosIniciales?.length || 0, documentosIniciales);
    // console.log('campusInicial:', campusInicial);
    // console.log('error:', error);

    // 🔍 DEBUG DETALLADO - Revisar contenido de archivos en documentos iniciales
    // if (documentosIniciales && documentosIniciales.length > 0) {
    //     console.log('=== DETALLE DE DOCUMENTOS INICIALES ===');
    //     documentosIniciales.forEach((doc, index) => {
    //         console.log(`Documento ${index + 1}:`, {
    //             id: doc.id,
    //             concepto: doc.concepto,
    //             estado: doc.estado,
    //             archivos: doc.archivos,
    //             cantidad_archivos: doc.archivos?.length || 0
    //         });

    //         if (doc.archivos && doc.archivos.length > 0) {
    //             console.log(`  Archivos del documento "${doc.concepto}":`);
    //             doc.archivos.forEach((archivo, archivoIndex) => {
    //                 console.log(`    Archivo ${archivoIndex + 1}:`, {
    //                     id: archivo.id,
    //                     nombre: archivo.nombre,
    //                     estado: archivo.estado,
    //                     tamaño: archivo.tamaño,
    //                     fechaSubida: archivo.fechaSubida
    //                 });
    //             });
    //         } else {
    //             console.log(`  Documento "${doc.concepto}" no tiene archivos`);
    //         }
    //     });
    // }

    const [campusActual, setCampusActual] = useState<Campus | null>(campusInicial);

    // Asegurar que cada documento tenga el campo archivos
    const documentosConArchivos = (documentosIniciales || []).map(doc => ({
        ...doc,
        archivos: doc.archivos || []
    }));

    // 🔍 DEBUG - Verificar procesamiento de documentosConArchivos
    // console.log('=== PROCESAMIENTO DOCUMENTOS CON ARCHIVOS ===');
    // console.log('documentosConArchivos procesados:', documentosConArchivos.length);
    // documentosConArchivos.forEach((doc, index) => {
    //     console.log(`DocConArchivos ${index + 1}: "${doc.concepto}" - ${doc.archivos?.length || 0} archivos`);
    // });

    const [documentos, setDocumentos] = useState<DocumentoRequerido[]>(documentosConArchivos);
    const [dragOver, setDragOver] = useState<string | null>(null);
    const [selectedFiles, setSelectedFiles] = useState<{ [key: string]: File[] }>({});
    const [selectedDocumento, setSelectedDocumento] = useState<DocumentoRequerido | null>(documentosConArchivos[0] || null);
    const [filtroEstado, setFiltroEstado] = useState<string>('todos');
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [busqueda, setBusqueda] = useState<string>('');

    // Estados para el loader
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadMessage, setUploadMessage] = useState('');
    const [currentFileName, setCurrentFileName] = useState('');
    const [uploadSuccess, setUploadSuccess] = useState(false);
    const [successCount, setSuccessCount] = useState(0);
    const [totalFiles, setTotalFiles] = useState(0);
    const [recentlyUploadedFiles, setRecentlyUploadedFiles] = useState<Set<string>>(new Set());
    const [showSuccessNotification, setShowSuccessNotification] = useState(false);
    const [notificationMessage, setNotificationMessage] = useState('');

    // Estado para carga unificada de documentos
    const [cargandoDocumentosCompleto, setCargandoDocumentosCompleto] = useState(false);
    const [documentosListosParaMostrar, setDocumentosListosParaMostrar] = useState(false);

    // Estados para modal de rechazo
    const [showRejectionModal, setShowRejectionModal] = useState(false);
    const [rejectionData, setRejectionData] = useState<{
        fileName: string;
        documentType: string;
        reason: string;
        percentage: number;
        expectedType: string;
        detectedType: string;
        // Información de validación de ciudad
        cityValidation?: {
            campusCity: string;
            documentCities: string[];
            isValid: boolean;
            details?: string;
        };
    } | null>(null);

    // 🔥 FIX: Sincronizar estado cuando cambien las props de Inertia
    useEffect(() => {
        console.log('🔄 Sincronizando estado con props de Inertia:', {
            documentosIniciales: documentosIniciales?.length,
            campusInicial: campusInicial?.ID_Campus
        });

        if (documentosIniciales && documentosIniciales.length > 0) {
            const documentosActualizados = documentosIniciales.map(doc => ({
                ...doc,
                archivos: doc.archivos || []
            }));
            setDocumentos(documentosActualizados);

            // Si no hay documento seleccionado, seleccionar el primero
            if (!selectedDocumento && documentosActualizados.length > 0) {
                setSelectedDocumento(documentosActualizados[0]);
            }
        }

        if (campusInicial) {
            setCampusActual(campusInicial);
        }
    }, [documentosIniciales, campusInicial]);

    // Helper para obtener la clave correcta para selectedFiles
    const getDocumentKey = (documento: DocumentoRequerido) => {
        // Para documentos médicos con uniqueKey, usar uniqueKey
        // Para documentos legales, usar id normal
        return documento.uniqueKey || documento.id;
    };

    // Función para asegurar que archivos siempre sea un array
    const getArchivosSeguro = (documento: DocumentoRequerido): ArchivoSubido[] => {
        return documento.archivos || [];
    };

    // Función para verificar si se puede subir más archivos
    const puedeSubirArchivos = (documento: DocumentoRequerido): boolean => {
        const archivos = getArchivosSeguro(documento);

        // Si no hay archivos, se puede subir
        if (archivos.length === 0) return true;

        // Si el documento está en estado 'vigente' (aprobado por administrador), NO se puede subir más
        // EXCEPTO si está dinámicamente caducado, entonces SÍ se puede subir para renovarlo
        // 🔧 USAR ESTADO REAL DE LA BD
        const estadoReal = getEstadoRealDocumento(documento);
        const estadoNormalizado = normalizeEstado(estadoReal);
        if (estadoNormalizado === 'vigente' && !isDocumentoCaducado(documento)) return false;

        // Si el documento está caducado (por estado o dinámicamente), SIEMPRE se puede subir
        if (estadoNormalizado === 'caducado' || isDocumentoCaducado(documento)) return true;

        // Verificar si hay algún archivo completado exitosamente (aprobado por IA)
        const archivoAprobado = archivos.find(archivo =>
            archivo.estado === 'completado' &&
            (!archivo.validacionIA || archivo.validacionIA.coincide)
        );

        // Si hay un archivo aprobado, NO se puede subir más (a menos que sea rechazado por admin)
        if (archivoAprobado && estadoReal !== 'rechazado') return false;

        // En cualquier otro caso (archivos rechazados, errores, o documento rechazado), se puede subir
        return true;
    };

    // 🔄 FUNCIÓN PARA ACTUALIZAR DATOS DESDE EL SERVIDOR
    const refrescarDocumentosDesdeServidor = async () => {
        if (!campusActual) {
            // console.log('No hay campus actual, no se puede refrescar');
            return;
        }

        try {
            // console.log('=== REFRESCANDO DOCUMENTOS DESDE SERVIDOR ===');
            // console.log('Campus actual:', campusActual.ID_Campus, campusActual.Campus);
            // console.log('Tipo de documento seleccionado:', tipoDocumento);
            // console.log('¿Campus tiene carreras médicas?', campusTieneCarrerasMedicas);

            // Si es tipo médico, necesitamos cargar estados por carrera
            if (tipoDocumento === 'medicos') {
                // console.log('🔄 EJECUTANDO refrescarDocumentosMedicos()');
                await refrescarDocumentosMedicos();
            } else {
                // console.log('🔄 EJECUTANDO refrescarDocumentosLegales()');
                await refrescarDocumentosLegales();
            }
        } catch (error) {
            console.error('Error al refrescar documentos:', error);
            // Resetear estados de carga en caso de error
            setCargandoDocumentosCompleto(false);
            setDocumentosListosParaMostrar(true);
            setCargandoDocumentosMedicos(false);
        }
    };

    const refrescarDocumentosLegales = async () => {
        if (!campusActual) {
            // console.log('No hay campus actual para documentos legales');
            return;
        }

        try {
            // Mostrar indicador de carga unificada
            setCargandoDocumentosCompleto(true);
            setDocumentosListosParaMostrar(false);

            const response = await fetch(`/documentos/upload?campus=${campusActual.ID_Campus}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (response.ok) {
                const data = await response.json();
                // console.log('Respuesta del servidor (legales):', data);

                if (data.props?.documentosRequeridos) {
                    // console.log('Documentos legales recibidos:', data.props.documentosRequeridos.length);

                    const documentosActualizados = (data.props.documentosRequeridos || []).map((doc: any) => ({
                        ...doc,
                        archivos: doc.archivos || []
                    }));

                    setDocumentos(documentosActualizados);
                    // console.log('Documentos legales actualizados');

                    // Actualizar documento seleccionado si corresponde
                    if (selectedDocumento) {
                        const docActualizado = documentosActualizados.find((d: any) => d.id === selectedDocumento.id);
                        if (docActualizado) {
                            setSelectedDocumento(docActualizado);
                            // console.log('Documento seleccionado actualizado');
                        }
                    } else if (documentosActualizados.length > 0) {
                        // Si no hay documento seleccionado, seleccionar el primero
                        setSelectedDocumento(documentosActualizados[0]);
                        // console.log('Primer documento seleccionado por defecto');
                    }

                    // Marcar como listo para mostrar inmediatamente
                    setDocumentosListosParaMostrar(true);
                    setCargandoDocumentosCompleto(false);
                } else {
                    // console.log('No se recibieron documentos legales en la respuesta');
                    setCargandoDocumentosCompleto(false);
                    setDocumentosListosParaMostrar(true);
                }
            } else {
                console.error('Error al actualizar documentos legales:', response.status, response.statusText);
                setCargandoDocumentosCompleto(false);
                setDocumentosListosParaMostrar(true);
            }
        } catch (error) {
            console.error('Error en refrescarDocumentosLegales:', error);
            setCargandoDocumentosCompleto(false);
            setDocumentosListosParaMostrar(true);
        }
    };

    const refrescarDocumentosMedicos = async () => {
        if (!campusActual) {
            // console.log('❌ No hay campus actual para documentos médicos');
            return;
        }

        // console.log('=== REFRESCANDO DOCUMENTOS MÉDICOS CON STORED PROCEDURE ===');
        // console.log('Campus:', campusActual.ID_Campus, campusActual.Campus);
        // console.log('URL que se va a llamar:', `/documentos/medicos-con-sp?campus_id=${campusActual.ID_Campus}`);

        // Mostrar indicador de carga unificada
        setCargandoDocumentosCompleto(true);
        setDocumentosListosParaMostrar(false);
        setCargandoDocumentosMedicos(true);

        try {
            // 🚀 NUEVA CONSULTA CON STORED PROCEDURE: Una sola petición usando SP optimizado
            const tiempoInicio = Date.now();
            // console.log('🔄 Realizando consulta con stored procedure...');

            const response = await fetch(`/documentos/medicos-con-sp?campus_id=${campusActual.ID_Campus}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (!response.ok) {
                throw new Error(`Error en la respuesta: ${response.status}`);
            }

            const data = await response.json();
            const tiempoTotal = Date.now() - tiempoInicio;
            // console.log(`⚡ Consulta con stored procedure completada en ${tiempoTotal}ms`);
            // console.log('Respuesta del stored procedure:', data);

            // Debug detallado de los datos recibidos
            // console.log('=== DEBUG DATOS DEL SP ===');
            // console.log('Carreras médicas recibidas:', data.carreras_medicas?.length || 0);
            // console.log('Documentos agrupados:', data.documentos_agrupados?.length || 0);
            // console.log('Documentos detalle keys:', Object.keys(data.documentos_detalle || {}));
            // console.log('Documentos agrupados completo:', data.documentos_agrupados);
            // console.log('Documentos detalle completo:', data.documentos_detalle);

            if (!data.success || !data.carreras_medicas) {
                // console.log('No se recibieron datos válidos del stored procedure');
                setDocumentosMedicosConEstado([]);
                return;
            }

            // 🔄 PROCESAR RESPUESTA DEL STORED PROCEDURE
            const carrerasRecibidas = data.carreras_medicas;
            const documentosAgrupados = data.documentos_agrupados || []; // Datos estadísticos del SP
            const documentosDetalle = data.documentos_detalle || {}; // Documentos específicos por carrera

            // console.log(`📋 Procesando ${carrerasRecibidas.length} carreras médicas desde stored procedure...`);

            // ✅ ACTUALIZAR CARRERAS MÉDICAS EN EL ESTADO
            setCarrerasMedicas(carrerasRecibidas);
            // console.log('🔄 Carreras médicas actualizadas en el estado:', carrerasRecibidas);

            // Buscar documentos base de "cartas de intención" en documentos actuales
            const cartasOriginales = documentos.filter(doc =>
                doc.concepto.toLowerCase().includes('cartas de intención de campo clínico')
            );

            if (cartasOriginales.length === 0) {
                // console.log('No se encontraron documentos base de cartas de intención');
                setDocumentosMedicosConEstado([]);
                return;
            }

            if (!carrerasRecibidas || carrerasRecibidas.length === 0) {
                // console.log('No se recibieron carreras médicas');
                setDocumentosMedicosConEstado([]);
                return;
            }

            // ✅ NUEVA LÓGICA: Usar SOLO los datos agrupados del SP para evitar duplicados
            const documentosConEstado: DocumentoRequerido[] = [];

            // En lugar de iterar carreras y crear documentos, usar directamente los datos agrupados
            if (documentosAgrupados && documentosAgrupados.length > 0) {
                // console.log('🎯 Usando documentos agrupados del SP directamente');

                documentosAgrupados.forEach((datosCarrera: any, index: number) => {
                    const carreraNombre = datosCarrera.carrera_nombre;

                    // 🔧 OBTENER carrera_id DIRECTAMENTE DEL SP (no buscar por nombre)
                    // El SP ya trae el carrera_id_rep correcto (ID_Especialidad mínimo del grupo)
                    let carreraId = null;

                    // Buscar en documentos_detalle para obtener el carrera_id real del SP
                    const documentosCarreraDetalle = documentosDetalle[carreraNombre] || [];
                    if (documentosCarreraDetalle.length > 0 && documentosCarreraDetalle[0].carrera_id) {
                        carreraId = documentosCarreraDetalle[0].carrera_id;
                        // console.log(`✅ carrera_id obtenido del SP detalle para "${carreraNombre}":`, carreraId);
                    } else {
                        // Fallback: buscar en carrerasRecibidas
                        const carreraInfo = carrerasRecibidas.find((c: any) => c.Descripcion === carreraNombre);
                        carreraId = carreraInfo?.ID_Especialidad;
                        // console.log(`⚠️ carrera_id obtenido de carrerasRecibidas para "${carreraNombre}":`, carreraId);
                    }

                    if (!carreraId) {
                        console.error(`❌ No se pudo obtener carrera_id para "${carreraNombre}". Saltando...`);
                        return; // Saltar esta carrera si no tiene ID
                    }                    // Usar el primer documento base como template
                    const docOriginal = cartasOriginales[0];
                    const uniqueKey = `${docOriginal.id}_carrera_${carreraId}`;

                    // Crear archivos basándose en los documentos del detalle
                    const documentosCarrera = documentosDetalle[carreraNombre] || [];
                    const archivosDoc: ArchivoSubido[] = documentosCarrera
                        .filter((doc: any) => doc.archivo_id) // Solo documentos con archivo_id real
                        .map((doc: any) => ({
                            id: doc.archivo_id.toString(),
                            nombre: doc.nombre_documento || doc.folio_documento || 'Documento médico',
                            tamaño: null,
                            fechaSubida: doc.subido_en || new Date().toISOString().split('T')[0],
                            estado: 'completado' as ArchivoSubido['estado'],
                            progreso: 100,
                            fechaExpedicion: doc.fecha_expedicion,
                            vigenciaDocumento: doc.vigencia_documento,
                            diasRestantesVigencia: doc.dias_restantes_vigencia,
                            metadata: {
                                folio_documento: doc.folio_documento,
                                fecha_expedicion: doc.fecha_expedicion,
                                vigencia_documento: doc.vigencia_documento,
                                estado_bd: doc.estado_final
                            }
                        }));

                    // Determinar estado basándose en las estadísticas del SP y archivos reales
                    let estadoDoc: DocumentoRequerido['estado'] = 'pendiente';

                    // Solo cambiar el estado si hay archivos reales o estadísticas válidas
                    if (archivosDoc.length > 0) {
                        // Si hay archivos reales, usar las estadísticas del SP
                        if (datosCarrera.Vigentes > 0) {
                            estadoDoc = 'vigente';
                        } else if (datosCarrera.Caducados > 0) {
                            estadoDoc = 'caducado';
                        } else if (datosCarrera.Rechazados > 0) {
                            estadoDoc = 'rechazado';
                        }
                    } else {
                        // Si no hay archivos reales, mantener como pendiente independientemente de las estadísticas
                        estadoDoc = 'pendiente';
                    }

                    const documentoGenerado: DocumentoRequerido = {
                        ...docOriginal,
                        id: docOriginal.id, // Mantener el ID original
                        documentoOriginalId: docOriginal.id, // Guardar referencia al ID original
                        uniqueKey: uniqueKey, // ID único para React
                        concepto: `${docOriginal.concepto} - ${carreraNombre}`,
                        descripcion: `${docOriginal.descripcion} para la carrera de ${carreraNombre}`,
                        carreraId: String(carreraId), // Agregar ID de carrera como string
                        carreraNombre: carreraNombre, // Agregar nombre de carrera
                        estado: estadoDoc,
                        archivos: archivosDoc
                    };

                    // console.log(`✅ Documento generado para ${carreraNombre} desde SP agrupado:`, {
                    //     uniqueKey: documentoGenerado.uniqueKey,
                    //     estado: documentoGenerado.estado,
                    //     totalArchivos: documentoGenerado.archivos?.length || 0,
                    //     estadisticas: {
                    //         vigentes: datosCarrera.Vigentes,
                    //         pendientes: datosCarrera.Pendientes,
                    //         caducados: datosCarrera.Caducados,
                    //         rechazados: datosCarrera.Rechazados
                    //     }
                    // });

                    documentosConEstado.push(documentoGenerado);
                });
            } else {
                // console.log('🔄 No hay datos agrupados del SP, generando documentos por carreras');

                // Fallback: generar documentos por carreras si no hay datos agrupados
                carrerasRecibidas.forEach((carrera: any) => {
                    const carreraId = carrera.ID_Especialidad;
                    const carreraNombre = carrera.Descripcion;

                    cartasOriginales.forEach(docOriginal => {
                        const uniqueKey = `${docOriginal.id}_carrera_${carreraId}`;

                        const documentoGenerado: DocumentoRequerido = {
                            ...docOriginal,
                            id: docOriginal.id,
                            documentoOriginalId: docOriginal.id,
                            uniqueKey: uniqueKey,
                            concepto: `${docOriginal.concepto} - ${carreraNombre}`,
                            descripcion: `${docOriginal.descripcion} para la carrera de ${carreraNombre}`,
                            carreraId: String(carreraId),
                            carreraNombre: carreraNombre,
                            estado: 'pendiente' as DocumentoRequerido['estado'],
                            archivos: []
                        };

                        documentosConEstado.push(documentoGenerado);
                    });
                });
            }

            // console.log(`📚 Total documentos médicos generados: ${documentosConEstado.length}`);

            // Actualizar el estado SEPARADO para documentos médicos
            setDocumentosMedicosConEstado(documentosConEstado);
            // console.log('📋 Documentos médicos actualizados en estado separado');

            // ✅ SINCRONIZAR DOCUMENTO SELECCIONADO CON DATOS MÉDICOS ACTUALIZADOS
            if (selectedDocumento && tipoDocumento === 'medicos') {
                // Si el documento seleccionado es médico, buscar la versión actualizada
                const docActualizadoMedico = documentosConEstado.find((d: any) =>
                    d.uniqueKey === selectedDocumento.uniqueKey ||
                    (d.carreraNombre === selectedDocumento.carreraNombre && d.documentoOriginalId === selectedDocumento.documentoOriginalId)
                );

                if (docActualizadoMedico) {
               //     console.log('🔄 Actualizando documento médico seleccionado con datos del SP:', docActualizadoMedico.concepto);
                    setSelectedDocumento(docActualizadoMedico);
                } else if (documentosConEstado.length > 0) {
                   // console.log('🔄 Seleccionando primer documento médico actualizado:', documentosConEstado[0].concepto);
                    setSelectedDocumento(documentosConEstado[0]);
                }
            } else if (!selectedDocumento && documentosConEstado.length > 0) {
                // Si no hay documento seleccionado, seleccionar el primero
                // console.log('🔄 Seleccionando primer documento médico por defecto:', documentosConEstado[0].concepto);
                setSelectedDocumento(documentosConEstado[0]);
            }

        } catch (error) {
            //console.error('Error en consulta optimizada de documentos médicos:', error);
            setDocumentosMedicosConEstado([]);
        } finally {
            // Marcar como completado inmediatamente
            setDocumentosListosParaMostrar(true);
            setCargandoDocumentosCompleto(false);
            setCargandoDocumentosMedicos(false);
        }
    };    // 🔄 AUTO-REFRESH DESPUÉS DE SUBIR ARCHIVOS
    const programarActualizacionAutomatica = () => {
        setTimeout(() => {
            // console.log('Actualizando panel automáticamente...');
            refrescarDocumentosDesdeServidor();
        }, 2000); // Esperar 2 segundos después de subir
    };

    // 🔄 AUTO-REFRESH POR CAMBIOS DE ESTADO - Deshabilitado temporalmente para evitar bucles
    // useEffect(() => {
    //     const documentosConArchivosCompletados = documentos.filter(doc =>
    //         getArchivosSeguro(doc).some(archivo =>
    //             archivo.estado === 'completado' && archivo.validacionIA?.coincide
    //         )
    //     );

    //     // Solo refrescar si hay documentos completados y no estamos en modo médicos
    //     if (documentosConArchivosCompletados.length > 0) {
    //         console.log('Detectados documentos completados, programando actualización...');
    //         const timer = setTimeout(() => {
    //             refrescarDocumentosDesdeServidor();
    //         }, 3000);

    //         return () => clearTimeout(timer);
    //     }
    // }, [documentos]);

    // 🚀 CARGA INICIAL - Asegurar que se carguen los documentos del campus inicial
    // 🔥 FIX: Agregar campusInicial como dependencia para que se ejecute cuando cambie la navegación
    useEffect(() => {
        console.log('🔄 useEffect de carga inicial ejecutándose con:', {
            campusInicial: campusInicial?.ID_Campus,
            documentosIniciales: documentosIniciales?.length
        });

        // Siempre empezar con indicador de carga en la primera renderización
        setCargandoDocumentosCompleto(true);
        setDocumentosListosParaMostrar(false);

        // Verificar si necesitamos refrescar desde el servidor
        const documentosVacios = documentosIniciales?.every(doc => !doc.archivos || doc.archivos.length === 0) || false;
        const noHayDocumentos = !documentosIniciales || documentosIniciales.length === 0;

        if (campusInicial && (noHayDocumentos || documentosVacios)) {
            if (noHayDocumentos) {
                // console.log('Campus inicial detectado SIN documentos, refrescando desde servidor...');
            } else {
                // console.log('Campus inicial detectado con documentos VACÍOS (sin archivos), refrescando desde servidor...');
            }

            // Crear función de refresco específica para carga inicial que no dependa de campusActual
            const refrescarInicial = async () => {
                try {

                    // Usar la misma ruta que handleCampusChange para asegurar compatibilidad
                    const response = await fetch('/documentos/cambiar-campus', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ campus_id: campusInicial.ID_Campus })
                    });

                    if (response.ok) {
                        const data = await response.json();
                     //   console.log('Respuesta del servidor (carga inicial):', data);

                        // La respuesta de cambiar-campus devuelve { campus, documentos } directamente
                        if (data.documentos) {
                          //  console.log('Documentos recibidos (carga inicial):', data.documentos.length);

                            const documentosActualizados = (data.documentos || []).map((doc: any) => ({
                                ...doc,
                                archivos: doc.archivos || []
                            }));

                            setDocumentos(documentosActualizados);
                            // console.log('Documentos estado actualizado (carga inicial)');

                            // Seleccionar primer documento si hay documentos
                            if (documentosActualizados.length > 0) {
                                setSelectedDocumento(documentosActualizados[0]);
                                // console.log('Primer documento seleccionado por defecto (carga inicial)');
                            }

                            // Marcar como completo inmediatamente
                            setDocumentosListosParaMostrar(true);
                            setCargandoDocumentosCompleto(false);
                        } else {
                            // console.log('No se recibieron documentos en la respuesta (carga inicial)');
                            setCargandoDocumentosCompleto(false);
                            setDocumentosListosParaMostrar(true);
                        }
                    } else {
                      //  console.error('Error al cargar documentos iniciales:', response.status, response.statusText);
                        setCargandoDocumentosCompleto(false);
                        setDocumentosListosParaMostrar(true);
                    }
                } catch (error) {
                   // console.error('Error al refrescar documentos (carga inicial):', error);
                    setCargandoDocumentosCompleto(false);
                    setDocumentosListosParaMostrar(true);
                }
            };

            // Ejecutar la carga inicial inmediatamente
            refrescarInicial();
        } else if (campusInicial && documentosIniciales && documentosIniciales.length > 0 && !documentosVacios) {
            // El estado documentos ya debe estar inicializado correctamente
            // Solo necesitamos asegurar que hay un documento seleccionado
            if (!selectedDocumento && documentos.length > 0) {

                setSelectedDocumento(documentos[0]);
            } else if (!selectedDocumento && documentosIniciales.length > 0) {
                // Fallback: usar documentos iniciales si el estado no está listo
                const primerDocumento = {
                    ...documentosIniciales[0],
                    archivos: documentosIniciales[0].archivos || []
                };
              //  console.log('Fallback: Seleccionando primer documento de documentos iniciales:', primerDocumento.concepto);
                setSelectedDocumento(primerDocumento);
            } else if (selectedDocumento) {
              //  console.log('Ya hay un documento seleccionado:', selectedDocumento.concepto);
            }

            // Marcar como listo inmediatamente para datos existentes
            setDocumentosListosParaMostrar(true);
            setCargandoDocumentosCompleto(false);
        } else {
           // console.log('No hay campus inicial, usando documentos proporcionados');
            // Si hay documentos iniciales, asegurar que el primer documento esté seleccionado
            if (documentosIniciales && documentosIniciales.length > 0) {
                const documentosActualizados = documentosIniciales.map(doc => ({
                    ...doc,
                    archivos: doc.archivos || []
                }));

              //  console.log('Actualizando estado con documentos proporcionados');
                setDocumentos(documentosActualizados);

                if (!selectedDocumento && documentosActualizados.length > 0) {
                 //   console.log('Seleccionando primer documento por defecto');
                    setSelectedDocumento(documentosActualizados[0]);
                }

                // Marcar como listo inmediatamente
                setDocumentosListosParaMostrar(true);
                setCargandoDocumentosCompleto(false);
            } else {
                // No hay documentos, marcar como listo de todos modos
                setCargandoDocumentosCompleto(false);
                setDocumentosListosParaMostrar(true);
            }
        }
    }, [campusInicial?.ID_Campus, documentosIniciales]); // 🔥 FIX: Agregar dependencias para que se ejecute en cada navegación

    // � ASEGURAR DOCUMENTO SELECCIONADO - Ejecutar cuando cambien los documentos
    useEffect(() => {
        // 🔍 DEBUG - Revisar archivos en el estado de documentos
        if (documentos.length > 0) {

            documentos.forEach((doc, index) => {
                const totalArchivos = doc.archivos?.length || 0;


                if (totalArchivos > 0) {
                    doc.archivos?.forEach((archivo, archivoIndex) => {

                    });
                }
            });
        }

        // Si hay documentos pero no hay documento seleccionado, seleccionar el primero
        if (documentos.length > 0 && !selectedDocumento) {
           // console.log('Auto-seleccionando primer documento:', documentos[0].concepto);
            setSelectedDocumento(documentos[0]);
        }
    }, [documentos, selectedDocumento]); // Ejecutar cuando cambien documentos o documento seleccionado

    // CARGAR ANALISIS - Cargar analisis de archivos completados
    useEffect(() => {
        const archivosCompletados = documentos.flatMap(doc =>
            (doc.archivos || []).filter(archivo =>
                archivo.estado === 'completado' &&
                !archivo.fechaExpedicion &&
                !archivo.vigenciaDocumento
            )
        );


        archivosCompletados.forEach(archivo => {

        });
    }, [documentos]);

    // 🔄 DETECTAR CAMBIOS EN FECHAS DE ARCHIVOS Y ACTUALIZAR selectedDocumento
    useEffect(() => {
        if (selectedDocumento) {
            // Buscar el documento actualizado en el estado documentos
            const docActualizado = documentos.find(d =>
                getDocumentKey(d) === getDocumentKey(selectedDocumento)
            );
            if (docActualizado) {
                // Verificar si hay diferencias en las fechas de los archivos
                const hayDiferencias = docActualizado.archivos?.some((archivoActualizado, index) => {
                    const archivoSeleccionado = selectedDocumento.archivos?.[index];
                    return archivoSeleccionado && (
                        archivoActualizado.fechaExpedicion !== archivoSeleccionado.fechaExpedicion ||
                        archivoActualizado.vigenciaDocumento !== archivoSeleccionado.vigenciaDocumento ||
                        archivoActualizado.diasRestantesVigencia !== archivoSeleccionado.diasRestantesVigencia
                    );
                });

                if (hayDiferencias) {

                    setSelectedDocumento(docActualizado);
                }
            }
        }
    }, [documentos]); // Se ejecuta cuando cambia documentos

    // SINCRONIZACIÓN - Refrescar cuando cambie el campus actual
    useEffect(() => {
        if (campusActual && campusActual.ID_Campus) {
            // Solo refrescar si no hay documentos o si hay muy pocos
            // (esto evita refrescos innecesarios cuando ya hay datos)
            if (documentos.length === 0) {

                refrescarDocumentosDesdeServidor();
            } else {
               //
            }
        }
    }, [campusActual]); // Ejecutar cuando cambie campusActual

    // Función para mostrar detalles del rechazo
    const mostrarDetallesRechazo = (archivo: ArchivoSubido, documentoConcepto: string) => {
        if (archivo.validacionIA && !archivo.validacionIA.coincide) {
            setRejectionData({
                fileName: archivo.nombre,
                documentType: documentoConcepto,
                reason: archivo.validacionIA.razon || 'Documento no válido',
                percentage: archivo.validacionIA.porcentaje || 0,
                expectedType: documentoConcepto,
                detectedType: 'Información de análisis IA'
            });
            setShowRejectionModal(true);
        }
    };

    // Función para obtener el mensaje del estado del documento
    const getMensajeEstado = (documento: DocumentoRequerido): string => {
        const archivos = getArchivosSeguro(documento);

        if (archivos.length === 0) {
            return "No hay archivos subidos para este documento";
        }

        // 🔧 USAR ESTADO REAL DE LA BD
        const estadoReal = getEstadoRealDocumento(documento);
        const estadoNormalizado = normalizeEstado(estadoReal);

        // Si el documento está rechazado por administrador, permitir nueva subida
        if (estadoNormalizado === 'rechazado') {
            return "El documento fue rechazado por un administrador. Puede subir un nuevo archivo.";
        }

        // Si el documento está caducado (por estado o dinámicamente), permitir nueva subida
        if (estadoNormalizado === 'caducado' || isDocumentoCaducado(documento)) {
            return "El documento ha caducado por vencimiento de fecha. Puede subir un nuevo archivo.";
        }

        // Si el documento está aprobado por administrador y NO está caducado, bloquear
        if (estadoNormalizado === 'vigente' && !isDocumentoCaducado(documento)) {
            return "Documento aprobado por administrador. No se pueden subir más archivos.";
        }

        const archivoAprobado = archivos.find(archivo =>
            archivo.estado === 'completado' &&
            (!archivo.validacionIA || archivo.validacionIA.coincide)
        );

        if (archivoAprobado) {
            return "Documento validado automáticamente por IA. Esperando revisión del administrador.";
        }

        const archivoRechazado = archivos.find(archivo =>
            archivo.estado === 'rechazado' ||
            (archivo.validacionIA && !archivo.validacionIA.coincide)
        );

        if (archivoRechazado) {
            return "El documento anterior fue rechazado por validación automática. Puede subir un nuevo archivo.";
        }

        return "Puede subir archivos para este documento";
    };

    const handleCampusChange = async (campusId: string) => {
        // Mostrar indicador de carga unificada
        setCargandoDocumentosCompleto(true);
        setDocumentosListosParaMostrar(false);

        try {
            const response = await csrfFetch('/documentos/cambiar-campus', {
                method: 'POST',
                body: JSON.stringify({ campus_id: campusId })
            });

            if (response.ok) {
                const data = await response.json();

                setCampusActual(data.campus);

                // Asegurar que cada documento tenga el campo archivos
                const documentosConArchivos = data.documentos.map((doc: any) => ({
                    ...doc,
                    archivos: doc.archivos || []
                }));


                setDocumentos(documentosConArchivos);
                setSelectedDocumento(documentosConArchivos[0] || null);

                // Limpiar archivos seleccionados al cambiar campus
                setSelectedFiles({});

                // Resetear selectores de documento
                setTipoDocumento('legales');

                // Verificar si el campus tiene carreras médicas
                await verificarCampusMedico(campusId);

                // Marcar como completado inmediatamente
                setDocumentosListosParaMostrar(true);
                setCargandoDocumentosCompleto(false);
            } else {

                setCargandoDocumentosCompleto(false);
                setDocumentosListosParaMostrar(true);
            }
        } catch (error) {

            setCargandoDocumentosCompleto(false);
            setDocumentosListosParaMostrar(true);
        }
    };

    // Función para verificar si un campus tiene carreras médicas
    const verificarCampusMedico = async (campusId: string) => {
        if (!campusId) return;


        setVerificandoCampusMedico(true);
        try {
            const response = await csrfFetch(`/documentos/campus/${campusId}/medico`);

            if (response.ok) {
                const data = await response.json();

                setCampusTieneCarrerasMedicas(data.tiene_carreras_medicas);
                setCarrerasMedicas(data.carreras_medicas || []);

                // Log detallado de cada carrera
                (data.carreras_medicas || []).forEach((carrera: CarreraMedica) => {

                });
            } else {

                setCampusTieneCarrerasMedicas(false);
                setCarrerasMedicas([]);
            }
        } catch (error) {

            setCampusTieneCarrerasMedicas(false);
            setCarrerasMedicas([]);
        } finally {
            setVerificandoCampusMedico(false);
        }
    };

    const handleDragOver = (e: React.DragEvent, documentoId: string) => {
        e.preventDefault();
        setDragOver(documentoId);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(null);
    };

    const handleDrop = (e: React.DragEvent, documentoId: string) => {
        e.preventDefault();
        setDragOver(null);

        const files = Array.from(e.dataTransfer.files);
        handleFileSelection(files, documentoId);
    };

    const handleFileSelection = (files: File[], documentoId: string) => {


        // VALIDAR QUE SOLO SEAN ARCHIVOS PDF
        const pdfFiles = files.filter(file => {
            const isPDF = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            if (!isPDF) {

                // Mostrar mensaje de error al usuario
                alert(`El archivo "${file.name}" no es un PDF válido. Solo se permiten archivos PDF.`);
            }
            return isPDF;
        });

        if (pdfFiles.length === 0) {

            return;
        }


        setSelectedFiles(prev => {
            const updated = {
                ...prev,
                [documentoId]: [...(prev[documentoId] || []), ...pdfFiles]
            };

            return updated;
        });
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>, documentoId: string) => {

        if (e.target.files) {
            const files = Array.from(e.target.files);
            handleFileSelection(files, documentoId);
        } else {

        }
    };

    const removeSelectedFile = (documentoId: string, fileIndex: number) => {
        setSelectedFiles(prev => ({
            ...prev,
            [documentoId]: prev[documentoId]?.filter((_, index) => index !== fileIndex) || []
        }));
    };

    const uploadFiles = async (documentoKey: string) => {

        documentosDelTipo.forEach((doc, index) => {
            console.log(`  Doc ${index}: key="${getDocumentKey(doc)}", uniqueKey="${doc.uniqueKey}", id="${doc.id}"`);
        });

        const files = selectedFiles[documentoKey];

        // Encontrar el documento para obtener información de carrera
        // Buscar por uniqueKey (documentos médicos) o por id (documentos legales)
        const documento = documentosDelTipo.find(doc => getDocumentKey(doc) === documentoKey);
        if (!documento) {

            alert('Error: Documento no encontrado');
            return;
        }


        if (!files || files.length === 0) {
            console.log('No hay archivos para subir');
            alert('No hay archivos seleccionados. Por favor selecciona un archivo primero.');
            return;
        }

        if (!campusActual?.ID_Campus) {
            console.error('No hay campus seleccionado');
            alert('No hay campus seleccionado');
            return;
        }

        // Validar que se haya seleccionado carrera para documentos médicos (ahora será automático)
        // Ya no necesitamos esta validación porque cada documento ya tiene su carrera asignada



        // Activar el loader
        setIsUploading(true);
        setUploadProgress(0);
        setUploadMessage('Preparando archivos...');
        setUploadSuccess(false);
        setSuccessCount(0);
        setTotalFiles(files.length);

        // Limpiar archivos seleccionados
        setSelectedFiles(prev => ({
            ...prev,
            [documentoKey]: []
        }));



        // Procesar cada archivo
        for (let i = 0; i < files.length; i++) {
            const file = files[i];

            // Actualizar loader para este archivo
            setCurrentFileName(file.name);
            setUploadMessage(`Subiendo ${file.name}...`);
            setUploadProgress(10);

            try {
                // Debug del archivo antes de enviar
                const formData = new FormData();
                formData.append('archivo', file);

                // Usar el ID original del documento para el backend, no el ID compuesto
                const documentoIdParaBackend = documento.documentoOriginalId || documento.id;
                formData.append('documento_id', documentoIdParaBackend.toString());
                formData.append('campus_id', campusActual.ID_Campus);

                // Si el documento tiene carreraId (documentos médicos), agregarlo como carrera_id (que es ID_Especialidad)
                if (documento.carreraId) {
                    const carreraIdStr = String(documento.carreraId); // Asegurar que sea string
                    formData.append('carrera_id', carreraIdStr);

                } else {
                   // No hacer nada, ya que no se requiere carrera_id
                }                // Debug del FormData

                for (let pair of formData.entries()) {

                }

                // Simular progreso visual en el loader
                await new Promise(resolve => setTimeout(resolve, 500));
                setUploadProgress(25);
                setUploadMessage(`Validando ${file.name}...`);

                await new Promise(resolve => setTimeout(resolve, 500));
                setUploadProgress(50);
                setUploadMessage(`Guardando ${file.name} en servidor...`);

                await new Promise(resolve => setTimeout(resolve, 500));
                setUploadProgress(75);
                setUploadMessage(`Finalizando subida de ${file.name}...`);


                // Obtener token CSRF manualmente
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                // Crear una nueva FormData para asegurar que está limpia
                const testFormData = new FormData();
                testFormData.append('archivo', file);
                testFormData.append('documento_id', documentoIdParaBackend.toString());
                testFormData.append('campus_id', campusActual.ID_Campus);

                // También agregar carrera_id si existe
                if (documento.carreraId) {
                    testFormData.append('carrera_id', documento.carreraId);
                }


                for (let pair of testFormData.entries()) {

                    if (pair[1] instanceof File) {

                    }
                }

                // Usar fetch nativo para descartar problemas con csrfFetch
                const response = await fetch('/documentos/subir-archivo', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: testFormData
                });


                // Obtener el texto crudo de la respuesta antes de parsearlo
                const responseText = await response.text();
                if (response.ok) {
                    try {
                        const data = JSON.parse(responseText);

                        // Verificar si realmente se subió el archivo
                        if (!data.archivo || !data.archivo.estado) {

                            setUploadMessage(`Error: El archivo no se procesó correctamente en el servidor`);
                            setIsUploading(false);
                            return;
                        }

                        setUploadProgress(100);
                        setUploadMessage(`${file.name} subido exitosamente`);
                        setSuccessCount(prev => prev + 1);

                        // Si hay análisis, mostrar en el loader
                        if (data.archivo.estado === 'analizando') {
                            setUploadMessage(`Analizando ${file.name} con IA...`);
                            setUploadProgress(100);

                            // Simular análisis
                            await new Promise(resolve => setTimeout(resolve, 2000));
                        }

                        // ACTUALIZAR INMEDIATAMENTE EL PANEL CON EL ARCHIVO SUBIDO
                        const nuevoArchivo: ArchivoSubido = {
                            id: data.archivo.id,
                            nombre: file.name,
                            tamaño: data.archivo.tamaño || file.size || 0,
                            fechaSubida: new Date().toISOString().split('T')[0],
                            estado: data.archivo.estado === 'analizando' ? 'analizando' : 'completado',
                            progreso: 100,
                            mensaje: data.archivo.estado === 'analizando' ? 'Analizando con IA...' : 'Completado'
                        };

                        // Actualizar el documento con el archivo INMEDIATAMENTE
                        setDocumentos(prev => prev.map(doc => {
                            if (getDocumentKey(doc) === documentoKey) {
                                return {
                                    ...doc,
                                    estado: 'subido' as DocumentoRequerido['estado'],
                                    archivos: [...getArchivosSeguro(doc), nuevoArchivo]
                                };
                            }
                            return doc;
                        }));

                        // Actualizar también el documento seleccionado si corresponde
                        if (selectedDocumento && getDocumentKey(selectedDocumento) === documentoKey) {
                            setSelectedDocumento(prev => {
                                if (prev) {
                                    return {
                                        ...prev,
                                        estado: 'subido' as DocumentoRequerido['estado'],
                                        archivos: [...getArchivosSeguro(prev), nuevoArchivo]
                                    };
                                }
                                return prev;
                            });
                        }

                        // NO mostrar notificación inmediata - esperar análisis IA
                        // La notificación se mostrará solo si el documento es aprobado por IA

                        // Marcar como recién subido para animación
                        setRecentlyUploadedFiles(prev => new Set(prev).add(data.archivo.id));

                        // Quitar el destacado después de unos segundos
                        setTimeout(() => {
                            setRecentlyUploadedFiles(prev => {
                                const newSet = new Set(prev);
                                newSet.delete(data.archivo.id);
                                return newSet;
                            });
                        }, 5000); // 5 segundos de animación
                        // Si hay análisis, monitorear (solo 1 intento para evitar bucles infinitos)
                        if (data.archivo.estado === 'analizando') {
                            monitorearAnalisis(data.archivo.id, documentoKey, 1);
                        }
                        programarActualizacionAutomatica();

                    } catch (parseError) {
                        setUploadMessage(`Error: El servidor devolvió una respuesta inválida`);
                        setIsUploading(false);
                        return;
                    }

                } else {
                    // También capturar respuesta cruda para errores

                    try {
                        const errorData = JSON.parse(responseText);

                        // Mostrar errores específicos de validación
                        if (errorData.validation_errors) {
                            //console.error('Errores de validación específicos:');
                            Object.keys(errorData.validation_errors).forEach(field => {
                                //console.error(`  - ${field}: ${errorData.validation_errors[field]}`);
                            });
                        }

                        if (errorData.debug_info) {
                        //console.error('Debug info:', errorData.debug_info);
                        }

                        setUploadMessage(`Error al subir ${file.name}: ${errorData.error || 'Error desconocido'}`);
                    } catch (parseError) {
                        //console.error('Error parseando respuesta de error:', parseError);
                        //console.error('Respuesta de error no es JSON válido:', responseText);
                        setUploadMessage(`Error al subir ${file.name}: Error del servidor (${response.status})`);
                    }

                    setUploadProgress(0);

                    // Esperar un momento para mostrar el error
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }

            } catch (error) {
               // console.error('Error en upload:', error);
                setUploadMessage(`Error de conexión con ${file.name}`);
                setUploadProgress(0);

                // Resetear estados de carga si hay error de conexión
                setCargandoDocumentosCompleto(false);
                setDocumentosListosParaMostrar(true);
                setCargandoDocumentosMedicos(false);

                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }

        // MOSTRAR ÉXITO AL FINALIZAR TODOS LOS ARCHIVOS
        setUploadSuccess(true);
        setUploadProgress(100);
        setUploadMessage(`Todos los archivos procesados exitosamente (${successCount + 1}/${files.length})`);
        setCurrentFileName('');

        // Esperar un momento para que el usuario vea el mensaje de éxito
        await new Promise(resolve => setTimeout(resolve, 3000));

        // Auto-cerrar el modal después del éxito
        setIsUploading(false);
        setUploadSuccess(false);
        setUploadProgress(0);
        setUploadMessage('');
        setCurrentFileName('');
        setSuccessCount(0);
        setTotalFiles(0);

       // console.log('Proceso de subida completado exitosamente');
    };    // Estado para manejar monitoreos activos
    const [monitoreosActivos, setMonitoreosActivos] = useState<Set<string>>(new Set());

    // Estado para verificar si el campus tiene carreras médicas
    const [campusTieneCarrerasMedicas, setCampusTieneCarrerasMedicas] = useState<boolean>(false);
    const [verificandoCampusMedico, setVerificandoCampusMedico] = useState<boolean>(false);
    const [carrerasMedicas, setCarrerasMedicas] = useState<CarreraMedica[]>([]);

    // Estado separado para documentos médicos para evitar re-renders del componente principal
    const [documentosMedicosConEstado, setDocumentosMedicosConEstado] = useState<DocumentoRequerido[]>([]);
    const [cargandoDocumentosMedicos, setCargandoDocumentosMedicos] = useState<boolean>(false);

    // Estados para el selector de tipo de documentos
    const [tipoDocumento, setTipoDocumento] = useState<'legales' | 'medicos'>('legales');

    // Limpiar monitores al desmontar el componente
    useEffect(() => {
        return () => {
            // Limpiar todos los monitores activos
          //  console.log('Limpiando monitores activos al desmontar componente');
            setMonitoreosActivos(new Set());
        };
    }, []);

    // Verificar si el campus inicial tiene carreras médicas
    useEffect(() => {
        if (campusActual?.ID_Campus) {
           // console.log('=== VERIFICANDO CARRERAS MÉDICAS PARA CAMPUS ===', campusActual.ID_Campus);
            verificarCampusMedico(campusActual.ID_Campus);
        }
    }, [campusActual]);

    // Refrescar documentos cuando cambia el tipo de documento (solo una vez por cambio)
    useEffect(() => {
        if (campusActual) {
            // Solo mostrar loader para cambios que no sean a médicos (para evitar salto de pantalla)
            if (tipoDocumento !== 'medicos') {
                setCargandoDocumentosCompleto(true);
                setDocumentosListosParaMostrar(false);
            }

            // Limpiar estado separado cuando cambia el tipo
            setDocumentosMedicosConEstado([]);

            //  NO RESETEAR DOCUMENTO SELECCIONADO para evitar cambio de tamaño del panel
            // setSelectedDocumento(null); // <-- Comentado para evitar salto de pantalla

            // Solo refrescar si es médicos, los legales ya se cargan desde el servidor inicial
            if (tipoDocumento === 'medicos') {
               // console.log('Cambiando a médicos sin recargar');
                // Ejecutar carga en background sin bloquear UI
                setTimeout(() => {
                    refrescarDocumentosDesdeServidor();
                }, 50); // Tiny delay para que no se vea el loader
                // Marcar como listo inmediatamente
                setDocumentosListosParaMostrar(true);
                setCargandoDocumentosCompleto(false);
            } else {
                // Para legales, usar los documentos ya cargados y seleccionar el primero
                const documentosLegales = documentos.filter(doc =>
                    !doc.concepto.toLowerCase().includes('cartas de intención de campo clínico')
                );

                if (documentosLegales.length > 0) {
                   // console.log('🔄 Seleccionando primer documento legal:', documentosLegales[0].concepto);
                    setSelectedDocumento(documentosLegales[0]);
                }

                setDocumentosListosParaMostrar(true);
                setCargandoDocumentosCompleto(false);
            }
        }
    }, [tipoDocumento, campusActual]);

    // NUEVO EFECTO: Sincronizar documento seleccionado cuando cambien los documentos médicos
    useEffect(() => {
        if (tipoDocumento === 'medicos' && documentosMedicosConEstado.length > 0) {
           // console.log('=== SINCRONIZANDO DOCUMENTO SELECCIONADO CON MÉDICOS ===');

            // Si no hay documento seleccionado o es de tipo incorrecto, seleccionar el primero
            if (!selectedDocumento || !selectedDocumento.uniqueKey) {
              //  console.log('🔄 Seleccionando primer documento médico actualizado:', documentosMedicosConEstado[0].concepto);
                setSelectedDocumento(documentosMedicosConEstado[0]);
            } else {
                // Si hay documento seleccionado, verificar que esté actualizado
                const docActualizado = documentosMedicosConEstado.find(d => d.uniqueKey === selectedDocumento.uniqueKey);
                if (docActualizado && docActualizado !== selectedDocumento) {
                   // console.log('🔄 Actualizando documento médico seleccionado con datos frescos:', docActualizado.concepto);
                    setSelectedDocumento(docActualizado);
                }
            }
        }
    }, [documentosMedicosConEstado, tipoDocumento]);

    // Función para monitorear el análisis automático
    const monitorearAnalisis = async (archivoId: string, documentoKey: string, maxIntentos: number = 2) => {
        // Evitar múltiples monitores para el mismo archivo
        if (monitoreosActivos.has(archivoId)) {
           // console.log(`Ya existe un monitor activo para archivo ${archivoId}`);
            return;
        }

        // Agregar a monitores activos
        setMonitoreosActivos(prev => new Set(prev).add(archivoId));

        let intentos = 0;

        const checkAnalisis = async () => {
            intentos++;

            try {
               // console.log(`Verificando análisis para archivo ${archivoId} - Intento ${intentos}/${maxIntentos}`);

                const response = await csrfFetch(`/documentos/analisis/estado?archivo_id=${archivoId}`, {
                    method: 'GET'
                });

                if (response.ok) {
                    const data = await response.json();

                   // console.log(`Estado del análisis:`, data);

                    // Actualizar estado del archivo
                    setDocumentos(prev => prev.map(doc => {
                        if (getDocumentKey(doc) === documentoKey) {
                            const archivosActualizados = getArchivosSeguro(doc).map(archivo => {
                                if (archivo.id === archivoId) {
                                    const estaCompleto = data.tiene_analisis;
                                    let nuevoEstado: ArchivoSubido['estado'] = estaCompleto ? 'completado' : 'analizando';
                                    let nuevoMensaje = '';

                                    // Verificar si el documento fue rechazado (verificar tanto estado directo como validación)
                                    const esRechazado = data.estado === 'rechazado' ||
                                        (estaCompleto && data.analisis && data.analisis.validacion && !data.analisis.validacion.coincide);

                                    if (esRechazado) {
                                        nuevoEstado = 'rechazado';

                                        // Obtener razón del rechazo
                                        let razonRechazo = '';
                                        if (data.analisis && data.analisis.validacion) {
                                            razonRechazo = data.analisis.validacion.razon;
                                        } else if (data.analisis && data.analisis.documento) {
                                            razonRechazo = data.analisis.documento.observaciones;
                                        } else {
                                            razonRechazo = 'Documento no cumple con los requisitos';
                                        }

                                        nuevoMensaje = `Documento rechazado: ${razonRechazo}`;

                                        // Mostrar modal de rechazo automáticamente
                                        const documentoActual = documentos.find(d => getDocumentKey(d) === documentoKey);
                                        setRejectionData({
                                            fileName: archivo.nombre,
                                            documentType: documentoActual?.concepto || 'Documento',
                                            reason: razonRechazo,
                                            percentage: 0, // Ya no se usa, pero mantenemos para evitar errores
                                            expectedType: (data.analisis?.validacion?.documento_esperado || documentoActual?.concepto) || 'Tipo de documento requerido',
                                            detectedType: (data.analisis?.validacion?.documento_detectado || data.analisis?.documento?.nombre_detectado || data.documento?.tipo_detectado) || 'Tipo no identificado',
                                            // Información de validación de ciudad si está disponible
                                            cityValidation: data.analisis?.validacion?.ciudad_campus ? {
                                                campusCity: data.analisis.validacion.ciudad_campus,
                                                documentCities: data.analisis.validacion.ciudades_documento || [],
                                                isValid: data.analisis.validacion.coincide || false,
                                                details: data.analisis.validacion.evaluacion_gpt === 'ciudad_incorrecta' ?
                                                    'El documento pertenece a una ciudad diferente a la del campus seleccionado' : undefined
                                            } : undefined
                                        });
                                        setShowRejectionModal(true);
                                    } else if (estaCompleto && data.analisis && data.analisis.validacion) {
                                        // Documento aceptado
                                        let mensajeValidacion = `Documento validado correctamente (${data.analisis.validacion.porcentaje_coincidencia}% coincidencia)`;

                                        // Añadir información de validación de ciudad si está disponible
                                        if (data.analisis.validacion.ciudad_campus && data.analisis.validacion.ciudades_documento) {
                                            mensajeValidacion += ` • Ubicación: ${data.analisis.validacion.ciudad_campus} ✓`;
                                        }

                                        nuevoMensaje = mensajeValidacion;
                                    } else if (estaCompleto) {
                                        nuevoMensaje = 'Análisis IA completado';
                                    } else {
                                        nuevoMensaje = `Analizando documento... (${intentos}/${maxIntentos})`;
                                    }

                                    return {
                                        ...archivo,
                                        estado: nuevoEstado,
                                        mensaje: nuevoMensaje,
                                        validacionIA: data.analisis?.validacion ? {
                                            coincide: data.analisis.validacion.coincide,
                                            porcentaje: data.analisis.validacion.porcentaje_coincidencia,
                                            razon: data.analisis.validacion.razon,
                                            accion: data.analisis.validacion.accion
                                        } : undefined,
                                        // Información de fechas extraída del documento por IA (puede ser imprecisa)
                                        fechaExpedicion: data.analisis?.documento?.fecha_expedicion,
                                        vigenciaDocumento: data.analisis?.documento?.vigencia_documento,
                                        diasRestantesVigencia: data.analisis?.documento?.dias_restantes_vigencia,
                                        // 🎯 Información de la BD (fuente de verdad - siempre priorizar sobre IA)
                                        metadata: data.metadata ? {
                                            folio_documento: data.metadata.folio_documento,
                                            fecha_expedicion: data.metadata.fecha_expedicion,
                                            vigencia_documento: data.metadata.vigencia_documento,
                                            lugar_expedicion: data.metadata.lugar_expedicion,
                                            // 🔧 ESTADO DE LA TABLA DE BD (no del JSON)
                                            estado_bd: data.metadata.estado_bd || data.estado_bd
                                        } : undefined
                                    };

                                    // Debug: Verificar qué datos de fecha estamos recibiendo
                                  /*   console.log('📅 DEBUG - Datos de fecha del análisis:', {
                                        archivo: archivo.nombre,
                                        analisis_completo: data.analisis,
                                        documento: data.analisis?.documento,
                                        fecha_expedicion: data.analisis?.documento?.fecha_expedicion,
                                        vigencia_documento: data.analisis?.documento?.vigencia_documento,
                                        dias_restantes: data.analisis?.documento?.dias_restantes_vigencia
                                    }); */
                                }
                                return archivo;
                            });

                            // 🔄 Actualizar estado del documento basado en archivos
                            let nuevoEstadoDoc = doc.estado;
                            const archivoValidado = archivosActualizados.find(a =>
                                a.estado === 'completado' &&
                                (!a.validacionIA || a.validacionIA.coincide)
                            );
                            const archivoRechazado = archivosActualizados.find(a =>
                                a.estado === 'rechazado' ||
                                (a.estado === 'completado' && a.validacionIA && !a.validacionIA.coincide)
                            );

                            // Determinar el nuevo estado (prioridad: rechazado > validado > subido)
                            if (archivoRechazado) {
                                nuevoEstadoDoc = 'rechazado';
                            } else if (archivoValidado) {
                                nuevoEstadoDoc = 'vigente';
                            } else if (archivosActualizados.some(a => a.estado === 'completado')) {
                                nuevoEstadoDoc = 'subido';
                                // console.log('Documento subido');
                            } return {
                                ...doc,
                                estado: nuevoEstadoDoc,
                                archivos: archivosActualizados
                            };
                        }
                        return doc;
                    }));

                    // 🔄 ACTUALIZAR DOCUMENTO SELECCIONADO EN TIEMPO REAL
                    if (selectedDocumento && getDocumentKey(selectedDocumento) === documentoKey) {
                        setSelectedDocumento(prev => {
                            if (prev) {
                                const docActualizado = documentos.find(d => getDocumentKey(d) === documentoKey);
                                if (docActualizado) {
                                    const esRechazado = data.estado === 'rechazado' ||
                                        (data.analisis?.validacion && !data.analisis.validacion.coincide);

                                    const nuevoDocumento: DocumentoRequerido = {
                                        ...prev,
                                        estado: esRechazado ? 'rechazado' :
                                            data.analisis?.validacion?.coincide ? 'vigente' :
                                                prev.estado,
                                        archivos: prev.archivos?.map(archivo => {
                                            if (archivo.id === archivoId) {
                                                const nuevoEstadoArchivo: ArchivoSubido['estado'] =
                                                    esRechazado ? 'rechazado' :
                                                        data.tiene_analisis ? 'completado' : 'analizando';

                                                return {
                                                    ...archivo,
                                                    estado: nuevoEstadoArchivo,
                                                    validacionIA: data.analisis?.validacion ? {
                                                        coincide: data.analisis.validacion.coincide,
                                                        porcentaje: data.analisis.validacion.porcentaje_coincidencia,
                                                        razon: data.analisis.validacion.razon,
                                                        accion: data.analisis.validacion.accion
                                                    } : undefined,
                                                    // Información de fechas extraída del documento
                                                    fechaExpedicion: data.analisis?.documento?.fecha_expedicion,
                                                    vigenciaDocumento: data.analisis?.documento?.vigencia_documento,
                                                    diasRestantesVigencia: data.analisis?.documento?.dias_restantes_vigencia
                                                };

                                                // Debug: Verificar datos en la segunda ubicación
                                              /*   console.log('📅 DEBUG - Segunda ubicación:', {
                                                    archivo: archivo.nombre,
                                                    fecha_expedicion: data.analisis?.documento?.fecha_expedicion,
                                                    vigencia_documento: data.analisis?.documento?.vigencia_documento
                                                }); */
                                            }
                                            return archivo;
                                        }) || []
                                    };
                                   /*  console.log('Actualizando selectedDocumento en tiempo real:', nuevoDocumento); */
                                    return nuevoDocumento;
                                }
                            }
                            return prev;
                        });
                    }                    // Si el análisis terminó o se alcanzó el máximo de intentos, parar
                    if (data.tiene_analisis || intentos >= maxIntentos) {
                      /*   console.log(`Finalizando monitoreo para archivo ${archivoId}. Análisis completado: ${data.tiene_analisis}, Intentos: ${intentos}`);
 */
                        // Remover de monitores activos
                        setMonitoreosActivos(prev => {
                            const newSet = new Set(prev);
                            newSet.delete(archivoId);
                            return newSet;
                        });

                        // 🔄 ACTUALIZAR PANEL DESPUÉS DEL ANÁLISIS IA
                        if (data.tiene_analisis) {
                        /*     console.log('Análisis IA completado - Actualizando panel...');
                            console.log('📅 FECHAS RECIBIDAS DEL ANÁLISIS:', {
                                fecha_expedicion: data.analisis?.documento?.fecha_expedicion,
                                vigencia_documento: data.analisis?.documento?.vigencia_documento,
                                dias_restantes: data.analisis?.documento?.dias_restantes_vigencia
                            }); */

                            // Si el documento fue rechazado, forzar actualización inmediata
                            const documentoAfectado = documentos.find(d => getDocumentKey(d) === documentoKey);
                            const archivoAfectado = documentoAfectado?.archivos?.find(a => a.id === archivoId);
                            const esRechazado = data.estado === 'rechazado' ||
                                (data.analisis && data.analisis.validacion && !data.analisis.validacion.coincide);

                            if (esRechazado) {
                           /*      console.log('Documento rechazado - Actualizando interfaz inmediatamente'); */
                                // Forzar re-render inmediato para mostrar el estado rechazado
                                setTimeout(() => {
                                    setDocumentos(prev => [...prev]); // Forzar re-render
                                }, 100);
                            } else if (data.analisis?.validacion?.coincide) {
                                //  MOSTRAR NOTIFICACIÓN DE ÉXITO SOLO SI EL DOCUMENTO ES APROBADO
                             /*    console.log('Documento aprobado por IA - Mostrando notificación de éxito'); */
                                const archivoAfectado = documentoAfectado?.archivos?.find(a => a.id === archivoId);
                                setNotificationMessage(`${archivoAfectado?.nombre || 'Documento'} validado exitosamente`);
                                setShowSuccessNotification(true);
                                setTimeout(() => setShowSuccessNotification(false), 4000);

                                // 🔄 FORZAR RE-RENDER PARA ACTUALIZAR FECHAS EN LA UI
                                setTimeout(() => {
                                   /*  console.log('🔄 Forzando re-render para actualizar fechas en UI'); */
                                    setSelectedDocumento(prev => prev ? {...prev} : prev);
                                }, 500);
                            }

                            programarActualizacionAutomatica();
                        }

                        if (!data.tiene_analisis && intentos >= maxIntentos) {
                           /*  console.warn(`Timeout en análisis de archivo ${archivoId} después de ${maxIntentos} intentos`);
 */
                            // Marcar como error por timeout
                            setDocumentos(prev => prev.map(doc => {
                                if (getDocumentKey(doc) === documentoKey) {
                                    const archivosActualizados = getArchivosSeguro(doc).map(archivo => {
                                        if (archivo.id === archivoId) {
                                            return {
                                                ...archivo,
                                                estado: 'error' as ArchivoSubido['estado']
                                            };
                                        }
                                        return archivo;
                                    });

                                    return {
                                        ...doc,
                                        archivos: archivosActualizados
                                    };
                                }
                                return doc;
                            }));
                        }

                        return; // Parar el monitoreo
                    }

                    // Continuar monitoreando si no terminó
                    setTimeout(checkAnalisis, 2000); // Verificar cada 2 segundos (más rápido)
                } else {
                  /*   console.error(`Error en petición de estado: ${response.status}`);
 */
                    // En caso de error, reintentar hasta el máximo
                    if (intentos < maxIntentos) {
                        setTimeout(checkAnalisis, 2000); // Verificar cada 2 segundos
                    } else {
                        // Remover de monitores activos
                        setMonitoreosActivos(prev => {
                            const newSet = new Set(prev);
                            newSet.delete(archivoId);
                            return newSet;
                        });
                    }
                }
            } catch (error) {
             /*    console.error('Error monitoreando análisis:', error);
 */
                // En caso de error, reintentar hasta el máximo
                if (intentos < maxIntentos) {
                    setTimeout(checkAnalisis, 3000); // Esperar 3 segundos en caso de error
                } else {
                    /* console.error(`Error persistente en monitoreo de archivo ${archivoId}, cancelando.`);
 */
                    // Remover de monitores activos
                    setMonitoreosActivos(prev => {
                        const newSet = new Set(prev);
                        newSet.delete(archivoId);
                        return newSet;
                    });
                }
            }
        };

        // Iniciar el monitoreo
        checkAnalisis();
    };

    // Función para detener todos los monitores
    const detenerTodosLosMonitores = () => {
       /*  console.log('Deteniendo todos los monitores manualmente'); */
        setMonitoreosActivos(new Set());

        // Actualizar todos los archivos que estén "analizando" a "error"
        setDocumentos(prev => prev.map(doc => ({
            ...doc,
            archivos: getArchivosSeguro(doc).map(archivo => {
                if (archivo.estado === 'analizando') {
                    return {
                        ...archivo,
                        estado: 'error' as ArchivoSubido['estado']
                    };
                }
                return archivo;
            })
        })));
    };

    // Función para reanalizar un documento
    const reanalizar = async (archivoId: string) => {
        try {
            const response = await csrfFetch('/documentos/analisis/reanalizar', {
                method: 'POST',
                body: JSON.stringify({ archivo_id: archivoId })
            });

            if (response.ok) {
                const data = await response.json();

                // Actualizar estado a analizando
                if (selectedDocumento) {
                    setDocumentos(prev => prev.map(doc => {
                        if (doc.id === selectedDocumento.id) {
                            const archivosActualizados = getArchivosSeguro(doc).map(archivo => {
                                if (archivo.id === archivoId) {
                                    return {
                                        ...archivo,
                                        estado: 'analizando' as ArchivoSubido['estado']
                                    };
                                }
                                return archivo;
                            });

                            return {
                                ...doc,
                                archivos: archivosActualizados
                            };
                        }
                        return doc;
                    }));

                    // Monitorear el progreso del nuevo análisis (solo 1 intento para evitar bucles infinitos)
                    monitorearAnalisis(archivoId, selectedDocumento.id, 1);
                }
            } else {
                console.error('Error en reanálisis');
            }
        } catch (error) {
            console.error('Error reanalizing document:', error);
        }
    };

    const getDaysUntilDeadline = (fechaLimite: string) => {
        const today = new Date();
        const deadline = new Date(fechaLimite);
        const diffTime = deadline.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    };

    const getDeadlineColor = (days: number) => {
        if (days < 0) return 'text-red-600';
        if (days <= 7) return 'text-orange-600';
        if (days <= 30) return 'text-yellow-600';
        return 'text-green-600';
    };

    // Función para normalizar el estado del documento
    const normalizeEstado = (estado: string): string => {
        const normalizado = estado.toLowerCase();
        //console.log(' Estado original:', estado, '-> normalizado:', normalizado);
        return normalizado;
    };

    // Función para determinar si un documento está realmente caducado basado en la fecha
    const isDocumentoCaducado = (documento: DocumentoRequerido): boolean => {
        // 🔧 USAR ESTADO REAL DE LA BD
        const estadoReal = getEstadoRealDocumento(documento);
        const estadoNormalizado = normalizeEstado(estadoReal);

        // Si ya está marcado como caducado en la BD
        if (estadoNormalizado === 'caducado') {
           // console.log('✅ Documento marcado como caducado en BD:', documento.concepto);
            return true;
        }

        const vigenciaInfo = getFechaVigenciaDocumento(documento);
        const diasRestantes = vigenciaInfo.diasRestantes !== null
            ? vigenciaInfo.diasRestantes
            : getDaysUntilDeadline(documento.fechaLimite);

        // Si tiene archivos vigentes pero la fecha ya pasó, está caducado
        const esCaducadoDinamico = (estadoNormalizado === 'vigente' && diasRestantes < 0);

        if (esCaducadoDinamico) {
            console.log('⏰ Documento caducado dinámicamente:', documento.concepto, 'Días restantes:', diasRestantes);
        }

        return esCaducadoDinamico;
    };

// Función para obtener el color del estado considerando caducidad dinámica Y estado de BD
const getEstadoColorDinamico = (documento: DocumentoRequerido): string => {
    // 🔧 PRIORIZAR ESTADO DE LA BD sobre el JSON
    const estadoReal = getEstadoRealDocumento(documento);

    if (isDocumentoCaducado(documento)) {
        return 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 border-orange-300 dark:border-orange-800';
    }
    const estadoNormalizado = normalizeEstado(estadoReal);
    return getEstadoColor(estadoNormalizado);
};

// Función para obtener el ícono del estado considerando caducidad dinámica Y estado de BD
const getEstadoIconDinamico = (documento: DocumentoRequerido) => {
    if (isDocumentoCaducado(documento)) {
        return <Calendar className="w-3 h-3" />;
    }
    // 🔧 PRIORIZAR ESTADO DE LA BD sobre el JSON
    const estadoReal = getEstadoRealDocumento(documento);
    const estadoNormalizado = normalizeEstado(estadoReal);
    return getEstadoIcon(estadoNormalizado);
};

// Función para obtener el texto del estado considerando caducidad dinámica Y estado de BD
const getEstadoTextoDinamico = (documento: DocumentoRequerido): string => {
    if (isDocumentoCaducado(documento)) {
        return 'caducado';
    }
    // 🔧 PRIORIZAR ESTADO DE LA BD sobre el JSON
    const estadoReal = getEstadoRealDocumento(documento);
    return normalizeEstado(estadoReal);
};

// 🔧 NUEVA FUNCIÓN: Obtener el estado real del documento priorizando BD sobre JSON
const getEstadoRealDocumento = (documento: DocumentoRequerido): string => {
    // Buscar archivos que tengan metadata con estado de BD
    const archivos = getArchivosSeguro(documento);
   /*  console.log(` Debug ${documento.concepto}:`, {
        total_archivos: archivos.length,
        archivos: archivos.map(a => ({
            id: a.id,
            nombre: a.nombre,
            metadata: a.metadata,
            tiene_estado_bd: !!a.metadata?.estado_bd
        }))
    }); */

    const archivosConEstadoBD = archivos.filter(archivo =>
        archivo.metadata?.estado_bd
    );

    if (archivosConEstadoBD.length > 0) {
        // Si hay archivos con estado de BD, usar el más reciente
        const archivoMasReciente = archivosConEstadoBD.sort((a, b) =>
            new Date(b.fechaSubida).getTime() - new Date(a.fechaSubida).getTime()
        )[0];

       // console.log(`Usando estado de BD para ${documento.concepto}:`, archivoMasReciente.metadata?.estado_bd);
        return archivoMasReciente.metadata?.estado_bd || documento.estado;
    }

    // Fallback: usar estado del documento JSON
    // console.log(` Usando estado JSON para ${documento.concepto}:`, documento.estado);
    return documento.estado;
};    // Filtrar documentos
    const documentosFiltrados = documentos.filter(doc => {
        const coincideBusqueda = doc.concepto.toLowerCase().includes(busqueda.toLowerCase()) ||
            doc.descripcion.toLowerCase().includes(busqueda.toLowerCase());

        // Lógica de filtrado por estado
        let coincidefiltro = false;
        const estadoNormalizado = normalizeEstado(doc.estado);

        if (filtroEstado === 'todos') {
            coincidefiltro = true;
        } else if (filtroEstado === 'subido') {
            // En "Subidos" incluir 'subido', 'vigente' y 'caducado' (documentos que ya se subieron)
            coincidefiltro = estadoNormalizado === 'subido' || estadoNormalizado === 'vigente' || estadoNormalizado === 'caducado' || isDocumentoCaducado(doc);
        } else {
            coincidefiltro = estadoNormalizado === filtroEstado || (filtroEstado === 'caducado' && isDocumentoCaducado(doc));
        }

        return coincideBusqueda && coincidefiltro;
    });

    // Filtrar documentos según el tipo seleccionado
    const documentosDelTipo = (() => {
        if (tipoDocumento === 'legales') {
            // Documentos legales: todo excepto cartas de intención de campo clínico
            return documentosFiltrados.filter(doc =>
                !doc.concepto.toLowerCase().includes('cartas de intención de campo clínico')
            );
        } else {
            // Documentos médicos: usar estado separado si está disponible
            if (documentosMedicosConEstado.length > 0) {
                console.log('🔄 Usando documentos médicos con estado actualizado:', documentosMedicosConEstado.length);

                // ✅ APLICAR FILTROS TAMBIÉN A DOCUMENTOS MÉDICOS DEL SP
                return documentosMedicosConEstado.filter(doc => {
                    const coincideBusqueda = doc.concepto.toLowerCase().includes(busqueda.toLowerCase()) ||
                        doc.descripcion.toLowerCase().includes(busqueda.toLowerCase());

                    // Lógica de filtrado por estado para documentos médicos
                    let coincidefiltro = false;
                    const estadoNormalizado = normalizeEstado(doc.estado);

                    if (filtroEstado === 'todos') {
                        coincidefiltro = true;
                    } else if (filtroEstado === 'subido') {
                        // En "Subidos" incluir 'subido', 'vigente' y 'caducado' (documentos que ya se subieron)
                        coincidefiltro = estadoNormalizado === 'subido' || estadoNormalizado === 'vigente' || estadoNormalizado === 'caducado' || isDocumentoCaducado(doc);
                    } else {
                        coincidefiltro = estadoNormalizado === filtroEstado || (filtroEstado === 'caducado' && isDocumentoCaducado(doc));
                    }

                    return coincideBusqueda && coincidefiltro;
                });
            }

            // Generar documentos médicos iniciales si no hay estado separado
            const cartasOriginales = documentosFiltrados.filter(doc =>
                doc.concepto.toLowerCase().includes('cartas de intención de campo clínico')
            );

            if (cartasOriginales.length === 0 || carrerasMedicas.length === 0) {
                return [];
            }

            // Generar documentos por carrera
            const documentosPorCarrera: DocumentoRequerido[] = [];

            carrerasMedicas.forEach(carrera => {
                cartasOriginales.forEach(docOriginal => {
                    const uniqueKey = `${docOriginal.id}_carrera_${carrera.ID_Especialidad}`;

                    const documentoGenerado: DocumentoRequerido = {
                        id: docOriginal.id, // Mantener el ID original
                        concepto: `${docOriginal.concepto} - ${carrera.Descripcion}`,
                        descripcion: `${docOriginal.descripcion} para la carrera de ${carrera.Descripcion}`,
                        fechaLimite: docOriginal.fechaLimite,
                        estado: 'pendiente' as DocumentoRequerido['estado'],
                        archivos: [],
                        obligatorio: docOriginal.obligatorio,
                        categoria: docOriginal.categoria,
                        documentoOriginalId: docOriginal.id, // Guardar referencia al ID original
                        uniqueKey: uniqueKey, // ID único para React
                        carreraId: String(carrera.ID_Especialidad), // Agregar ID de carrera como string
                        carreraNombre: carrera.Descripcion, // Agregar nombre de carrera
                    };

              /*       console.log(` Documento generado para ${carrera.Descripcion}:`, {
                        uniqueKey: documentoGenerado.uniqueKey,
                        carreraId: documentoGenerado.carreraId,
                        carreraIdType: typeof documentoGenerado.carreraId,
                        documentoOriginalId: documentoGenerado.documentoOriginalId
                    }); */

                    documentosPorCarrera.push(documentoGenerado);
                });
            });

          //  console.log(`📚 Total documentos médicos generados: ${documentosPorCarrera.length}`);
            return documentosPorCarrera;
        }
    })();

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Documentos', href: '/documentos' },
                { title: 'Subir Documentos', href: '/documentos/upload' }
            ]}
        >
            <Head title="Subida de Documentos">
                <meta name="csrf-token" content={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
            </Head>

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Notificación de éxito flotante */}
                {showSuccessNotification && (
                    <div className="fixed top-4 right-4 z-40 animate-in slide-in-from-right-full duration-500">
                        <Card className="bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800 shadow-lg">
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 bg-green-100 dark:bg-green-900/50 rounded-full flex items-center justify-center">
                                        <CheckCircle className="w-5 h-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-green-800 dark:text-green-200">
                                            Archivo subido exitosamente
                                        </p>
                                        <p className="text-xs text-green-600 dark:text-green-400">
                                            {notificationMessage}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowSuccessNotification(false)}
                                        className="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 hover:bg-green-100 dark:hover:bg-green-900/50 h-6 w-6 p-0"
                                    >
                                        <X className="w-3 h-3" />
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
                {/* Loader Overlay */}
                {isUploading && (
                    <div
                        className="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50"
                        onClick={(e) => e.preventDefault()} // Prevenir cierre al hacer clic fuera
                    >
                        <Card
                            className="w-96 mx-4 dark:bg-gray-800 dark:border-gray-700"
                            onClick={(e) => e.stopPropagation()} // Prevenir propagación del clic en el contenido
                        >
                            <CardContent className="p-8">
                                <div className="text-center space-y-6">
                                    {/* Spinner animado o éxito */}
                                    <div className="relative">
                                        {uploadSuccess ? (
                                            /* Animación de éxito */
                                            <div className="w-16 h-16 mx-auto">
                                                <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                                                    <CheckCircle className="w-12 h-12 text-green-600 animate-pulse" />
                                                </div>
                                                <div className="absolute inset-0 flex items-center justify-center">
                                                    <div className="w-20 h-20 bg-green-600 rounded-full opacity-20 animate-ping"></div>
                                                </div>
                                            </div>
                                        ) : (
                                            /* Spinner normal */
                                            <div className="w-16 h-16 mx-auto">
                                                <Loader2 className="w-16 h-16 text-blue-600 animate-spin" />
                                                <div className="absolute inset-0 flex items-center justify-center">
                                                    <div className="w-12 h-12 bg-blue-600 rounded-full opacity-20 animate-ping"></div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Información del archivo */}
                                    <div className="space-y-2">
                                        <h3 className={`text-lg font-semibold ${uploadSuccess ? 'text-green-900 dark:text-green-100' : 'text-gray-900 dark:text-gray-100'}`}>
                                            {uploadSuccess ? 'Documentos Subidos Exitosamente' : 'Procesando Documentos'}
                                        </h3>
                                        {currentFileName && (
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {currentFileName}
                                            </p>
                                        )}
                                        <p className={`text-sm font-medium ${uploadSuccess ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'}`}>
                                            {uploadMessage}
                                        </p>
                                        {uploadSuccess && successCount > 0 && (
                                            <div className="flex items-center justify-center gap-2 mt-3">
                                                <CheckCircle className="w-5 h-5 text-green-600 dark:text-green-400" />
                                                <span className="text-sm font-semibold text-green-700 dark:text-green-300">
                                                    {successCount} de {totalFiles} archivo(s) procesado(s) exitosamente
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Barra de progreso */}
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                            <span>Progreso</span>
                                            <span>{uploadProgress}%</span>
                                        </div>
                                        <Progress
                                            value={uploadProgress}
                                            className={`h-3 ${uploadSuccess ? 'bg-green-100 dark:bg-green-900/30' : 'dark:bg-gray-700'}`}
                                        />
                                    </div>

                                    {/* Mensaje adicional */}
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {uploadSuccess ?
                                            'Los documentos se están procesando en segundo plano...' :
                                            'Por favor espera mientras procesamos tus documentos...'
                                        }
                                    </div>

                                    {/* Botón de cerrar en caso de éxito */}
                                    {uploadSuccess && (
                                        <Button
                                            onClick={() => {
                                                setIsUploading(false);
                                                setUploadSuccess(false);
                                                setUploadProgress(0);
                                                setUploadMessage('');
                                                setCurrentFileName('');
                                                setSuccessCount(0);
                                                setTotalFiles(0);
                                            }}
                                            className="w-full bg-green-600 hover:bg-green-700 text-white"
                                        >
                                            <CheckCircle className="w-4 h-4 mr-2" />
                                            Continuar
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Modal de Documento Rechazado */}
                {showRejectionModal && rejectionData && (
                    <div
                        className="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 p-4"
                        onClick={(e) => e.preventDefault()} // Prevenir cierre al hacer clic fuera
                    >
                        <Card
                            className="w-full max-w-2xl bg-white dark:bg-gray-800 shadow-xl dark:border-gray-700"
                            onClick={(e) => e.stopPropagation()} // Prevenir propagación del clic en el contenido
                        >
                            {/* Header minimalista */}
                            <CardHeader className="pb-4">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                                            <AlertCircle className="w-4 h-4 text-red-600 dark:text-red-400" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-lg text-gray-900 dark:text-gray-100">Documento Rechazado</CardTitle>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">Validación automática</p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowRejectionModal(false)}
                                        className="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 h-8 w-8 p-0"
                                    >
                                        <X className="w-4 h-4" />
                                    </Button>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                {/* Archivo */}
                                <div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Archivo:</p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-lg">{rejectionData.fileName}</p>
                                </div>

                                {/* Motivo */}
                                <div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Motivo:</p>
                                    <p className="text-sm text-gray-600 dark:text-gray-300 bg-red-50 dark:bg-red-900/20 p-3 rounded-lg border-l-4 border-red-400 dark:border-red-600">
                                        {rejectionData.reason}
                                    </p>
                                </div>

                                {/* Comparación simple */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Esperado:</p>
                                        <p className="text-sm text-gray-700 dark:text-gray-300 bg-blue-50 dark:bg-blue-900/20 p-2 rounded text-center">{rejectionData.expectedType}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Detectado:</p>
                                        <p className="text-sm text-gray-700 dark:text-gray-300 bg-orange-50 dark:bg-orange-900/20 p-2 rounded text-center">{rejectionData.detectedType}</p>
                                    </div>
                                </div>

                                {/* Validación de Ciudad */}
                                {rejectionData.cityValidation && (
                                    <div>
                                        <p className="text-sm font-medium text-gray-700 mb-2">Validación de Ubicación:</p>
                                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <div className="grid grid-cols-2 gap-4 mb-3">
                                                <div>
                                                    <p className="text-xs font-medium text-gray-500 mb-1">Campus:</p>
                                                    <p className="text-sm text-gray-700 bg-white p-2 rounded border">
                                                        📍 {rejectionData.cityValidation.campusCity}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs font-medium text-gray-500 mb-1">Documento de:</p>
                                                    <p className="text-sm text-gray-700 bg-white p-2 rounded border">
                                                        🏙️ {rejectionData.cityValidation.documentCities.join(', ') || 'No detectado'}
                                                    </p>
                                                </div>
                                            </div>
                                            {rejectionData.cityValidation.details && (
                                                <div className="flex items-start gap-2 text-sm text-yellow-800">
                                                    <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                                                    <p>{rejectionData.cityValidation.details}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Instrucciones simples */}
                                <div className="bg-blue-50 p-4 rounded-lg">
                                    <p className="text-sm font-medium text-blue-800 mb-2">¿Qué hacer?</p>
                                    <p className="text-sm text-blue-700">
                                        Suba el documento correcto o contacte al administrador si considera que es un error.
                                    </p>
                                </div>
                                {/* Botones simples */}
                                <div className="flex gap-3 pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowRejectionModal(false)}
                                        className="flex-1"
                                    >
                                        Cerrar
                                    </Button>
                                    <Button
                                        onClick={() => {
                                            setShowRejectionModal(false);
                                            const zona = selectedDocumento ? document.getElementById(`file-input-${getDocumentKey(selectedDocumento)}`) : null;
                                            zona?.scrollIntoView({ behavior: 'smooth' });
                                        }}
                                        className="flex-1"
                                    >
                                        <Upload className="w-4 h-4 mr-2" />
                                        Subir Documento Correcto
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Header */}
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight mb-2 dark:text-gray-100">
                                Subir Documentos
                            </h1>
                            <p className="text-muted-foreground dark:text-gray-400">
                                Selecciona un campus y un documento de la lista para subir los archivos requeridos
                            </p>
                        </div>
                        {/* Indicador de análisis activos */}
                        {monitoreosActivos.size > 0 && (
                            <div className="flex items-center gap-3 px-3 py-2 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div className="flex items-center gap-2">
                                    <Loader2 className="w-4 h-4 text-blue-600 dark:text-blue-400 animate-spin" />
                                    <span className="text-sm text-blue-700 dark:text-blue-300">
                                        {monitoreosActivos.size} análisis en curso
                                    </span>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 px-2 text-xs text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-100 hover:bg-blue-100 dark:hover:bg-blue-900/50"
                                    onClick={detenerTodosLosMonitores}
                                >
                                    <X className="w-3 h-3 mr-1" />
                                    Detener
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Error de campus si no hay campus asignados */}
                {(!campusDelDirector || !Array.isArray(campusDelDirector) || campusDelDirector.length === 0) && (
                    <Card className="mb-6 border-destructive">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-destructive">
                                <AlertCircle className="w-5 h-5" />
                                <p className="font-medium">
                                    {error || 'No tienes campus asignados para subir documentos'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Estadísticas rápidas - Solo si hay campus seleccionado */}
                {campusActual && (
                    <div className="grid gap-4 md:grid-cols-5 mb-6">
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <FileText className="w-4 h-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">Total {tipoDocumento === 'medicos' ? 'Médicos' : 'Legales'}</p>
                                        <p className="text-2xl font-bold">{documentosDelTipo.length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="w-4 h-4 text-yellow-600" />
                                    <div>
                                        <p className="text-sm font-medium">Pendientes</p>
                                        <p className="text-2xl font-bold">{documentosDelTipo.filter(d => normalizeEstado(d.estado) === 'pendiente').length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <CheckCircle className="w-4 h-4 text-green-600" />
                                    <div>
                                        <p className="text-sm font-medium">Completados</p>
                                        <p className="text-2xl font-bold">{documentosDelTipo.filter(d => normalizeEstado(d.estado) === 'vigente' && !isDocumentoCaducado(d)).length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="w-4 h-4 text-orange-600" />
                                    <div>
                                        <p className="text-sm font-medium">Caducados</p>
                                        <p className="text-2xl font-bold">{documentosDelTipo.filter(d => normalizeEstado(d.estado) === 'caducado' || isDocumentoCaducado(d)).length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="w-4 h-4 text-red-600" />
                                    <div>
                                        <p className="text-sm font-medium">Rechazados</p>
                                        <p className="text-2xl font-bold">{documentosDelTipo.filter(d => normalizeEstado(d.estado) === 'rechazado').length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Diseño de dos columnas - Solo si hay campus seleccionado */}
                {campusActual && (
                    <>
                        {/* Loader de carga unificada */}
                        {cargandoDocumentosCompleto && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <div className="lg:col-span-1">
                                    <Card className="h-fit">
                                        <CardHeader>
                                            <div className="flex justify-between items-center">
                                                <CardTitle className="text-lg">Documentos Requeridos</CardTitle>
                                                {/* Selector de Campus (siempre visible) */}
                                                {campusDelDirector && Array.isArray(campusDelDirector) && campusDelDirector.length > 0 && (
                                                    <div className="flex items-center gap-2">
                                                        <Label htmlFor="campus-select" className="text-sm font-medium text-muted-foreground">
                                                            Campus:
                                                        </Label>
                                                        <Select
                                                            value={campusActual?.ID_Campus || ''}
                                                            onValueChange={handleCampusChange}
                                                        >
                                                            <SelectTrigger className="w-52">
                                                                <SelectValue placeholder="Selecciona campus" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {[...campusDelDirector]
                                                                    .sort((a, b) => a.Campus.localeCompare(b.Campus, 'es', { sensitivity: 'base' }))
                                                                    .map((campus) => (
                                                                        <SelectItem key={campus.ID_Campus} value={campus.ID_Campus}>
                                                                            {campus.Campus}
                                                                        </SelectItem>
                                                                    ))
                                                                }
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                )}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="flex items-center justify-center h-96">
                                            <div className="text-center space-y-4">
                                                <Loader2 className="w-12 h-12 animate-spin text-blue-600 mx-auto" />
                                                <div className="space-y-2">
                                                    <p className="text-lg font-medium text-gray-700">Cargando Documentos</p>
                                                    <p className="text-sm text-gray-500">
                                                        {tipoDocumento === 'medicos'
                                                            ? 'Obteniendo estados de documentos médicos...'
                                                            : 'Obteniendo documentos legales...'}
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                                <div className="lg:col-span-2">
                                    <Card className="h-fit">
                                        <CardContent className="flex items-center justify-center h-96">
                                            <div className="text-center space-y-4">
                                                <div className="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto">
                                                    <FileText className="w-8 h-8 text-gray-400 dark:text-gray-500" />
                                                </div>
                                                <div className="space-y-2">
                                                    <p className="text-lg font-medium text-gray-700 dark:text-gray-300">Preparando Información</p>
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        Por favor espera mientras cargamos la información completa...
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        )}

                        {/* Contenido principal - Solo mostrar cuando esté listo */}
                        {!cargandoDocumentosCompleto && documentosListosParaMostrar && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                {/* Columna izquierda: Lista de documentos */}
                                <div className="lg:col-span-1">
                                    <Card className="h-fit">
                                        <CardHeader className="pb-4">
                                            <div className="flex justify-between items-center">
                                                <CardTitle className="text-lg">Documentos Requeridos</CardTitle>
                                                {/* Selector de Campus */}
                                                {campusDelDirector && Array.isArray(campusDelDirector) && campusDelDirector.length > 0 && (
                                                    <div className="flex items-center gap-2">
                                                        <Label htmlFor="campus-select" className="text-sm font-medium text-muted-foreground">
                                                            Campus:
                                                        </Label>
                                                        <Select
                                                            value={campusActual?.ID_Campus || ''}
                                                            onValueChange={handleCampusChange}
                                                        >
                                                            <SelectTrigger className="w-52">
                                                                <SelectValue placeholder="Selecciona campus" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {[...campusDelDirector]
                                                                    .sort((a, b) => a.Campus.localeCompare(b.Campus, 'es', { sensitivity: 'base' }))
                                                                    .map((campus) => (
                                                                        <SelectItem key={campus.ID_Campus} value={campus.ID_Campus}>
                                                                            {campus.Campus}
                                                                        </SelectItem>
                                                                    ))
                                                                }
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Selector de tipo de documento */}
                                            <div className="space-y-3">
                                                <div className="flex items-center gap-2">
                                                    <Label className="text-sm font-medium text-muted-foreground">Tipo:</Label>
                                                    <Select
                                                        value={tipoDocumento}
                                                        onValueChange={(value: 'legales' | 'medicos') => {
                                                            setTipoDocumento(value);
                                                        }}
                                                    >
                                                        <SelectTrigger className="w-40">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="legales">Legales</SelectItem>
                                                            {campusTieneCarrerasMedicas && (
                                                                <SelectItem value="medicos">Médicos</SelectItem>
                                                            )}
                                                        </SelectContent>
                                                    </Select>

                                                    {verificandoCampusMedico && (
                                                        <Loader2 className="w-4 h-4 animate-spin text-blue-500" />
                                                    )}
                                                </div>

                                                {/* Filtros y búsqueda */}
                                                <div className="space-y-3">
                                                    <div className="relative">
                                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
                                                        <Input
                                                            placeholder="Buscar documento..."
                                                            value={busqueda}
                                                            onChange={(e) => setBusqueda(e.target.value)}
                                                            className="pl-9"
                                                        />
                                                    </div>

                                                    <div className="flex gap-2">
                                                        <Button
                                                            variant={filtroEstado === 'todos' ? 'default' : 'outline'}
                                                            size="sm"
                                                            onClick={() => setFiltroEstado('todos')}
                                                        >
                                                            Todos
                                                        </Button>
                                                        <Button
                                                            variant={filtroEstado === 'pendiente' ? 'default' : 'outline'}
                                                            size="sm"
                                                            onClick={() => setFiltroEstado('pendiente')}
                                                        >
                                                            Pendientes
                                                        </Button>
                                                        <Button
                                                            variant={filtroEstado === 'subido' ? 'default' : 'outline'}
                                                            size="sm"
                                                            onClick={() => setFiltroEstado('subido')}
                                                        >
                                                            Subidos
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="p-0">
                                            <div ref={scrollContainerRef} className="max-h-[600px] overflow-y-auto">
                                                {documentosDelTipo.map((documento) => {
                                                    const documentKey = documento.uniqueKey || documento.id;
                                                    const isSelected = selectedDocumento?.uniqueKey === documentKey ||
                                                        (selectedDocumento?.id === documento.id && !documento.uniqueKey);

                                                    return (
                                                        <div
                                                            key={documentKey}
                                                            className={`p-4 border-b cursor-pointer transition-colors hover:bg-muted/50 ${isSelected ? 'bg-primary/10 border-l-4 border-l-primary' : ''
                                                                }`}
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                // Preservar la posición de scroll actual
                                                                const currentScrollTop = scrollContainerRef.current?.scrollTop || 0;
                                                                setSelectedDocumento(documento);
                                                                // Restaurar la posición de scroll después del re-render
                                                                requestAnimationFrame(() => {
                                                                    if (scrollContainerRef.current) {
                                                                        scrollContainerRef.current.scrollTop = currentScrollTop;
                                                                    }
                                                                });
                                                            }}
                                                        >
                                                            <div className="flex items-start justify-between mb-2">
                                                                <h3 className="font-medium text-sm line-clamp-2">{documento.concepto}</h3>
                                                                {documento.obligatorio && (
                                                                    <Badge variant="destructive" className="text-xs ml-2">
                                                                        Req.
                                                                    </Badge>
                                                                )}
                                                            </div>

                                                            <div className="flex items-center gap-2 mb-2">
                                                                <Badge className={getEstadoColorDinamico(documento)} variant="outline">
                                                                    {getEstadoIconDinamico(documento)}
                                                                    <span className="ml-1 capitalize text-xs">{getEstadoTextoDinamico(documento)}</span>
                                                                </Badge>
                                                            </div>

                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <Calendar className="w-3 h-3" />
                                                                {(() => {
                                                                    const vigenciaInfo = getFechaVigenciaDocumento(documento);
                                                                    const diasCalculados = vigenciaInfo.diasRestantes !== null
                                                                        ? vigenciaInfo.diasRestantes
                                                                        : getDaysUntilDeadline(documento.fechaLimite);

                                                                    // Asegurar que diasCalculados tenga un valor válido
                                                                    const diasFinal = diasCalculados ?? 0;

                                                                    return (
                                                                        <>
                                                                            <span>{formatDate(vigenciaInfo.fecha)}</span>
                                                                            <span className={`font-medium ${getDeadlineColor(diasFinal)}`}>
                                                                                ({diasFinal >= 0 ? `${diasFinal}d` : `${Math.abs(diasFinal)}d vencido`})
                                                                            </span>
                                                                        </>
                                                                    );
                                                                })()}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Columna derecha: Detalles y subida del documento seleccionado */}
                                <div className="lg:col-span-2">
                                    {selectedDocumento ? (
                                        <Card className="h-fit dark:bg-gray-800 dark:border-gray-700">
                                            <CardHeader>
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <CardTitle className="text-xl dark:text-gray-100">{selectedDocumento.concepto}</CardTitle>
                                                        <p className="text-muted-foreground dark:text-gray-400 mt-1">{selectedDocumento.descripcion}</p>
                                                    </div>
                                                    <Badge className={getEstadoColorDinamico(selectedDocumento)}>
                                                        <div className="flex items-center gap-1">
                                                            {getEstadoIconDinamico(selectedDocumento)}
                                                            <span className="capitalize">{getEstadoTextoDinamico(selectedDocumento)}</span>
                                                        </div>
                                                    </Badge>
                                                </div>

                                                <div className="flex items-center gap-4 text-sm pt-2">
                                                    <div className="flex items-center gap-1 text-muted-foreground dark:text-gray-400">
                                                        <Calendar className="w-4 h-4" />
                                                        <span>Vence: {formatDate(getFechaVigenciaDocumento(selectedDocumento).fecha)}</span>
                                                    </div>
                                                    {selectedDocumento.obligatorio && (
                                                        <Badge variant="destructive" className="text-xs">
                                                            Documento Obligatorio
                                                        </Badge>
                                                    )}
                                                    {/* Badge para documento caducado */}
                                                    {isDocumentoCaducado(selectedDocumento) && (
                                                        <Badge className="text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 border-orange-300 dark:border-orange-800">
                                                            CADUCADO
                                                        </Badge>
                                                    )}
                                                    {/* Mostrar carrera específica para documentos médicos */}
                                                    {selectedDocumento?.carreraNombre && (
                                                        <Badge variant="secondary" className="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400">
                                                            {selectedDocumento.carreraNombre}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>

                                            <CardContent className="space-y-6">
                                                {/* Archivos ya subidos */}
                                                {getArchivosSeguro(selectedDocumento).length > 0 && (
                                                    <div>
                                                        <h4 className="font-semibold mb-3 dark:text-gray-200">
                                                            Archivos subidos ({getArchivosSeguro(selectedDocumento).length})
                                                        </h4>
                                                        <div className="space-y-2">
                                                            {getArchivosSeguro(selectedDocumento).map((archivo) => {
                                                                // Determinar el color del contenedor basado en el estado real del documento
                                                                const estaRechazado = selectedDocumento?.estado === 'rechazado' ||
                                                                    (archivo.validacionIA && !archivo.validacionIA.coincide);
                                                                const estaCaducado = isDocumentoCaducado(selectedDocumento);

                                                                let containerClass = '';

                                                                if (recentlyUploadedFiles.has(archivo.id)) {
                                                                    containerClass = 'border-green-400 dark:border-green-600 bg-green-50 dark:bg-green-900/20 shadow-lg ring-2 ring-green-200 dark:ring-green-800 ring-opacity-50 animate-pulse';
                                                                } else if (archivo.estado === 'subiendo' || archivo.estado === 'procesando' || archivo.estado === 'analizando') {
                                                                    containerClass = 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 shadow-lg';
                                                                } else if (archivo.estado === 'completado') {
                                                                    if (estaRechazado) {
                                                                        containerClass = 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20';  // Rojo si está rechazado
                                                                    } else if (estaCaducado) {
                                                                        containerClass = 'border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20';  // Naranja si está caducado
                                                                    } else {
                                                                        containerClass = 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20';  // Verde si está aprobado y vigente
                                                                    }
                                                                } else if (archivo.estado === 'rechazado') {
                                                                    containerClass = 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20 shadow-lg';
                                                                } else if (archivo.estado === 'error') {
                                                                    containerClass = 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20';
                                                                } else {
                                                                    containerClass = 'border-gray-200 dark:border-gray-700 dark:bg-gray-800/50';
                                                                }

                                                                return (
                                                                    <div
                                                                        key={archivo.id}
                                                                        className={`p-3 border rounded-lg transition-all duration-300 ${containerClass}`}
                                                                    >
                                                                    <div className="flex items-center justify-between">
                                                                        <div className="flex items-center gap-3 flex-1">
                                                                            <div className="flex-1">
                                                                                <p className="text-sm font-medium dark:text-gray-200">{archivo.nombre}</p>
                                                                                <p className="text-xs text-muted-foreground dark:text-gray-400">
                                                                                    {formatDocumentInfo(archivo)}
                                                                                </p>                                                                    {/* Progreso visible para estados activos */}
                                                                                {archivo.estado === 'subiendo' && (
                                                                                    <div className="mt-2">
                                                                                        <div className="flex items-center gap-2 mb-2">
                                                                                            <Upload className="w-4 h-4 text-blue-600 dark:text-blue-400 animate-bounce" />
                                                                                            <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                                                                                {archivo.mensaje || 'Subiendo archivo...'}
                                                                                            </span>
                                                                                            <span className="text-xs text-blue-500 dark:text-blue-400 font-bold">
                                                                                                {archivo.progreso}%
                                                                                            </span>
                                                                                        </div>
                                                                                        <Progress
                                                                                            value={archivo.progreso}
                                                                                            className="h-3 bg-blue-100 dark:bg-blue-900/30"
                                                                                        />
                                                                                    </div>
                                                                                )}

                                                                                {archivo.estado === 'procesando' && (
                                                                                    <div className="mt-2">
                                                                                        <div className="flex items-center gap-2">
                                                                                            <Loader2 className="w-4 h-4 text-yellow-600 dark:text-yellow-400 animate-spin" />
                                                                                            <span className="text-sm font-medium text-yellow-700 dark:text-yellow-300">
                                                                                                {archivo.mensaje || 'Procesando documento...'}
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                )}

                                                                                {archivo.estado === 'analizando' && (
                                                                                    <div className="mt-2">
                                                                                        <div className="flex items-center gap-2">
                                                                                            <Brain className="w-4 h-4 text-purple-600 dark:text-purple-400 animate-pulse" />
                                                                                            <span className="text-sm font-medium text-purple-700 dark:text-purple-300">
                                                                                                {archivo.mensaje || 'Analizando con IA...'}
                                                                                            </span>
                                                                                        </div>
                                                                                        <div className="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                                                                            <div className="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                                                                                procesando el documento...
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                )}

                                                                                {archivo.estado === 'completado' && (
                                                                                    <>
                                                                                        {/* Determinar el estado real del documento considerando caducidad */}
                                                                                        {(() => {
                                                                                            const estaRechazado = selectedDocumento?.estado === 'rechazado' ||
                                                                                                (archivo.validacionIA && !archivo.validacionIA.coincide);
                                                                                            const estaCaducado = isDocumentoCaducado(selectedDocumento);
                                                                                            const estaAprobado = !estaRechazado && !estaCaducado;

                                                                                            if (estaRechazado) {
                                                                                                return (
                                                                                                    <div className="flex items-center gap-2 mt-1">
                                                                                                        <AlertCircle className="w-4 h-4 text-red-600 dark:text-red-400" />
                                                                                                        <span className="text-sm font-medium text-red-700 dark:text-red-300">
                                                                                                            Documento Rechazado
                                                                                                        </span>
                                                                                                        {archivo.validacionIA && (
                                                                                                            <span className="text-xs bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 px-2 py-1 rounded">
                                                                                                                No aprobado ({archivo.validacionIA.porcentaje}%)
                                                                                                            </span>
                                                                                                        )}
                                                                                                    </div>
                                                                                                );
                                                                                            } else if (estaCaducado) {
                                                                                                return (
                                                                                                    <div className="flex items-center gap-2 mt-1">
                                                                                                        <Calendar className="w-4 h-4 text-orange-600 dark:text-orange-400" />
                                                                                                        <span className="text-sm font-medium text-orange-700 dark:text-orange-300">
                                                                                                            Documento Caducado
                                                                                                        </span>
                                                                                                        <span className="text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 px-2 py-1 rounded">
                                                                                                            Vencido - Requiere Renovación
                                                                                                        </span>
                                                                                                    </div>
                                                                                                );
                                                                                            } else {
                                                                                                return (
                                                                                                    <div className="flex items-center gap-2 mt-1">
                                                                                                        <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                                                                                                        <span className="text-sm font-medium text-green-700 dark:text-green-300">
                                                                                                            Documento Vigente
                                                                                                        </span>
                                                                                                        {archivo.validacionIA && (
                                                                                                            <span className="text-xs bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 px-2 py-1 rounded">
                                                                                                                Aprobado ({archivo.validacionIA.porcentaje}%)
                                                                                                            </span>
                                                                                                        )}
                                                                                                    </div>
                                                                                                );
                                                                                            }
                                                                                        })()}
                                                                                        {/* 🖼️ PREVIEW DEL PDF MEJORADO Y GRANDE */}
                                                                                        <div className="mt-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden">
                                                                                            {/* Header del preview */}
                                                                                            <div className="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                                                                                <div className="flex items-center justify-between">
                                                                                                    <div className="flex items-center gap-3">
                                                                                                        <div className="p-2 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                                                                                                            <FileText className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                                                                                        </div>
                                                                                                        <div>
                                                                                                            <h3 className="font-semibold text-gray-800 dark:text-gray-200">Vista Previa del Documento</h3>
                                                                                                            <p className="text-xs text-gray-500 dark:text-gray-400">{archivo.nombre}</p>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                    <Button
                                                                                                        variant="outline"
                                                                                                        size="sm"
                                                                                                        onClick={() => window.open(`/documentos/file/${archivo.file_hash_sha256}`, '_blank')}
                                                                                                        className="gap-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm"
                                                                                                    >
                                                                                                        <Eye className="w-4 h-4" />
                                                                                                        Abrir Completo
                                                                                                    </Button>
                                                                                                </div>
                                                                                            </div>

                                                                                            {/* Contenedor del iframe mejorado y más grande */}
                                                                                            <div className="relative bg-gray-50 dark:bg-gray-900">
                                                                                                <iframe
                                                                                                    src={`/documentos/archivo/${archivo.id}#toolbar=1&navpanes=0&scrollbar=1&view=FitH&zoom=125`}
                                                                                                    className="w-full h-[700px] bg-white dark:bg-gray-900"
                                                                                                    title={`Preview de ${archivo.nombre}`}
                                                                                                    style={{
                                                                                                        backgroundColor: '#ffffff',
                                                                                                        border: 'none',
                                                                                                        borderRadius: '0'
                                                                                                    }}
                                                                                                    onError={() => {
                                                                                                        console.log('Error cargando preview del PDF');
                                                                                                    }}
                                                                                                    loading="lazy"
                                                                                                    allowFullScreen
                                                                                                />

                                                                                                {/* Overlay informativo mejorado */}
                                                                                                <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 hover:opacity-100 transition-all duration-300 pointer-events-none">
                                                                                                    <div className="text-center text-white bg-gray-900 dark:bg-gray-800 bg-opacity-80 p-6 rounded-lg backdrop-blur-sm">
                                                                                                        <FileText className="w-16 h-16 mx-auto mb-4 text-blue-400" />
                                                                                                        <p className="text-lg font-medium mb-2">Documento PDF Validado</p>
                                                                                                        <p className="text-sm text-gray-300 mb-4">Haz clic para abrir en una nueva ventana</p>
                                                                                                        <Button
                                                                                                            variant="secondary"
                                                                                                            onClick={() => window.open(`/documentos/file/${archivo.file_hash_sha256}`, '_blank')}
                                                                                                            className="gap-2 pointer-events-auto bg-white text-gray-900 hover:bg-gray-100"
                                                                                                        >
                                                                                                            <Eye className="w-4 h-4" />
                                                                                                            Ver en Nueva Ventana
                                                                                                        </Button>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>

                                                                                            {/* Footer con información del documento */}
                                                                                            <div className="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                                                                                                <div className="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                                                                                    <div className="flex items-center gap-4 text-xs">
                                                                                                        <span>
                                                                                                            {formatDocumentInfo(archivo)}
                                                                                                        </span>
                                                                                                    </div>

                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </>
                                                                                )}

                                                                                {archivo.estado === 'rechazado' && (
                                                                                    <div className="flex flex-col gap-2 mt-1">
                                                                                        <div className="flex items-center justify-between">
                                                                                            <div className="flex items-center gap-2">
                                                                                                <AlertCircle className="w-4 h-4 text-red-600" />
                                                                                                <span className="text-sm font-medium text-red-700">
                                                                                                    Documento Rechazado
                                                                                                </span>
                                                                                            </div>
                                                                                            {archivo.validacionIA && (
                                                                                                <Button
                                                                                                    variant="ghost"
                                                                                                    size="sm"
                                                                                                    onClick={() => mostrarDetallesRechazo(archivo, selectedDocumento.concepto)}
                                                                                                    className="text-red-600 hover:text-red-800 hover:bg-red-100 h-6 px-2"
                                                                                                >
                                                                                                    <span className="text-xs">Ver detalles</span>
                                                                                                </Button>
                                                                                            )}
                                                                                        </div>
                                                                                        {archivo.validacionIA && (
                                                                                            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                                                                                                <p className="text-sm text-red-800 font-medium mb-1">
                                                                                                    Motivo del rechazo:
                                                                                                </p>
                                                                                                <p className="text-xs text-red-700">
                                                                                                    {archivo.validacionIA.razon}
                                                                                                </p>
                                                                                                {archivo.validacionIA.porcentaje !== undefined && (
                                                                                                    <p className="text-xs text-red-600 mt-1">
                                                                                                        Coincidencia: {archivo.validacionIA.porcentaje}%
                                                                                                    </p>
                                                                                                )}
                                                                                            </div>
                                                                                        )}
                                                                                    </div>
                                                                                )}

                                                                                {archivo.estado === 'error' && (
                                                                                    <div className="flex items-center gap-2 mt-1">
                                                                                        <AlertCircle className="w-4 h-4 text-red-600" />
                                                                                        <span className="text-sm font-medium text-red-700">
                                                                                            {archivo.mensaje || 'Error en proceso'}
                                                                                        </span>
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                </div>
                                                            );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Archivos seleccionados para subir */}
                                                {(selectedFiles[getDocumentKey(selectedDocumento)] || []).length > 0 && (
                                                    <div>
                                                        <h4 className="font-semibold mb-3 dark:text-gray-200">
                                                            Archivos seleccionados ({(selectedFiles[getDocumentKey(selectedDocumento)] || []).length})
                                                        </h4>
                                                        <div className="space-y-2">
                                                            {(selectedFiles[getDocumentKey(selectedDocumento)] || []).map((file, index) => (
                                                                <div
                                                                    key={index}
                                                                    className="flex items-center justify-between p-3 bg-primary/5 dark:bg-primary/10 rounded-lg border border-primary/20 dark:border-primary/30"
                                                                >
                                                                    <div className="flex items-center gap-3">
                                                                        <FileText className="w-5 h-5 text-primary" />
                                                                        <div>
                                                                            <p className="text-sm font-medium dark:text-gray-200">{file.name}</p>
                                                                            <p className="text-xs text-muted-foreground dark:text-gray-400">
                                                                                {formatFileSize(file.size)}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => removeSelectedFile(getDocumentKey(selectedDocumento), index)}
                                                                        className="text-destructive hover:text-destructive dark:text-red-400 dark:hover:text-red-300"
                                                                    >
                                                                        <X className="w-4 h-4" />
                                                                    </Button>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <div className="flex gap-2 mt-4">
                                                            <Button onClick={() => uploadFiles(getDocumentKey(selectedDocumento))}>
                                                                <Upload className="w-4 h-4 mr-2" />
                                                                Subir Archivos ({(selectedFiles[getDocumentKey(selectedDocumento)] || []).length})
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                onClick={() => setSelectedFiles(prev => ({
                                                                    ...prev,
                                                                    [getDocumentKey(selectedDocumento)]: []
                                                                }))}
                                                            >
                                                                Cancelar
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Zona de subida - No mostrar si está vigente (pero sí si está caducado) */}
                                                {(normalizeEstado(getEstadoRealDocumento(selectedDocumento)) !== 'vigente' || isDocumentoCaducado(selectedDocumento)) && puedeSubirArchivos(selectedDocumento) ? (
                                                    <div
                                                        className={`
                                                border-2 border-dashed rounded-lg p-8 text-center transition-colors
                                                ${dragOver === getDocumentKey(selectedDocumento)
                                                                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                                                                : 'border-muted-foreground/25 hover:border-muted-foreground/50 dark:border-gray-700 dark:hover:border-gray-600'
                                                            }
                                            `}
                                                        onDragOver={(e) => handleDragOver(e, getDocumentKey(selectedDocumento))}
                                                        onDragLeave={handleDragLeave}
                                                        onDrop={(e) => handleDrop(e, getDocumentKey(selectedDocumento))}
                                                    >
                                                        <Upload className={`
                                                w-12 h-12 mx-auto mb-4
                                                ${dragOver === getDocumentKey(selectedDocumento) ? 'text-primary' : 'text-muted-foreground dark:text-gray-400'}
                                            `} />
                                                        <h3 className="font-semibold mb-2 dark:text-gray-200">
                                                            Arrastra archivos aquí
                                                        </h3>
                                                        <p className="text-muted-foreground dark:text-gray-400 mb-4 text-sm">
                                                            o haz clic para seleccionar archivos
                                                        </p>
                                                        <input
                                                            type="file"
                                                            id={`file-input-${getDocumentKey(selectedDocumento)}`}
                                                            className="hidden"
                                                            multiple
                                                            accept=".pdf"
                                                            onChange={(e) => handleFileInputChange(e, getDocumentKey(selectedDocumento))}
                                                        />
                                                        <Button
                                                            variant="outline"
                                                            onClick={() => {
                                                                const input = document.getElementById(`file-input-${getDocumentKey(selectedDocumento)}`) as HTMLInputElement;
                                                                input?.click();
                                                            }}
                                                        >
                                                            <Plus className="w-4 h-4 mr-2" />
                                                            Seleccionar Archivos
                                                        </Button>
                                                        <p className="text-xs text-muted-foreground dark:text-gray-400 mt-3">
                                                            Formatos: PDF
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <div className="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-8 text-center bg-gray-50 dark:bg-gray-800/50">
                                                        <div className="flex items-center justify-center mb-4">
                                                            {(normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'vigente' && !isDocumentoCaducado(selectedDocumento)) ? (
                                                                <CheckCircle className="w-12 h-12 text-green-600 dark:text-green-400" />
                                                            ) : normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'rechazado' ? (
                                                                <AlertCircle className="w-12 h-12 text-red-600 dark:text-red-400" />
                                                            ) : (normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'caducado' || isDocumentoCaducado(selectedDocumento)) ? (
                                                                <Calendar className="w-12 h-12 text-orange-600 dark:text-orange-400" />
                                                            ) : (
                                                                <AlertCircle className="w-12 h-12 text-gray-400" />
                                                            )}
                                                        </div>
                                                        <h3 className="font-semibold mb-2 text-gray-700 dark:text-gray-200">
                                                            {(normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'vigente' && !isDocumentoCaducado(selectedDocumento))
                                                                ? 'Documento Aprobado'
                                                                : normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'rechazado'
                                                                    ? 'Documento Rechazado - Puede Subir Nuevo'
                                                                    : (normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'caducado' || isDocumentoCaducado(selectedDocumento))
                                                                        ? 'Documento Caducado - Puede Subir Nuevo'
                                                                        : 'Subida Bloqueada'
                                                            }
                                                        </h3>
                                                        <p className="text-gray-600 dark:text-gray-400 text-sm mb-4">
                                                            {getMensajeEstado(selectedDocumento)}
                                                        </p>
                                                        {(normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'vigente' && !isDocumentoCaducado(selectedDocumento)) && (
                                                            <div className="bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-3 text-green-800 dark:text-green-400">
                                                                <p className="text-sm font-medium">
                                                                    Este documento ha sido validado y aprobado.
                                                                </p>
                                                                <p className="text-xs mt-1 text-green-700 dark:text-green-400">
                                                                    No se pueden realizar más cambios a menos que sea rechazado por un administrador.
                                                                </p>
                                                            </div>
                                                        )}
                                                        {normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'rechazado' && (
                                                            <div className="bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-3 text-red-800 dark:text-red-400">
                                                                <p className="text-sm font-medium">
                                                                    Este documento fue rechazado por un administrador.
                                                                </p>
                                                                <p className="text-xs mt-1 text-red-700 dark:text-red-400">
                                                                    Por favor, revise los comentarios y suba una nueva versión.
                                                                </p>
                                                            </div>
                                                        )}
                                                        {(normalizeEstado(getEstadoRealDocumento(selectedDocumento)) === 'caducado' || isDocumentoCaducado(selectedDocumento)) && (
                                                            <div className="bg-orange-100 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 rounded-lg p-3 text-orange-800 dark:text-orange-400">
                                                                <p className="text-sm font-medium">
                                                                    Este documento ha caducado por vencimiento de fecha.
                                                                </p>
                                                                <p className="text-xs mt-1 text-orange-700 dark:text-orange-400">
                                                                    Puede subir una nueva versión actualizada del documento.
                                                                </p>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ) : (
                                        <Card className="h-full dark:bg-gray-800 dark:border-gray-700">
                                            <CardContent className="flex items-center justify-center h-full">
                                                <div className="text-center">
                                                    <FileText className="w-12 h-12 text-muted-foreground dark:text-gray-400 mx-auto mb-4" />
                                                    <h3 className="font-semibold mb-2 dark:text-gray-200">Selecciona un documento</h3>
                                                    <p className="text-muted-foreground dark:text-gray-400 text-sm">
                                                        Elige un documento de la lista para comenzar a subir archivos
                                                    </p>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
};

export default DocumentUploadPage;
