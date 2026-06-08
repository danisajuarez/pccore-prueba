SELECT tr.object_id, t.name, t.slug
FROM wp_term_relationships tr
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE tr.object_id = 15818
AND tt.taxonomy = 'product_visibility';