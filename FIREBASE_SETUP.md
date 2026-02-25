# Firebase Cloud Messaging - Configuración

Este proyecto utiliza **Firebase Cloud Messaging (FCM)** para enviar notificaciones push desde el backend de Laravel hacia los usuarios del frontend Vue.

---

## 📋 Requisitos Previos

1. Proyecto de Firebase creado en [Firebase Console](https://console.firebase.google.com/)
2. Aplicación web registrada en Firebase
3. Cloud Messaging habilitado

---

## 🔧 Configuración del Backend (Laravel + Firebase Admin SDK)

### 1. Obtener las credenciales de Service Account

1. Ve a **Firebase Console** → Tu proyecto (`squadcontrol-b5ab2`)
2. Ve a **Project Settings** (⚙️ Configuración del proyecto)
3. Ve a la pestaña **Service Accounts**
4. Haz clic en **Generate new private key**
5. Se descargará un archivo JSON con las credenciales

### 2. Colocar el archivo de credenciales

Copia el archivo JSON descargado en:

```
storage/app/firebase-service-account.json
```

**Importante**: Este archivo NO debe subirse a repositorios públicos. Ya está incluido en `.gitignore`.

### 3. Estructura del archivo Service Account (Ejemplo)

```json
{
  "type": "service_account",
  "project_id": "squadcontrol-b5ab2",
  "private_key_id": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-xxxxx@squadcontrol-b5ab2.iam.gserviceaccount.com",
  "client_id": "xxxxxxxxxxxxxxxxxxxx",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/..."
}
```

---

## 🌐 Configuración del Frontend (Vue + Firebase Web SDK)

### 1. Obtener las claves de configuración

Ya están configuradas en tu proyecto:

```env
VITE_FIREBASE_API_KEY=AIzaSyC0B2aOfGeyaf37TiMKtBEODg3bEh7bD_M
VITE_FIREBASE_AUTH_DOMAIN=squadcontrol-b5ab2.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=squadcontrol-b5ab2
VITE_FIREBASE_STORAGE_BUCKET=squadcontrol-b5ab2.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=769683780186
VITE_FIREBASE_APP_ID=1:769683780186:web:2b0053017dd242ccbe1e63
VITE_FIREBASE_MEASUREMENT_ID=G-NWH770HVBK
```

### 2. Obtener la clave VAPID (Web Push Certificates)

1. Ve a **Firebase Console** → Tu proyecto
2. Ve a **Project Settings** → **Cloud Messaging**
3. En la sección **Web Push certificates**, copia la clave:
   - Si no existe, haz clic en **Generate key pair**
4. Copia la clave y agrégala al archivo `.env`:

```env
VITE_FIREBASE_VAPID_KEY=tu_clave_vapid_aqui
```

---

## 📝 Variables de Entorno (`.env`)

Asegúrate de tener configuradas todas las variables necesarias en tu `.env`:

```env
# Firebase Configuration (Frontend)
VITE_FIREBASE_API_KEY=AIzaSyC0B2aOfGeyaf37TiMKtBEODg3bEh7bD_M
VITE_FIREBASE_AUTH_DOMAIN=squadcontrol-b5ab2.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=squadcontrol-b5ab2
VITE_FIREBASE_STORAGE_BUCKET=squadcontrol-b5ab2.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=769683780186
VITE_FIREBASE_APP_ID=1:769683780186:web:2b0053017dd242ccbe1e63
VITE_FIREBASE_MEASUREMENT_ID=G-NWH770HVBK
VITE_FIREBASE_VAPID_KEY=TU_CLAVE_VAPID_AQUI

# Firebase Service Account (Backend)
# Archivo: storage/app/firebase-service-account.json
```

---

## 🚀 Uso

### Backend: Enviar notificaciones

#### Enviar notificación individual

```php
use App\Services\FirebaseService;

$firebase = app(FirebaseService::class);

$firebase->sendNotification(
    $user->fcm_token,
    'Título de la notificación',
    'Cuerpo de la notificación',
    ['data_key' => 'data_value'] // Opcional
);
```

#### Enviar notificación a múltiples usuarios

```php
$tokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

$firebase->sendMulticast(
    $tokens,
    'Título broadcast',
    'Mensaje a todos los usuarios',
    ['type' => 'broadcast']
);
```

### Frontend: Los tokens se guardan automáticamente

Cuando un usuario inicia sesión y otorga permisos de notificación:

1. Se genera el token FCM automáticamente
2. Se envía al backend y se guarda en `users.fcm_token`
3. Las notificaciones en primer plano se muestran con Toast

---

## 🧪 Probar la integración

### 1. Enviar notificación de prueba

Ruta disponible para usuarios autenticados:

```bash
POST /fcm/test
```

### 2. Enviar broadcast (solo Admin/RH/Supervisor)

```bash
POST /fcm/broadcast
Content-Type: application/json

{
  "title": "Mantenimiento programado",
  "body": "El sistema estará en mantenimiento mañana de 2-4 AM",
  "data": {
    "type": "maintenance"
  }
}
```

---

## 🔒 Seguridad

- ✅ El archivo `firebase-service-account.json` está excluido de Git
- ✅ Las rutas de FCM están protegidas con autenticación
- ✅ El broadcast está restringido a roles Admin/RH/Supervisor
- ✅ Los tokens FCM se almacenan cifrados en la BD

---

## 📚 Documentación Oficial

- [Firebase Admin SDK PHP](https://firebase-php.readthedocs.io/)
- [Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging)
- [Firebase Web Setup](https://firebase.google.com/docs/web/setup)

---

## ❓ Troubleshooting

### Error: "Service account file not found"
- Verifica que `storage/app/firebase-service-account.json` existe
- Revisa los permisos del archivo

### Error: "Failed to send notification"
- Verifica que el token FCM sea válido
- Revisa que el proyecto Firebase tenga Cloud Messaging habilitado

### Token FCM no se genera
- Verifica que `VITE_FIREBASE_VAPID_KEY` esté configurada
- Asegúrate de que el usuario haya aceptado los permisos de notificación
- Revisa la consola del navegador para errores

---

## 📞 Soporte

Para problemas con Firebase Admin SDK:
- Email de Service Account: `firebase-adminsdk-fbsvc@squadcontrol-b5ab2.iam.gserviceaccount.com`
- Project ID: `squadcontrol-b5ab2`
