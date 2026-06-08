-- REPARAR UN SOLO PRODUCTO: ID 9555 (Impresora 3D Creality Ender 3 V2)

-- Insertar _visibility
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES (9555, '_visibility', 'visible');

-- Insertar _catalog_visibility
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES (9555, '_catalog_visibility', 'visible');

-- Verificar que se insertó
SELECT p.ID, p.post_title, vis.meta_value as visibility, cat_vis.meta_value as catalog_visibility
FROM wp_posts p
LEFT JOIN wp_postmeta vis ON p.ID = vis.post_id AND vis.meta_key = '_visibility'
LEFT JOIN wp_postmeta cat_vis ON p.ID = cat_vis.post_id AND cat_vis.meta_key = '_catalog_visibility'
WHERE p.ID = 9555;
