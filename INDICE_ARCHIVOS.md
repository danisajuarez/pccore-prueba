# Índice de Archivos Críticos - Búsqueda Rápida

**Propósito**: Encontrar rápidamente dónde está cada cosa en el codebase

---

## 1. BÚSQUEDA POR FUNCIÓN

### ¿Dónde se valida el login?

**Archivo**: [php/api/login.php](php/api/login.php)  
**Línea**: 1-50  
**Función**: `AuthService::login($clienteId, $password)`  
**Query**: `SELECT * FROM sige_two_terwoo WHERE TER_IdTercero=? AND TWO_Pass=?`

### ¿Dónde se carga $\_SESSION['cliente_config']?

**Archivo**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php)  
**Línea**: 47-93  
**Función**: `SessionManager::login($clienteData)`  
**Qué hace**: Mapea toda la fila de sige_two_terwoo a $\_SESSION['cliente_config']

### ¿Dónde se conecta a BD SIGE?

**Archivo**: [php/src/Database/DatabaseService.php](php/src/Database/DatabaseService.php)  
**Línea**: 43-65  
**Función**: `DatabaseService::__construct()`  
**Lee de**: `$_SESSION['cliente_config']['db_host/user/pass/name/port']`

### ¿Dónde se sincroniza con WooCommerce?

**Archivo**: [php/api/auto-sync.php](php/api/auto-sync.php)  
**Función**: Principal + `wcRequest()`  
**Flujo**: Busca individual → Batch update

### ¿Dónde se define la API Key?

**Archivo**: [php/bootstrap.php](php/bootstrap.php#L207)  
**Línea**: 207-209  
**Patrón**: `{getClienteId()}-sync-2024`  
**Ejemplo**: `2-sync-2024` para cliente ID 2

### ¿Dónde se conecta a BD Master?

**Archivo**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php)  
**Línea**: 1-50  
**Usa**: Credenciales de [php/config/master.php](php/config/master.php)

### ¿Dónde están las credenciales master hardcodeadas?

**Archivo**: [php/config/master.php](php/config/master.php)  
**Línea**: 11-15  
**Variables**: MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME  
**⚠️ CRÍTICO**: Esto está hardcodeado, debería ser .env

### ¿Dónde se valida la API Key?

**Archivo**: [php/api/auto-sync.php](php/api/auto-sync.php#L22-L26)  
**Línea**: 22-26  
**Validación**: Compara `$keyFromUrl` contra `getClienteId() . '-sync-2024'`

---

## 2. BÚSQUEDA POR TABLA DE BD

### sige_two_terwoo (Maestro de clientes)

**BD**: u962801258_vUylQ (Master)  
**Dónde se consulta**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php#L65-L80)  
**Query**:

```sql
SELECT * FROM sige_two_terwoo
WHERE TER_IdTercero = ? AND TWO_Pass = ? AND TWO_Activo = 'S'
```

**Campos clave**: TER_IdTercero, TWO_Pass, TWO_ServidorDBAnt, TWO_UserDBAnt, TWO_WooUrl, TWO_WooKey

### sige_prs_presho (Precio/stock por punto de venta)

**BD**: BD SIGE (dinámica por cliente)  
**Dónde se consulta**: [php/api/auto-sync.php](php/api/auto-sync.php#L87-L107)  
**Query**:

```sql
SELECT s.art_idarticulo, s.pal_precvtaart, s.ads_disponible
FROM sige_prs_presho s
WHERE s.pal_precvtaart <> s.prs_precvtaart
   OR s.prs_disponible <> s.ads_disponible
```

### sige_art_articulo (Artículos/productos)

**BD**: BD SIGE  
**Dónde se consulta**: Joins con sige_prs_presho  
**Campo importante**: ART_PorcIVARI (porcentaje de IVA)

### sige_tml_termerlib (Mercado Libre config)

**BD**: Master (u962801258_vUylQ)  
**Dónde se consulta**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php#L109-L125)  
**Función**: `getClienteML($clienteId)`

---

## 3. BÚSQUEDA POR VARIABLE DE SESIÓN

### $\_SESSION['logged_in']

**Tipo**: Boolean  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L51)  
**Línea**: 51  
**Valor**: true si login exitoso

### $\_SESSION['cliente_id']

**Tipo**: Integer (TER_IdTercero)  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L52)  
**Línea**: 52  
**Valor**: ID del cliente logueado

### $\_SESSION['cliente_config']

**Tipo**: Array (toda la fila de sige_two_terwoo mapeada)  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L55-L86)  
**Línea**: 55-86  
**Contenido**:

```
['id'] = TER_IdTercero
['nombre'] = TER_RazonSocialTer
['db_host'] = TWO_ServidorDBAnt
['db_user'] = TWO_UserDBAnt
['db_pass'] = TWO_PassDBAnt
['db_port'] = TWO_PuertoDBAnt
['db_name'] = TWO_NombreDBAnt
['wc_url'] = TWO_WooUrl
['wc_key'] = TWO_WooKey
['wc_secret'] = TWO_WooSecret
['lista_precio'] = TWO_ListaPrecio
['deposito'] = TWO_Deposito
['sincronizar_auto'] = TWO_SincronizarAut
```

### $\_SESSION['user']

**Tipo**: Integer (alias de cliente_id)  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L89)  
**Línea**: 89

### $\_SESSION['user_id']

**Tipo**: Integer (alias de cliente_id)  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L90)  
**Línea**: 90

### $\_SESSION['user_nombre']

**Tipo**: String (alias de cliente_nombre)  
**Setea en**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php#L91)  
**Línea**: 91

---

## 4. BÚSQUEDA POR CONSTANTE GLOBAL

### WC_BASE_URL

**Define en**: [php/bootstrap.php](php/bootstrap.php#L267)  
**Línea**: 267  
**Valor**: `$CLIENTE_CONFIG['wc_url']`  
**Ejemplo**: `https://domain.com/wp-json/wc/v3`  
**Usado en**: auto-sync.php, sync.php, product-publish.php

### WC_CONSUMER_KEY

**Define en**: [php/bootstrap.php](php/bootstrap.php#L268)  
**Línea**: 268  
**Valor**: `$CLIENTE_CONFIG['wc_key']`  
**Ejemplo**: `ck_0a9d0169...`

### WC_CONSUMER_SECRET

**Define en**: [php/bootstrap.php](php/bootstrap.php#L269)  
**Línea**: 269  
**Valor**: `$CLIENTE_CONFIG['wc_secret']`  
**Ejemplo**: `cs_16139d32...`

### DB_HOST

**Define en**: [php/bootstrap.php](php/bootstrap.php#L271)  
**Línea**: 271  
**Valor**: `$CLIENTE_CONFIG['db_host']`

### DB_PORT, DB_USER, DB_PASS, DB_NAME

**Define en**: [php/bootstrap.php](php/bootstrap.php#L272-L275)  
**Línea**: 272-275

### API_KEY (usado en config.php legacy)

**Define en**: [php/config.php](php/config.php#L48)  
**Línea**: 48  
**Valor**: `$CLIENTE_ID . '-sync-2024'`  
**⚠️ NOTA**: Define() pero se genera de variables, puede variar por cliente

---

## 5. BÚSQUEDA POR ENDPOINT HTTP

### GET /api/login.php

**Archivo**: [php/api/login.php](php/api/login.php)  
**Método**: GET (mostrar formulario) + POST (procesar)  
**Sin autenticación**: ✓ (es el punto de entrada)  
**Parámetros**: cliente_id, password

### POST /api/logout.php

**Archivo**: [php/api/logout.php](php/api/logout.php)  
**Método**: GET (con ?logout=1)  
**Requiere autenticación**: ✓  
**Qué hace**: Destruye sesión, redirige a login

### GET /index.php

**Archivo**: [php/index.php](php/index.php)  
**Requiere autenticación**: ✓  
**Qué muestra**: Panel principal con opciones de sync  
**API Key visible**: `{cliente_id}-sync-2024`

### POST /api/auto-sync.php

**Archivo**: [php/api/auto-sync.php](php/api/auto-sync.php)  
**Requiere autenticación**: ✓ Sesión  
**Requiere API Key**: ✓ En query parameter ?key=...  
**Respuesta**: JSON con { success, updated, skipped }

### PUT/POST /api/sync.php

**Archivo**: [php/api/sync.php](php/api/sync.php)  
**Requiere autenticación**: ✓ Sesión  
**Requiere API Key**: ✓ En header X-Api-Key  
**Body**: JSON { sku, regular_price, stock_quantity }

### POST /api/product-publish.php

**Archivo**: [php/api/product-publish.php](php/api/product-publish.php)  
**Requiere autenticación**: ✓ Sesión  
**Requiere API Key**: ✓ En header X-Api-Key  
**Body**: JSON con datos del producto

### GET /api/product-search.php

**Archivo**: [php/api/product-search.php](php/api/product-search.php)  
**Requiere autenticación**: ✓ Sesión  
**Parámetros**: ?q=SKU  
**Respuesta**: Busca en SIGE + WooCommerce

---

## 6. BÚSQUEDA POR ANTI-PATRÓN

### Hardcoded Master DB Credentials

**Archivo**: [php/config/master.php](php/config/master.php)  
**Línea**: 11-15  
**Variables**: MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME  
**Solución**: Mover a .env

### Plain Text Passwords en BD

**Tabla**: sige_two_terwoo, campo TWO_Pass  
**Dónde valida**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php#L70)  
**Línea**: 70  
**Solución**: Usar password_hash() + password_verify()

### SSL Deshabilitado

**Archivo 1**: [php/api/auto-sync.php](php/api/auto-sync.php#L41-L42)  
**Archivo 2**: [php/api/product-publish.php](php/api/product-publish.php#L42-L43)  
**Archivo 3**: [php/api/sync.php](php/api/sync.php#L36)  
**Línea**: Buscar CURLOPT_SSL_VERIFYPEER  
**Solución**: Cambiar a true (o eliminar, true es default)

### Dinamic API Key Pattern

**Archivo**: [php/bootstrap.php](php/bootstrap.php#L207)  
**Línea**: 207-209  
**Patrón**: `getClienteId() . '-sync-2024'`  
**Problema**: Adivinable  
**Solución**: Generar key aleatoria en BD, guardar, comparar hash

### Dos Sistemas de Config Coexisten

**Config legacy**: [php/config.php](php/config.php)  
**Config nuevo**: [php/bootstrap.php](php/bootstrap.php)  
**Problema**: Confunde el flujo  
**Solución**: Deprecar config.php completamente

### Constantes Dinámicas con define()

**Archivo**: [php/bootstrap.php](php/bootstrap.php#L264-L277)  
**Línea**: 264-277  
**Problema**: WC_BASE_URL varía por cliente  
**Solución**: Usar propiedades de clase en lugar de define()

---

## 7. BÚSQUEDA POR TEST/DEBUG

### Test de Autenticación Multi-tenant

**Archivo**: [php/test-multitenant.php](php/test-multitenant.php)  
**Qué prueba**: Login + carga de config + listado de clientes

### Test de Conexión Master

**Archivo**: [php/test-conexion.php](php/test-conexion.php)  
**Qué prueba**: Conecta a BD Master, lista clientes

### Explorador de BD Master

**Archivo**: [php/explore-master.php](php/explore-master.php)  
**Qué hace**: Lista todos los clientes y sus configuraciones

### Test Sync

**Archivo**: [php/test_sync.php](php/test_sync.php)  
**Archivo**: [php/test_sync2.php](php/test_sync2.php)  
**Qué prueba**: Flujo de sincronización

---

## 8. BÚSQUEDA POR ARCHIVO DE CONFIGURACIÓN

### Master Database

**Archivo**: [php/config/master.php](php/config/master.php)  
**Contiene**: Hardcodeado MASTER*DB*\*

### Cliente pccoreprueba

**Archivo**: [php/config/pccoreprueba.txt](php/config/pccoreprueba.txt)  
**Contenido**: WooCommerce URL, BD host/user/pass, lista_precio, deposito, admin_user

### Cliente digitalpergamino

**Archivo**: [php/config/digitalpergamino.txt](php/config/digitalpergamino.txt)  
**Contenido**: Mismo formato que pccoreprueba

### Mercado Libre

**Archivo**: [php/config/mercadolibre.php](php/config/mercadolibre.php)  
**Contiene**: Configuración de Mercado Libre (si existe)

### Bootstrap

**Archivo**: [php/bootstrap.php](php/bootstrap.php)  
**Contiene**: Inicialización central, carga de servicios, funciones helpers

### Configuración Legacy

**Archivo**: [php/config.php](php/config.php)  
**Estado**: DEPRECADO, mantiene compatibilidad

---

## 9. BÚSQUEDA POR CLASE/NAMESPACE

### App\Auth\SessionManager

**Archivo**: [php/src/Auth/SessionManager.php](php/src/Auth/SessionManager.php)  
**Métodos clave**: `login()`, `logout()`, `isLoggedIn()`, `getClienteConfig()`

### App\Auth\AuthService

**Archivo**: [php/src/Auth/AuthService.php](php/src/Auth/AuthService.php)  
**Métodos clave**: `login()`, `logout()`, `check()`, `attempt()`

### App\Database\MasterDatabase

**Archivo**: [php/src/Database/MasterDatabase.php](php/src/Database/MasterDatabase.php)  
**Métodos clave**: `findCliente()`, `getClienteById()`, `getClienteML()`

### App\Database\DatabaseService

**Archivo**: [php/src/Database/DatabaseService.php](php/src/Database/DatabaseService.php)  
**Métodos clave**: `__construct()` (lee sesión), `getConnection()`

### App\Config\AppConfig

**Archivo**: [php/src/Config/AppConfig.php](php/src/Config/AppConfig.php)  
**Métodos clave**: `getClienteId()`, `getWcUrl()`, `getDbHost()`, `getApiKey()`

### App\Container

**Archivo**: [php/src/Container.php](php/src/Container.php)  
**Métodos clave**: `register()`, `get()`, `boot()`, `isBooted()`

### App\WooCommerce\WooCommerceClient

**Archivo**: [php/src/WooCommerce/WooCommerceClient.php](php/src/WooCommerce/WooCommerceClient.php)  
**Qué hace**: Cliente HTTP para API WooCommerce

---

## 10. BÚSQUEDA POR PATRÓN DE CÓDIGO

### ¿Cómo obtener la configuración actual?

```php
// Opción 1: Función helper
$config = getClienteConfig();

// Opción 2: AppConfig
$appConfig = \App\Container::get(\App\Config\AppConfig::class);
$wc_url = $appConfig->getWcUrl();

// Opción 3: Directo de sesión
$db_host = $_SESSION['cliente_config']['db_host'];
```

### ¿Cómo conectar a BD SIGE?

```php
// Función helper
$dbService = getSigeConnection();
$db = $dbService->getConnection();

// O directo
$dbService = \App\Container::get(\App\Database\DatabaseService::class);
$db = $dbService->getConnection();
```

### ¿Cómo validar autenticación?

```php
// En controladores/APIs
requireAuth();  // Redirige si no está logueado

// O verificar
if (!isAuthenticated()) {
    http_response_code(401);
    exit;
}
```

### ¿Cómo validar API Key?

```php
$keyFromUrl = $_GET['key'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';
if ($keyFromUrl !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API Key']);
    exit;
}
```

---

## 11. RESUMEN - NAVEGACIÓN RÁPIDA

| Necesito...             | Ir a...                                    |
| ----------------------- | ------------------------------------------ |
| Entender login completo | ANALISIS_ARQUITECTURA.md → Sección 1       |
| Ver tabla de sesión     | REFERENCIA_RAPIDA.md → Variables de Sesión |
| Entender sincronización | DIAGRAMAS_ARQUITECTURA.md → Sección 2      |
| Encontrar anti-patrones | ANALISIS_ARQUITECTURA.md → Sección 6       |
| Ver flujo visual        | DIAGRAMAS_ARQUITECTURA.md                  |
| Ubicación de archivo    | Este documento → Sección 1-3               |
| Entender estructura DB  | ANALISIS_ARQUITECTURA.md → Sección 3       |
| Checklist seguridad     | REFERENCIA_RAPIDA.md → Checklist           |
