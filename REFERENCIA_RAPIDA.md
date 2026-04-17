# Referencia Rápida - Arquitectura Multi-tenant

**Última actualización**: 16 de abril de 2026

---

## TABLA DE UBICACIÓN DE COMPONENTES

| Componente             | Ubicación                              | Responsabilidad                      | Crítico |
| ---------------------- | -------------------------------------- | ------------------------------------ | ------- |
| **SessionManager**     | `php/src/Auth/SessionManager.php`      | Maneja `$_SESSION['cliente_config']` | ✓       |
| **AuthService**        | `php/src/Auth/AuthService.php`         | Login/logout contra BD Master        | ✓       |
| **MasterDatabase**     | `php/src/Database/MasterDatabase.php`  | Consulta sige_two_terwoo             | ✓       |
| **AppConfig**          | `php/src/Config/AppConfig.php`         | Lee config de sesión                 | ✓       |
| **DatabaseService**    | `php/src/Database/DatabaseService.php` | Conexión dinámica a BD SIGE          | ✓       |
| **Login Endpoint**     | `php/api/login.php`                    | Formulario + procesamiento login     | ✓       |
| **Auto-Sync Endpoint** | `php/api/auto-sync.php`                | Sincronización híbrida WC            | ✓       |
| **Product-Publish**    | `php/api/product-publish.php`          | Publicar producto en WC              | ✓       |
| **BD Master Config**   | `php/config/master.php`                | Credenciales hardcodeadas            | ⚠️      |

---

## TABLA DE CREDENCIALES Y SU ORIGEN

| Credencial          | Origen                             | Dónde Se Usa            | Tipo de Seguridad |
| ------------------- | ---------------------------------- | ----------------------- | ----------------- |
| `TER_IdTercero`     | sige_two_terwoo (BD Master)        | Login form + sesión     | Público (ID)      |
| `TWO_Pass`          | sige_two_terwoo (BD Master)        | Autenticación login     | Plain text ⚠️     |
| `TWO_ServidorDBAnt` | sige_two_terwoo                    | Conexión a BD SIGE      | Host externo      |
| `TWO_UserDBAnt`     | sige_two_terwoo                    | Conexión a BD SIGE      | Plain text ⚠️     |
| `TWO_PassDBAnt`     | sige_two_terwoo                    | Conexión a BD SIGE      | Plain text ⚠️     |
| `TWO_WooUrl`        | sige_two_terwoo                    | Llamadas API WC         | URL base          |
| `TWO_WooKey`        | sige_two_terwoo                    | Autenticación WC API    | Plain text ⚠️     |
| `TWO_WooSecret`     | sige_two_terwoo                    | Autenticación WC API    | Plain text ⚠️     |
| `MASTER_DB_*`       | php/config/master.php              | BD Master               | Hardcodeado ⚠️⚠️  |
| API Key             | Generada: `{cliente_id}-sync-2024` | Validación de endpoints | Adivinable ⚠️     |

---

## FLUJO DE DATOS - LOGIN A SYNC

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USUARIO INGRESA CREDENCIALES                             │
│    GET /api/login.php                                        │
│    POST con: cliente_id=2, password=portalgcom2024          │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. AUTHSERVICE VALIDA CONTRA BD MASTER                      │
│    MasterDatabase::findCliente(2, 'portalgcom2024')         │
│    SELECT * FROM sige_two_terwoo                            │
│    WHERE TER_IdTercero=2 AND TWO_Pass=? AND TWO_Activo='S'  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. SESSIONMANAGER CARGA CONFIG EN SESIÓN                    │
│    $_SESSION['cliente_config'] = [                          │
│        'db_host' => 'giuggia.dyndns-home.com',              │
│        'db_user' => 'root',                                 │
│        'db_pass' => 'giuggia',                              │
│        'db_name' => 'giuggia',                              │
│        'wc_url' => 'https://domain.com/wp-json/wc/v3',     │
│        'wc_key' => 'ck_xxxxx',                              │
│        'wc_secret' => 'cs_xxxxx'                            │
│    ]                                                        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. BOOTSTRAP DEFINE CONSTANTES                              │
│    define('WC_BASE_URL', $_SESSION['cliente_config']['wc_url'])
│    define('WC_CONSUMER_KEY', $_SESSION['cliente_config']['wc_key'])
│    define('WC_CONSUMER_SECRET', $_SESSION['cliente_config']['wc_secret'])
│    define('DB_HOST', $_SESSION['cliente_config']['db_host'])
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. POST /api/auto-sync.php?key=2-sync-2024                 │
│    - Valida sesión: isset($_SESSION['cliente_config'])      │
│    - Valida API Key: 2-sync-2024 ✓                         │
│    - Trae SKUs con cambios de BD SIGE                       │
│    - Busca en WooCommerce                                   │
│    - Actualiza en batch                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## CONSULTAS SQL CRÍTICAS

### Query de Validación Login

```sql
SELECT *
FROM sige_two_terwoo
WHERE TER_IdTercero = 2
  AND TWO_Pass = 'portalgcom2024'
  AND TWO_Activo = 'S';
```

**Resultado**: Una fila con todos los campos que van a `$_SESSION['cliente_config']`

### Query de Productos para Sync

```sql
SELECT s.art_idarticulo as sku,
       s.pal_precvtaart as precio,
       s.ads_disponible as stock,
       (s.pal_precvtaart / (1 + (a.ART_PorcIVARI / 100))) AS precio_sin_iva
FROM sige_prs_presho s
INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
WHERE (s.pal_precvtaart <> s.prs_precvtaart
   OR s.prs_disponible <> s.ads_disponible)
LIMIT 50;
```

**Lógica**: Busca productos donde el precio o stock cambió (pal = anterior, prs = posterior, ads = disponible)

### Tabla de Relaciones

```
sige_two_terwoo (Master) 1──────→ N sige_art_articulo (SIGE)
                                     │
                                     └──→ 1 sige_prs_presho
                                         (precio/stock por punto de venta)
```

---

## ENDPOINTS DE SINCRONIZACIÓN

### 1. AUTO-SYNC (Recomendado)

```
POST /api/auto-sync.php?key=2-sync-2024

Requerimientos:
  ✓ Sesión activa (post-login)
  ✓ API Key válida
  ✓ $_SESSION['cliente_config'] con credenciales WC

Flujo:
  1. Busca SKUs con cambios (50 a la vez)
  2. Para cada SKU: GET /products?sku=XXXX en WC
  3. Prepara batch con los encontrados
  4. PUT batch actualización
  5. Retorna { success, updated, skipped, errors }

Credenciales usadas:
  - WC_BASE_URL (de sesión)
  - WC_CONSUMER_KEY (de sesión)
  - WC_CONSUMER_SECRET (de sesión)
  - BD SIGE via DatabaseService
```

### 2. SYNC (Individual)

```
PUT /api/sync.php

Body JSON:
{
  "sku": "PROD001",
  "regular_price": "99.99",
  "stock_quantity": 50
}

Headers:
  X-Api-Key: 2-sync-2024

Flujo:
  1. Valida API Key
  2. Busca IVA del producto
  3. Calcula precio sin IVA
  4. Actualiza en WooCommerce
  5. Retorna { success, message, product_id }
```

### 3. PRODUCT-PUBLISH

```
POST /api/product-publish.php

Requerimientos:
  ✓ Sesión activa
  ✓ API Key en header X-Api-Key
  ✓ Datos del producto en JSON

Flujo:
  1. Busca categoría en WC, crea si no existe
  2. Calcula precio CON IVA
  3. Busca imágenes en Mercado Libre (opcional)
  4. POST /products en WC
  5. Actualiza flag en BD SIGE
```

---

## ANTI-PATRONES MAPA

```
NIVEL      ANTI-PATRÓN                        UBICACIÓN              SEVERIDAD
═══════════════════════════════════════════════════════════════════════════════════

SECRETS    Hardcodeado en archivo              php/config/master.php  🔴 CRÍTICO
           MASTER_DB_USER, MASTER_DB_PASS

SECRETS    Passwords plain text en BD          sige_two_terwoo        🔴 CRÍTICO
           TWO_Pass, TWO_PassDBAnt, etc

SECRETS    API Key adivinable                  bootstrap.php          🟡 MEDIO
           cliente_id + '-sync-2024'

NETWORK    SSL deshabilitado                   auto-sync.php          🔴 CRÍTICO
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)

ARCH       Dos sistemas config coexisten       config.php vs          🟡 MEDIO
           config.php (legacy) + bootstrap.php bootstrap.php

CODE       Constantes dinámicas con define()   bootstrap.php          🟡 MEDIO
           WC_BASE_URL varía por cliente

DB         Consultas sin encriptación end-to-end                      🟡 MEDIO
           Passwords en plain en tránsito entre servicios
```

---

## CHECKLIST DE SEGURIDAD

- [ ] ¿Las credenciales master están en env vars, no en config.php?
- [ ] ¿Se usa password_hash() para TWO_Pass?
- [ ] ¿SSL está habilitado (CURLOPT_SSL_VERIFYPEER = true)?
- [ ] ¿Las API Keys se guardan en BD, no se generan dinámicamente?
- [ ] ¿Se elimina el código legacy de config.php?
- [ ] ¿Se valida API Key contra DB en lugar de comparación simple?
- [ ] ¿Se usa prepared statements en todas las queries?
- [ ] ¿Hay rate limiting en /api/login.php?
- [ ] ¿Las credenciales se cierran/limpian en logout?
- [ ] ¿Hay auditoría de qué sincronizaciones ocurrieron?

---

## ARCHIVOS CLAVE PARA MODIFICACIÓN

Si necesitas hacer cambios, estos son los archivos principales:

```
PRIORIDAD  ARCHIVO                          CAMBIO SUGERIDO
══════════════════════════════════════════════════════════════════════════════

P0         php/config/master.php            → Mover a env vars (.env)

P0         php/src/Auth/SessionManager.php  → Mantener pero revisar carga

P1         php/bootstrap.php                → Eliminar define() dinámicos

P1         php/config.php                   → Deprecar completamente

P2         php/api/auto-sync.php            → Habilitar SSL

P2         php/api/product-publish.php      → Habilitar SSL

P2         php/api/sync.php                 → Habilitar SSL

P3         php/sql/insert_portalgcom.sql    → Agregar más clientes (test)

P3         tests/                           → Agregar tests de seguridad
```

---

## VARIABLES DE SESIÓN POST-LOGIN

Después de hacer login exitoso, la sesión contiene:

```php
$_SESSION = [
    'logged_in' => true,
    'cliente_id' => 2,                          // TER_IdTercero
    'cliente_nombre' => 'Portalgcom',           // TER_RazonSocialTer
    'user' => 2,                                // Alias de cliente_id
    'user_id' => 2,                             // Alias de cliente_id
    'user_nombre' => 'Portalgcom',              // Alias de cliente_nombre

    'cliente_config' => [
        'id' => 2,
        'nombre' => 'Portalgcom',

        // Credenciales BD SIGE
        'db_host' => 'giuggia.dyndns-home.com',
        'db_user' => 'root',
        'db_pass' => 'giuggia',
        'db_port' => 3307,
        'db_name' => 'giuggia',

        // Credenciales BD WooCommerce (opcionales)
        'woo_db_host' => null,
        'woo_db_user' => null,
        'woo_db_pass' => null,
        'woo_db_port' => 3306,
        'woo_db_name' => null,

        // Credenciales API WooCommerce
        'wc_url' => 'https://domain.com/wp-json/wc/v3',
        'wc_key' => 'ck_xxxxxxxxxxxxx',
        'wc_secret' => 'cs_xxxxxxxxxxxxx',

        // Configuración SIGE
        'lista_precio' => 2,
        'deposito' => 1,

        // Flags
        'sincronizar_auto' => false,  // TWO_SincronizarAut = 'N'
    ]
];
```

---

## FUNCIONES HELPER GLOBALES

Disponibles en [php/bootstrap.php](php/bootstrap.php#L188-L230):

```php
isAuthenticated()           // ¿Está logueado?
getClienteConfig()          // $_SESSION['cliente_config']
getConfig($key, $default)   // $_SESSION['cliente_config'][$key]
getClienteId()              // $_SESSION['cliente_id']
requireAuth($loginUrl)      // Redirige si no está logueado
getSigeConnection()         // DatabaseService (conexión a BD SIGE)
getDbConnection()           // Alias legacy
```

Ejemplo:

```php
// En cualquier API protegida
requireAuth();  // Valida que existe sesión con cliente_config

$config = getClienteConfig();
$wc_url = $config['wc_url'];    // Credenciales del cliente
$db = getSigeConnection()->getConnection();  // BD SIGE
```
