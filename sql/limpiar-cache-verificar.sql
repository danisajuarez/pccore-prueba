-- LIMPIAR CACHE Y VERIFICAR

-- 1. Limpiar transients de WooCommerce
DELETE FROM wp_options WHERE option_name LIKE '_transient_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_wc_%';
DELETE FROM wp_options WHERE option_name LIKE '_site_transient_wc_%';

-- 2. Actualizar el conteo de la categoría "Impresoras 3D" (term_taxonomy_id = 824)
UPDATE wp_term_taxonomy
SET count = (
    SELECT COUNT(*)
    FROM wp_term_relationships tr
    JOIN wp_posts p ON tr.object_id = p.ID
    WHERE tr.term_taxonomy_id = 824
    AND p.post_status = 'publish'
    AND p.post_type = 'product'
)
WHERE term_taxonomy_id = 824;

-- 3. Ver el conteo actualizado
SELECT t.name, tt.count
FROM wp_term_taxonomy tt
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE tt.term_taxonomy_id = 824;

-- 4. Ver todos los productos en esa categoría
SELECT p.ID, p.post_title, p.post_status
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
WHERE tr.term_taxonomy_id = 824
AND p.post_type = 'product'
AND p.post_status = 'publish';

-- 5. Verificar si el 9555 está en la lista
SELECT 'PRODUCTO 9555 EN CATEGORIA:' as check_result, COUNT(*) as encontrado
FROM wp_term_relationships
WHERE object_id = 9555 AND term_taxonomy_id = 824;
