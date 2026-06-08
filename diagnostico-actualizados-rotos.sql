
-- ============================================
-- DIAGNOSTICO: Productos antiguos actualizados recientemente que pueden tener problemas
-- ============================================

-- 1. Productos creados antes de 2024 pero modificados recientemente
-- que podrían haber sido "rotos" por la actualización
SELECT
    p.ID,
    p.post_title,
    p.post_date as fecha_creacion,
    p.post_modified as fecha_modificacion,
    vis.meta_value as visibility,
    stock.meta_value as stock,
    stock_status.meta_value as stock_status
FROM wp_posts p
LEFT JOIN wp_postmeta vis ON p.ID = vis.post_id AND vis.meta_key = '_visibility'
LEFT JOIN wp_postmeta stock ON p.ID = stock.post_id AND stock.meta_key = '_stock'
LEFT JOIN wp_postmeta stock_status ON p.ID = stock_status.post_id AND stock_status.meta_key = '_stock_status'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND p.post_date < '2024-01-01'
AND p.post_modified > '2024-06-01'
AND (vis.meta_value IN ('search', 'hidden') OR vis.meta_value IS NULL)
ORDER BY p.post_modified DESC
LIMIT 100;

-- 2. Productos publicados SIN visibility definida (puede causar problemas)
SELECT
    p.ID,
    p.post_title,
    p.post_date
FROM wp_posts p
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND NOT EXISTS (
    SELECT 1 FROM wp_postmeta pm
    WHERE pm.post_id = p.ID
    AND pm.meta_key = '_visibility'
)
LIMIT 50;

-- 3. Productos que tienen stock pero aparecen como "outofstock" en lookup
SELECT
    p.ID,
    p.post_title,
    stock.meta_value as stock_meta,
    lookup.stock_quantity as stock_lookup,
    lookup.stock_status as status_lookup
FROM wp_posts p
JOIN wp_postmeta stock ON p.ID = stock.post_id AND stock.meta_key = '_stock'
JOIN wp_wc_product_meta_lookup lookup ON p.ID = lookup.product_id
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND CAST(stock.meta_value AS UNSIGNED) > 0
AND lookup.stock_status = 'outofstock'
LIMIT 50;

-- 4. Contar productos en cada estado de visibility
SELECT
    COALESCE(pm.meta_value, 'SIN DEFINIR') as visibility,
    COUNT(*) as cantidad
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_visibility'
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
GROUP BY pm.meta_value
ORDER BY cantidad DESC;
