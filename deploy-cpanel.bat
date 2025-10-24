@echo off
echo ===================================
echo    DEPLOY A CPANEL CON PNPM
echo    Documentos Fiscales Campus
echo ===================================

echo.
echo 🔧 PASO 1: Verificando dependencias...
where pnpm >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: pnpm no está instalado
    echo    Instala pnpm primero: npm install -g pnpm
    pause
    exit /b 1
)

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: PHP no está en el PATH
    echo    Agrega PHP al PATH del sistema
    pause
    exit /b 1
)

echo ✅ pnpm encontrado
echo ✅ PHP encontrado

echo.
echo 📦 PASO 2: Instalando dependencias con pnpm...
pnpm install
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: Falló la instalación de dependencias
    pause
    exit /b 1
)

echo.
echo 🔨 PASO 3: Compilando assets para producción...
pnpm run build
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ERROR: Falló la compilación de assets
    pause
    exit /b 1
)

echo.
echo ⚙️ PASO 4: Optimizando Laravel...
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo.
echo 🔑 PASO 5: Verificando configuración...
if not exist ".env.production" (
    echo ⚠️ ADVERTENCIA: .env.production no existe
    echo    Creando desde .env.example...
    copy .env.example .env.production
)

echo.
echo 📋 PASO 6: Generando lista de archivos para subir...
echo Creando lista de archivos para cPanel...

echo.
echo ===================================
echo    ✅ BUILD COMPLETADO
echo ===================================
echo.
echo 📁 ESTRUCTURA PARA CPANEL:
echo.
echo 📂 public_html/ (directorio público)
echo    ├── index.php          (desde public/)
echo    ├── robots.txt         (desde public/)
echo    ├── .htaccess          (desde public/)
echo    └── build/             (assets compilados)
echo.
echo 📂 documentos_fiscales_app/ (directorio privado)
echo    ├── app/               ├── config/
echo    ├── bootstrap/         ├── database/
echo    ├── routes/            ├── resources/
echo    ├── storage/           ├── vendor/
echo    ├── .env               (renombrar .env.production)
echo    ├── artisan            ├── composer.json
echo    └── package.json
echo.
echo 🚀 PRÓXIMOS PASOS:
echo.
echo 1. 🌐 CREAR BASE DE DATOS en cPanel:
echo    - Ir a MySQL Databases
echo    - Crear BD: tu_usuario_gdfs
echo    - Crear usuario con privilegios completos
echo.
echo 2. 📤 SUBIR ARCHIVOS:
echo    - public/* → public_html/
echo    - resto del proyecto → documentos_fiscales_app/
echo.
echo 3. ⚙️ CONFIGURAR EN SERVIDOR:
echo    - Actualizar .env con datos de BD
echo    - Ejecutar: php artisan migrate --force
echo    - Ejecutar: php artisan key:generate --force
echo    - Configurar permisos de storage/
echo.
echo 4. 🔧 USAR CPANEL.YML (opcional):
echo    - Si tu cPanel soporta deploy automático
echo    - El archivo .cpanel.yml ya está configurado
echo.
echo ⚠️ IMPORTANTE:
echo - Mantén archivos sensibles fuera de public_html/
echo - Configura SSL/HTTPS en producción
echo - Verifica que storage/ tenga permisos 755
echo.
pause
