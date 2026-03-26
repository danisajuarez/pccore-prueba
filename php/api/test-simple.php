<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API funcionando OK',
    'timestamp' => date('Y-m-d H:i:s')
]);
