<?php
header('Content-Type: application/json');
echo '{"success":true,"message":"JSON OK","sku":"' . ($_GET['sku'] ?? 'ninguno') . '"}';
