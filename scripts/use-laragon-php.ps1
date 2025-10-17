# Script para usar la versión de PHP de Laragon sólo en la sesión actual
param(
    [string]$PhpDir = 'C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64'
)

if (-Not (Test-Path $PhpDir)) {
    Write-Error "El directorio de PHP no existe: $PhpDir"
    return 1
}

# Anteponer la ruta de PHP al PATH solo para esta sesión
$env:Path = "$PhpDir;" + $env:Path

Write-Output "PATH actualizado temporalmente para esta sesión. php --version:" 
php --version

Write-Output "Puedes ejecutar: composer run dev:check o php artisan ..."
Write-Output "Para volver a la sesión normal, cierra esta ventana de PowerShell."

return 0
