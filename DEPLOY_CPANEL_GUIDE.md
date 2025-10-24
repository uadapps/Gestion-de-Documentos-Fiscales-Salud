# 🚀 GUÍA DE DEPLOY A CPANEL

## 📋 **PREPARACIÓN LOCAL**

### 1. Ejecutar script de build
```bash
# Ejecutar el script de deploy
deploy-cpanel.bat

# O manualmente:
pnpm install
pnpm run build
php artisan optimize:clear
php artisan key:generate --env=production
```

## 🌐 **CONFIGURACIÓN EN CPANEL**

### 2. Crear Base de Datos MySQL
1. Ir a **MySQL Databases** en cPanel
2. Crear nueva base de datos: `tu_usuario_gdfs`
3. Crear usuario MySQL con todos los privilegios
4. Anotar: nombre BD, usuario, password

### 3. Estructura de Archivos en cPanel
```
public_html/
├── index.php (de la carpeta public/)
├── robots.txt
├── build/ (carpeta completa de public/build/)
├── img/ (si existe)

private/ (o fuera de public_html/)
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env (renombrado de .env.production)
├── artisan
├── composer.json
├── package.json
└── todos los demás archivos
```

### 4. Actualizar .env en el servidor
```env
APP_URL=https://tu-dominio.com
DB_DATABASE=tu_usuario_gdfs
DB_USERNAME=tu_usuario_bd
DB_PASSWORD=tu_password_bd
```

### 5. Actualizar index.php en public_html
Editar `public_html/index.php` y cambiar las rutas:
```php
// Cambiar estas líneas para apuntar a la ubicación correcta
require __DIR__.'/../private/vendor/autoload.php';
$app = require_once __DIR__.'/../private/bootstrap/app.php';
```

## ⚡ **COMANDOS POST-DEPLOY**

### 6. Ejecutar en Terminal de cPanel
```bash
# Ir al directorio del proyecto
cd private/

# Instalar dependencias de composer
composer install --optimize-autoloader --no-dev

# Ejecutar migraciones
php artisan migrate --force

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configurar permisos
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

## 🔧 **VERIFICACIONES**

### 7. Checklist Post-Deploy
- [ ] Sitio web carga correctamente
- [ ] Login funciona
- [ ] Base de datos conecta
- [ ] Assets (CSS/JS) cargan
- [ ] Rutas de API funcionan
- [ ] Dashboard de supervisión funciona para rol 16
- [ ] Logs no muestran errores críticos

### 8. Troubleshooting Común
- **500 Error**: Verificar permisos de storage/ y bootstrap/cache/
- **Assets no cargan**: Verificar ASSET_URL en .env
- **DB Error**: Verificar credenciales de base de datos
- **Rutas no funcionan**: Ejecutar `php artisan route:cache`

## 📝 **NOTAS IMPORTANTES**
- Usar HTTPS en producción
- Configurar backups automáticos
- Monitorear logs en storage/logs/
- Mantener .env fuera de public_html por seguridad
