-- Primero eliminar la relación
DELETE FROM wp_term_relationships WHERE object_id = 9559 AND term_taxonomy_id = 824;

-- Volver a crear la relación
INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES (9559, 824, 0);

-- Actualizar el conteo de la categoría
UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = 824) WHERE term_taxonomy_id = 824;
