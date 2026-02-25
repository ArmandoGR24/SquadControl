# 🚀 Integración Firebase Cloud Messaging - SquadControl

## ✅ Implementación Completa

### 📦 Backend (Laravel + Firebase Admin SDK)

#### 1. Instalación de Dependencias
- ✅ Extensión PHP `sodium` habilitada
- ✅ SDK Firebase Admin instalado: `kreait/firebase-php ^8.1`

#### 2. Base de Datos
- ✅ Migración creada: `add_fcm_token_to_users_table`
- ✅ Columna `fcm_token` agregada a tabla `users`
- ✅ Modelo `User` actualizado con `fillable`

#### 3. Servicios Backend
- ✅ **FirebaseService** (`app/Services/FirebaseService.php`)
  - Envío de notificaciones individuales
  - Envío masivo (multicast)
  - Validación de tokens FCM

#### 4. Controlador y Rutas
- ✅ **FirebaseController** (`app/Http/Controllers/FirebaseController.php`)
  - `POST /fcm/token` - Guardar token FCM del usuario autenticado
  - `POST /fcm/test` - Enviar notificación de prueba
  - `POST /fcm/broadcast` - Envío masivo (Admin/RH/Supervisor)

---

### 🌐 Frontend (Vue + Firebase Web SDK)

#### 1. Firebase Web SDK
- ✅ Dependencia instalada: `firebase ^12.9.0`
- ✅ Módulo de inicialización: `resources/js/lib/firebase.ts`
  - `initializeFirebaseAnalytics()` - Google Analytics
  - `initializeFirebaseMessaging()` - Cloud Messaging + registro de token
  - `onForegroundFirebaseMessage()` - Listener de mensajes en primer plano

#### 2. Service Worker
- ✅ Service Worker de FCM: `public/firebase-messaging-sw.js`
  - Manejo de notificaciones en segundo plano
  - Scope aislado: `/firebase-cloud-messaging-push-scope`

#### 3. Integración con App
- ✅ `resources/js/app.ts` actualizado:
  - Inicialización automática de Firebase al cargar la app
  - Envío automático de token FCM al backend
  - Notificaciones en primer plano con Toast

---

## 🔧 Configuración Requerida

### 1. Variables de Entorno Frontend (`.env`)

```env
VITE_FIREBASE_API_KEY=AIzaSyC0B2aOfGeyaf37TiMKtBEODg3bEh7bD_M
VITE_FIREBASE_AUTH_DOMAIN=squadcontrol-b5ab2.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=squadcontrol-b5ab2
VITE_FIREBASE_STORAGE_BUCKET=squadcontrol-b5ab2.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=769683780186
VITE_FIREBASE_APP_ID=1:769683780186:web:2b0053017dd242ccbe1e63
VITE_FIREBASE_MEASUREMENT_ID=G-NWH770HVBK
VITE_FIREBASE_VAPID_KEY=<OBTENER_DESDE_FIREBASE_CONSOLE>
```

### 2. Service Account Backend

**Ubicación**: `storage/app/firebase-service-account.json`

**Cómo obtenerlo**:
1. Ve a [Firebase Console](https://console.firebase.google.com/)
2. Selecciona tu proyecto: **squadcontrol-b5ab2**
3. Ve a **Project Settings** → **Service Accounts**
4. Haz clic en **Generate new private key**
5. Descarga el archivo JSON
6. Renómbralo a `firebase-service-account.json`
7. Colócalo en `storage/app/`

**Email de Service Account**: `firebase-adminsdk-fbsvc@squadcontrol-b5ab2.iam.gserviceaccount.com`

---

## 📝 Pasos para Finalizar la Configuración

### ✅ Paso 1: Obtener VAPID Key

1. Ve a [Firebase Console](https://console.firebase.google.com/)
2. Selecciona tu proyecto
3. Ve a **Project Settings** → **Cloud Messaging**
4. En la sección **Web Push certificates**:
   - Si no existe clave, haz clic en **Generate key pair**
   - Copia la clave generada
5. Agrégala a tu `.env`:

```env
VITE_FIREBASE_VAPID_KEY=tu_clave_vapid_aqui
```

### ✅ Paso 2: Descargar Service Account

1. Ve a [Firebase Console](https://console.firebase.google.com/)
2. **Project Settings** → **Service Accounts**
3. Haz clic en **Generate new private key**
4. Guarda el archivo como `storage/app/firebase-service-account.json`

### ✅ Paso 3: Compilar Frontend

```bash
npm run build
# o para desarrollo
npm run dev
```

### ✅ Paso 4: Probar la Integración

#### Opción 1: Notificación de Prueba (curl)

```bash
curl -X POST http://localhost:8000/fcm/test \
  -H "Content-Type: application/json" \
  -H "Cookie: laravel_session=tu_sesion"
```

#### Opción 2: Desde la App

1. Inicia sesión en la aplicación
2. Acepta los permisos de notificación
3. El token FCM se guardará automáticamente
4. Ve a la consola del navegador para ver el token generado

#### Opción 3: Enviar Broadcast (Admin)

```bash
curl -X POST http://localhost:8000/fcm/broadcast \
  -H "Content-Type: application/json" \
  -H "Cookie: laravel_session=tu_sesion" \
  -d '{
    "title": "Prueba de Broadcast",
    "body": "Esta es una notificación masiva",
    "data": {
      "type": "test"
    }
  }'
```

---

## 🎯 Funcionalidades Implementadas

### Backend
- ✅ Guardar token FCM del usuario al iniciar sesión
- ✅ Enviar notificación individual por token
- ✅ Enviar notificación masiva a múltiples usuarios
- ✅ Validación de tokens FCM
- ✅ Rutas protegidas con autenticación y roles

### Frontend
- ✅ Solicitud automática de permisos de notificación
- ✅ Registro automático del Service Worker
- ✅ Generación y envío automático del token FCM al backend
- ✅ Notificaciones en primer plano con Toast
- ✅ Notificaciones en segundo plano con Service Worker
- ✅ Integración con Firebase Analytics

---

## 📚 Archivos Creados/Modificados

### Backend
- `app/Services/FirebaseService.php` - Servicio principal de FCM
- `app/Http/Controllers/FirebaseController.php` - Controlador de API
- `routes/web.php` - Rutas de FCM agregadas
- `database/migrations/2026_02_20_155151_add_fcm_token_to_users_table.php` - Migración
- `app/Models/User.php` - Modelo actualizado

### Frontend
- `resources/js/lib/firebase.ts` - Cliente Firebase
- `resources/js/app.ts` - Integración con bootstrap
- `resources/js/types/globals.d.ts` - Tipos TypeScript
- `public/firebase-messaging-sw.js` - Service Worker FCM

### Documentación
- `FIREBASE_SETUP.md` - Guía de configuración completa
- `INTEGRATION_SUMMARY.md` - Este archivo
- `.env.example` - Variables de entorno actualizadas
- `storage/app/firebase-service-account.json.example` - Plantilla de credenciales

---

## 🔒 Seguridad

- ✅ `firebase-service-account.json` excluido de Git
- ✅ Rutas protegidas con autenticación
- ✅ Broadcast restringido a Admin/RH/Supervisor
- ✅ Tokens FCM almacenados en base de datos cifrada
- ✅ Validación de entrada en controladores

---

## 🐛 Troubleshooting

### Error: "Service account file not found"
**Solución**: Descarga el archivo Service Account y colócalo en `storage/app/firebase-service-account.json`

### Error: "Failed to generate FCM token"
**Solución**: 
1. Verifica que `VITE_FIREBASE_VAPID_KEY` esté configurada
2. Asegúrate de aceptar los permisos de notificación en el navegador
3. Revisa la consola del navegador para errores

### Token no se guarda en la base de datos
**Solución**:
1. Verifica que el usuario esté autenticado
2. Revisa la consola de red (Network) para errores 401/403
3. Verifica que la columna `fcm_token` exista en la tabla `users`

### Notificaciones no llegan
**Solución**:
1. Verifica que el archivo Service Account esté correctamente configurado
2. Revisa que el token FCM del usuario sea válido
3. Verifica que Cloud Messaging esté habilitado en Firebase Console

---

## 📞 Información del Proyecto

- **Nombre del proyecto Firebase**: squadcontrol-b5ab2
- **Project ID**: squadcontrol-b5ab2
- **Service Account**: firebase-adminsdk-fbsvc@squadcontrol-b5ab2.iam.gserviceaccount.com
- **App ID**: 1:769683780186:web:2b0053017dd242ccbe1e63

---

## 🎉 Siguiente Paso

**¡Ya casi terminas!** Solo te falta:

1. Obtener la **VAPID Key** desde Firebase Console
2. Descargar el archivo **Service Account JSON**
3. Agregar ambos a tu configuración
4. ¡Listo para enviar notificaciones push! 🚀
