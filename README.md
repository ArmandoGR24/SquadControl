# SquadControl

Aplicación web para gestión de cuadrillas, tareas, check-ins y notificaciones (incluyendo push con Firebase), construida con Laravel + Inertia + Vue.

## Stack tecnológico

- Backend: Laravel 12, PHP 8.4, Fortify (auth), Inertia.js
- Frontend: Vue 3 + TypeScript + Vite + Tailwind CSS
- Base de datos: MySQL
- Notificaciones: Laravel Notifications + Firebase Cloud Messaging (FCM)
- Testing: Pest + PHPUnit

## Requisitos

- PHP 8.4+
- Composer 2+
- Node.js 20+
- npm 10+
- MySQL 8+

## Instalación local (desarrollo)

1. Clonar el repositorio.
2. Instalar dependencias de backend:

```bash
composer install
```

3. Crear variables de entorno y llave de app:

```bash
cp .env.example .env
php artisan key:generate
```

4. Configurar base de datos en `.env` y ejecutar migraciones:

```bash
php artisan migrate
```

5. Instalar dependencias frontend:

```bash
npm install
```

6. Levantar entorno de desarrollo completo:

```bash
composer run dev
```

Este comando inicia:
- servidor Laravel
- cola (`queue:listen`)
- Vite en modo desarrollo

## Scripts útiles

### Backend

```bash
php artisan serve
php artisan migrate
php artisan optimize:clear
```

### Frontend

```bash
npm run dev
npm run build
npm run lint
npm run format
```

### Calidad y pruebas

```bash
composer test
php artisan test
composer run lint
composer run test:lint
```

## Ejecución con Docker

El proyecto incluye [docker-compose.yml](docker-compose.yml) con servicio `app` (PHP + Node) para servir la aplicación.

```bash
docker compose up -d
```

La aplicación queda disponible en:
- http://localhost:8000

## Funcionalidades principales

- Autenticación completa (login, registro, recuperación y 2FA)
- Gestión de usuarios por roles (`Admin`, `RH`, `Supervisor`, `Lider de Cuadrilla`, `Empleado`)
- Gestión de tareas y evidencias multimedia
- Flujo de revisión de tareas
- Check-in / Check-out
- Notificaciones internas y push (FCM)

## Estructura base

- [app](app): lógica de negocio (controladores, modelos, servicios, notificaciones)
- [resources/js](resources/js): frontend Vue + Inertia
- [routes](routes): rutas web y settings
- [database](database): migraciones, factories y seeders
- [tests](tests): pruebas de integración y unitarias

## Documentación del sistema de notificaciones

- [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)
- [QUICK_START_NOTIFICATIONS.md](QUICK_START_NOTIFICATIONS.md)
- [NOTIFICATIONS_GUIDE.md](NOTIFICATIONS_GUIDE.md)
- [FIREBASE_PUSH_NOTIFICATIONS.md](FIREBASE_PUSH_NOTIFICATIONS.md)
- [FRONTEND_NOTIFICATIONS.md](FRONTEND_NOTIFICATIONS.md)
- [TROUBLESHOOTING_FCM.md](TROUBLESHOOTING_FCM.md)

## Configuración recomendada para desarrollo

- Mantener `APP_ENV=local`
- Ejecutar `php artisan optimize:clear` después de cambios en config/traducciones
- Verificar que `QUEUE_CONNECTION` esté correctamente configurado
- Si usas FCM, completar variables `VITE_FIREBASE_*` y certificados requeridos

## Estado actual

El proyecto está orientado a entorno multi-rol con interfaz en español y flujo de autenticación traducido.
