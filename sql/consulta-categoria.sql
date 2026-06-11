SELECT t.term_id, t.name, t.slug, tt.count, tt.parent FROM wp_terms t JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id WHERE t.term_id = 824;
