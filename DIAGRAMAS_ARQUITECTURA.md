# Diagramas de Arquitectura - Sistema Multi-tenant

**Última actualización**: 16 de abril de 2026

---

## 1. FLUJO DE AUTENTICACIÓN Y CARGA DE CONFIG

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  USUARIO: Browser abierto en https://pccore.antartidasige.com/         │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ GET /api/login.php                                                      │
│                                                                         │
│ Renderiza formulario:                                                   │
│  - Input: ID de Cliente (TER_IdTercero)                                │
│  - Input: Password (TWO_Pass)                                          │
│  - Button: Ingresar                                                     │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ POST /api/login.php                                                     │
│ Body: { cliente_id: 2, password: "portalgcom2024" }                    │
│                                                                         │
│ [AuthService::login($clienteId, $password)]                            │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ [MasterDatabase::findCliente($clienteId, $password)]                   │
│                                                                         │
│ Conexión: MASTER_DB_HOST (localhost)                                   │
│          MASTER_DB_NAME (u962801258_vUylQ)                             │
│          MASTER_DB_USER (u962801258_0Ov4s)                             │
│          MASTER_DB_PASS (Dona2012)                    ← HARDCODEADO    │
│                                                                         │
│ Query:                                                                  │
│  SELECT *                                                              │
│  FROM sige_two_terwoo                                                  │
│  WHERE TER_IdTercero = 2                                               │
│    AND TWO_Pass = 'portalgcom2024'                                     │
│    AND TWO_Activo = 'S'                                                │
│                                                                         │
│ Result: 1 fila con TODO lo que necesita el cliente                     │
│         ├─ TER_IdTercero: 2                                            │
│         ├─ TWO_ServidorDBAnt: giuggia.dyndns-home.com                 │
│         ├─ TWO_UserDBAnt: root                                         │
│         ├─ TWO_PassDBAnt: giuggia                                      │
│         ├─ TWO_NombreDBAnt: giuggia                                    │
│         ├─ TWO_WooUrl: https://domain.com/wp-json/wc/v3               │
│         ├─ TWO_WooKey: ck_xxxxx                                        │
│         └─ TWO_WooSecret: cs_xxxxx                                     │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ [SessionManager::login($clienteData)]                                  │
│                                                                         │
│ Mapea la fila de BD a estructura de sesión:                            │
│                                                                         │
│  $_SESSION['logged_in'] = true;                                        │
│  $_SESSION['cliente_id'] = 2;                                          │
│  $_SESSION['cliente_config'] = [                                       │
│      'id' => 2,                                                        │
│      'nombre' => 'Portalgcom',                                         │
│      'db_host' => 'giuggia.dyndns-home.com',                           │
│      'db_user' => 'root',                                              │
│      'db_pass' => 'giuggia',                                           │
│      'db_port' => 3307,                                                │
│      'db_name' => 'giuggia',                                           │
│      'wc_url' => 'https://domain.com/wp-json/wc/v3',                  │
│      'wc_key' => 'ck_xxxxx',                                           │
│      'wc_secret' => 'cs_xxxxx',                                        │
│      'lista_precio' => 2,                                              │
│      'deposito' => '1',                                                │
│      'sincronizar_auto' => false                                       │
│  ];                                                                    │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ [bootstrap.php: Define constantes from $_SESSION]                      │
│                                                                         │
│ Código (línea 264-277):                                                │
│  if ($CLIENTE_AUTENTICADO) {                                           │
│      define('WC_BASE_URL', $CLIENTE_CONFIG['wc_url']);                │
│      define('WC_CONSUMER_KEY', $CLIENTE_CONFIG['wc_key']);            │
│      define('WC_CONSUMER_SECRET', $CLIENTE_CONFIG['wc_secret']);      │
│      define('DB_HOST', $CLIENTE_CONFIG['db_host']);                   │
│      define('DB_PORT', $CLIENTE_CONFIG['db_port']);                   │
│      define('DB_USER', $CLIENTE_CONFIG['db_user']);                   │
│      define('DB_PASS', $CLIENTE_CONFIG['db_pass']);                   │
│      define('DB_NAME', $CLIENTE_CONFIG['db_name']);                   │
│  }                                                                     │
│                                                                         │
│ Resultado: Constantes disponibles para APIs                            │
│           (pero varían por cliente!)                                   │
│                                                                         │
└────────────────────────┬────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ redirect to /index.php                                                  │
│                                                                         │
│ Usuario ve panel de sincronización con:                                │
│  - Nombre del cliente: "Portalgcom"                                    │
│  - Botón: Sincronizar Ahora                                            │
│  - API Key visible: "2-sync-2024"                                      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. FLUJO DE SINCRONIZACIÓN (AUTO-SYNC)

```
┌──────────────────────────────────────────────────────────────────────────┐
│  USUARIO HACE CLICK: "Sincronizar Ahora"                                │
│  POST /api/auto-sync.php?key=2-sync-2024                                │
└────────────────────────┬─────────────────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ [bootstrap.php carga]                                                    │
│  - Verifica: isAuthenticated()? ✓ (hay $_SESSION['cliente_config'])     │
│  - Verifica: API Key válida? $key == getClienteId() . '-sync-2024'      │
│             ('2-sync-2024' == '2-sync-2024')? ✓                         │
│                                                                          │
└────────────────────────┬─────────────────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ [auto-sync.php Lógica]                                                   │
│                                                                          │
│ PASO 1: Conectar a BD SIGE del cliente                                  │
│  $dbService = getSigeConnection();  ← Lee de $_SESSION['cliente_config']│
│  $db = $dbService->getConnection();  → mysqli a 'giuggia' BD            │
│                                                                          │
│ PASO 2: Contar productos con cambios pendientes                         │
│  SELECT COUNT(*) as total                                               │
│  FROM sige_prs_presho s                                                 │
│  WHERE s.pal_precvtaart <> s.prs_precvtaart                            │
│     OR s.prs_disponible <> s.ads_disponible                            │
│  ──→ Si 0, retorna: { success: true, remaining: 0 }                   │
│                                                                          │
│ PASO 3: Traer lote de 50 productos                                      │
│  SELECT s.art_idarticulo as sku,                                        │
│         s.pal_precvtaart as precio,                                     │
│         s.ads_disponible as stock,                                      │
│         (s.pal_precvtaart / (1 + (a.ART_PorcIVARI / 100))) AS precio_sin_iva
│  FROM sige_prs_presho s                                                 │
│  INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
│  WHERE (s.pal_precvtaart <> s.prs_precvtaart                            │
│     OR s.prs_disponible <> s.ads_disponible)                            │
│  LIMIT 50                                                                │
│                                                                          │
│ PASO 4: Para CADA SKU, buscar en WooCommerce                            │
│  foreach ($productos as $prod) {                                        │
│    $sku = $prod['sku'];  // e.g., 'DCPT530DW'                          │
│    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));        │
│    if ($wcProduct encontrado) {                                         │
│      $batchUpdate[] = [ 'id' => $wcProduct['id'], ... ];               │
│    }                                                                    │
│  }                                                                      │
│                                                                          │
│  wcRequest() usa:                                                       │
│    - WC_BASE_URL = 'https://domain.com/wp-json/wc/v3'                 │
│    - WC_CONSUMER_KEY = 'ck_xxxxx'                                      │
│    - WC_CONSUMER_SECRET = 'cs_xxxxx'                                   │
│    ⚠️  curl_setopt(CURLOPT_SSL_VERIFYPEER, false)  ← INSEGURO          │
│                                                                          │
│ PASO 5: Actualizar en batch                                             │
│  POST /products/batch con payload de actualizaciones                    │
│  [{ 'id': 123, 'regular_price': '99.99', 'stock_quantity': 50 }, ...]  │
│                                                                          │
│ PASO 6: Retornar resultado                                              │
│  { success: true,                                                       │
│    updated: 15,                                                         │
│    skipped: 5,                                                          │
│    errors: [] }                                                         │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 3. FLUJO DE CREDENCIALES - BD MASTER → SESSION → SYNC

```
┌────────────────────────────────────┐
│ php/config/master.php              │
│ (HARDCODEADO - ANTI-PATRÓN)        │
│                                    │
│ define('MASTER_DB_HOST', 'localhost')
│ define('MASTER_DB_USER', 'u962801258_0Ov4s')
│ define('MASTER_DB_PASS', 'Dona2012')
│ define('MASTER_DB_NAME', 'u962801258_vUylQ')
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│ MasterDatabase                     │
│ Conecta a BD Master                │
│ (u962801258_vUylQ)                 │
│                                    │
│ Query:                             │
│  SELECT *                          │
│  FROM sige_two_terwoo              │
│  WHERE TER_IdTercero = 2           │
│    AND TWO_Pass = ?                │
│    AND TWO_Activo = 'S'            │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│ Fila de sige_two_terwoo            │
│                                    │
│ ├─ TER_IdTercero: 2                │
│ ├─ TWO_ServidorDBAnt: giuggia...   │
│ ├─ TWO_UserDBAnt: root             │
│ ├─ TWO_PassDBAnt: giuggia          │
│ ├─ TWO_NombreDBAnt: giuggia        │
│ ├─ TWO_WooUrl: https://domain...   │
│ ├─ TWO_WooKey: ck_xxxxx            │
│ ├─ TWO_WooSecret: cs_xxxxx         │
│ └─ TWO_ListaPrecio: 2              │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│ SessionManager::login()            │
│                                    │
│ Mapea a estructura de sesión:      │
│ $_SESSION['cliente_config'] = [    │
│   'db_host' => '...',              │
│   'db_user' => '...',              │
│   'db_pass' => '...',              │
│   'wc_url' => '...',               │
│   'wc_key' => '...',               │
│   'wc_secret' => '...',            │
│   ...                              │
│ ]                                  │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│ bootstrap.php                      │
│                                    │
│ Define constantes:                 │
│ define('WC_BASE_URL', $CLIENTE_CONFIG['wc_url'])
│ define('WC_CONSUMER_KEY', $CLIENTE_CONFIG['wc_key'])
│ define('WC_CONSUMER_SECRET', $CLIENTE_CONFIG['wc_secret'])
│ define('DB_HOST', $CLIENTE_CONFIG['db_host'])
│ define('DB_PORT', $CLIENTE_CONFIG['db_port'])
│ define('DB_USER', $CLIENTE_CONFIG['db_user'])
│ define('DB_PASS', $CLIENTE_CONFIG['db_pass'])
│ define('DB_NAME', $CLIENTE_CONFIG['db_name'])
│                                    │
│ ⚠️  Constantes varían por cliente! │
└────────────┬───────────────────────┘
             │
             ├─────────────┬──────────────┐
             │             │              │
             ▼             ▼              ▼
      ┌─────────┐  ┌──────────┐  ┌──────────┐
      │ auto-   │  │ product- │  │ sync.php │
      │ sync.php│  │ publish  │  │          │
      │         │  │          │  │          │
      │ usa WC_ │  │ usa WC_  │  │ usa DB_* │
      │ *       │  │ *        │  │          │
      │ usa DB_ │  │ usa DB_* │  │          │
      │ *       │  │          │  │          │
      └─────────┘  └──────────┘  └──────────┘
```

---

## 4. TABLA DE RESPONSABILIDADES

```
COMPONENTE                RESPONSABILIDAD                        SEGURIDAD
═══════════════════════════════════════════════════════════════════════════════

SessionManager            Mapea BD → $_SESSION['cliente_config']  ⚠️  Plain text
                          post-login

AuthService               Valida login contra BD Master           ⚠️  Plain text
                          Delega a SessionManager                 password

MasterDatabase            Conexión a BD Master (única             ⚠️  Hardcoded
                          centralizada)                           creds

AppConfig                 Lee de $_SESSION['cliente_config']      ✓ Post-login
                          Proporciona getters

DatabaseService           Conexión dinámica a BD SIGE             ✓ Dinámica
                          basada en cliente_config                 (por cliente)

bootstrap.php             Define constantes globales desde        ⚠️  Variables
                          cliente_config                          dinámicas

auto-sync.php             Sincronización híbrida                  ⚠️  SSL off
                          (busca individual, batch update)        Adivinable
                                                                  API Key

product-publish.php       Publica producto en WC                  ⚠️  SSL off
                          Busca en ML si existe

sync.php                  Sincronización individual               ⚠️  SSL off
                          por SKU
```

---

## 5. ÁRBOL DE DEPENDENCIAS

```
login.php
├─ bootstrap.php
│  ├─ config/master.php           (HARDCODEADO)
│  ├─ src/Container.php
│  ├─ src/Database/MasterDatabase.php
│  │  └─ sige_two_terwoo (BD Master)
│  ├─ src/Auth/AuthService.php
│  │  ├─ MasterDatabase::findCliente()
│  │  └─ SessionManager::login()
│  │     └─ $_SESSION['cliente_config'] ← AQUÍ SE CARGA
│  └─ src/Config/AppConfig.php
│     └─ $_SESSION['cliente_config'] (read-only)
│
auto-sync.php
├─ bootstrap.php (same as above)
│  └─ Constantes WC_* y DB_* desde $_SESSION['cliente_config']
├─ getSigeConnection()
│  └─ DatabaseService::__construct() (lee $_SESSION['cliente_config'])
└─ wcRequest() (usa WC_BASE_URL, WC_CONSUMER_KEY, WC_CONSUMER_SECRET)

product-publish.php
├─ bootstrap.php (same)
├─ getSigeConnection()
├─ config/mercadolibre.php (si existe)
└─ wcRequest() (usa constantes)
```

---

## 6. MATRIZ DE SEGURIDAD

```
ASPECTO              ACTUAL                           RECOMENDADO
════════════════════════════════════════════════════════════════════════════

Secrets Master BD    Hardcodeados en .php             ✓ .env o env vars

Login Password       Plain text en BD                 ✓ password_hash()

API Key              Generada dinámicamente           ✓ Generar en BD, guardar

SSL/TLS              Deshabilitado                    ✓ CURLOPT_SSL_VERIFYPEER=true

Validación API Key   Comparación simple string        ✓ Hash en DB

Config Fallback      Dos sistemas coexisten           ✓ Eliminar config.php

Constantes Dinámicas define() en runtime              ✓ Class properties

Auditoría            No hay logs                      ✓ Agregar auditoría

Session Timeout      Sin timeout configurado          ✓ Agregar timeout

Rate Limiting        No hay                           ✓ Agregar en login
```
