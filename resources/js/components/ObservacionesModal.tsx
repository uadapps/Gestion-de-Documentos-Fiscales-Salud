import React, { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MessageSquare, Loader2, AlertCircle, Check, X, Clock } from 'lucide-react';

interface Observacion {
    id: number;
    campus_id: string;
    documento_informacion_id: number | null;
    tipo_observacion: string | null;
    observacion: string;
    estatus: 'pendiente' | 'atendido' | 'rechazado';
    creado_por: string | null;
    creado_por_nombre?: string | null;
    creado_en: string;
    actualizado_por: string | null;
    actualizado_en: string | null;
    activo: boolean;
}

interface ObservacionesModalProps {
    isOpen: boolean;
    onClose: () => void;
    documentoInformacionId?: number | null;
    campusId: string;
    documentoNombre?: string;
    isGlobalComment?: boolean; // Para comentarios generales del campus
    readOnly?: boolean; // Solo lectura, no permite crear observaciones
    canMarkAttended?: boolean; // Puede marcar observaciones como atendidas (solo rol 16)
    observacionesSistema?: string | null; // Observaciones del sistema
    observacionesArchivoSistema?: string | null; // Observaciones de archivo del sistema
}

export default function ObservacionesModal({
    isOpen,
    onClose,
    documentoInformacionId,
    campusId,
    documentoNombre,
    isGlobalComment = false,
    readOnly = false,
    canMarkAttended = false,
    observacionesSistema = null,
    observacionesArchivoSistema = null
}: ObservacionesModalProps) {
    const [observaciones, setObservaciones] = useState<Observacion[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [nuevaObservacion, setNuevaObservacion] = useState('');
    const [tipoObservacion, setTipoObservacion] = useState(
        isGlobalComment ? 'Observación general' : 'Observación'
    );
    const [error, setError] = useState('');

    // Cargar observaciones al abrir el modal
    useEffect(() => {
        if (isOpen) {
            // Establecer el tipo de observación correcto al abrir
            setTipoObservacion(isGlobalComment ? 'Observación general' : 'Observación');
            cargarObservaciones();
            // Marcar observaciones pendientes como atendidas al abrir el modal - solo si tiene permiso
            if (canMarkAttended) {
                marcarObservacionesComoAtendidas();
            }
        } else {
            // Resetear estado al cerrar
            setNuevaObservacion('');
            setTipoObservacion(isGlobalComment ? 'Observación general' : 'Observación');
            setError('');
        }
    }, [isOpen, documentoInformacionId, campusId, canMarkAttended, isGlobalComment]);

    const marcarObservacionesComoAtendidas = async () => {
        try {
            const url = isGlobalComment
                ? `/observaciones/campus/${campusId}/marcar-atendidas`
                : `/observaciones/documento/${documentoInformacionId}/marcar-atendidas`;

            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });
        } catch (err) {
            console.error('Error al marcar observaciones como atendidas:', err);
        }
    };

    const cargarObservaciones = async () => {
        setIsLoading(true);
        setError('');

        try {
            const url = isGlobalComment
                ? `/observaciones/campus/${campusId}`
                : `/observaciones/documento/${documentoInformacionId}`;

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            const data = await response.json();

            if (data.success) {
                setObservaciones(data.observaciones);
            } else {
                setError(data.message || 'Error al cargar observaciones');
            }
        } catch (err) {
            console.error('Error al cargar observaciones:', err);
            setError('Error de conexión al cargar observaciones');
        } finally {
            setIsLoading(false);
        }
    };

    const handleGuardarObservacion = async () => {
        if (!nuevaObservacion.trim()) {
            setError('La observación no puede estar vacía');
            return;
        }

        setIsSaving(true);
        setError('');

        try {
            const url = isGlobalComment
                ? '/observaciones/campus'
                : '/observaciones/documento';

            const payload = isGlobalComment
                ? {
                    campus_id: campusId,
                    tipo_observacion: tipoObservacion,
                    observacion: nuevaObservacion,
                    estatus: 'pendiente'
                }
                : {
                    documento_informacion_id: documentoInformacionId,
                    campus_id: campusId,
                    tipo_observacion: tipoObservacion,
                    observacion: nuevaObservacion,
                    estatus: 'pendiente'
                };

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                // Limpiar el formulario
                setNuevaObservacion('');
                setTipoObservacion(isGlobalComment ? 'Observación general' : 'Observación');

                // Recargar observaciones
                await cargarObservaciones();
            } else {
                setError(data.message || 'Error al guardar observación');
            }
        } catch (err) {
            console.error('Error al guardar observación:', err);
            setError('Error de conexión al guardar');
        } finally {
            setIsSaving(false);
        }
    };

    const handleActualizarEstatus = async (observacionId: number, nuevoEstatus: string) => {
        try {
            const response = await fetch(`/observaciones/${observacionId}/estatus`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ estatus: nuevoEstatus })
            });

            const data = await response.json();

            if (data.success) {
                await cargarObservaciones();
            }
        } catch (err) {
            console.error('Error al actualizar estatus:', err);
        }
    };

    const getEstatusColor = (estatus: string) => {
        switch (estatus) {
            case 'pendiente':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'atendido':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'rechazado':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const getEstatusIcon = (estatus: string) => {
        switch (estatus) {
            case 'pendiente':
                return <Clock className="h-3 w-3" />;
            case 'atendido':
                return <Check className="h-3 w-3" />;
            case 'rechazado':
                return <X className="h-3 w-3" />;
            default:
                return null;
        }
    };

    const getTipoObservacionColor = (tipo: string | null) => {
        if (!tipo) return 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-600';

        const tipoLower = tipo.toLowerCase();

        if (tipoLower.includes('observación')) {
            return 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 border-blue-200 dark:border-blue-800';
        } else if (tipoLower.includes('caducado') || tipoLower.includes('vencido')) {
            return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 border-red-200 dark:border-red-800';
        } else if (tipoLower.includes('falta')) {
            return 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400 border-orange-200 dark:border-orange-800';
        } else if (tipoLower.includes('revisar') || tipoLower.includes('revisión')) {
            return 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400 border-purple-200 dark:border-purple-800';
        } else if (tipoLower.includes('inconsistencia')) {
            return 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800';
        }

        return 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-600';
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MessageSquare className="h-5 w-5 text-blue-600" />
                        {isGlobalComment
                            ? 'Comentarios Generales del Campus'
                            : `Observaciones - ${documentoNombre || 'Documento'}`
                        }
                    </DialogTitle>
                    <DialogDescription>
                        {readOnly ? (
                            isGlobalComment
                                ? 'Consulta los comentarios generales sobre el campus'
                                : 'Consulta las observaciones o comentarios sobre este documento'
                        ) : (
                            isGlobalComment
                                ? 'Agregar comentarios generales sobre el campus'
                                : 'Agregar observaciones o comentarios sobre este documento'
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="overflow-y-auto flex-1 px-1">
                    {/* Formulario para nueva observación - Solo visible si no es readOnly */}
                    {!readOnly && (
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Tipo de observación</label>
                            <Select value={tipoObservacion} onValueChange={setTipoObservacion}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {isGlobalComment ? (
                                        <>
                                            <SelectItem value="Observación general">Observación general</SelectItem>
                                            <SelectItem value="Revisión general">Revisión general</SelectItem>
                                            <SelectItem value="Falta documentación">Falta documentación</SelectItem>
                                            <SelectItem value="Inconsistencia">Inconsistencia</SelectItem>
                                        </>
                                    ) : (
                                        <>
                                            <SelectItem value="Observación">Observación</SelectItem>
                                            <SelectItem value="Documento caducado">Documento caducado</SelectItem>
                                            <SelectItem value="Falta información">Falta información</SelectItem>
                                            <SelectItem value="Revisar datos">Revisar datos</SelectItem>
                                            <SelectItem value="Inconsistencia">Inconsistencia</SelectItem>
                                        </>
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Observación</label>
                            <Textarea
                                value={nuevaObservacion}
                                onChange={(e) => setNuevaObservacion(e.target.value)}
                                placeholder="Escriba su observación o comentario aquí..."
                                rows={4}
                                className="resize-none"
                            />
                        </div>

                        {error && (
                            <div className="flex items-center gap-2 text-sm text-red-600 bg-red-50 p-2 rounded">
                                <AlertCircle className="h-4 w-4" />
                                {error}
                            </div>
                        )}

                        <Button
                            onClick={handleGuardarObservacion}
                            disabled={isSaving || !nuevaObservacion.trim()}
                            className="w-full"
                        >
                            {isSaving ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Guardando...
                                </>
                            ) : (
                                <>
                                    <MessageSquare className="h-4 w-4 mr-2" />
                                    Guardar Observación
                                </>
                            )}
                        </Button>
                    </div>
                )}

                {/* Lista de observaciones existentes */}
                <div className="border-t pt-4">
                    <h4 className="text-sm font-semibold mb-3">
                        Observaciones anteriores ({observaciones.length + (observacionesSistema || observacionesArchivoSistema ? (observacionesSistema ? 1 : 0) + (observacionesArchivoSistema ? 1 : 0) : 0)})
                    </h4>

                    {isLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                        </div>
                    ) : (observaciones.length === 0 && !observacionesSistema && !observacionesArchivoSistema) ? (
                        <div className="text-center py-8 text-gray-500">
                            <MessageSquare className="h-12 w-12 mx-auto mb-2 text-gray-300" />
                            <p className="text-sm">No hay observaciones registradas</p>
                        </div>
                    ) : (
                        <div className="space-y-3 max-h-64 overflow-y-auto">
                            {/* Observaciones del sistema primero */}
                            {observacionesSistema && (
                                <div className="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <div className="flex items-start justify-between mb-2">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline" className="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 border-blue-300">
                                                Observación del Sistema
                                            </Badge>
                                            <Badge className="text-xs bg-gray-100 text-gray-800 border-gray-200">
                                                <span className="mr-1"><AlertCircle className="h-3 w-3" /></span>
                                                Sistema
                                            </Badge>
                                        </div>
                                    </div>

                                    <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                        {observacionesSistema}
                                    </p>

                                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Creado por: Sistema</span>
                                    </div>
                                </div>
                            )}

                            {observacionesArchivoSistema && (
                                <div className="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
                                    <div className="flex items-start justify-between mb-2">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline" className="text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-400 border-indigo-300">
                                                Observación de Archivo
                                            </Badge>
                                            <Badge className="text-xs bg-gray-100 text-gray-800 border-gray-200">
                                                <span className="mr-1"><AlertCircle className="h-3 w-3" /></span>
                                                Sistema
                                            </Badge>
                                        </div>
                                    </div>

                                    <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                        {observacionesArchivoSistema}
                                    </p>

                                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Creado por: Sistema</span>
                                    </div>
                                </div>
                            )}

                            {/* Observaciones de usuarios */}
                            {observaciones.map((obs) => (
                                <div
                                    key={obs.id}
                                    className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                                >
                                    <div className="flex items-start justify-between mb-2">
                                        <div className="flex items-center gap-2">
                                            <Badge className={`text-xs font-medium ${getTipoObservacionColor(obs.tipo_observacion)}`}>
                                                {obs.tipo_observacion}
                                            </Badge>
                                            <Badge className={`text-xs ${getEstatusColor(obs.estatus)}`}>
                                                <span className="mr-1">{getEstatusIcon(obs.estatus)}</span>
                                                {obs.estatus}
                                            </Badge>
                                        </div>
                                    </div>

                                    <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                        {obs.observacion}
                                    </p>

                                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>
                                            Creado por: {obs.creado_por_nombre || obs.creado_por || 'Sistema'}
                                        </span>
                                        <span>
                                            {new Date(obs.creado_en).toLocaleString('es-MX', {
                                                day: 'numeric',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
