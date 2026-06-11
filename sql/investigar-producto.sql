-- Investigar producto SKU: 6971636408796

SELECT
    p.ID,
    p.post_title,
    p.post_status,
    p.post_date,
    sku.meta_value as sku,
    vis.meta_value as visibility,
    cat_vis.meta_value as catalog_visibility,
    stock.meta_value as stock,
    stock_status.meta_value as stock_status,
    lookup.stock_status as lookup_stock_status,
    lookup.stock_quantity as lookup_stock_qty,
    GROUP_CONCAT(t.name) as categorias
FROM wp_posts p
JOIN wp_postmeta sku ON p.ID = sku.post_id AND sku.meta_key = '_sku'
LEFT JOIN wp_postmeta vis ON p.ID = vis.post_id AND vis.meta_key = '_visibility'
LEFT JOIN wp_postmeta cat_vis ON p.ID = cat_vis.post_id AND cat_vis.meta_key = '_catalog_visibility'
LEFT JOIN wp_postmeta stock ON p.ID = stock.post_id AND stock.meta_key = '_stock'
LEFT JOIN wp_postmeta stock_status ON p.ID = stock_status.post_id AND stock_status.meta_key = '_stock_status'
LEFT JOIN wp_wc_product_meta_lookup lookup ON p.ID = lookup.product_id
LEFT JOIN wp_term_relationships tr ON p.ID = tr.object_id
LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
LEFT JOIN wp_terms t ON tt.term_id = t.term_id
WHERE sku.meta_value = '6971636408796'
GROUP BY p.ID;
