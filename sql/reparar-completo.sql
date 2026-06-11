-- ============================================
-- REPARACION COMPLETA DE PRODUCTOS INVISIBLES
-- HACER BACKUP ANTES DE EJECUTAR
-- ============================================

-- =====================
-- PARTE A: Diagnóstico previo
-- =====================

-- A1. Ver cuántos productos tienen problema de visibility
SELECT
    COALESCE(pm.meta_value, 'NULL/SIN_DEFINIR') as visibility,
    COUNT(*) as cantidad
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product' AND p.post_status = 'publish'
GROUP BY pm.meta_value;

-- =====================
-- PARTE B: Reparar _visibility
-- =====================

-- B1. Actualizar productos con visibility = 'search' o 'hidden' a 'visible'
UPDATE wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
SET pm.meta_value = 'visible'
WHERE pm.meta_key = '_visibility'
AND p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden');

-- B2. Insertar _visibility='visible' para productos que no lo tienen
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT p.ID, '_visibility', 'visible'
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_visibility'
);

-- =====================
-- PARTE C: Reparar _catalog_visibility (WooCommerce 3.0+)
-- =====================

-- C1. Actualizar _catalog_visibility si existe
UPDATE wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
SET pm.meta_value = 'visible'
WHERE pm.meta_key = '_catalog_visibility'
AND p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden');

-- =====================
-- PARTE D: Reparar wp_wc_product_meta_lookup
-- =====================

-- D1. Actualizar stock_status en lookup para productos con stock > 0
UPDATE wp_wc_product_meta_lookup lookup
JOIN wp_postmeta stock ON lookup.product_id = stock.post_id AND stock.meta_key = '_stock'
SET lookup.stock_status = 'instock'
WHERE CAST(stock.meta_value AS SIGNED) > 0
AND lookup.stock_status = 'outofstock';

-- D2. Sincronizar stock_quantity en lookup desde postmeta
UPDATE wp_wc_product_meta_lookup lookup
JOIN wp_postmeta stock ON lookup.product_id = stock.post_id AND stock.meta_key = '_stock'
SET lookup.stock_quantity = CAST(stock.meta_value AS SIGNED)
WHERE lookup.stock_quantity != CAST(stock.meta_value AS SIGNED);

-- =====================
-- PARTE E: Limpiar cache
-- =====================

DELETE FROM wp_options WHERE option_name LIKE '_transient_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_site_transient_wc_%';

-- =====================
-- PARTE F: Verificación final
-- =====================

SELECT
    COALESCE(pm.meta_value, 'NULL') as visibility,
    COUNT(*) as cantidad
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product' AND p.post_status = 'publish'
GROUP BY pm.meta_value;
