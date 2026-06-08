-- INVESTIGAR PROFUNDO: Producto 9555

-- 1. Ver la relación con categorías
SELECT
    tr.object_id as product_id,
    tr.term_taxonomy_id,
    tt.term_id,
    tt.taxonomy,
    tt.count as conteo_categoria,
    t.name as categoria
FROM wp_term_relationships tr
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE tr.object_id = 9555;

-- 2. Ver entrada en wp_wc_product_meta_lookup
SELECT * FROM wp_wc_product_meta_lookup WHERE product_id = 9555;

-- 3. Ver TODOS los postmeta del producto
SELECT meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 9555
ORDER BY meta_key;

-- 4. Comparar con un producto que SI funciona (dame un ID de uno que funcione)
-- Por ahora vamos a ver el term_taxonomy_id de "Impresoras 3D"
SELECT t.term_id, t.name, tt.term_taxonomy_id, tt.count
FROM wp_terms t
JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
WHERE t.name LIKE '%Impresoras 3D%';
