<?php
/**
 * Debug: Ver qué taxonomías tiene WooCommerce
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

header('Content-Type: text/html; charset=utf-8');

try {
    echo "<h2>Taxonomías disponibles en WooCommerce:</h2>";

    // Intentar obtener tags
    echo "<h3>Tags (Etiquetas):</h3>";
    try {
        $tags = wcRequest('/products/tags?per_page=10');
        if (!empty($tags)) {
            echo "<p style='color: green;'>✓ Tags disponibles (" . count($tags) . " encontrados)</p>";
            echo "<ul>";
            foreach (array_slice($tags, 0, 5) as $tag) {
                echo "<li>{$tag['name']} (ID: {$tag['id']}, count: {$tag['count']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠ No hay tags creados aún</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    }

    echo "<hr>";

    // Intentar obtener atributos (pueden incluir marcas)
    echo "<h3>Atributos de producto:</h3>";
    try {
        $attributes = wcRequest('/products/attributes?per_page=20');
        if (!empty($attributes)) {
            echo "<p style='color: green;'>✓ Atributos disponibles (" . count($attributes) . " encontrados)</p>";
            echo "<ul>";
            foreach ($attributes as $attr) {
                echo "<li><strong>{$attr['name']}</strong> (slug: {$attr['slug']}, ID: {$attr['id']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠ No hay atributos creados</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    }

    echo "<hr>";

    // Información adicional
    echo "<h3>Información:</h3>";
    echo "<ul>";
    echo "<li><strong>Categorías:</strong> Ya implementado ✓</li>";
    echo "<li><strong>Tags:</strong> Soportado por WooCommerce nativamente</li>";
    echo "<li><strong>Marcas:</strong> Depende de si tenés un plugin instalado o se maneja como atributo</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error general: " . $e->getMessage() . "</p>";
}
