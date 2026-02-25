# 🚀 Cómo Probar Firebase - Pasos Simples

## Paso 1: Descargar Credenciales (5 minutos)

1. Ve a: https://console.firebase.google.com/project/squadcontrol-b5ab2/settings/serviceaccounts/adminsdk

2. Clic en **"Generar nueva clave privada"**

3. Guarda el archivo descargado como:
   ```
   D:\Proyectos\SquadControl\storage\app\firebase-service-account.json
   ```

**¡Eso es todo lo que necesitas descargar!** ✅

---

## Paso 2: Iniciar Sesión en la App (1 minuto)

1. Inicia el servidor:
   ```powershell
   php artisan serve
   ```

2. Abre: http://localhost:8000

3. **Inicia sesión** con tu usuario

4. **Acepta** los permisos de notificación cuando aparezca el popup

5. En la consola del navegador (F12) verás:
   ```
   Firebase messaging token generated.
   ```

**¡Listo! Tu token se guardó automáticamente** ✅

---

## Paso 3: Probar Notificaciones (30 segundos)

### Opción A: Comando Simple (Recomendado)

```powershell
# Ver usuarios con tokens
php artisan firebase:test

# Enviar notificación al usuario ID 1
php artisan firebase:test --user=1
```

### Opción B: Desde el Navegador

Abre la consola del navegador (F12) y pega:

```javascript
fetch('/fcm/test', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
}).then(r => r.json()).then(console.log);
```

---

## ¿Qué debe pasar?

✅ Recibirás una notificación push en tu navegador  
✅ Si minimizas el navegador, verás la notificación del sistema  
✅ En la consola verás "success: true"

---

## Si algo falla:

### "Service account file not found"
→ Revisa que el archivo esté en: `storage/app/firebase-service-account.json`

### "No hay usuarios con token FCM"
→ Inicia sesión en la app y acepta los permisos de notificación

### No recibo notificaciones
→ Verifica que aceptaste los permisos del navegador  
→ Prueba minimizando el navegador (las notificaciones en segundo plano son más visibles)

---

## 🎯 Comandos Útiles

```powershell
# Ver usuarios con tokens
php artisan firebase:test

# Enviar a usuario específico
php artisan firebase:test --user=1

# Enviar a todos (broadcast)
php artisan firebase:test --broadcast

# Ver tokens en la base de datos
php artisan tinker
>>> User::whereNotNull('fcm_token')->get(['id','name','fcm_token']);
```

---

## ✅ Checklist Rápido

- [ ] Archivo `firebase-service-account.json` en `storage/app/`
- [ ] Usuario con sesión iniciada en la app
- [ ] Permisos de notificación aceptados
- [ ] Comando `php artisan firebase:test` ejecutado
- [ ] Notificación recibida 🔔

**¡Listo! 🎉**
