-- ============================================
-- DIAGNOSTICO DE PRODUCTOS INVISIBLES EN CATEGORIAS
-- Productos que solo aparecen en búsqueda
-- ============================================

-- 1. Contar productos con catalog_visibility = 'search' (solo aparecen buscando)
SELECT
    pm.meta_value as visibility,
    COUNT(*) as cantidad
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
GROUP BY pm.meta_value;

-- 2. Productos publicados que tienen visibility='search' o 'hidden' (PROBLEMATICOS)
SELECT
    p.ID,
    p.post_title,
    p.post_date,
    pm.meta_value as visibility,
    stock.meta_value as stock,
    CASE
        WHEN p.post_date < '2024-01-01' THEN 'ANTIGUO'
        ELSE 'RECIENTE'
    END as era
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
LEFT JOIN wp_postmeta stock ON p.ID = stock.post_id AND stock.meta_key = '_stock'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND pm.meta_value IN ('search', 'hidden')
ORDER BY p.post_date;

-- 3. Productos SIN relacion de categoria (huérfanos)
SELECT
    p.ID,
    p.post_title,
    p.post_date
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1
    FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
    WHERE tr.object_id = p.ID
    AND tt.taxonomy = 'product_cat'
);

-- 4. Ver todos los valores de _visibility en uso
SELECT DISTINCT pm.meta_value as visibility_values
FROM wp_postmeta pm
WHERE pm.meta_key = '_visibility';
