SELECT
    pm1.meta_key,
    MAX(CASE WHEN pm1.post_id = 14284 THEN pm1.meta_value END) as producto_que_SI_aparece,
    MAX(CASE WHEN pm1.post_id = 9559 THEN pm1.meta_value END) as producto_que_NO_aparece
FROM wp_postmeta pm1
WHERE pm1.post_id IN (14284, 9559)
GROUP BY pm1.meta_key
ORDER BY pm1.meta_key;
