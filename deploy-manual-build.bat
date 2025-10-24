@echo off
echo ===================================
echo    DEPLOY MANUAL CON BUILD LOCAL
echo    Documentos Fiscales Campus
echo ===================================

echo.
echo 🔧 PASO 1: Verificando dependencias...
where pnpm >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: pnpm no está instalado
    pause
    exit /b 1
)

echo ✅ pnpm encontrado

echo.
echo 📦 PASO 2: Instalando dependencias...
pnpm install
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: Falló la instalación
    pause
    exit /b 1
)

echo.
echo 🔨 PASO 3: Compilando assets para producción...
pnpm run build
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: Falló la compilación
    pause
    exit /b 1
)

echo.
echo ⚙️ PASO 4: Optimizando Laravel...
php artisan optimize:clear

echo.
echo 📁 PASO 5: Creando carpeta de deploy...
if exist "deploy_temp" rmdir /s /q deploy_temp
mkdir deploy_temp
mkdir deploy_temp\app_files
mkdir deploy_temp\public_files

echo.
echo 📦 PASO 6: Copiando archivos de aplicación...
xcopy app deploy_temp\app_files\app\ /E /I /Q
xcopy routes deploy_temp\app_files\routes\ /E /I /Q
xcopy resources deploy_temp\app_files\resources\ /E /I /Q
xcopy config deploy_temp\app_files\config\ /E /I /Q
xcopy database deploy_temp\app_files\database\ /E /I /Q
xcopy bootstrap deploy_temp\app_files\bootstrap\ /E /I /Q
xcopy storage deploy_temp\app_files\storage\ /E /I /Q
xcopy vendor deploy_temp\app_files\vendor\ /E /I /Q
xcopy lang deploy_temp\app_files\lang\ /E /I /Q
copy composer.json deploy_temp\app_files\
copy composer.lock deploy_temp\app_files\
copy artisan deploy_temp\app_files\
copy package.json deploy_temp\app_files\
copy pnpm-lock.yaml deploy_temp\app_files\
copy vite.config.ts deploy_temp\app_files\
copy tsconfig.json deploy_temp\app_files\
copy .env.production deploy_temp\app_files\.env

echo.
echo 🌐 PASO 7: Copiando archivos públicos...
copy public\index.php deploy_temp\public_files\
copy public\.htaccess deploy_temp\public_files\
copy public\robots.txt deploy_temp\public_files\
if exist "public\favicon.ico" copy public\favicon.ico deploy_temp\public_files\
if exist "public\favicon.svg" copy public\favicon.svg deploy_temp\public_files\
if exist "public\img" xcopy public\img deploy_temp\public_files\img\ /E /I /Q

echo.
echo 🎯 PASO 8: Copiando assets compilados...
if exist "public\build" (
    xcopy public\build deploy_temp\public_files\build\ /E /I /Q
    echo ✅ Assets compilados copiados
) else (
    echo ❌ ERROR: No se encontraron assets compilados
    echo    Ejecuta primero: pnpm run build
    pause
    exit /b 1
)

echo.
echo ===================================
echo    ✅ DEPLOY PREPARADO
echo ===================================
echo.
echo 📁 ARCHIVOS LISTOS EN: deploy_temp\
echo.
echo 📤 INSTRUCCIONES PARA SUBIR A CPANEL:
echo.
echo 1. 📂 SUBIR APLICACIÓN:
echo    - Comprimir: deploy_temp\app_files\*
echo    - Subir a: /home/tu_usuario/documentos_fiscales_app/
echo.
echo 2. 🌐 SUBIR ARCHIVOS PÚBLICOS:
echo    - Comprimir: deploy_temp\public_files\*
echo    - Subir a: /home/tu_usuario/public_html/
echo.
echo 3. ⚙️ CONFIGURAR EN SERVIDOR:
echo    - Actualizar .env con datos reales
echo    - Ejecutar: php artisan migrate --force
echo    - Ejecutar: php artisan key:generate --force
echo    - Configurar permisos: chmod 755 storage/
echo.
echo 4. 🧹 LIMPIAR LOCAL:
echo    - Eliminar carpeta: deploy_temp\
echo.
pause
