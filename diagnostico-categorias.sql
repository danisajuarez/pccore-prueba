-- Ver todos los productos publicados con stock que deberían aparecer en categorías
-- pero pueden tener problemas en las tablas de índice

SELECT
    p.ID,
    p.post_title,
    p.post_date,
    p.post_status,
    t.name as categoria,
    lookup.stock_status,
    lookup.stock_quantity,
    CASE
        WHEN p.post_date < '2024-01-01' THEN 'VIEJO (pre-2024) - posible problema'
        ELSE 'RECIENTE - probablemente OK'
    END as diagnostico
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
LEFT JOIN wp_wc_product_meta_lookup lookup ON p.ID = lookup.product_id
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND tt.taxonomy = 'product_cat'
AND lookup.stock_status = 'instock'
AND p.post_date < '2024-01-01'
ORDER BY t.name, p.post_date;
