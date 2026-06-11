-- Contar productos en categoría "Auriculares" según BD
SELECT COUNT(*) as en_base_datos
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
JOIN wp_wc_product_meta_lookup lookup ON p.ID = lookup.product_id
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND tt.taxonomy = 'product_cat'
AND t.name = 'Auriculares'
AND lookup.stock_status = 'instock';
