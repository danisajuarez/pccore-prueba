SELECT COUNT(*) as total_productos_problematicos
FROM wp_posts p
JOIN wp_wc_product_meta_lookup lookup ON p.ID = lookup.product_id
WHERE p.post_type = 'product'
AND p.post_status = 'publish'
AND lookup.stock_status = 'instock'
AND p.post_date < '2024-01-01';
