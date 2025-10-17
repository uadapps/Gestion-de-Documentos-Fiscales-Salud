import { Link, router } from '@inertiajs/react';
import { ShieldX, ArrowLeft, LogOut } from 'lucide-react';

interface AccessDeniedProps {
    message: string;
    subtitle: string;
}

export default function AccessDenied({ message, subtitle }: AccessDeniedProps) {
    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-red-50 to-red-100 flex items-center justify-center p-4">
            <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
                <div className="mb-6">
                    <ShieldX className="mx-auto h-16 w-16 text-red-500" />
                </div>

                <h1 className="text-2xl font-bold text-gray-900 mb-4">
                    Acceso Denegado
                </h1>

                <p className="text-gray-600 mb-2">
                    {message}
                </p>

                <p className="text-sm text-gray-500 mb-8">
                    {subtitle}
                </p>

                <div className="space-y-3">
                    <Link
                        href="/dashboard"
                        className="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Volver al Dashboard
                    </Link>

                    <button
                        onClick={handleLogout}
                        className="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        Cerrar Sesión
                    </button>
                </div>

                <div className="mt-8 pt-6 border-t border-gray-200">
                    <p className="text-xs text-gray-400">
                        Si crees que esto es un error, contacta al administrador del sistema.
                    </p>
                </div>
            </div>
        </div>
    );
}
