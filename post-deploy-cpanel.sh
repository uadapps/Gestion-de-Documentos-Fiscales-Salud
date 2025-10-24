#!/bin/bash

# Script de post-deploy para cPanel
# Ejecutar este script en el servidor después de subir archivos

echo "🚀 POST-DEPLOY DOCUMENTOS FISCALES"
echo "=================================="

# Definir rutas (ajustar según tu cPanel)
APPPATH="/home/tu_usuario_cpanel/documentos_fiscales_app"
PUBLICPATH="/home/tu_usuario_cpanel/public_html"

# Ir al directorio de la aplicación
cd $APPPATH

echo "📦 Instalando dependencias de Composer..."
/usr/local/bin/ea-php83 composer.phar install --optimize-autoloader --no-dev --no-interaction

echo "🔑 Generando clave de aplicación..."
/usr/local/bin/ea-php83 artisan key:generate --force

echo "🗄️ Ejecutando migraciones..."
echo "⚠️ ATENCIÓN: Esto ejecutará las migraciones en la base de datos"
read -p "¿Continuar? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    /usr/local/bin/ea-php83 artisan migrate --force
    echo "✅ Migraciones ejecutadas"
else
    echo "⏭️ Migraciones omitidas (ejecutar manualmente después)"
fi

echo "⚡ Optimizando Laravel para producción..."
/usr/local/bin/ea-php83 artisan config:cache
/usr/local/bin/ea-php83 artisan route:cache
/usr/local/bin/ea-php83 artisan view:cache

echo "🔗 Configurando enlace simbólico para storage..."
rm -f $PUBLICPATH/storage
ln -s $APPPATH/storage/app/public $PUBLICPATH/storage

echo "🔒 Configurando permisos..."
find $APPPATH/storage -type d -exec chmod 755 {} \;
find $APPPATH/storage -type f -exec chmod 644 {} \;
find $APPPATH/bootstrap/cache -type d -exec chmod 755 {} \;
find $APPPATH/bootstrap/cache -type f -exec chmod 644 {} \;

# Configurar permisos específicos para directorios críticos
chmod -R 755 $APPPATH/storage/app/public
chmod -R 755 $APPPATH/storage/framework/cache
chmod -R 755 $APPPATH/storage/framework/sessions
chmod -R 755 $APPPATH/storage/framework/views
chmod -R 755 $APPPATH/storage/logs

echo "🧹 Limpiando archivos innecesarios..."
rm -f $APPPATH/debug_*.php
rm -f $APPPATH/temp_*.php
rm -f $APPPATH/deploy-cpanel.bat
rm -f $APPPATH/install_telescope.bat
rm -f $APPPATH/php-dev.ini
rm -f $APPPATH/php.ini
rm -rf $APPPATH/scripts

echo "✨ Verificaciones finales..."
echo "🔍 Verificando archivos críticos..."

if [ -f "$APPPATH/.env" ]; then
    echo "✅ .env existe"
else
    echo "❌ .env NO EXISTE - ¡CRÍTICO!"
fi

if [ -f "$PUBLICPATH/index.php" ]; then
    echo "✅ index.php en public_html existe"
else
    echo "❌ index.php NO EXISTE en public_html - ¡CRÍTICO!"
fi

if [ -d "$PUBLICPATH/build" ]; then
    echo "✅ Directorio build/ existe"
else
    echo "⚠️ Directorio build/ no encontrado - verificar compilación de assets"
fi

if [ -L "$PUBLICPATH/storage" ]; then
    echo "✅ Enlace simbólico storage configurado"
else
    echo "⚠️ Enlace simbólico storage no configurado"
fi

echo ""
echo "🎉 POST-DEPLOY COMPLETADO"
echo "========================"
echo ""
echo "📋 TAREAS MANUALES RESTANTES:"
echo ""
echo "1. 🗄️ Configurar .env con datos reales:"
echo "   - DB_DATABASE=tu_bd_real"
echo "   - DB_USERNAME=tu_usuario_real"
echo "   - DB_PASSWORD=tu_password_real"
echo "   - APP_URL=https://tu-dominio.com"
echo ""
echo "2. 🔑 Si no se ejecutaron las migraciones:"
echo "   cd $APPPATH"
echo "   /usr/local/bin/ea-php83 artisan migrate --force"
echo ""
echo "3. 🧪 Probar la aplicación:"
echo "   - Verificar que carga la página principal"
echo "   - Probar login"
echo "   - Verificar dashboard de supervisión (rol 16)"
echo ""
echo "4. 📊 Monitorear logs:"
echo "   tail -f $APPPATH/storage/logs/laravel.log"
echo ""

# Mostrar información del sistema
echo "ℹ️ INFORMACIÓN DEL SISTEMA:"
echo "PHP Version: $(/usr/local/bin/ea-php83 -v | head -n 1)"
echo "Directorio de la app: $APPPATH"
echo "Directorio público: $PUBLICPATH"
echo "Usuario actual: $(whoami)"
echo ""
echo "¡Deploy completado! 🚀"
