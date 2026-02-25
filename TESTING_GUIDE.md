# 🧪 Guía de Pruebas - Firebase Cloud Messaging

## ⚠️ Requisito IMPORTANTE: Service Account

**Antes de probar, necesitas descargar el archivo de credenciales:**

### 📥 Paso 1: Descargar Service Account JSON

1. Ve a: https://console.firebase.google.com/project/squadcontrol-b5ab2/settings/serviceaccounts/adminsdk
2. Haz clic en el botón **"Generar nueva clave privada"** (Generate new private key)
3. Se descargará un archivo JSON (ejemplo: `squadcontrol-b5ab2-firebase-adminsdk-xxxxx.json`)
4. **Renómbralo** a: `firebase-service-account.json`
5. **Muévelo** a: `D:\Proyectos\SquadControl\storage\app\firebase-service-account.json`

---

## ✅ Verificar Configuración

Tu archivo `.env` ya tiene todo configurado:
- ✅ API Keys de Firebase
- ✅ VAPID Key para notificaciones push
- ✅ Project ID y configuración

**Solo falta el archivo JSON del Service Account.**

---

## ⚡ Método Rápido: Comando de Artisan

**La forma más fácil de probar:**

```powershell
# Ver usuarios con token FCM y opciones
php artisan firebase:test

# Enviar notificación a un usuario específico
php artisan firebase:test --user=1

# Enviar broadcast a todos
php artisan firebase:test --broadcast
```

Este comando:
- ✅ Verifica que existe el Service Account
- ✅ Muestra usuarios con tokens FCM
- ✅ Envía notificaciones de prueba
- ✅ Maneja errores automáticamente

---

## 🧪 Prueba 1: Verificar que el backend está funcionando

### Opción A: Desde el navegador

1. Inicia el servidor de Laravel:
```powershell
php artisan serve --host 0.0.0.0
```

2. Abre tu navegador en: http://localhost:8000 (o https://pruebas.codigomaestro.org)

3. **Inicia sesión** con tu cuenta

4. **Acepta los permisos** de notificación cuando el navegador te pregunte

5. Abre la **Consola del Navegador** (F12) → **Console**

6. Deberías ver un mensaje como:
```
Firebase messaging token generated.
```

7. El token se guardó automáticamente en la base de datos ✅

---

## 🧪 Prueba 2: Enviar Notificación de Prueba

### Método 1: Desde el navegador (DevTools)

1. Con la sesión iniciada, abre la **Consola del Navegador** (F12)

2. Ejecuta este código JavaScript:

```javascript
// Enviar notificación de prueba
fetch('/fcm/test', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
})
.then(response => response.json())
.then(data => console.log('Resultado:', data))
.catch(error => console.error('Error:', error));
```

3. Deberías recibir una notificación push 🔔

---

### Método 2: Desde PowerShell

**Importante:** Primero necesitas tu cookie de sesión.

1. En el navegador con sesión iniciada:
   - F12 → Application → Cookies → selecciona tu dominio
   - Copia el valor de `laravel_session`

2. Ejecuta en PowerShell:

```powershell
# Reemplaza TU_COOKIE con el valor copiado
$session = "TU_COOKIE"

Invoke-RestMethod -Uri "http://localhost:8000/fcm/test" `
    -Method POST `
    -Headers @{
        "Cookie" = "laravel_session=$session"
        "Content-Type" = "application/json"
    }
```

---

## 🧪 Prueba 3: Enviar Broadcast (Admin)

**Solo para usuarios Admin/RH/Supervisor**

### Desde PowerShell:

```powershell
$session = "TU_COOKIE"

$body = @{
    title = "¡Prueba de Broadcast! 📢"
    body = "Esta es una notificación masiva a todos los usuarios"
    data = @{
        type = "test"
        action = "none"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8000/fcm/broadcast" `
    -Method POST `
    -Headers @{
        "Cookie" = "laravel_session=$session"
        "Content-Type" = "application/json"
    } `
    -Body $body
```

### Desde JavaScript (navegador):

```javascript
fetch('/fcm/broadcast', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        title: '¡Prueba de Broadcast! 📢',
        body: 'Esta es una notificación masiva a todos los usuarios',
        data: {
            type: 'test',
            action: 'none'
        }
    })
})
.then(response => response.json())
.then(data => console.log('Resultado:', data));
```

---

## 🧪 Prueba 4: Probar Notificaciones en Segundo Plano

1. **Minimiza el navegador** o cambia a otra pestaña
2. Envía una notificación usando cualquiera de los métodos anteriores
3. Deberías ver la notificación del sistema operativo 🔔

---

## 🔍 Verificar en la Base de Datos

Comprueba que los tokens se están guardando:

```sql
SELECT id, name, email, fcm_token 
FROM users 
WHERE fcm_token IS NOT NULL;
```

O desde Laravel Tinker:

```powershell
php artisan tinker
```

```php
// Ver todos los usuarios con token FCM
User::whereNotNull('fcm_token')->get(['id', 'name', 'email', 'fcm_token']);

// Ver tu token FCM
auth()->user()->fcm_token;
```

---

## 🐛 Troubleshooting

### Error: "Service account file not found"

**Solución:** Descarga el archivo JSON de Firebase Console y colócalo en:
```
D:\Proyectos\SquadControl\storage\app\firebase-service-account.json
```

### No recibo notificaciones

**Verificar:**
1. ¿Aceptaste los permisos de notificación en el navegador?
2. ¿El navegador está en primer plano? (prueba minimizándolo)
3. Abre DevTools → Console y busca errores
4. Verifica que tu usuario tenga `fcm_token` en la base de datos

### Error 401 Unauthorized

**Solución:** Asegúrate de estar autenticado. Verifica que tu cookie de sesión sea válida.

### Error 403 Forbidden (en broadcast)

**Solución:** Solo usuarios con rol Admin, RH o Supervisor pueden enviar broadcast.

---

## 📱 Probar desde Móvil

1. Accede desde tu móvil a: https://pruebas.codigomaestro.org
2. Inicia sesión
3. Acepta los permisos de notificación
4. El token se guardará automáticamente
5. Envía una notificación de prueba desde el servidor

---

## 🎯 Siguiente Paso: Integrar con Eventos

Una vez que funcione, puedes enviar notificaciones automáticas desde eventos de Laravel:

```php
// Ejemplo: Enviar notificación cuando se asigna una tarea
use App\Services\FirebaseService;

public function asignarTarea($userId, $task)
{
    $user = User::find($userId);
    
    if ($user->fcm_token) {
        $firebase = app(FirebaseService::class);
        
        $firebase->sendNotification(
            $user->fcm_token,
            '📋 Nueva Tarea Asignada',
            "Se te asignó la tarea: {$task->title}",
            ['task_id' => $task->id, 'type' => 'task_assigned']
        );
    }
}
```

---

## ✅ Checklist de Prueba

- [ ] Service Account JSON descargado y colocado en `storage/app/`
- [ ] Servidor Laravel iniciado (`php artisan serve`)
- [ ] Frontend compilado (`npm run build` o `npm run dev`)
- [ ] Usuario iniciado sesión en el navegador
- [ ] Permisos de notificación aceptados
- [ ] Token FCM visible en consola del navegador
- [ ] Token guardado en base de datos
- [ ] Notificación de prueba enviada y recibida ✅
- [ ] Notificación en segundo plano funciona ✅
- [ ] Broadcast enviado (si eres Admin) ✅

---

**¡Listo para producción! 🚀**

Una vez que todo funcione, recuerda:
- No subir `firebase-service-account.json` a Git (ya está en `.gitignore`)
- Actualizar las variables de entorno en producción
- Configurar HTTPS para notificaciones push (ya tienes: pruebas.codigomaestro.org)
