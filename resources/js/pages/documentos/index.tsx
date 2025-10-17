import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    FileText,
    Calendar,
    CheckCircle,
    Clock,
    AlertTriangle,
    Download,
    Eye
} from 'lucide-react';

// Datos de ejemplo
const documentos = [
    {
        id: 1,
        nombre: 'RFC Campus 2024',
        concepto: 'Cédula de Identificación Fiscal',
        fechaSubida: '2024-10-15',
        fechaVencimiento: '2025-01-15',
        estado: 'aprobado',
        tamaño: '1.2 MB'
    },
    {
        id: 2,
        nombre: 'Comprobante Domicilio Oct 2024',
        concepto: 'Comprobante de Domicilio',
        fechaSubida: '2024-10-10',
        fechaVencimiento: '2024-12-10',
        estado: 'pendiente',
        tamaño: '856 KB'
    },
    {
        id: 3,
        nombre: 'Estados Financieros 2023',
        concepto: 'Estados Financieros',
        fechaSubida: '2024-10-08',
        fechaVencimiento: '2024-12-31',
        estado: 'revision',
        tamaño: '3.4 MB'
    },
    {
        id: 4,
        nombre: 'Constancia SAT 2024',
        concepto: 'Constancia de Situación Fiscal',
        fechaSubida: '2024-09-28',
        fechaVencimiento: '2024-11-20',
        estado: 'rechazado',
        tamaño: '945 KB'
    }
];

const getEstadoBadge = (estado: string) => {
    switch (estado) {
        case 'aprobado':
            return <Badge className="bg-green-100 text-green-800 hover:bg-green-100"><CheckCircle className="w-3 h-3 mr-1" />Aprobado</Badge>;
        case 'pendiente':
            return <Badge className="bg-yellow-100 text-yellow-800 hover:bg-yellow-100"><Clock className="w-3 h-3 mr-1" />Pendiente</Badge>;
        case 'revision':
            return <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-100"><Eye className="w-3 h-3 mr-1" />En Revisión</Badge>;
        case 'rechazado':
            return <Badge className="bg-red-100 text-red-800 hover:bg-red-100"><AlertTriangle className="w-3 h-3 mr-1" />Rechazado</Badge>;
        default:
            return <Badge variant="outline">Desconocido</Badge>;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

export default function MisDocumentos() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Mis Documentos', href: '/documentos' }
            ]}
        >
            <Head title="Mis Documentos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                        Mis Documentos
                    </h1>
                    <p className="text-muted-foreground">
                        Consulta el estatus y gestiona todos tus documentos fiscales
                    </p>
                </div>

                {/* Resumen rápido */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-2">
                                <CheckCircle className="w-4 h-4 text-green-600" />
                                <div>
                                    <p className="text-sm font-medium">Aprobados</p>
                                    <p className="text-2xl font-bold">1</p>
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
                                    <p className="text-2xl font-bold">1</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-2">
                                <Eye className="w-4 h-4 text-blue-600" />
                                <div>
                                    <p className="text-sm font-medium">En Revisión</p>
                                    <p className="text-2xl font-bold">1</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="w-4 h-4 text-red-600" />
                                <div>
                                    <p className="text-sm font-medium">Rechazados</p>
                                    <p className="text-2xl font-bold">1</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Lista de documentos */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="w-5 h-5" />
                            Documentos Subidos
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {documentos.map((documento) => (
                                <div
                                    key={documento.id}
                                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <FileText className="w-4 h-4 text-muted-foreground" />
                                            <h3 className="font-medium">{documento.nombre}</h3>
                                            {getEstadoBadge(documento.estado)}
                                        </div>
                                        <p className="text-sm text-muted-foreground mb-1">
                                            {documento.concepto}
                                        </p>
                                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                            <span className="flex items-center gap-1">
                                                <Calendar className="w-3 h-3" />
                                                Subido: {formatDate(documento.fechaSubida)}
                                            </span>
                                            <span>Tamaño: {documento.tamaño}</span>
                                            <span>Vence: {formatDate(documento.fechaVencimiento)}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm">
                                            <Eye className="w-4 h-4 mr-1" />
                                            Ver
                                        </Button>
                                        <Button variant="outline" size="sm">
                                            <Download className="w-4 h-4 mr-1" />
                                            Descargar
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
