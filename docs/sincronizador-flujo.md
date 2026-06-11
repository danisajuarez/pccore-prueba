# Flujo del Sincronizador — Pseudocódigo Detallado

## BOTÓN "Sincronizar Ahora" → onclick="runAutoSync()" [index.php:265]

```
función runAutoSync()   [index.php:310]
    si syncRunning = true → salir (ya está corriendo, no hace nada)
    syncRunning = true
    deshabilita el botón (btn.disabled = true)
    resetea contadores: totalSynced=0, totalNotInWoo=0, totalFailed=0
    llama a processBatch(btn, status)
fin runAutoSync


función processBatch(btn, status)   [index.php:327]

    loop (se llama a sí misma mientras remaining > 0)

        llama a auto-sync.php?key=API_KEY   [index.php:331]
        recibe respuesta JSON en data

        si data.success = true

            si data.message existe   (caso "Sin cambios detectados")
                muestra mensaje en log
                llama a finishSync()
                salir del loop
            fin si

            acumula contadores:
                totalSynced    += data.successful
                totalNotInWoo  += data.not_in_woo
                totalFailed    += data.failed
                remaining       = data.remaining

            muestra en log los primeros 5 detalles de data.details   [index.php:351]

            si remaining > 0
                espera 2 segundos
                vuelve a llamar processBatch()
            sino
                llama a finishSync()
            fin si

        sino (data.success = false)
            muestra error en log
            llama a finishSync()
        fin si

    fin loop

fin processBatch


función finishSync(btn, status)   [index.php:383]
    syncRunning = false
    reactiva el botón
    resetea countdown a 600 (próximo auto-sync en 10 min)
    muestra resumen final: "Completado: X sync, Y no en Woo, Z errores"
fin finishSync
```

---

## CADA VEZ QUE SE LLAMA A auto-sync.php [auto-sync.php]

```
auto-sync.php   [auto-sync.php:1]

    AUTENTICACIÓN   [auto-sync.php:94]
        lee key del URL (?key=pccore-sync-2024)
        si hay sesión activa ($SESSION['logged_in'])
            toma $SYNC_CONFIG desde $_SESSION['cliente_config']
            valida que la key del URL = clienteId + "-sync-2024"
        sino (llamada de cron, sin sesión)
            llama a loadClienteConfigFromKey(key)
                extrae clienteId de la key (formato "clienteid-sync-2024")
                busca el cliente en BD Master (tabla sige_two_terwoo)
                devuelve credenciales: db_host, db_user, db_pass, wc_url, wc_key, wc_secret, etc.
            fin loadClienteConfigFromKey
        fin si

    CONEXIÓN A BD SIGE del cliente   [auto-sync.php:196]
        new mysqli(db_host, db_user, db_pass, db_name, db_port)
        detecta charset (latin1 o utf8) para evitar problemas con tildes/ñ


    función BuscarPendientes()   [auto-sync.php:221]
        SELECT COUNT(*) FROM sige_prs_presho
        WHERE pal_precvtaart <> prs_precvtaart    ← precio actual ≠ precio sincronizado
           OR ads_disponible <> prs_disponible    ← stock actual ≠ stock sincronizado
        devuelve totalPendientes

        si totalPendientes = 0
            devuelve JSON: { success:true, message:"Sin cambios detectados.", remaining:0 }
            fin (el loop del frontend para)
        fin si

        SELECT TOP 100 FROM sige_prs_presho   (LIMIT = BATCH_SIZE = 100)   [auto-sync.php:241]
            campos: sku, precio, stock, precio_sin_iva = precio / (1 + IVA%)
        devuelve array $productos (hasta 100 filas)
    fin BuscarPendientes


    si $productos tiene datos

        función BuscarIdEnWoo($productos)   [auto-sync.php:278]
            junta todos los SKUs separados por coma
            llama a wcRequest():
                GET /products?sku=SKU1,SKU2,...,SKU100&per_page=100   [auto-sync.php:279]
            WooCommerce devuelve los productos que existen en la tienda
            construye mapa: $wcIdBySku[ sku ] = woo_id
            devuelve $wcIdBySku
        fin BuscarIdEnWoo

        clasifica cada producto del lote   [auto-sync.php:298]
            para cada $sku en $productos
                si $wcIdBySku tiene ese sku
                    agrega a $batchUpdate: { id:woo_id, regular_price, stock_quantity, stock_status, meta_data[precio_sin_iva] }
                sino
                    agrega a $notInWoo: [ sku ]
                fin si
            fin para

        función GrabarDatosEnWoo($batchUpdate)   [auto-sync.php:340]
            si $batchUpdate no está vacío
                llama a wcRequest():
                    POST /products/batch
                    {
                        "update": [
                            {
                                id,
                                regular_price,
                                sale_price: "",
                                manage_stock: true,
                                stock_quantity,
                                stock_status,
                                meta_data: [ precio_sin_iva ]
                            },
                            ...
                        ]
                    }
                    timeout: 120 segundos

                si batch exitoso
                    para cada producto en $batchUpdate
                        UPDATE sige_prs_presho
                        SET prs_fecultactweb = NOW(),
                            prs_precvtaart   = precio,    ← actualiza snapshot precio
                            prs_disponible   = stock      ← actualiza snapshot stock
                        WHERE art_idarticulo = sku
                        $successful++
                    fin para

                sino batch falló
                    NO toca la BD
                    $failed++ para cada uno
                    quedan pendientes para el próximo lote
                fin si

            fin si

            para cada sku en $notInWoo   [auto-sync.php:374]
                UPDATE sige_prs_presho SET snapshot = valores actuales
                (los marca igual para que no queden en cola para siempre)
            fin para

        fin GrabarDatosEnWoo

    sino $productos vacío
        (no entra acá, ya cortó antes en BuscarPendientes con remaining=0)
    fin si

    devuelve JSON:   [auto-sync.php:394]
    {
        success:    true,
        processed:  cantidad de productos que tomó este lote,
        successful: cuántos se actualizaron OK en Woo,
        not_in_woo: cuántos no existían en la tienda,
        failed:     cuántos fallaron en el batch,
        remaining:  totalPendientes - procesados   ← si > 0, el frontend vuelve a llamar
        details:    [ {sku, status, price, stock} por cada producto ]
    }

fin auto-sync.php
```

---

## Nota sobre el loop

El "for each" está dividido entre dos capas:

- **El loop vive en el frontend** — `processBatch` se llama a sí misma mientras `remaining > 0`
- **Cada iteración procesa un lote de 100 productos** en el backend (`auto-sync.php`)

Cada vuelta del loop = una llamada HTTP completa a `auto-sync.php` que toma 100 artículos, los busca en Woo, los actualiza, y devuelve cuántos quedan.
