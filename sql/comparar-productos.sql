SELECT p.ID, p.post_title, p.post_status, p.post_date,
       MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
       MAX(CASE WHEN pm.meta_key = '_visibility' THEN pm.meta_value END) as visibility,
       MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'product'
AND p.ID IN (
    SELECT tr.object_id FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
    WHERE tt.term_id = 824
)
GROUP BY p.ID;
