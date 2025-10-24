@echo off
echo Instalando Laravel Telescope...
composer require laravel/telescope
echo.
echo Publicando archivos de configuracion de Telescope...
php artisan telescope:install
echo.
echo Ejecutando migraciones de Telescope...
php artisan migrate
echo.
echo ¡Telescope instalado correctamente!
echo Puedes acceder en: http://localhost:8000/telescope
echo.
pause
