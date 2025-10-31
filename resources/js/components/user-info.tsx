import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();
    const { props } = usePage();

    // Priorizar nombre_completo (desde la tabla personas) sobre otros campos
    const displayName = user.nombre_completo ||
                       user.name ||
                       (user as any).Usuario ||
                       'Usuario';

    // Memorizar timestamp para evitar recarga de imagen en cada render
    const userId = user.id || (user as any).ID_Usuario || 'unknown';
    const avatarSrc = useMemo(() => {
        const timestamp = Date.now();
        return `/api/user/photo?user=${userId}&t=${timestamp}`;
    }, [userId]); // Solo cambia cuando cambia el userId

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage
                    src={avatarSrc}
                    alt={displayName}
                    onError={(e) => {
                        console.log('Error cargando imagen del usuario:', e);
                    }}
                />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(displayName)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{displayName}</span>
                {user.campus && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.campus}
                    </span>
                )}
            </div>
        </>
    );
}
