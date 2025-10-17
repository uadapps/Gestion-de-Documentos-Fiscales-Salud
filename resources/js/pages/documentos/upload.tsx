import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    Filter
} from 'lucide-react';

interface DocumentoRequerido {
    id: string;
    concepto: string;
    descripcion: string;
    fechaLimite: string;
    estado: 'pendiente' | 'subido' | 'revisado' | 'rechazado';
    archivos: ArchivoSubido[];
    obligatorio: boolean;
}

interface ArchivoSubido {
    id: string;
    nombre: string;
    tamaño: number;
    fechaSubida: string;
    estado: 'subiendo' | 'completado' | 'error';
    progreso: number;
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
        estado: 'revisado',
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
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'subido':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'revisado':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'rechazado':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const getEstadoIcon = (estado: string) => {
    switch (estado) {
        case 'pendiente':
            return <Clock className="w-4 h-4" />;
        case 'subido':
            return <Upload className="w-4 h-4" />;
        case 'revisado':
            return <CheckCircle className="w-4 h-4" />;
        case 'rechazado':
            return <AlertCircle className="w-4 h-4" />;
        default:
            return <FileText className="w-4 h-4" />;
    }
};

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const DocumentUploadPage: React.FC = () => {
    const [documentos, setDocumentos] = useState<DocumentoRequerido[]>(documentosRequeridos);
    const [dragOver, setDragOver] = useState<string | null>(null);
    const [selectedFiles, setSelectedFiles] = useState<{ [key: string]: File[] }>({});
    const [selectedDocumento, setSelectedDocumento] = useState<DocumentoRequerido | null>(documentos[0]);
    const [filtroEstado, setFiltroEstado] = useState<string>('todos');
    const [busqueda, setBusqueda] = useState<string>('');

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
        setSelectedFiles(prev => ({
            ...prev,
            [documentoId]: [...(prev[documentoId] || []), ...files]
        }));
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>, documentoId: string) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            handleFileSelection(files, documentoId);
        }
    };

    const removeSelectedFile = (documentoId: string, fileIndex: number) => {
        setSelectedFiles(prev => ({
            ...prev,
            [documentoId]: prev[documentoId]?.filter((_, index) => index !== fileIndex) || []
        }));
    };

    const uploadFiles = async (documentoId: string) => {
        const files = selectedFiles[documentoId];
        if (!files || files.length === 0) return;

        // Aquí iría la lógica de subida real
        console.log('Subiendo archivos para documento:', documentoId, files);

        // Simular subida exitosa
        setTimeout(() => {
            setSelectedFiles(prev => ({
                ...prev,
                [documentoId]: []
            }));
        }, 2000);
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

    // Filtrar documentos
    const documentosFiltrados = documentos.filter(doc => {
        const coincideBusqueda = doc.concepto.toLowerCase().includes(busqueda.toLowerCase()) ||
                                doc.descripcion.toLowerCase().includes(busqueda.toLowerCase());
        const coincidefiltro = filtroEstado === 'todos' || doc.estado === filtroEstado;
        return coincideBusqueda && coincidefiltro;
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Documentos', href: '/documentos' },
                { title: 'Subir Documentos', href: '/documentos/upload' }
            ]}
        >
            <Head title="Subida de Documentos" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                        Subir Documentos Fiscales
                    </h1>
                    <p className="text-muted-foreground">
                        Selecciona un documento de la lista para subir los archivos requeridos
                    </p>
                </div>

                {/* Estadísticas rápidas */}
                <div className="grid gap-4 md:grid-cols-4 mb-6">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-2">
                                <FileText className="w-4 h-4 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Total</p>
                                    <p className="text-2xl font-bold">{documentos.length}</p>
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
                                    <p className="text-2xl font-bold">{documentos.filter(d => d.estado === 'pendiente').length}</p>
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
                                    <p className="text-2xl font-bold">{documentos.filter(d => d.estado === 'revisado').length}</p>
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
                                    <p className="text-2xl font-bold">{documentos.filter(d => d.estado === 'rechazado').length}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Diseño de dos columnas */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full">
                    {/* Columna izquierda: Lista de documentos */}
                    <div className="lg:col-span-1">
                        <Card className="h-full">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-lg">Documentos Requeridos</CardTitle>

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
                            </CardHeader>

                            <CardContent className="p-0">
                                <div className="max-h-[600px] overflow-y-auto">
                                    {documentosFiltrados.map((documento) => {
                                        const daysUntilDeadline = getDaysUntilDeadline(documento.fechaLimite);
                                        const isSelected = selectedDocumento?.id === documento.id;

                                        return (
                                            <div
                                                key={documento.id}
                                                className={`p-4 border-b cursor-pointer transition-colors hover:bg-muted/50 ${
                                                    isSelected ? 'bg-primary/10 border-l-4 border-l-primary' : ''
                                                }`}
                                                onClick={() => setSelectedDocumento(documento)}
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
                                                    <Badge className={getEstadoColor(documento.estado)} variant="outline">
                                                        {getEstadoIcon(documento.estado)}
                                                        <span className="ml-1 capitalize text-xs">{documento.estado}</span>
                                                    </Badge>
                                                </div>

                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Calendar className="w-3 h-3" />
                                                    <span>{formatDate(documento.fechaLimite)}</span>
                                                    <span className={`font-medium ${getDeadlineColor(daysUntilDeadline)}`}>
                                                        ({daysUntilDeadline >= 0 ? `${daysUntilDeadline}d` : `${Math.abs(daysUntilDeadline)}d vencido`})
                                                    </span>
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
                            <Card className="h-full">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-xl">{selectedDocumento.concepto}</CardTitle>
                                            <p className="text-muted-foreground mt-1">{selectedDocumento.descripcion}</p>
                                        </div>
                                        <Badge className={getEstadoColor(selectedDocumento.estado)}>
                                            <div className="flex items-center gap-1">
                                                {getEstadoIcon(selectedDocumento.estado)}
                                                <span className="capitalize">{selectedDocumento.estado}</span>
                                            </div>
                                        </Badge>
                                    </div>

                                    <div className="flex items-center gap-4 text-sm pt-2">
                                        <div className="flex items-center gap-1 text-muted-foreground">
                                            <Calendar className="w-4 h-4" />
                                            <span>Vence: {formatDate(selectedDocumento.fechaLimite)}</span>
                                        </div>
                                        {selectedDocumento.obligatorio && (
                                            <Badge variant="destructive" className="text-xs">
                                                Documento Obligatorio
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-6">
                                    {/* Archivos ya subidos */}
                                    {selectedDocumento.archivos.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold mb-3">
                                                Archivos subidos ({selectedDocumento.archivos.length})
                                            </h4>
                                            <div className="space-y-2">
                                                {selectedDocumento.archivos.map((archivo) => (
                                                    <div
                                                        key={archivo.id}
                                                        className="flex items-center justify-between p-3 border rounded-lg"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <FileText className="w-5 h-5 text-muted-foreground" />
                                                            <div>
                                                                <p className="text-sm font-medium">{archivo.nombre}</p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {formatFileSize(archivo.tamaño)} • {formatDate(archivo.fechaSubida)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="w-4 h-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="sm">
                                                                <Download className="w-4 h-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                                <Trash2 className="w-4 h-4" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Archivos seleccionados para subir */}
                                    {(selectedFiles[selectedDocumento.id] || []).length > 0 && (
                                        <div>
                                            <h4 className="font-semibold mb-3">
                                                Archivos seleccionados ({(selectedFiles[selectedDocumento.id] || []).length})
                                            </h4>
                                            <div className="space-y-2">
                                                {(selectedFiles[selectedDocumento.id] || []).map((file, index) => (
                                                    <div
                                                        key={index}
                                                        className="flex items-center justify-between p-3 bg-primary/5 rounded-lg border border-primary/20"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <FileText className="w-5 h-5 text-primary" />
                                                            <div>
                                                                <p className="text-sm font-medium">{file.name}</p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {formatFileSize(file.size)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeSelectedFile(selectedDocumento.id, index)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <X className="w-4 h-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="flex gap-2 mt-4">
                                                <Button onClick={() => uploadFiles(selectedDocumento.id)}>
                                                    <Upload className="w-4 h-4 mr-2" />
                                                    Subir Archivos ({(selectedFiles[selectedDocumento.id] || []).length})
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    onClick={() => setSelectedFiles(prev => ({
                                                        ...prev,
                                                        [selectedDocumento.id]: []
                                                    }))}
                                                >
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </div>
                                    )}

                                    {/* Zona de subida */}
                                    {selectedDocumento.estado !== 'revisado' && (
                                        <div
                                            className={`
                                                border-2 border-dashed rounded-lg p-8 text-center transition-colors
                                                ${dragOver === selectedDocumento.id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-muted-foreground/25 hover:border-muted-foreground/50'
                                                }
                                            `}
                                            onDragOver={(e) => handleDragOver(e, selectedDocumento.id)}
                                            onDragLeave={handleDragLeave}
                                            onDrop={(e) => handleDrop(e, selectedDocumento.id)}
                                        >
                                            <Upload className={`
                                                w-12 h-12 mx-auto mb-4
                                                ${dragOver === selectedDocumento.id ? 'text-primary' : 'text-muted-foreground'}
                                            `} />
                                            <h3 className="font-semibold mb-2">
                                                Arrastra archivos aquí
                                            </h3>
                                            <p className="text-muted-foreground mb-4 text-sm">
                                                o haz clic para seleccionar archivos
                                            </p>
                                            <input
                                                type="file"
                                                id={`file-input-${selectedDocumento.id}`}
                                                className="hidden"
                                                multiple
                                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                onChange={(e) => handleFileInputChange(e, selectedDocumento.id)}
                                            />
                                            <Button
                                                variant="outline"
                                                onClick={() => {
                                                    const input = document.getElementById(`file-input-${selectedDocumento.id}`) as HTMLInputElement;
                                                    input?.click();
                                                }}
                                            >
                                                <Plus className="w-4 h-4 mr-2" />
                                                Seleccionar Archivos
                                            </Button>
                                            <p className="text-xs text-muted-foreground mt-3">
                                                Formatos: PDF, DOC, DOCX, JPG, PNG (máx. 10MB)
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="h-full">
                                <CardContent className="flex items-center justify-center h-full">
                                    <div className="text-center">
                                        <FileText className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                                        <h3 className="font-semibold mb-2">Selecciona un documento</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Elige un documento de la lista para comenzar a subir archivos
                                        </p>
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

export default DocumentUploadPage;
