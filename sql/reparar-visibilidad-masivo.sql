-- ============================================
-- REPARAR PRODUCTOS INVISIBLES EN CATEGORIAS
-- Ejecutar con cuidado - HACER BACKUP PRIMERO
-- ============================================

-- PASO 1: Contar cuántos hay que reparar (ejecutar primero para ver)
SELECT COUNT(*) as productos_a_reparar
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden');

-- PASO 2: Ver cuáles son (antes de reparar)
SELECT p.ID, p.post_title, pm.meta_value as visibility_actual
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden')
LIMIT 50;

-- PASO 3: REPARAR - Cambiar visibility de 'search' o 'hidden' a 'visible'
UPDATE wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
SET pm.meta_value = 'visible'
WHERE pm.meta_key = '_visibility'
AND p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden');

-- PASO 4: También actualizar catalog_visibility si existe como meta separado
-- (WooCommerce moderno usa esto)
UPDATE wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
SET pm.meta_value = 'visible'
WHERE pm.meta_key = '_catalog_visibility'
AND p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden');

-- PASO 5: Verificar reparación
SELECT COUNT(*) as productos_reparados
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value = 'visible';

-- PASO 6: Limpiar cache de WooCommerce (opcional pero recomendado)
DELETE FROM wp_options WHERE option_name LIKE '_transient_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_wc_%';
