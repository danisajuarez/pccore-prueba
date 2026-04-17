# Análisis Exhaustivo de Arquitectura Multi-tenant

**Fecha**: 16 de abril de 2026  
**Objetivo**: Entender la arquitectura actual para optimizar y consolidar

---

## 1. SESSION MANAGER - Manejo de Sesiones y Configuración

### 1.1 Ubicación

- **Archivo**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php)
- **Bootstrap**: [php/bootstrap.php](php/bootstrap.php#L83-L85)
- **Punto de entrada**: [php/api/login.php](php/api/login.php)

### 1.2 Flujo de Login y Asignación de `$_SESSION['cliente_config']`

```
1. Usuario ingresa ID cliente + password en /api/login.php
   ↓
2. AuthService::login($clienteId, $password)
   ↓
3. MasterDatabase::findCliente($clienteId, $password)
   └→ SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = ? AND TWO_Pass = ? AND TWO_Activo = 'S'
   ↓
4. SessionManager::login($clienteData) ← Aquí se asigna $_SESSION['cliente_config']
   ↓
5. Se guarda TODA la fila de sige_two_terwoo en $_SESSION['cliente_config']
```

### 1.3 Código de SessionManager::login()

Ubicación: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L47-L93)

```php
public function login(array $clienteData): void
{
    $_SESSION['logged_in'] = true;
    $_SESSION['cliente_id'] = $clienteData['TER_IdTercero'];
    $_SESSION['cliente_nombre'] = $clienteData['TER_RazonSocialTer'];

    // ← AQUÍ se asigna $_SESSION['cliente_config']
    $_SESSION['cliente_config'] = [
        // Datos del cliente
        'id' => $clienteData['TER_IdTercero'],
        'nombre' => $clienteData['TER_RazonSocialTer'],

        // Credenciales BD SIGE (Antártida)
        'db_host' => $clienteData['TWO_ServidorDBAnt'],
        'db_user' => $clienteData['TWO_UserDBAnt'],
        'db_pass' => $clienteData['TWO_PassDBAnt'],
        'db_port' => (int)($clienteData['TWO_PuertoDBAnt'] ?? 3306),
        'db_name' => $clienteData['TWO_NombreDBAnt'],

        // Credenciales BD WooCommerce (si existen)
        'woo_db_host' => $clienteData['TWO_ServidorDBWoo'] ?? null,
        'woo_db_user' => $clienteData['TWO_UserDBWoo'] ?? null,
        'woo_db_pass' => $clienteData['TWO_PassDBWoo'] ?? null,
        'woo_db_port' => (int)($clienteData['TWO_PuertoDBWoo'] ?? 3306),
        'woo_db_name' => $clienteData['TWO_NombreDBWoo'] ?? null,

        // Credenciales API WooCommerce
        'wc_url' => $clienteData['TWO_WooUrl'] ?? null,
        'wc_key' => $clienteData['TWO_WooKey'] ?? null,
        'wc_secret' => $clienteData['TWO_WooSecret'] ?? null,

        // Configuración SIGE
        'lista_precio' => (int)($clienteData['TWO_ListaPrecio'] ?? 1),
        'deposito' => $clienteData['TWO_Deposito'] ?? '1',

        // Flags
        'sincronizar_auto' => ($clienteData['TWO_SincronizarAut'] ?? 'N') === 'S',
    ];

    // También guarda datos del usuario
    $_SESSION['user'] = $clienteData['TER_IdTercero'];
    $_SESSION['user_id'] = $clienteData['TER_IdTercero'];
    $_SESSION['user_nombre'] = $clienteData['TER_RazonSocialTer'];
}
```

### 1.4 Verificación de Sesión

- **Función**: `isAuthenticated()` en [php/bootstrap.php](php/bootstrap.php#L193-L195)

```php
$CLIENTE_AUTENTICADO = isset($_SESSION['logged_in']) &&
                        $_SESSION['logged_in'] === true &&
                        isset($_SESSION['cliente_config']);
```

- **Reutilización**: Las variables globales `$CLIENTE_CONFIG` y `$CLIENTE_ID` se setean post-login desde `$_SESSION`

---

## 2. APPCONFIG - Clase de Configuración

### 2.1 Ubicación

- **Archivo**: [php/src/Config/AppConfig.php](php/src/Config/AppConfig.php)

### 2.2 Cómo Lee la Configuración Post-Login

```php
public function __construct()
{
    $this->loadConfigFromSession();
}

private function loadConfigFromSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa. Debe iniciar sesión primero.");
    }

    $sessionConfig = $_SESSION['cliente_config'];

    // Guardar ID del cliente
    $this->clienteId = (string)($sessionConfig['id'] ?? '');

    // Mapea directamente de la sesión
    $this->config = [
        // WooCommerce API
        'wc_url' => $sessionConfig['wc_url'] ?? '',
        'wc_key' => $sessionConfig['wc_key'] ?? '',
        'wc_secret' => $sessionConfig['wc_secret'] ?? '',

        // Base de datos SIGE
        'db_host' => $sessionConfig['db_host'] ?? '',
        'db_port' => $sessionConfig['db_port'] ?? 3306,
        'db_user' => $sessionConfig['db_user'] ?? '',
        'db_pass' => $sessionConfig['db_pass'] ?? '',
        'db_name' => $sessionConfig['db_name'] ?? '',

        // Configuración SIGE
        'lista_precio' => $sessionConfig['lista_precio'] ?? 1,
        'deposito' => $sessionConfig['deposito'] ?? '1',
    ];
}
```

### 2.3 Getters Disponibles

Ubicación: [php/src/Config/AppConfig.php](php/src/Config/AppConfig.php#L75-L150)

```php
getClienteId()      // TER_IdTercero
getWcUrl()          // TWO_WooUrl
getWcKey()          // TWO_WooKey
getWcSecret()       // TWO_WooSecret
getDbHost()         // TWO_ServidorDBAnt
getDbPort()         // TWO_PuertoDBAnt
getDbUser()         // TWO_UserDBAnt
getDbPass()         // TWO_PassDBAnt
getDbName()         // TWO_NombreDBAnt
getListaPrecio()    // TWO_ListaPrecio
getDeposito()       // TWO_Deposito
getApiKey()         // Genera: {clienteId}-sync-2024
```

---

## 3. TABLA SIGE_TWO_TERWOO - Maestro de Clientes

### 3.1 Ubicación

- **BD**: `u962801258_vUylQ` (BD Master hardcodeada)
- **Host**: Credenciales en [php/config/master.php](php/config/master.php)
- **Acceso**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php)

### 3.2 Estructura de la Tabla

| Campo                  | Tipo    | Descripción                 | Crítico |
| ---------------------- | ------- | --------------------------- | ------- |
| **TER_IdTercero**      | INT     | ID cliente (PK)             | ✓       |
| **TER_RazonSocialTer** | VARCHAR | Nombre cliente              | ✓       |
| **TWO_Pass**           | VARCHAR | Password plain text         | ✓       |
| **TWO_Activo**         | CHAR(1) | 'S'='activo','N'='inactivo' | ✓       |
| TWO_ServidorDBAnt      | VARCHAR | Host BD SIGE (Antártida)    | ✓       |
| TWO_PuertoDBAnt        | INT     | Puerto BD SIGE (def. 3306)  | ✓       |
| TWO_UserDBAnt          | VARCHAR | Usuario BD SIGE             | ✓       |
| TWO_PassDBAnt          | VARCHAR | Pass BD SIGE                | ✓       |
| TWO_NombreDBAnt        | VARCHAR | Nombre BD SIGE              | ✓       |
| TWO_ServidorDBWoo      | VARCHAR | Host BD WooCommerce         | ○       |
| TWO_UserDBWoo          | VARCHAR | Usuario BD WooCommerce      | ○       |
| TWO_PassDBWoo          | VARCHAR | Pass BD WooCommerce         | ○       |
| TWO_PuertoDBWoo        | INT     | Puerto BD WooCommerce       | ○       |
| TWO_NombreDBWoo        | VARCHAR | Nombre BD WooCommerce       | ○       |
| TWO_WooUrl             | VARCHAR | URL API WooCommerce         | ✓       |
| TWO_WooKey             | VARCHAR | Consumer Key WC API         | ✓       |
| TWO_WooSecret          | VARCHAR | Consumer Secret WC API      | ✓       |
| TWO_ListaPrecio        | INT     | ID lista SIGE (default 1)   | ✓       |
| TWO_Deposito           | VARCHAR | ID depósito SIGE            | ✓       |
| TWO_SincronizarAut     | CHAR(1) | 'S'=auto-sync,'N'=manual    | ○       |

### 3.3 Query de Búsqueda (Login)

Ubicación: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php#L65-L80)

```sql
SELECT *
FROM sige_two_terwoo
WHERE TER_IdTercero = ?
  AND TWO_Pass = ?
  AND TWO_Activo = 'S'
```

### 3.4 Ejemplo de Registro

Ubicación: [php/sql/insert_portalgcom.sql](php/sql/insert_portalgcom.sql)

```sql
INSERT INTO sige_two_terwoo (
    TER_IdTercero,
    TER_RazonSocialTer,
    TWO_Pass,
    TWO_Activo,
    TWO_ServidorDBAnt,
    TWO_PuertoDBAnt,
    TWO_UserDBAnt,
    TWO_PassDBAnt,
    TWO_NombreDBAnt
) VALUES (
    2,                           -- Cliente ID
    'Portalgcom',               -- Nombre
    'portalgcom2024',           -- Password plain text
    'S',                        -- Activo
    'giuggia.dyndns-home.com',  -- BD SIGE host
    3307,                       -- Puerto
    'root',                     -- Usuario BD
    'giuggia',                  -- Password BD
    'giuggia'                   -- Nombre BD
);
```

---

## 4. PUNTOS DE ENTRADA DE SINCRONIZACIÓN

### 4.1 AUTO-SYNC: Sincronización Híbrida (Recomendada)

**Archivo**: [php/api/auto-sync.php](php/api/auto-sync.php)

**Endpoint**: `POST /api/auto-sync.php?key={cliente_id}-sync-2024`

**Flujo**:

1. Valida autenticación por sesión
2. Valida API Key: `{cliente_id}-sync-2024`
3. Trae productos con cambios de SIGE:
   ```sql
   WHERE (s.pal_precvtaart <> s.prs_precvtaart
      OR s.prs_disponible <> s.ads_disponible)
   LIMIT 50  -- BATCH_SIZE
   ```
4. Para CADA SKU, busca en WooCommerce individualmente
5. Prepara batch UPDATE con los encontrados
6. Actualiza todos juntos en WooCommerce

**Credenciales Usadas**:

- Sesión: `$_SESSION['cliente_config']['wc_url']`
- Sesión: `$_SESSION['cliente_config']['wc_key']`
- Sesión: `$_SESSION['cliente_config']['wc_secret']`

**Fragmento Clave**:

```php
// Línea 75: Obtiene conexión a BD SIGE del cliente
$dbService = getSigeConnection();
$db = $dbService->getConnection();

// Línea 36-41: wcRequest usa constantes del bootstrap
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;  // Definido en bootstrap desde sesión
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;
    // ...
}
```

### 4.2 SYNC: Sincronización Individual

**Archivo**: [php/api/sync.php](php/api/sync.php)

**Endpoint**: `PUT/POST /api/sync.php`

**Entrada**: JSON con SKU, regular_price, stock_quantity

**Flujo**:

1. Valida API Key en headers: `X-Api-Key: {cliente_id}-sync-2024`
2. Busca producto por SKU en SIGE
3. Obtiene IVA del producto
4. Calcula precio sin IVA
5. Actualiza precio/stock en WooCommerce

**Credenciales Usadas**: Mismas que auto-sync (de sesión)

### 4.3 PRODUCT-PUBLISH: Publicar en WooCommerce

**Archivo**: [php/api/product-publish.php](php/api/product-publish.php)

**Endpoint**: `POST /api/product-publish.php`

**Flujo**:

1. Requiere sesión + API Key válida
2. Busca o crea categoría en WooCommerce
3. Calcula precio CON IVA (WC no calcula)
4. Busca imágenes en Mercado Libre (opcional)
5. Crea o actualiza producto en WooCommerce
6. Registra sync en BD SIGE

**Código Clave** (línea 30-40):

```php
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // ← ANTI-PATRÓN
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // ← ANTI-PATRÓN
    // ...
}
```

### 4.4 PRODUCT-SEARCH: Buscar en SIGE + WooCommerce

**Archivo**: [php/api/product-search.php](php/api/product-search.php)

**Endpoint**: `GET /api/product-search.php?q={sku}`

**Requiere**: Sesión autenticada

---

## 5. CÓMO SE USAN LAS CREDENCIALES EN SINCRONIZACIÓN

### 5.1 Flujo de Credenciales desde Login hasta Sync

```
/api/login.php
    ↓
POST con (cliente_id, password)
    ↓
AuthService::login($clienteId, $password)
    ↓
MasterDatabase::findCliente() ← Lee de sige_two_terwoo
    ↓
SessionManager::login($clienteData)
    ↓
$_SESSION['cliente_config'] = [
    'wc_url' => $clienteData['TWO_WooUrl'],      ← De BD Master
    'wc_key' => $clienteData['TWO_WooKey'],      ← De BD Master
    'wc_secret' => $clienteData['TWO_WooSecret'],← De BD Master
    'db_host' => $clienteData['TWO_ServidorDBAnt'],
    'db_user' => $clienteData['TWO_UserDBAnt'],
    'db_pass' => $clienteData['TWO_PassDBAnt'],
    'db_name' => $clienteData['TWO_NombreDBAnt'],
]
    ↓
bootstrap.php: Define constantes WC_* desde $_SESSION['cliente_config']
    ↓
/api/auto-sync.php usa WC_BASE_URL, WC_CONSUMER_KEY, WC_CONSUMER_SECRET
```

### 5.2 Credenciales en Bootstrap

Ubicación: [php/bootstrap.php](php/bootstrap.php#L264-L277)

```php
// Líneas 264-277: Definir constantes desde sesión para legacy
if ($CLIENTE_AUTENTICADO) {
    if (!defined('WC_BASE_URL')) {
        define('WC_BASE_URL', $CLIENTE_CONFIG['wc_url'] ?? '');
        define('WC_CONSUMER_KEY', $CLIENTE_CONFIG['wc_key'] ?? '');
        define('WC_CONSUMER_SECRET', $CLIENTE_CONFIG['wc_secret'] ?? '');

        define('DB_HOST', $CLIENTE_CONFIG['db_host'] ?? '');
        define('DB_PORT', $CLIENTE_CONFIG['db_port'] ?? 3306);
        define('DB_USER', $CLIENTE_CONFIG['db_user'] ?? '');
        define('DB_PASS', $CLIENTE_CONFIG['db_pass'] ?? '');
        define('DB_NAME', $CLIENTE_CONFIG['db_name'] ?? '');
    }
}
```

### 5.3 Acceso a Credenciales en APIs

```php
// En /api/auto-sync.php (línea 36-41)
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;  // ← Constante de bootstrap
    $url .= 'consumer_key=' . WC_CONSUMER_KEY;      // ← De bootstrap
    $url .= 'consumer_secret=' . WC_CONSUMER_SECRET; // ← De bootstrap
    // ...
}

// O por sesión directamente
$config = getClienteConfig();
$wc_url = $config['wc_url'];        // ← De $_SESSION['cliente_config']
```

---

## 6. ANTI-PATRONES DETECTADOS

### 6.1 Credenciales Hardcodeadas de BD Master

**Archivo**: [php/config/master.php](php/config/master.php)

```php
define('MASTER_DB_HOST', 'localhost');
define('MASTER_DB_PORT', 3306);
define('MASTER_DB_USER', 'u962801258_0Ov4s');      // ← Hardcodeado
define('MASTER_DB_PASS', 'Dona2012');              // ← Hardcodeado
define('MASTER_DB_NAME', 'u962801258_vUylQ');      // ← Hardcodeado
```

**Riesgo**: Credenciales expuestas en repositorio

**Alternativa**: Variables de entorno

### 6.2 Passwords en Plain Text

**Tabla sige_two_terwoo**:

- Campo `TWO_Pass` almacena passwords sin cifrar
- Se comparan directamente en query: `WHERE TWO_Pass = ?`

**Ejemplo en BD**:

```
TER_IdTercero=2, TWO_Pass='portalgcom2024'  (visible en plain)
```

**Riesgo**: Si BD se compromete, todos los clientes expuestos

### 6.3 SSL Deshabilitado en Todas Partes

**Ubicación**: [php/api/auto-sync.php](php/api/auto-sync.php#L41-L42)

```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // ← ANTI-PATRÓN
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // ← ANTI-PATRÓN
```

Se repite en:

- product-publish.php
- sync.php
- product-search.php
- Todas las llamadas a WooCommerce

**Riesgo**: Vulnerable a MITM (Man in the Middle)

### 6.4 API Key Generada Dinámicamente

**Sin persistencia**:

```php
$expectedKey = getClienteId() . '-sync-2024';  // Generada al vuelo
```

**Nunca se guarda**: No se almacena en BD ni sesión

**Riesgo**: Cualquiera puede adivinarla si conoce el cliente_id

### 6.5 Fallback a config.php Confunde el Flujo

**Archivo**: [php/config.php](php/config.php#L14-L27)

```php
// Líneas 14-27: ¿Usar bootstrap o config.php legacy?
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    require_once __DIR__ . '/bootstrap.php';
    $appConfig = \App\Container::get(\App\Config\AppConfig::class);
} else {
    // Fallback al código original
    $CLIENTE_ID = getClienteId();
    $CONFIG = loadClientConfig($CLIENTE_ID);
}
```

**Problema**: Coexisten dos flujos de configuración

### 6.6 Constantes Dinámicas con define()

**Ubicación**: [php/bootstrap.php](php/bootstrap.php#L264-L277)

```php
// Las constantes se definen DESPUÉS del login, no al startup
if ($CLIENTE_AUTENTICADO) {
    if (!defined('WC_BASE_URL')) {
        define('WC_BASE_URL', $CLIENTE_CONFIG['wc_url'] ?? '');  // ← Varía por cliente
    }
}
```

**Problema**: PHP cree que son constantes pero varían. Si dos usuarios acceden en paralelo a apis diferentes pueden intervenirse.

---

## 7. RESUMEN EJECUTIVO

### Arquitectura Actual

- **Patrón**: Multi-tenant por sesión + BD dinámica
- **Master BD**: Centraliza credenciales de todos los clientes
- **Sesión**: Almacena configuración completa post-login
- **Sincronización**: Usa credenciales de sesión para conectar a BD SIGE + WooCommerce

### Fortalezas

✓ Isolamiento de datos por cliente (BD diferente)  
✓ Sin necesidad de archivos de config por cliente  
✓ Login centralizado contra BD Master

### Debilidades

✗ Passwords plain text en BD  
✗ API Key adivinable (patrón predecible)  
✗ SSL deshabilitado en sincronización  
✗ Credenciales master hardcodeadas  
✗ Coexistencia de flujos legacy + nuevo  
✗ Constantes dinámicas pueden colisionar

### Recomendaciones Inmediatas

1. Migrar credenciales master a variables de entorno
2. Implementar hashing de passwords
3. Habilitar SSL en todas las llamadas a WooCommerce
4. Generar y guardar API Keys únicas en BD
5. Consolidar un solo flujo de configuración (eliminar config.php legacy)
