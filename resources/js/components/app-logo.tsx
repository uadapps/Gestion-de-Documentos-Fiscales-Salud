import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md">
                <img
                    src="/img/lobo_rojo.png"
                    alt="Logo Campus"
                    className="size-8 object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    Documentos Fiscales
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Campus UAD
                </span>
            </div>
        </>
    );
}
