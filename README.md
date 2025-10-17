# Gestión de Documentos Fiscales Salud (GDFS)

Sistema de gestión de documentos fiscales desarrollado con Laravel, React e Inertia.js.

## 🚀 Tecnologías

- **Backend**: Laravel 12.x
- **Frontend**: React 19.x + TypeScript
- **Comunicación**: Inertia.js
- **Base de datos**: SQL Server
- **Gestión de paquetes**: pnpm
- **Autenticación**: Laravel Fortify
- **Estilos**: Tailwind CSS

## 📋 Requisitos del sistema

- PHP 8.2 o superior
- Node.js 18.x o superior
- pnpm
- SQL Server
- Composer

## 🛠️ Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/uadapps/Gestion-de-Documentos-Fiscales-Salud.git
cd Gestion-de-Documentos-Fiscales-Salud
```

2. **Instalar dependencias de PHP**
```bash
composer install
```

3. **Instalar dependencias de Node.js**
```bash
pnpm install
```

4. **Configurar variables de entorno**
```bash
cp .env.example .env
```

5. **Configurar base de datos en `.env`**
```env
DB_CONNECTION=sqlsrv
DB_HOST=tu_servidor_sql
DB_PORT=1433
DB_DATABASE=tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
DB_PREFIX=GDFS_
```

6. **Generar clave de aplicación**
```bash
php artisan key:generate
```

7. **Ejecutar migraciones**
```bash
php artisan migrate
```

8. **Compilar assets**
```bash
pnpm run build
```

## Uso de PHP por proyecto (Laragon)

Si quieres que este proyecto use la versión de PHP instalada en Laragon solo para este repositorio (sin cambiar la configuración global del sistema), hay scripts incluidos que anteponen temporalmente la carpeta de PHP de Laragon a la variable PATH en la sesión actual.

Archivos añadidos:

- `scripts/use-laragon-php.ps1` — Script PowerShell. Ejecuta en PowerShell desde la raíz del repo:

	powershell -NoProfile -ExecutionPolicy Bypass -File .\\scripts\\use-laragon-php.ps1

- `scripts/use-laragon-php.bat` — Script para cmd. Ejecuta en CMD desde la raíz del repo:

	.\\scripts\\use-laragon-php.bat

- `.vscode/tasks.json` — Tarea para ejecutar el script PowerShell desde VS Code (Terminal integrado).

Notas:

- Estos scripts solo modifican PATH en la sesión actual (temporal). Cierra la terminal para volver a la configuración anterior.
- Si tu instalación de Laragon está en otra ruta, edita el parámetro o la variable `PHPDIR` en los scripts para apuntar a tu versión.
- Si prefieres un cambio permanente para tu usuario, edita la variable PATH del sistema (no recomendado si sólo quieres afectar este proyecto).


## 🏃‍♂️ Desarrollo

Para ejecutar el proyecto en modo desarrollo:

```bash
# Iniciar servidor Laravel y Vite en paralelo
composer run dev
```

O ejecutar por separado:

```bash
# Servidor Laravel
php artisan serve

# Vite dev server
pnpm run dev
```

## 🗄️ Base de datos

### Nomenclatura de tablas

Todas las tablas del sistema usan el prefijo `GDFS_` para identificar claramente las tablas del sistema:

- `GDFS_users` - Usuarios del sistema
- `GDFS_password_reset_tokens` - Tokens de recuperación de contraseña
- `GDFS_sessions` - Sesiones de usuario
- `GDFS_cache` - Caché de la aplicación
- `GDFS_jobs` - Cola de trabajos

### Migraciones

```bash
# Ejecutar migraciones
php artisan migrate

# Rollback de migraciones
php artisan migrate:rollback

# Estado de migraciones
php artisan migrate:status
```

## 🧪 Testing

```bash
# Ejecutar tests
php artisan test

# Ejecutar tests con Pest
./vendor/bin/pest
```

## 🔧 Scripts disponibles

- `composer run setup` - Instalación completa del proyecto
- `composer run dev` - Modo desarrollo
- `composer run test` - Ejecutar tests
- `pnpm run build` - Compilar para producción
- `pnpm run dev` - Servidor de desarrollo Vite
- `pnpm run lint` - Linter de código
- `pnpm run format` - Formatear código

## 📁 Estructura del proyecto

```
├── app/                    # Código de la aplicación Laravel
├── resources/js/           # Código React/TypeScript
│   ├── components/         # Componentes reutilizables
│   ├── layouts/           # Layouts de la aplicación
│   ├── pages/             # Páginas de la aplicación
│   └── types/             # Definiciones de tipos TypeScript
├── database/              # Migraciones y seeders
├── public/                # Assets públicos
└── routes/                # Definición de rutas
```

## 🔐 Autenticación

El sistema incluye autenticación completa con:

- Login/Registro
- Verificación de email
- Recuperación de contraseña
- Autenticación de dos factores (2FA)
- Gestión de perfil de usuario

## 🎨 UI/UX

- **Componentes**: Basados en Radix UI
- **Estilos**: Tailwind CSS con sistema de design tokens
- **Iconos**: Lucide React
- **Tema**: Soporte para modo claro/oscuro

## 📝 Licencia

Este proyecto es propietario de UAD Apps.

## 👥 Equipo de desarrollo

- **Desarrollo**: UAD Apps
- **Repositorio**: https://github.com/uadapps/Gestion-de-Documentos-Fiscales-Salud

## 🐛 Reporte de errores

Para reportar errores o solicitar nuevas características, por favor crear un issue en el repositorio de GitHub.
