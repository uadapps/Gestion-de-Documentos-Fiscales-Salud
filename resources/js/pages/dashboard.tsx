import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Upload, FileText } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Welcome Section */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold tracking-tight mb-2">
                        Panel de Campus - Documentos Fiscales
                    </h1>
                    <p className="text-muted-foreground">
                        Gestiona y sube los documentos fiscales requeridos para tu campus
                    </p>
                </div>

                {/* Quick Actions para Campus */}
                <div className="grid gap-6 md:grid-cols-2 mb-8">
                    <Card className="cursor-pointer transition-colors hover:bg-muted/50">
                        <CardContent className="p-6">
                            <Link href="/documentos/upload" className="flex flex-col items-center text-center">
                                <div className="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4">
                                    <Upload className="w-6 h-6 text-primary" />
                                </div>
                                <h3 className="font-semibold mb-2">Subir Documentos</h3>
                                <p className="text-sm text-muted-foreground">
                                    Carga los documentos fiscales requeridos por tu campus
                                </p>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className="cursor-pointer transition-colors hover:bg-muted/50">
                        <CardContent className="p-6">
                            <div className="flex flex-col items-center text-center">
                                <div className="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4">
                                    <FileText className="w-6 h-6 text-primary" />
                                </div>
                                <h3 className="font-semibold mb-2">Mis Documentos</h3>
                                <p className="text-sm text-muted-foreground">
                                    Consulta el estatus y historial de tus documentos
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Statistics Section */}
                <div className="grid gap-4 md:grid-cols-3 mb-8">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Pendientes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold mb-1">3</div>
                            <p className="text-xs text-muted-foreground">
                                Documentos que requieren atención
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Aprobados</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold mb-1">12</div>
                            <p className="text-xs text-muted-foreground">
                                Documentos verificados
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold mb-1">15</div>
                            <p className="text-xs text-muted-foreground">
                                Total de documentos
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Activity Section */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Actividad Reciente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between p-3 rounded-lg border">
                                <div className="flex items-center gap-3">
                                    <div className="w-2 h-2 bg-primary rounded-full"></div>
                                    <span className="text-sm">Documento "RFC Actualizado" aprobado</span>
                                </div>
                                <span className="text-xs text-muted-foreground">Hace 2 horas</span>
                            </div>
                            <div className="flex items-center justify-between p-3 rounded-lg border">
                                <div className="flex items-center gap-3">
                                    <div className="w-2 h-2 bg-primary rounded-full"></div>
                                    <span className="text-sm">Nuevo documento "Acta Constitutiva" subido</span>
                                </div>
                                <span className="text-xs text-muted-foreground">Hace 5 horas</span>
                            </div>
                            <div className="flex items-center justify-between p-3 rounded-lg border">
                                <div className="flex items-center gap-3">
                                    <div className="w-2 h-2 bg-primary rounded-full"></div>
                                    <span className="text-sm">Documento "Estados Financieros" en revisión</span>
                                </div>
                                <span className="text-xs text-muted-foreground">Hace 1 día</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
