# Sistema de Sincronización SIGE ↔ WooCommerce

Panel web multi-tenant que sincroniza precios y stock desde el ERP **Antártida SIGE** hacia tiendas **WooCommerce**. Cada cliente (empresa) tiene su propia base de datos SIGE y su propia tienda WooCommerce; las credenciales se almacenan centralizadas en una BD Master.

---

## Tabla de contenidos

1. [Requisitos](#requisitos)
2. [Estructura del proyecto](#estructura-del-proyecto)
3. [Cómo funciona (resumen rápido)](#cómo-funciona-resumen-rápido)
4. [Instalación y configuración](#instalación-y-configuración)
5. [Agregar un nuevo cliente](#agregar-un-nuevo-cliente)
6. [Endpoints principales](#endpoints-principales)
7. [Documentación interna](#documentación-interna)
8. [Deuda técnica conocida](#deuda-técnica-conocida)

---

## Requisitos

- PHP 7.4+ con extensiones: `mysqli`, `curl`, `json`
- Servidor web (Apache/Nginx) con `mod_rewrite` activo
- Acceso a la BD Master MySQL (Hostinger, ver `php/config/master.php`)
- Cada cliente necesita: BD SIGE accesible desde el servidor + credenciales API WooCommerce

---

## Estructura del proyecto

```
php/
├── index.php                  # Panel de sincronización (página principal post-login)
├── bootstrap.php              # Inicialización: carga clases, sesión, constantes
├── config.php                 # Config legacy (se mantiene por compatibilidad)
├── config/
│   ├── master.php             # ⚠️ Credenciales BD Master HARDCODEADAS (ver deuda técnica)
│   └── mercadolibre.php       # Credenciales API Mercado Libre
├── src/
│   ├── Auth/
│   │   ├── AuthService.php    # Login/logout — llama a MasterDatabase
│   │   ├── SessionManager.php # Carga $_SESSION['cliente_config'] post-login
│   │   └── ApiKeyValidator.php
│   ├── Config/
│   │   └── AppConfig.php      # Lee config del cliente desde la sesión
│   ├── Database/
│   │   ├── MasterDatabase.php # Consulta sige_two_terwoo (tabla de clientes)
│   │   └── DatabaseService.php# Conexión dinámica a BD SIGE del cliente
│   ├── Sige/
│   │   ├── ProductRepository.php
│   │   └── SyncService.php
│   ├── WooCommerce/
│   │   ├── WooCommerceClient.php
│   │   └── ProductMapper.php
│   └── MercadoLibre/
│       ├── MercadoLibreClient.php
│       ├── ImageSearchService.php
│       └── TokenManager.php
├── api/
│   ├── login.php              # Formulario + procesamiento de login
│   ├── logout.php             # Cierra sesión y redirige
│   ├── auto-sync.php          # ★ Sincronización masiva (sesión o cron)
│   ├── sync.php               # Sincronización individual por SKU
│   ├── product-publish.php    # Publica producto nuevo en WooCommerce
│   ├── product-search.php     # Busca producto en SIGE + WooCommerce
│   ├── product-create.php     # Crea producto completo
│   ├── product-update.php     # Actualiza producto existente
│   ├── admin-productos.php    # Panel de administración de productos
│   ├── image-search.php       # Busca imágenes via Mercado Libre
│   ├── image-upload.php       # Sube imagen a WooCommerce
│   └── health.php             # Healthcheck del servidor
└── templates/
    ├── login.php
    ├── dashboard.php
    └── admin/
        ├── productos.php
        └── nuevo-producto.php

sql/                           # Scripts SQL de diagnóstico y mantenimiento
docs/
    └── sincronizador-flujo.md # Pseudocódigo detallado del sincronizador
tests/                         # Tests E2E con Playwright
```

---

## Cómo funciona (resumen rápido)

```
1. El usuario abre https://{cliente}.antartidasige.com/api/login.php
2. Ingresa su ID de cliente (número) + contraseña
3. El sistema consulta la tabla sige_two_terwoo en la BD Master
4. Si es válido, guarda TODAS las credenciales en $_SESSION['cliente_config']
5. El usuario ve el panel de sincronización (index.php)
6. Al presionar "Sincronizar Ahora":
   - Llama a auto-sync.php en lotes de 100 productos
   - Detecta diferencias precio/stock entre SIGE y lo último sincronizado
   - Busca cada SKU en WooCommerce
   - Actualiza todos en un batch
   - Repite hasta que remaining = 0
```

Ver pseudocódigo detallado en [docs/sincronizador-flujo.md](docs/sincronizador-flujo.md).

---

## Instalación y configuración

### 1. Subir al servidor

El proyecto se despliega en la raíz de cada subdominio:
- `pccore.antartidasige.com` → cliente "pccore"
- `portalgcom.antartidasige.com` → cliente "portalgcom"

### 2. Configurar BD Master

Editar `php/config/master.php` con las credenciales de la BD Master de Hostinger:

```php
define('MASTER_DB_HOST', 'localhost');
define('MASTER_DB_PORT', 3306);
define('MASTER_DB_USER', 'usuario_master');
define('MASTER_DB_PASS', 'password_master');
define('MASTER_DB_NAME', 'nombre_bd_master');
```

> ⚠️ Estas credenciales están en texto plano en el archivo. Ver [deuda técnica](#deuda-técnica-conocida).

### 3. Sin Composer

El proyecto **no usa Composer**. Las clases se cargan manualmente en `bootstrap.php`. No hay que correr `composer install`.

### 4. Verificar instalación

Abrir `https://{dominio}/api/health.php` — debe responder `{"status":"ok"}`.

---

## Agregar un nuevo cliente

Insertar una fila en la tabla `sige_two_terwoo` de la BD Master:

```sql
INSERT INTO sige_two_terwoo (
    TER_IdTercero,       -- ID numérico del cliente (ej: 3)
    TER_RazonSocialTer,  -- Nombre visible (ej: 'Mi Empresa')
    TWO_Pass,            -- Password plain text para login
    TWO_Activo,          -- 'S' para activo, 'N' para inactivo
    TWO_ServidorDBAnt,   -- Host de la BD SIGE del cliente
    TWO_PuertoDBAnt,     -- Puerto (default 3306)
    TWO_UserDBAnt,       -- Usuario BD SIGE
    TWO_PassDBAnt,       -- Password BD SIGE
    TWO_NombreDBAnt,     -- Nombre de la BD SIGE
    TWO_WooUrl,          -- URL API WooCommerce (ej: https://tienda.com/wp-json/wc/v3)
    TWO_WooKey,          -- Consumer Key de WooCommerce
    TWO_WooSecret,       -- Consumer Secret de WooCommerce
    TWO_ListaPrecio,     -- ID de lista de precios en SIGE (generalmente 1 o 2)
    TWO_Deposito         -- ID de depósito en SIGE (generalmente 1)
) VALUES (
    3, 'Mi Empresa', 'password123', 'S',
    'host-sige.com', 3306, 'usuario', 'pass', 'nombre_bd',
    'https://mitienda.com/wp-json/wc/v3', 'ck_xxx', 'cs_xxx',
    1, '1'
);
```

Ver ejemplo completo en `php/sql/insert_portalgcom.sql`.

---

## Endpoints principales

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| GET/POST | `/api/login.php` | Formulario de login | — |
| GET | `/api/logout.php` | Cierra sesión | Sesión |
| POST | `/api/auto-sync.php?key={id}-sync-2024` | Sync masivo (lote de 100) | Sesión o API Key |
| PUT | `/api/sync.php` | Sync individual por SKU | API Key header |
| GET | `/api/product-search.php?q={sku}` | Busca en SIGE + WooCommerce | Sesión |
| POST | `/api/product-publish.php` | Publica producto en WooCommerce | Sesión + API Key |
| GET | `/api/health.php` | Healthcheck | — |

### API Key

La API Key de cada cliente es: `{TER_IdTercero}-sync-2024`

Ejemplo para cliente ID 2: `2-sync-2024`

Se pasa como query param (`?key=2-sync-2024`) o header (`X-Api-Key: 2-sync-2024`).

---

## Documentación interna

| Archivo | Contenido |
|---------|-----------|
| [ANALISIS_ARQUITECTURA.md](ANALISIS_ARQUITECTURA.md) | Análisis exhaustivo de todos los componentes |
| [REFERENCIA_RAPIDA.md](REFERENCIA_RAPIDA.md) | Tablas de referencia: credenciales, flujos, anti-patrones |
| [INDICE_ARCHIVOS.md](INDICE_ARCHIVOS.md) | Índice de búsqueda: "¿dónde está X?" |
| [DIAGRAMAS_ARQUITECTURA.md](DIAGRAMAS_ARQUITECTURA.md) | Diagramas ASCII del flujo completo |
| [INTEGRACION.md](INTEGRACION.md) | Guía de integración y ejemplos de código |
| [docs/sincronizador-flujo.md](docs/sincronizador-flujo.md) | Pseudocódigo del sincronizador auto-sync |

---

## Deuda técnica conocida

Estos problemas estaban documentados al momento del traspaso. No se resolvieron por falta de tiempo pero son importantes:

### 🔴 Crítico

1. **Credenciales master hardcodeadas** — `php/config/master.php` tiene usuario y contraseña de la BD en texto plano en el repositorio. Migrar a variables de entorno (`.env`).

2. **Passwords en plain text en BD** — La tabla `sige_two_terwoo` guarda `TWO_Pass` y `TWO_PassDBAnt` sin hashear. Si la BD se compromete, todos los clientes quedan expuestos.

3. **SSL deshabilitado** — Todas las llamadas a WooCommerce tienen `CURLOPT_SSL_VERIFYPEER = false`. Vulnerable a MITM. Habilitar SSL o agregar el certificado correcto.

### 🟡 Medio

4. **API Key predecible** — Se genera como `{cliente_id}-sync-2024`, cualquiera que conozca el ID puede generarla. Reemplazar por tokens random guardados en BD.

5. **Dos sistemas de config coexisten** — `config.php` (legacy) y `bootstrap.php` (nuevo). El legacy tiene un fallback que puede confundir. Eliminar `config.php` cuando se confirme que nada lo usa.

6. **Constantes dinámicas con `define()`** — `WC_BASE_URL`, `DB_HOST`, etc. se definen después del login desde la sesión. Si PHP procesa dos requests en el mismo proceso (raro pero posible), puede haber colisión.

7. **Muchos archivos de diagnóstico en `php/api/`** — Hay ~40 archivos `debug-*.php`, `test-*.php`, `check-*.php`, `fix-*.php` que se usaron durante el desarrollo. No deberían estar en producción.
