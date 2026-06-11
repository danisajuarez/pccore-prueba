-- REPARAR: Agregar _visibility = 'visible' a los 499 productos que no lo tienen

-- PASO 1: Insertar _visibility
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT p.ID, '_visibility', 'visible'
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_visibility'
);

-- PASO 2: Insertar _catalog_visibility también
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT p.ID, '_catalog_visibility', 'visible'
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_catalog_visibility'
);

-- PASO 3: Limpiar cache de WooCommerce
DELETE FROM wp_options WHERE option_name LIKE '_transient_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_wc_%';

-- PASO 4: Verificar que se repararon
SELECT COUNT(*) as productos_sin_visibility
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_visibility'
);
