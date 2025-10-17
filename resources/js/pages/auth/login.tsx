import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Eye, EyeOff, Lock, Heart, Shield, Users,FileSearch,Database,Clock } from 'lucide-react';
import Plasma from '@/components/Plasma';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [isLoaded, setIsLoaded] = useState(false);
    const [showLight, setShowLight] = useState(false);
    const [currentFeature, setCurrentFeature] = useState(0);

    const features = [
     { icon: FileSearch, title: "Validación Inteligente", description: "Revisión automática de documentos y licencias" },
{ icon: Database, title: "Almacenamiento Seguro", description: "Respaldo cifrado y acceso controlado" },
{ icon: Clock, title: "Monitoreo en Tiempo Real", description: "Seguimiento continuo de vencimientos y actualizaciones" }

    ];

    useEffect(() => {
        // Keyhole aparece inmediatamente sin delay
        setShowLight(true);

        // Después de 2 segundos, el keyhole comienza a abrirse
        const openTimer = setTimeout(() => {
            setIsLoaded(true);
        }, 500);

        return () => {
            clearTimeout(openTimer);
        };
    }, []);    useEffect(() => {
        // Preload background image
        const img = new Image();
        img.src = '/medical-bg.svg';
    }, []);

    useEffect(() => {
        // Auto-rotate features every 3 seconds
        const interval = setInterval(() => {
            setCurrentFeature((prev) => (prev + 1) % features.length);
        }, 3000);

        return () => clearInterval(interval);
    }, [features.length]);

    return (
        <>
            <Head title="Iniciar Sesión" />

            {/* Picaporte Reveal Loader */}
            {showLight && !isLoaded && (
                <div className="fixed inset-0 z-50 overflow-hidden bg-black">

                    {/* Keyhole opaco que bloquea todo */}
                    {showLight && !isLoaded && (
                        <>
                            {/* Capa negra que cubre todo */}
                            <div className="absolute inset-0 bg-black z-10" />

                            {/* Ondas de pulso concéntricas - minimalista pero creativo */}

                            {/* Pulsación 1 - Latido principal intenso */}
                            <div
                                className="absolute inset-0 z-18"
                                style={{
                                    maskImage: 'url(\'/img/lock.svg\')',
                                    WebkitMaskImage: 'url(\'/img/lock.svg\')',
                                    maskPosition: 'center 50%',
                                    WebkitMaskPosition: 'center 50%',
                                    maskRepeat: 'no-repeat',
                                    WebkitMaskRepeat: 'no-repeat',
                                    maskSize: '25px',
                                    WebkitMaskSize: '25px',
                                    background: 'radial-gradient(circle, rgba(255,255,255,1) 0%, rgba(255,255,255,0.8) 60%, transparent 100%)',
                                    filter: 'blur(1px)',
                                    animation: 'keyframes-expand 1.5s ease-out infinite',
                                    animationDelay: '0s'
                                }}
                            />

                            {/* Pulsación 2 - Eco del latido */}
                            <div
                                className="absolute inset-0 z-19"
                                style={{
                                    maskImage: 'url(\'/img/lock.svg\')',
                                    WebkitMaskImage: 'url(\'/img/lock.svg\')',
                                    maskPosition: 'center 50%',
                                    WebkitMaskPosition: 'center 50%',
                                    maskRepeat: 'no-repeat',
                                    WebkitMaskRepeat: 'no-repeat',
                                    maskSize: '25px',
                                    WebkitMaskSize: '25px',
                                    background: 'radial-gradient(circle, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.6) 50%, transparent 80%)',
                                    filter: 'blur(1px)',
                                    animation: 'keyframes-expand 1.8s ease-out infinite',
                                    animationDelay: '0.2s'
                                }}
                            />

                            {/* Pulsación 3 - Onda exterior poderosa */}
                            <div
                                className="absolute inset-0 z-20"
                                style={{
                                    maskImage: 'url(\'/img/lock.svg\')',
                                    WebkitMaskImage: 'url(\'/img/lock.svg\')',
                                    maskPosition: 'center 50%',
                                    WebkitMaskPosition: 'center 50%',
                                    maskRepeat: 'no-repeat',
                                    WebkitMaskRepeat: 'no-repeat',
                                    maskSize: '25px',
                                    WebkitMaskSize: '25px',
                                    background: 'radial-gradient(circle, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 70%, transparent 100%)',
                                    filter: 'blur(2px)',
                                    animation: 'keyframes-expand 2.1s ease-out infinite',
                                    animationDelay: '0.4s'
                                }}
                            />

                            {/* Pulsación 4 - Onda masiva final */}
                            <div
                                className="absolute inset-0 z-21"
                                style={{
                                    maskImage: 'url(\'/img/lock.svg\')',
                                    WebkitMaskImage: 'url(\'/img/lock.svg\')',
                                    maskPosition: 'center 50%',
                                    WebkitMaskPosition: 'center 50%',
                                    maskRepeat: 'no-repeat',
                                    WebkitMaskRepeat: 'no-repeat',
                                    maskSize: '25px',
                                    WebkitMaskSize: '25px',
                                    background: 'radial-gradient(circle, rgba(255,255,255,0.7) 0%, rgba(255,255,255,0.3) 60%, transparent 90%)',
                                    filter: 'blur(2px)',
                                    animation: 'keyframes-expand 2.4s ease-out infinite',
                                    animationDelay: '0.6s'
                                }}
                            />

                            {/* Keyhole principal negro opaco */}
                            <div
                                className="absolute inset-0 z-30"
                                style={{
                                    maskImage: 'url(\'/img/lock.svg\')',
                                    WebkitMaskImage: 'url(\'/img/lock.svg\')',
                                    maskPosition: 'center 50%',
                                    WebkitMaskPosition: 'center 50%',
                                    maskRepeat: 'no-repeat',
                                    WebkitMaskRepeat: 'no-repeat',
                                    maskSize: '50px',
                                    WebkitMaskSize: '50px',
                                    background: 'white',
                                    animation: 'lightPulse 1.5s ease-in-out infinite alternate'
                                }}
                            />
                        </>
                    )}
                </div>
            )}

            {/* Background mask with keyhole effect */}
            <div
                id="background-mask"
                className={`absolute inset-0 min-h-screen w-full ${isLoaded ? 'unlocked' : ''}`}
            >
                {/* FULL Login interface that will be revealed */}
                <div className="min-h-screen flex">
                            {/* Left side - Hero Section with Plasma animated background */}
                            <div className="hidden lg:flex lg:w-3/5 relative overflow-hidden">
                                {/* Plasma WebGL Background */}
                                <Plasma
                                    color="#960a17"
                                    speed={0.7}
                                    direction="forward"
                                    scale={1.0}
                                    opacity={0.7}
                                    mouseInteractive={false}
                                />

                                {/* Overlay for better text readability */}
                                <div className="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/20"></div>

                                {/* Subtle floating particles for extra depth */}
                                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                                    {[...Array(8)].map((_, i) => {
                                        const size = Math.random() * 20 + 10;
                                        const leftPosition = Math.random() * 100;
                                        const duration = Math.random() * 8 + 15;
                                        const delay = Math.random() * -10;

                                        return (
                                            <div
                                                key={i}
                                                className="absolute rounded-full animate-float-bubble opacity-30"
                                                style={{
                                                    width: `${size}px`,
                                                    height: `${size}px`,
                                                    left: `${leftPosition}%`,
                                                    bottom: '-10%',
                                                    background: `radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.02))`,
                                                    backdropFilter: 'blur(2px)',
                                                    border: '1px solid rgba(255, 255, 255, 0.1)',
                                                    animationDuration: `${duration}s`,
                                                    animationDelay: `${delay}s`,
                                                }}
                                            />
                                        );
                                    })}
                                </div>

                                {/* Enhanced Content - Perfectamente Centrado */}
                                <div className="relative z-20 flex flex-col justify-center items-center text-white px-8 py-8 text-center min-h-screen w-full backdrop-blur-[1px]">
                                    <div className={`w-full max-w-4xl mx-auto transform transition-all duration-1000 ${isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'}`}>
                                        {/* Main Title with enhanced visibility over Plasma */}
                                        <div className="mb-12 text-center w-full">
                                            <h1 className="text-5xl font-bold mb-6 leading-tight text-center drop-shadow-lg">
                                                <span className="animate-slideInLeft block mb-3 text-center text-white">Sistema de Gestión</span>
                                                <span className="block text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-amber-400 to-yellow-300 animate-gradient-shift text-4xl text-center drop-shadow-lg filter-none"
                                                      style={{
                                                          filter: 'drop-shadow(0 4px 8px rgba(0,0,0,0.8))',
                                                          WebkitTextStroke: '2px rgba(0,0,0,0.3)'
                                                      }}>
                                                   de Documentos Fiscales
                                                </span>
                                            </h1>
                                            <div className="w-32 h-1.5 bg-gradient-to-r from-yellow-400 via-amber-500 to-yellow-400 mx-auto rounded-full animate-pulse shadow-lg shadow-yellow-400/60"></div>
                                        </div>

                                 {/*        <p className="text-xl mb-12 opacity-95 max-w-2xl leading-relaxed animate-fadeInUp mx-auto text-center text-white drop-shadow-md backdrop-blur-sm bg-black/10 rounded-2xl p-6 border border-white/20">
                                            Plataforma integral para la administración, validación y control de documentos legales y fiscales requeridos para los procesos de autorización institucional y de programas del área de la salud.
                                        </p> */}

                                        {/* Enhanced features grid with better contrast over Plasma */}
                                        <div className="grid gap-6 max-w-2xl mx-auto w-full">
                                            {features.map((feature, index) => {
                                                const Icon = feature.icon;
                                                const isActive = currentFeature === index;
                                                return (
                                                    <div
                                                        key={index}
                                                        className={`flex items-center space-x-6 p-6 rounded-2xl backdrop-blur-xl transition-all duration-1000 border ${
                                                            isActive
                                                                ? 'scale-105 opacity-100 translate-x-0 shadow-2xl bg-white/25 border-white/40 shadow-white/20'
                                                                : 'scale-98 opacity-85 translate-x-2 bg-white/15 border-white/20'
                                                        }`}
                                                        style={{
                                                            animationDelay: `${index * 0.2}s`,
                                                            boxShadow: isActive ? '0 8px 32px rgba(255,255,255,0.15), inset 0 1px 0 rgba(255,255,255,0.3)' : '0 4px 16px rgba(0,0,0,0.1)'
                                                        }}
                                                    >
                                                        <div className="flex-shrink-0">
                                                            <div className={`w-16 h-16 rounded-2xl flex items-center justify-center backdrop-blur-sm transition-all duration-1000 ${
                                                                isActive
                                                                    ? 'scale-110 bg-gradient-to-br from-white/40 to-white/20 shadow-lg'
                                                                    : 'scale-100 bg-gradient-to-br from-white/25 to-white/10'
                                                            }`}
                                                            style={{
                                                                boxShadow: isActive ? 'inset 0 1px 0 rgba(255,255,255,0.4), 0 4px 16px rgba(255,255,255,0.2)' : 'inset 0 1px 0 rgba(255,255,255,0.2)'
                                                            }}>
                                                                <Icon className={`w-8 h-8 text-white transition-all duration-300 ${
                                                                    isActive ? 'drop-shadow-sm' : ''
                                                                }`} />
                                                            </div>
                                                        </div>
                                                        <div className="text-left flex-1">
                                                            <h3 className={`font-bold text-xl mb-2 text-white transition-all duration-300 ${
                                                                isActive ? 'drop-shadow-sm' : ''
                                                            }`}>
                                                                {feature.title}
                                                            </h3>
                                                            <p className={`text-base leading-relaxed transition-all duration-300 ${
                                                                isActive ? 'text-white/95 drop-shadow-sm' : 'text-white/85'
                                                            }`}>
                                                                {feature.description}
                                                            </p>
                                                        </div>
                                                        <div className="flex-shrink-0">
                                                            <div className={`w-3 h-3 rounded-full transition-all duration-1000 ${
                                                                isActive
                                                                    ? 'bg-white animate-pulse scale-125 shadow-lg shadow-white/50'
                                                                    : 'bg-white/60 animate-pulse scale-100'
                                                            }`}></div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Right side - Login Form - Tono hueso elegante */}
                            <div className="w-full lg:w-2/5 flex items-center justify-center p-6 bg-stone-100 dark:bg-stone-900">
                                <div className={`w-full max-w-md transform transition-all duration-1000 delay-300 ${isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'}`}>
                                    {/* Logo and title */}
                                    <div className="text-center mb-8">
                                        <div className="mx-auto w-36 h-36 mb-6 relative transform hover:scale-110 transition-transform duration-300 overflow-hidden rounded-full">
                                            <img
                                                src="/img/lobo_rojo.png"
                                                alt="Logo Institucional"
                                                className="w-full h-full object-contain drop-shadow-lg"
                                            />
                                            {/* Shimmer effect - va y vuelve, solo el tamaño del logo */}
                                            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-25 animate-shimmer-pendulum"></div>
                                        </div>
                                        <h2 className="text-3xl font-bold text-stone-800 dark:text-stone-100 mb-2 animate-fadeInUp">
                                            Bienvenido de nuevo
                                        </h2>
                                    </div>

                                    {status && (
                                        <div className="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                            <p className="text-sm text-green-700 dark:text-green-400 text-center font-medium">
                                                {status}
                                            </p>
                                        </div>
                                    )}

                                    <Form
                                        {...store.form()}
                                        resetOnSuccess={['password']}
                                        className="space-y-6"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                {/* User field */}
                                                <div className="space-y-2">
                                                    <Label htmlFor="Usuario" className="text-sm font-medium text-stone-700 dark:text-stone-300">
                                                        Usuario
                                                    </Label>
                                                    <div className="relative group">
                                                        <Users className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-stone-400 transition-colors duration-200 group-focus-within:text-[#c10230]" />
                                                        <Input
                                                            id="Usuario"
                                                            type="text"
                                                            name="Usuario"
                                                            required
                                                            autoFocus
                                                            tabIndex={1}
                                                            autoComplete="username"
                                                            placeholder="Ingresa tu usuario"
                                                            className="pl-12 h-12 border-2 border-stone-200 dark:border-stone-700 rounded-xl transition-all duration-300 focus:border-[#c10230] dark:focus:border-[#c10230] focus:ring-2 focus:ring-[#c10230]/20 hover:border-stone-300 dark:hover:border-stone-600 hover:shadow-md"
                                                        />
                                                    </div>
                                                    <InputError message={errors.Usuario} />
                                                </div>

                                                {/* Password field */}
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <Label htmlFor="password" className="text-sm font-medium text-stone-700 dark:text-stone-300">
                                                            Contraseña
                                                        </Label>
                                                        {canResetPassword && (
                                                            <TextLink
                                                                href={request()}
                                                                className="text-sm transition-colors duration-200"
                                                                style={{ color: '#c10230' }}
                                                                tabIndex={5}
                                                            >
                                                                ¿Olvidaste tu contraseña?
                                                            </TextLink>
                                                        )}
                                                    </div>
                                                    <div className="relative group">
                                                        <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-stone-400 transition-colors duration-200 group-focus-within:text-[#c10230]" />
                                                        <Input
                                                            id="password"
                                                            type={showPassword ? "text" : "password"}
                                                            name="password"
                                                            required
                                                            tabIndex={2}
                                                            autoComplete="current-password"
                                                            placeholder="Tu contraseña"
                                                            className="pl-12 pr-12 h-12 border-2 border-stone-200 dark:border-stone-700 rounded-xl transition-all duration-300 focus:border-[#c10230] dark:focus:border-[#c10230] focus:ring-2 focus:ring-[#c10230]/20 hover:border-stone-300 dark:hover:border-stone-600 hover:shadow-md"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => setShowPassword(!showPassword)}
                                                            className="absolute right-3 top-1/2 transform -translate-y-1/2 text-stone-400 hover:text-[#c10230] dark:hover:text-stone-200 transition-colors duration-200 hover:scale-110"
                                                        >
                                                            {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                                        </button>
                                                    </div>
                                                    <InputError message={errors.password} />
                                                </div>

                                                {/* Remember me checkbox */}
                                                <div className="flex items-center space-x-3">
                                                    <Checkbox
                                                        id="remember"
                                                        name="remember"
                                                        tabIndex={3}
                                                        className="rounded border-2 border-stone-300 dark:border-stone-600"
                                                    />
                                                    <Label
                                                        htmlFor="remember"
                                                        className="text-sm text-stone-600 dark:text-stone-400 cursor-pointer"
                                                    >
                                                        Recordarme
                                                    </Label>
                                                </div>

                                                {/* Submit button */}
                                                <Button
                                                    type="submit"
                                                    className="w-full h-12 text-white rounded-xl font-semibold text-base transform hover:scale-[1.02] transition-all duration-300 shadow-lg hover:shadow-xl relative overflow-hidden"
                                                    style={{
                                                        background: 'linear-gradient(135deg, #c10230 0%, #8b0000 50%, #a91b3d 100%)',
                                                        boxShadow: '0 4px 20px rgba(193, 2, 48, 0.4)'
                                                    }}
                                                    tabIndex={4}
                                                    disabled={processing}
                                                    data-test="login-button"
                                                    onMouseEnter={(e) => {
                                                        e.currentTarget.style.background = 'linear-gradient(135deg, #d12345 0%, #9a0000 50%, #bb1e4a 100%)';
                                                        e.currentTarget.style.boxShadow = '0 6px 25px rgba(193, 2, 48, 0.6)';
                                                    }}
                                                    onMouseLeave={(e) => {
                                                        e.currentTarget.style.background = 'linear-gradient(135deg, #c10230 0%, #8b0000 50%, #a91b3d 100%)';
                                                        e.currentTarget.style.boxShadow = '0 4px 20px rgba(193, 2, 48, 0.4)';
                                                    }}
                                                >
                                                    {processing ? (
                                                        <div className="flex items-center space-x-2">
                                                            <Spinner className="w-5 h-5" />
                                                            <span>Iniciando sesión...</span>
                                                        </div>
                                                    ) : (
                                                        "Iniciar Sesión"
                                                    )}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </div>
                            </div>
                        </div>
                </div>

            {/* El contenido principal se mantiene siempre en el mask - sin duplicación */}
        </>
    );
}
