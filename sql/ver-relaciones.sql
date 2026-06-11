SELECT tr.object_id as product_id, tr.term_taxonomy_id, tt.term_id, tt.taxonomy, t.name
FROM wp_term_relationships tr
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE tr.object_id IN (9559, 14284, 9547, 9551, 9555)
AND tt.taxonomy = 'product_cat'
ORDER BY tr.object_id;
