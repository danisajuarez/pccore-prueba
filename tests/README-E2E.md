# E2E Admin Productos (Playwright)

## Requisitos
- Tener PHP disponible en PATH.
- Credenciales de `admin-productos.php`.

## Ejecutar

```powershell
$env:ADMIN_USER="tu_usuario"
$env:ADMIN_PASS="tu_password"
npm run test:e2e
```

## Modos utiles

```powershell
npm run test:e2e:headed
npm run test:e2e:ui
```

## Publicacion real (opcional)
Por seguridad, el flujo que publica en WooCommerce esta desactivado por defecto.

```powershell
$env:ADMIN_USER="tu_usuario"
$env:ADMIN_PASS="tu_password"
$env:RUN_PUBLISH_FLOW="true"
npm run test:e2e
```
