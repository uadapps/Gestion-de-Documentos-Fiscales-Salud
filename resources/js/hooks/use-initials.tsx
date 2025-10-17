import { useCallback } from 'react';

export function useInitials() {
    return useCallback((fullName?: string | null): string => {
        // Handle undefined, null, or empty string
        if (!fullName || typeof fullName !== 'string') {
            return 'U'; // Default initial for "User"
        }

        const names = fullName.trim().split(' ').filter(name => name.length > 0);

        if (names.length === 0) return 'U';
        if (names.length === 1) return names[0].charAt(0).toUpperCase();

        const firstInitial = names[0].charAt(0);
        const lastInitial = names[names.length - 1].charAt(0);

        return `${firstInitial}${lastInitial}`.toUpperCase();
    }, []);
}
