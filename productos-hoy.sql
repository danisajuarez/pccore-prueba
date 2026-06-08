SELECT ID, post_title, post_status, post_type, post_date FROM wp_posts WHERE post_type = 'product' AND post_date > '2026-05-22' ORDER BY post_date DESC LIMIT 10;
