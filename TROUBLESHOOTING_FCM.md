# 🔍 Troubleshooting: Token FCM No Se Guarda

## ✅ Paso 1: Verificar que el token se genera

1. Abre la aplicación en el navegador
2. Presiona **F12** para abrir DevTools
3. Ve a la pestaña **Console**
4. Recarga la página (F5)

**Deberías ver:**
```
✅ Firebase messaging token generated: BKcN... (un token largo)
```

### ❌ Si NO ves el token:

#### Problema A: No aparece nada de Firebase
```
Solución: Verifica que VITE_FIREBASE_VAPID_KEY esté en tu .env
```

#### Problema B: Dice "Notification permission denied"
```
Solución: 
1. Haz clic en el ícono 🔒 en la barra de direcciones
2. Ve a "Notificaciones" y selecciona "Permitir"
3. Recarga la página
```

#### Problema C: Error de VAPID key
```
Solución: Verifica que el VAPID key sea correcto en .env
```

---

## ✅ Paso 2: Verificar que se envía al backend

En la consola del navegador deberías ver:

```
✅ FCM token saved successfully
🔔 Notificaciones push activadas
```

### ❌ Si ves error 401 (Unauthorized):

**Problema:** No estás autenticado cuando se intenta guardar

**Solución Automática:**
- El sistema guardará el token automáticamente cuando navegues después del login
- Simplemente ve a cualquier página (Dashboard, Tareas, etc.)
- Deberías ver: "🔔 Notificaciones push activadas"

**Solución Manual:**
Ejecuta esto en la consola del navegador:

```javascript
// Ver si hay token pendiente
localStorage.getItem('pendingFCMToken')

// Forzar reintentar guardar
import('/resources/js/composables/useFCMToken.js').then(m => m.retryPendingFCMToken())
```

---

## ✅ Paso 3: Verificar en la base de datos

```sql
SELECT id, name, email, fcm_token 
FROM users 
WHERE id = TU_ID_USUARIO;
```

O desde Artisan:

```powershell
php artisan tinker
```

```php
// Ver tu usuario
User::find(1)->fcm_token

// Ver todos los usuarios con token
User::whereNotNull('fcm_token')->get(['id','name','fcm_token']);
```

---

## 🔄 Solución Rápida: Forzar Guardado

### Opción 1: Desde la consola del navegador

```javascript
// 1. Copiar el token de la consola (el que dice "token generated: XXX")
const token = "PEGA_AQUI_EL_TOKEN";

// 2. Ejecutar esto
fetch('/fcm/token', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ token })
}).then(r => r.json()).then(console.log);
```

### Opción 2: Desde PowerShell (con Artisan)

```powershell
php artisan tinker
```

```php
// Reemplaza USER_ID y TOKEN con sus valores reales
$user = User::find(USER_ID);
$user->fcm_token = 'TU_TOKEN_AQUI';
$user->save();
```

---

## 🧪 Verificar que funciona

Una vez guardado el token, prueba:

```powershell
php artisan firebase:test --user=TU_ID
```

Deberías recibir una notificación 🔔

---

## 📋 Checklist de Diagnóstico

Revisa en la consola del navegador (F12):

- [ ] ✅ `Firebase messaging token generated: XXX`
- [ ] ✅ `FCM token saved successfully`  
- [ ] ✅ Toast: "🔔 Notificaciones push activadas"
- [ ] ❌ Error 401 → El token se guardará automáticamente al navegar
- [ ] ❌ CSRF token not found → Recarga la página
- [ ] ❌ Notification permission denied → Acepta permisos en el navegador

---

## 🔍 Debug Avanzado

### Ver requests en Network

1. F12 → Network
2. Filtro: Busca "fcm/token"
3. Deberías ver un POST request
4. Status: 200 = ✅ Éxito | 401 = No autenticado | 500 = Error servidor

### Ver lo que llega al backend

En `app/Http/Controllers/FirebaseController.php` agrega temporalmente:

```php
public function saveToken(Request $request)
{
    \Log::info('FCM Token recibido', [
        'user_id' => Auth::id(),
        'token' => $request->token,
    ]);
    
    // ... resto del código
}
```

Luego revisa: `storage/logs/laravel.log`

---

## 🎯 Solución Definitiva

Si nada funciona:

1. **Cierra sesión**
2. **Cierra el navegador completamente**
3. **Abre el navegador de nuevo**
4. **Ve a la app y haz login**
5. **Acepta los permisos de notificación**
6. **Espera 2 segundos**
7. **Ve a cualquier página (Dashboard)**

El sistema ahora reintenta automáticamente después de cada navegación.

---

## 💡 Tip Pro

Después de hacer login, abre la consola y ejecuta:

```javascript
// Ver estado actual
console.table({
    'Token generado': !!localStorage.getItem('fcm_token'),
    'Usuario autenticado': !!document.querySelector('meta[name="user-id"]'),
    'CSRF disponible': !!document.querySelector('meta[name="csrf-token"]'),
});
```

Todos deberían estar en `true` ✅
