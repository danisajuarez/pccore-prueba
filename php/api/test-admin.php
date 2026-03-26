<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: PHP funciona ✓<br>";

try {
    require_once __DIR__ . '/../config.php';
    echo "Test 2: config.php carga ✓<br>";
} catch (Exception $e) {
    echo "Test 2: ERROR - " . $e->getMessage() . "<br>";
    exit;
}

try {
    checkAuth();
    echo "Test 3: Auth funciona ✓<br>";
} catch (Exception $e) {
    echo "Test 3: ERROR - " . $e->getMessage() . "<br>";
    // Continuar para ver más
}

echo "Test 4: Intentando cargar admin-productos.php...<br>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Admin</title>
</head>
<body>
    <h1>Tests completados</h1>
    <p>Si ves esto, el PHP funciona. El problema debe ser JavaScript.</p>
    <button onclick="alert('JavaScript funciona!')">Test JS</button>
</body>
</html>
