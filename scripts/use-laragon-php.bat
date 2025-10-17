@echo off
REM Script para usar la versión de PHP de Laragon sólo en esta consola (cmd)
SET "PHPDIR=C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64"
IF NOT EXIST "%PHPDIR%\php.exe" (
  echo Directorio no encontrado: %PHPDIR%
  exit /b 1
)
SET PATH=%PHPDIR%;%PATH%
echo PATH actualizado temporalmente para esta consola. php --version:
php --version
echo Ejecuta: composer run dev:check o php artisan --version
echo Para volver, cierra esta ventana de cmd.
exit /b 0
