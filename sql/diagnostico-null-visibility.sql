-- DIAGNOSTICO: Productos con visibility NULL (el problema real)

-- 1. Contar cuántos productos tienen este problema
SELECT COUNT(*) as productos_sin_visibility
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_visibility'
);

-- 2. Ver cuáles son (primeros 50)
SELECT p.ID, p.post_title, p.post_date
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID AND pm.meta_key = '_visibility'
)
ORDER BY p.post_date
LIMIT 50;
