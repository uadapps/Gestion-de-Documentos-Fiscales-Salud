import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    // Get the display name - try different possible fields with proper type checking
    const userName = user.name;
    const usuarioField = (user as any).Usuario; // Since your DB uses 'Usuario' field
    const displayName = (typeof userName === 'string' && userName) ||
                       (typeof usuarioField === 'string' && usuarioField) ||
                       'Usuario';

    const displayEmail = (typeof user.email === 'string' && user.email) || '';

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={displayName} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(displayName)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{displayName}</span>
                {showEmail && displayEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {displayEmail}
                    </span>
                )}
            </div>
        </>
    );
}
