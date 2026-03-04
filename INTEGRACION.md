# Script de Integracion - Sistema WooCommerce/SIGE

## Resumen de lo Implementado

Este proyecto implementa:
1. **Sistema de login** validando contra tabla `sige_usu_usuario`
2. **APIs para productos** (buscar, publicar, actualizar en WooCommerce)
3. **Sistema multi-cliente** detectando cliente desde subdominio
4. **Panel de administracion** para gestionar productos por SKU

---

## Estructura de Archivos

```
php/
├── config.php                 # Configuracion central + funciones
├── config/
│   └── {cliente}.txt          # Config por cliente (credenciales WC, BD, etc)
└── api/
    ├── login.php              # Login con formulario HTML
    ├── logout.php             # Cierre de sesion
    ├── admin-productos.php    # Panel de administracion de productos
    ├── product-search.php     # API: Buscar producto por SKU
    ├── product-publish.php    # API: Publicar producto en WooCommerce
    └── product-update.php     # API: Actualizar producto existente
```

---

## 1. Sistema de Configuracion Multi-Cliente

### Archivo: `config.php`

El sistema detecta el cliente de 2 formas:
- **Subdominio**: `pccore.antartidasige.com` -> cliente = `pccore`
- **Parametro GET**: `?cliente=pccore` (para desarrollo local)

```php
function getClienteId() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Si es subdominio.antartidasige.com, extraer subdominio
    if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
        return strtolower($matches[1]);
    }

    // Para desarrollo local, usar parametro
    if (isset($_GET['cliente'])) {
        return strtolower($_GET['cliente']);
    }

    return 'default';  // Cliente por defecto
}
```

### Archivo de configuracion: `config/{cliente}.txt`

```ini
; Configuracion del cliente
; =========================

; WooCommerce API
wc_url=https://tudominio.com/wp-json/wc/v3
wc_key=ck_xxxxxxxxxx
wc_secret=cs_xxxxxxxxxx

; Base de datos (donde esta SIGE)
db_host=127.0.0.1
db_port=3306
db_user=usuario
db_pass=password
db_name=nombre_bd

; Configuracion SIGE
lista_precio=1     ; ID de lista de precios
deposito=1         ; ID de deposito para stock

; Admin fallback (si falla conexion BD)
admin_user=admin
admin_pass=tu_password
```

---

## 2. Sistema de Login

### Validacion contra tabla `sige_usu_usuario`

```php
function validateLogin($user, $pass) {
    try {
        $conn = getDbConnection();

        $stmt = $conn->prepare("SELECT USU_IDUsuario, USU_LogUsu, USU_DatosUsu, USU_Habilitado
                                FROM sige_usu_usuario
                                WHERE USU_LogUsu = ? AND USU_PassWord = ?");
        $stmt->bind_param("ss", $user, $pass);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Verificar si el usuario esta habilitado
            if ($row['USU_Habilitado'] !== 'S') {
                return false;
            }

            return [
                'USU_IDUsuario' => $row['USU_IDUsuario'],
                'USU_LogUsu' => $row['USU_LogUsu'],
                'USU_DatosUsu' => $row['USU_DatosUsu']
            ];
        }

        return false;

    } catch (Exception $e) {
        // Fallback a credenciales del config si falla la BD
        if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
            return [
                'USU_IDUsuario' => 1,
                'USU_LogUsu' => $user,
                'USU_DatosUsu' => 'Administrador'
            ];
        }
        return false;
    }
}
```

### Variables de sesion al loguearse

```php
$_SESSION['logged_in'] = true;
$_SESSION['cliente_id'] = $CLIENTE_ID;
$_SESSION['user'] = $user;
$_SESSION['user_id'] = $usuario['USU_IDUsuario'];
$_SESSION['user_nombre'] = $usuario['USU_DatosUsu'];
```

### Verificar sesion en paginas protegidas

```php
function checkSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /api/login.php');
        exit();
    }

    // Verificar que la sesion es del mismo cliente
    global $CLIENTE_ID;
    if ($_SESSION['cliente_id'] !== $CLIENTE_ID) {
        session_destroy();
        header('Location: /api/login.php');
        exit();
    }
}
```

---

## 3. Consulta SQL para Productos (SIGE)

### Query completa con JOINs

```sql
SELECT
    sige_art_articulo.ART_IDArticulo as sku,
    sige_art_articulo.ART_DesArticulo as nombre,
    sige_art_articulo.ART_PartNumber as part_number,
    sige_art_articulo.art_artobs as descripcion_larga,
    sige_pal_preartlis.PAL_PrecVtaArt AS precio_sin_iva,
    (sige_pal_preartlis.PAL_PrecVtaArt * (1 + (sige_art_articulo.ART_PorcIVARI / 100))) AS precio_final,
    (sige_ads_artdepsck.ADS_CanFisicoArt - sige_ads_artdepsck.ADS_CanReservArt) AS stock,
    sige_adv_artdatvar.ADV_Peso as peso,
    sige_adv_artdatvar.ADV_Alto as alto,
    sige_adv_artdatvar.ADV_Ancho as ancho,
    sige_adv_artdatvar.ADV_Profundidad as profundidad,
    sige_aat_artatrib.atr_descatr as attr_nombre,
    sige_aat_artatrib.aat_descripcion as attr_valor
FROM sige_art_articulo
INNER JOIN sige_pal_preartlis ON sige_art_articulo.ART_IDArticulo = sige_pal_preartlis.ART_IDArticulo
INNER JOIN sige_ads_artdepsck ON sige_art_articulo.ART_IDArticulo = sige_ads_artdepsck.ART_IDArticulo
LEFT JOIN sige_adv_artdatvar ON sige_art_articulo.ART_IDArticulo = sige_adv_artdatvar.art_idarticulo
LEFT JOIN sige_aat_artatrib ON sige_art_articulo.ART_IDArticulo = sige_aat_artatrib.art_idarticulo
WHERE sige_art_articulo.ART_IDArticulo = ?
AND sige_pal_preartlis.LIS_IDListaPrecio = ?
AND sige_ads_artdepsck.DEP_IDDeposito = ?
ORDER BY sige_aat_artatrib.aat_orden
```

### Tablas involucradas:
- `sige_art_articulo` - Datos del articulo (nombre, SKU, IVA)
- `sige_pal_preartlis` - Precios por lista
- `sige_ads_artdepsck` - Stock por deposito
- `sige_adv_artdatvar` - Dimensiones y peso (opcional)
- `sige_aat_artatrib` - Atributos del producto (opcional)
- `sige_usu_usuario` - Usuarios para login

---

## 4. Funcion para Requests a WooCommerce

```php
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode");
    }

    return json_decode($response, true);
}
```

---

## 5. APIs Implementadas

### GET /api/product-search.php?sku={SKU}

Busca producto en SIGE y WooCommerce.

**Respuesta:**
```json
{
  "success": true,
  "producto": {
    "sku": "123",
    "nombre": "Producto X",
    "precio": 1500.00,
    "precio_sin_iva": 1239.67,
    "stock": 10,
    "peso": 2.5,
    "alto": 30,
    "ancho": 20,
    "profundidad": 15,
    "atributos": [
      {"nombre": "Color", "valor": "Negro"},
      {"nombre": "Marca", "valor": "Samsung"}
    ]
  },
  "woo_producto": {
    "id": 456,
    "status": "publish",
    "permalink": "https://...",
    "regular_price": "1500.00",
    "stock_quantity": 10
  }
}
```

### POST /api/product-publish.php

Publica/actualiza producto en WooCommerce.

**Body:**
```json
{"sku": "123"}
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Producto creado en WooCommerce",
  "product": {
    "id": 456,
    "sku": "123",
    "name": "Producto X",
    "status": "publish",
    "permalink": "https://..."
  }
}
```

---

## 6. Autenticacion de APIs

### Por API Key (header o query param)

```php
function checkAuth() {
    $headers = getallheaders();
    $apiKey = $headers['X-Api-Key'] ?? $_GET['api_key'] ?? '';

    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key invalida']);
        exit();
    }
}
```

La API Key se genera automaticamente: `{cliente_id}-sync-2024`

Ejemplo: `pccore-sync-2024`

---

## 7. Pasos para Integrar al Nuevo Proyecto

### Paso 1: Copiar archivos base

```
Copiar:
- php/config.php
- php/api/login.php
- php/api/logout.php
- php/config/ (carpeta)
```

### Paso 2: Crear archivo de configuracion

Crear `php/config/{tu_cliente}.txt` con los datos de WooCommerce y BD.

### Paso 3: Agregar session_start() en config.php

El `config.php` ya tiene `session_start()` al inicio.

### Paso 4: En paginas protegidas

```php
require_once __DIR__ . '/../config.php';
checkSession();  // Redirige a login si no esta logueado

// Tu codigo...
```

### Paso 5: En APIs protegidas

```php
require_once __DIR__ . '/../config.php';
checkAuth();  // Verifica API Key

// Tu codigo...
```

### Paso 6: Para obtener datos del usuario logueado

```php
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_nombre'];
$clienteId = $_SESSION['cliente_id'];
```

---

## 8. Endpoints de Logout

### Archivo: `logout.php`

```php
<?php
session_start();
session_destroy();
header('Location: /api/login.php');
exit();
```

---

## Notas Importantes

1. **Las passwords en SIGE estan en texto plano** (campo `USU_PassWord`)
2. **El campo `USU_Habilitado` debe ser 'S'** para permitir login
3. **Cada cliente tiene su propia sesion** (validada por `$_SESSION['cliente_id']`)
4. **El config.php maneja CORS** automaticamente
5. **Todas las respuestas de API son JSON**
