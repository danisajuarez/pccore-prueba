<?php
/**
 * Layout principal
 *
 * Variables disponibles:
 * - $pageTitle: Título de la página
 * - $clienteId: ID del cliente
 * - $userName: Nombre del usuario
 * - $activePage: Página activa ('dashboard' o 'productos')
 * - $extraCss: CSS adicional (opcional)
 * - $extraJs: JS adicional (opcional)
 * - $content: Contenido de la página
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/public/css/app.css">
    <?php if (!empty($extraCss)): ?>
    <style><?= $extraCss ?></style>
    <?php endif; ?>
</head>
<body class="<?= $activePage === 'dashboard' ? 'dashboard' : '' ?>">

    <div class="container">
        <header>
            <div class="logo">
                <?= htmlspecialchars(strtoupper($clienteId)) ?>
                <span style="font-size: 10px; color: #64748b; font-weight: normal;">
                    <?= $activePage === 'dashboard' ? 'Sync Panel' : 'Admin Productos' ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="nav-links">
                    <a href="/" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Sincronizador</a>
                    <a href="/api/admin-productos.php" class="<?= $activePage === 'productos' ? 'active' : '' ?>">Productos</a>
                    <a href="/api/admin-nuevo-producto.php" class="<?= $activePage === 'nuevo-producto' ? 'active' : '' ?>">+ Nuevo</a>
                    <a href="/api/logout.php" class="logout">Salir</a>
                </div>
                <div class="status" id="statusIndicator">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="statusText"><?= htmlspecialchars($userName) ?></span>
                </div>
            </div>
        </header>

        <?= $content ?>
    </div>

    <script>
        const API_KEY = '<?= $apiKey ?>';
        const API_BASE = '/api';
    </script>
    <?php if (!empty($jsFile)): ?>
    <script src="<?= $jsFile ?>"></script>
    <?php endif; ?>
    <?php if (!empty($extraJs)): ?>
    <script><?= $extraJs ?></script>
    <?php endif; ?>
</body>
</html>
