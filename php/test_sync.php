<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
$_GET['key'] = 'pccoreprueba-sync-2024';

require __DIR__ . '/api/auto-sync.php';
