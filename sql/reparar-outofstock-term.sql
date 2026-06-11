-- REPARAR: El producto 9555 tiene el término "outofstock" en product_visibility
-- Esto hace que WooCommerce lo oculte aunque tenga stock

-- 1. Ver el term_taxonomy_id de "instock"
SELECT t.term_id, t.name, tt.term_taxonomy_id, tt.taxonomy
FROM wp_terms t
JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
WHERE tt.taxonomy = 'product_visibility';

-- 2. Eliminar la relación con "outofstock" (term_taxonomy_id = 9)
DELETE FROM wp_term_relationships
WHERE object_id = 9555
AND term_taxonomy_id = 9;

-- 3. Verificar que se eliminó

Expandir/ColapsarEstructurawp_elfsight_whatsapp_chat_widgets
Expandir/ColapsarEstructurawp_e_events
Expandir/ColapsarEstructurawp_e_submissions
Expandir/ColapsarEstructurawp_e_submissions_actions_log
Expandir/ColapsarEstructurawp_e_submissions_values
Expandir/ColapsarEstructurawp_financing_options
Expandir/ColapsarEstructurawp_links
Expandir/ColapsarEstructurawp_mobbex_cache
Expandir/ColapsarEstructurawp_mobbex_log
Expandir/ColapsarEstructurawp_mobbex_transaction
Expandir/ColapsarEstructurawp_options
Expandir/ColapsarEstructurawp_options_terms_rel
Expandir/ColapsarEstructurawp_pmxi_files
Expandir/ColapsarEstructurawp_pmxi_hash
Expandir/ColapsarEstructurawp_pmxi_history
Expandir/ColapsarEstructurawp_pmxi_images
Expandir/ColapsarEstructurawp_pmxi_imports
Expandir/ColapsarEstructurawp_pmxi_posts
Expandir/ColapsarEstructurawp_pmxi_templates
Expandir/ColapsarEstructurawp_postmeta
Expandir/ColapsarEstructurawp_posts
Expandir/ColapsarEstructurawp_revslider_css
Expandir/ColapsarEstructurawp_revslider_css_bkp
Expandir/ColapsarEstructurawp_revslider_layer_animations
Expandir/ColapsarEstructurawp_revslider_layer_animations_bkp
 Servidor: 127.0.0.1:3306
 Base de datos: u962801258_cMW0F
 Tabla: wp_postmeta
Examinar Examinar
Estructura Estructura
SQL SQL
Buscar Buscar
Insertar Insertar
Exportar Exportar
Importar Importar
Operaciones Operaciones
Disparadores Disparadores
Ajustes de página relacionada Pulse en la barra para deslizarse al tope de la página
Consola de consultas SQL Consola
ascendentedescendenteOrden:Depuración SQLOrden de ejecuciónTiempo necesarioOrdenar por:Consultas grupales
Ocurrió un error al obtener información de depuración SQL.
OpcionesDefinir predeterminado
Siempre expandir mensajes de consultas
Mostrar histórico de consultas al iniciar
Mostrar consulta de navegación actual
 Ejecute consultas en Enter e inserte una nueva línea con Shift+Enter. Para que esto sea permanente, consulte la configuración.
Cambiar al tema oscuro

 MySQL ha devuelto un conjunto de valores vacío (es decir: cero columnas). (La consulta tardó 0.0003 segundos.)
SELECT tr.*, t.name FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id JOIN wp_terms t ON tt.term_id = t.term_id WHERE tr.object_id = 9555 AND tt.taxonomy = 'product_visibility';
 Perfilando [ Editar en línea ] [ Editar ] [ Explicar SQL ] [ Crear código PHP ] [ Actualizar ]
object_id	term_taxonomy_id	term_order	name	