#!/bin/bash

echo "🚀 Iniciando deploy a cPanel..."

# 1. Construir assets de producción
echo "📦 Construyendo assets de producción..."
npm ci --production=false
npm run build

# 2. Limpiar archivos innecesarios
echo "🧹 Limpiando archivos innecesarios..."
rm -rf node_modules
rm -rf tests
rm -rf .git
rm -rf storage/logs/*
rm -f .env
rm -f .env.example

# 3. Instalar dependencias de Composer para producción
echo "📚 Instalando dependencias de Composer..."
composer install --optimize-autoloader --no-dev

# 4. Optimizar Laravel
echo "⚡ Optimizando Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Generar key de aplicación
echo "🔑 Generando APP_KEY..."
php artisan key:generate --show

echo "✅ Deploy preparado! Sube los archivos a cPanel."
echo "📋 No olvides:"
echo "   - Configurar .env en el servidor"
echo "   - Ejecutar migraciones: php artisan migrate"
echo "   - Configurar permisos: chmod 755 storage bootstrap/cache"
