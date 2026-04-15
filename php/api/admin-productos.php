<?php
/**
 * Admin Productos - Multi-tenant
 *
 * Gestión completa de productos: búsqueda, publicación,
 * actualización y sincronización con WooCommerce.
 *
 * Requiere doble autenticación:
 * 1. Login cliente (Master DB) - ya hecho
 * 2. Login usuario SIGE (sige_usu_usuario) - verificado aquí
 */
require_once __DIR__ . '/../bootstrap.php';

// Primero: requiere login de cliente
requireAuth('/api/login.php');

// Segundo: requiere login de admin SIGE
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /api/admin-login.php');
    exit();
}

// Obtener datos del cliente y usuario
$clienteConfig = getClienteConfig();
$clienteNombre = $clienteConfig['nombre'] ?? 'Sistema';
$clienteId = getClienteId();
$userName = $_SESSION['admin_user_nombre'] ?? $_SESSION['admin_user'] ?? 'Usuario';

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(strtoupper($clienteNombre)) ?> - Admin Productos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8px 16px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #334155;
            margin-bottom: 8px;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #3b82f6;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #1e293b;
            border-radius: 20px;
            font-size: 14px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
        }

        .status-dot.error {
            background: #ef4444;
        }

        .nav-links {
            display: flex;
            gap: 8px;
        }

        .nav-links a {
            padding: 8px 16px;
            background: #1e293b;
            border-radius: 6px;
            font-size: 13px;
            color: #e2e8f0;
            text-decoration: none;
            border: 1px solid #334155;
            transition: background 0.2s;
        }

        .nav-links a:hover {
            background: #334155;
        }

        .nav-links a.active {
            background: #3b82f6;
            border-color: #3b82f6;
        }

        .nav-links a.logout {
            background: #dc2626;
            border-color: #dc2626;
        }

        .nav-links a.logout:hover {
            background: #b91c1c;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        .card {
            background: #1e293b;
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid #334155;
        }

        .card h2 {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-value {
            font-size: 22px;
            font-weight: bold;
            color: #f8fafc;
        }

        .card-value.success { color: #22c55e; }
        .card-value.warning { color: #f59e0b; }
        .card-value.error { color: #ef4444; }

        .main-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
        }

        @media (max-width: 900px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Estado del producto más visible */
        .product-status-banner {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .product-status-banner.new {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }

        .product-status-banner.published {
            background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
        }

        .product-status-banner.draft {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .product-status-text {
            font-size: 16px;
            font-weight: 600;
        }

        .product-status-action {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .product-status-action:hover {
            background: rgba(255,255,255,0.3);
        }

        .search-section {
            background: #1e293b;
            border-radius: 6px;
            padding: 14px;
            border: 1px solid #334155;
            height: fit-content;
            position: sticky;
            top: 16px;
        }

        .search-section h2 {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .search-box {
            display: flex;
            gap: 8px;
        }

        .search-box input {
            flex: 1;
            padding: 8px 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 4px;
            font-size: 13px;
            color: #e2e8f0;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .search-box input::placeholder {
            color: #64748b;
        }

        button {
            padding: 6px 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #2563eb;
        }

        button:disabled {
            background: #475569;
            cursor: not-allowed;
        }

        button.success {
            background: #22c55e;
        }

        button.success:hover {
            background: #16a34a;
        }

        button.warning {
            background: #f59e0b;
            color: #1e293b;
        }

        button.warning:hover {
            background: #d97706;
        }

        button.secondary {
            background: #475569;
        }

        button.secondary:hover {
            background: #64748b;
        }

        /* Estilos removidos - ahora usamos toast */

        /* Producto info - columna derecha */
        .product-info {
            display: none;
            background: #1e293b;
            border-radius: 6px;
            padding: 14px;
            border: 1px solid #334155;
        }

        .product-info.visible {
            display: block;
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 4px;
        }

        .product-sku {
            font-size: 12px;
            color: #64748b;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-publish {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-draft {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .status-not-in-woo {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }

        .info-item {
            background: #0f172a;
            padding: 10px 12px;
            border-radius: 6px;
        }

        .info-item.full {
            grid-column: span 2;
        }

        .info-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #f8fafc;
            font-weight: 500;
        }

        .info-value.price {
            color: #22c55e;
            font-size: 18px;
        }

        .attributes-section {
            display: none;
            margin-bottom: 12px;
        }

        .attributes-section.visible {
            display: block;
        }

        .attributes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .attr-item {
            background: #334155;
            padding: 8px 10px;
            border-radius: 4px;
        }

        .attr-name {
            font-size: 10px;
            color: #94a3b8;
        }

        .attr-value {
            font-size: 12px;
            color: #f8fafc;
        }

        .product-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .link-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #22c55e;
            color: white;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .link-btn:hover {
            background: #16a34a;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff40;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }

        /* Sección de imágenes */

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .image-card {
            position: relative;
            background: #0f172a;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .image-card:hover {
            border-color: #475569;
        }

        .image-card.selected {
            border-color: #3b82f6;
        }

        .image-card img {
            width: 100%;
            height: 100px;
            object-fit: contain;
            background: #fff;
        }

        .image-card .img-check {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 20px;
            height: 20px;
            background: rgba(0,0,0,0.5);
            border: 2px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .image-card.selected .img-check {
            background: #3b82f6;
            border-color: #3b82f6;
        }

        .image-card .img-source {
            padding: 4px 6px;
            font-size: 9px;
            color: #64748b;
            background: #1e293b;
        }

        .images-woo {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .images-woo h4 {
            font-size: 12px;
            color: #22c55e;
            margin-bottom: 10px;
        }

        .images-ml {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            padding: 12px;
        }

        .images-ml h4 {
            font-size: 12px;
            color: #3b82f6;
            margin-bottom: 6px;
        }

        .images-ml .ml-info {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .select-all-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #94a3b8;
        }

        .select-all-row input {
            width: 16px;
            height: 16px;
        }

        .no-images-msg {
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        .loading-images {
            padding: 20px;
            text-align: center;
            color: #64748b;
        }

        /* Sección de actualización unificada */
        .update-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #334155;
        }

        .update-section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #f8fafc;
        }

        .update-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }

        .update-option {
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 6px;
            padding: 10px;
            transition: all 0.2s;
        }

        .update-option.active {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        .update-option-header {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .update-option-header input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .update-option-label {
            font-size: 14px;
            font-weight: 600;
            color: #e2e8f0;
            flex: 1;
        }

        .update-option-content {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #334155;
            display: none;
        }

        .update-option.active .update-option-content {
            display: block;
        }

        /* Sección de descripción */
        .desc-option {
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .desc-option:hover {
            border-color: #475569;
        }

        .desc-option.selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .desc-option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .desc-option-source {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .desc-option-source.bd {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .desc-option-source.woo {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .desc-option-source.ml {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .desc-option-check {
            width: 20px;
            height: 20px;
            background: #334155;
            border: 2px solid #475569;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .desc-option.selected .desc-option-check {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .desc-option-text {
            font-size: 13px;
            color: #e2e8f0;
            white-space: pre-wrap;
            line-height: 1.5;
            max-height: 100px;
            overflow: hidden;
            position: relative;
            transition: max-height 0.3s ease;
        }

        .desc-option-text.expanded {
            max-height: none;
            overflow-y: auto;
            max-height: 500px;
        }

        .desc-option-text.empty {
            color: #64748b;
            font-style: italic;
        }

        .desc-expand-btn {
            display: block;
            margin-top: 8px;
            font-size: 11px;
            color: #3b82f6;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            text-align: left;
        }

        .desc-expand-btn:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        .desc-char-count {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .cards {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-item.full {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo"><?= htmlspecialchars(strtoupper($clienteNombre)) ?> <span style="font-size: 10px; color: #64748b; font-weight: normal;">Admin Productos</span></div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="nav-links">
                    <a href="/">Sincronizador</a>
                    <a href="/api/admin-productos.php" class="active">Productos</a>
                    <a href="/api/admin-logout.php" class="logout">Salir</a>
                </div>
                <div class="status" id="statusIndicator">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="userNameText"><?= htmlspecialchars($userName) ?></span>
                </div>
            </div>
        </header>

        <div class="main-grid">
            <!-- Columna izquierda: Búsqueda -->
            <div class="search-section">
                <h2>🔍 Buscar Producto por SKU</h2>
                <div class="search-box">
                    <input type="text" id="skuInput" placeholder="Ingresá el SKU del producto..." onkeypress="if(event.key==='Enter')debouncedBuscarProducto()">
                    <button onclick="debouncedBuscarProducto()" id="searchBtn">Buscar</button>
                </div>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #334155; font-size: 11px; color: #64748b;">
                    <div style="margin-bottom: 6px;">💡 Ingresá el código SKU del producto para:</div>
                    <ul style="margin: 0; padding-left: 16px; line-height: 1.8;">
                        <li>Ver datos de SIGE</li>
                        <li>Publicar en WooCommerce</li>
                        <li>Actualizar precio/stock</li>
                        <li>Buscar imágenes en ML</li>
                    </ul>
                </div>
            </div>

            <!-- Columna derecha: Info del producto -->
            <div class="product-info" id="productInfo">
                    <!-- Banner de estado compacto -->
                    <div class="product-status-banner" id="statusBanner">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                            <span class="product-status-text" id="statusText">-</span>
                            <span style="font-size: 11px; opacity: 0.8;" id="statusSubtext"></span>
                        </div>
                        <button class="product-status-action" id="statusAction" style="display: none;">-</button>
                    </div>

                    <!-- Nombre del producto -->
                    <div id="prodNombre" style="font-size: 15px; font-weight: 600; padding: 8px 0; color: #f8fafc;">-</div>

                    <!-- Elementos ocultos para compatibilidad con JS -->
                    <div style="display: none;">
                        <span id="prodSku">-</span>
                        <span id="prodPrecio">-</span>
                        <span id="prodPrecioSinIva">-</span>
                        <span id="prodStock">-</span>
                        <div id="prodPeso"></div>
                        <div id="prodDimensiones"></div>
                        <div id="descLargaItem"><div id="prodDescLarga">-</div></div>
                        <span class="status-badge" id="prodStatus">-</span>
                    </div>

                    <div class="attributes-section" id="attrSection" style="display: none;">
                        <div class="info-label" style="margin-bottom: 8px;">Atributos</div>
                        <div class="attributes-grid" id="prodAtributos"></div>
                    </div>

                    <div class="product-actions" id="productActions" style="display: none;"></div>

                    <!-- Sección para PRODUCTO NUEVO - Publicar directo -->
                    <div class="update-section" id="publishSection" style="display: none;">
                        <div style="font-size: 13px; font-weight: 600; margin-bottom: 10px; color: #f8fafc;">📋 Datos del producto:</div>

                        <!-- Tabla de datos (mismo orden que updateSection) -->
                        <table style="width: 100%; background: #0f172a; border-radius: 6px; font-size: 12px; border-collapse: collapse; margin-bottom: 12px;">
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b; width: 100px;">SKU</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600;" colspan="3" id="pubSku">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Stock</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600; color: #3b82f6;" id="pubStock">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Precio c/IVA</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600; color: #22c55e; font-size: 14px;" id="pubPrecio">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Precio s/IVA</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #94a3b8;" id="pubPrecioSinIva">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Categoría</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="pubCategoria">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Marca</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="pubMarca">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Peso</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="pubPeso">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Dimensiones</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" colspan="3" id="pubDimensiones">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; color: #64748b; vertical-align: top;">Desc. larga</td>
                                <td style="padding: 8px; font-size: 11px; max-height: 40px; overflow: hidden;" colspan="3" id="pubDescLarga">-</td>
                            </tr>
                        </table>

                        <!-- Sección de imágenes (igual que updateSection) -->
                        <div id="imagesSectionPublish" style="background: #0f172a; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">🖼️ Imágenes:</div>

                            <!-- Cargando -->
                            <div id="loadingImagesPublish" style="display: none; text-align: center; padding: 10px;">
                                <span class="spinner"></span> Buscando imágenes en ML...
                            </div>

                            <!-- Imágenes encontradas -->
                            <div id="imagesMlPublish" style="display: none;">
                                <div id="mlInfoPublish" style="font-size: 11px; color: #64748b; margin-bottom: 8px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 11px;">
                                        <input type="checkbox" id="selectAllMlPublish" onchange="toggleSelectAllPublish()">
                                        Seleccionar todas
                                    </label>
                                    <span id="selectedCountPublish" style="font-size: 11px; color: #94a3b8;">0 seleccionadas</span>
                                </div>
                                <div id="mlImagesGridPublish" class="images-grid"></div>
                            </div>

                            <!-- Sin imágenes -->
                            <div id="noImagesMsgPublish" style="display: none; text-align: center; color: #64748b; font-size: 12px; padding: 10px;">
                                No se encontraron imágenes en ML
                            </div>
                        </div>

                        <!-- Ocultos para compatibilidad -->
                        <div id="publishSummary" style="display: none;"></div>
                        <div id="publishDescripcion" style="display: none;"></div>
                        <div id="publishDescripcionContent" style="display: none;"></div>
                        <div id="publishImagenes" style="display: none;"></div>
                        <div id="publishImagenesContent" style="display: none;"></div>
                        <div id="dimensionesContent" style="display: none;"></div>
                        <div id="publishImagenesLista" style="display: none;"></div>
                        <span id="pubImagenesCount" style="display: none;"></span>

                        <button class="success" onclick="publicarProductoDirecto()" id="btnPublicarDirecto" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 600;">
                            🚀 Publicar en WooCommerce
                        </button>
                    </div>

                    <!-- Sección para PRODUCTO EXISTENTE - Actualizar -->
                    <div class="update-section" id="updateSection" style="display: none;">
                        <div style="font-size: 13px; font-weight: 600; margin-bottom: 10px; color: #f8fafc;">📋 Datos del producto:</div>

                        <!-- Tabla de datos compacta -->
                        <table style="width: 100%; background: #0f172a; border-radius: 6px; font-size: 12px; border-collapse: collapse; margin-bottom: 12px;">
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b; width: 100px;">SKU</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600;" colspan="3" id="updSku">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Stock</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600; color: #3b82f6;" id="updStock">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Precio c/IVA</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; font-weight: 600; color: #22c55e; font-size: 14px;" id="updPrecio">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Precio s/IVA</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #94a3b8;" id="updPrecioSinIva">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Categoría</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="updCategoria">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Marca</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="updMarca">-</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Peso</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" id="updPeso">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #334155; color: #64748b;">Dimensiones</td>
                                <td style="padding: 8px; border-bottom: 1px solid #334155;" colspan="3" id="updDimensiones">-</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; color: #64748b; vertical-align: top;">Desc. larga</td>
                                <td style="padding: 8px; font-size: 11px; max-height: 40px; overflow: hidden;" colspan="3" id="updDescLarga">-</td>
                            </tr>
                        </table>

                        <!-- Checkboxes simples -->
                        <div style="background: #1e293b; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <div style="font-size: 12px; color: #94a3b8; margin-bottom: 10px;">Actualizar:</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                <label id="labelPrecioStock" style="display: flex; align-items: center; gap: 6px; cursor: not-allowed; background: #22c55e20; padding: 6px 12px; border-radius: 6px; border: 1px solid #22c55e;">
                                    <input type="checkbox" id="checkPrecioStock" checked disabled style="accent-color: #22c55e;">
                                    <span style="font-size: 12px; color: #22c55e;">💰 Precio/Stock</span>
                                </label>
                                <label id="labelDimensiones" style="display: flex; align-items: center; gap: 6px; cursor: pointer; background: #0f172a; padding: 6px 12px; border-radius: 6px; border: 1px solid #334155;" onclick="toggleCheckLabel('Dimensiones')">
                                    <input type="checkbox" id="checkDimensiones" style="accent-color: #3b82f6; pointer-events: none;">
                                    <span style="font-size: 12px;">📐 Dimensiones</span>
                                </label>
                                <label id="labelDescripcion" style="display: flex; align-items: center; gap: 6px; cursor: pointer; background: #0f172a; padding: 6px 12px; border-radius: 6px; border: 1px solid #334155;" onclick="toggleCheckLabel('Descripcion')">
                                    <input type="checkbox" id="checkDescripcion" style="accent-color: #3b82f6; pointer-events: none;">
                                    <span style="font-size: 12px;">📝 Descripción</span>
                                </label>
                                <label id="labelImagenes" style="display: flex; align-items: center; gap: 6px; cursor: pointer; background: #0f172a; padding: 6px 12px; border-radius: 6px; border: 1px solid #334155;" onclick="toggleCheckLabel('Imagenes')">
                                    <input type="checkbox" id="checkImagenes" style="accent-color: #3b82f6; pointer-events: none;">
                                    <span style="font-size: 12px;">🖼️ Imágenes <span id="imgCountBadge" style="display: none; background: #3b82f6; color: white; padding: 1px 6px; border-radius: 8px; font-size: 10px; margin-left: 4px;"></span></span>
                                </label>
                            </div>
                        </div>

                        <!-- Sección de imágenes (se muestra automáticamente) -->
                        <div id="imagesSectionUpdate" style="background: #0f172a; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">🖼️ Imágenes:</div>

                            <!-- Cargando -->
                            <div class="loading-images" id="loadingImages" style="display: none; text-align: center; padding: 10px;">
                                <span class="spinner"></span> Buscando imágenes en ML...
                            </div>

                            <!-- Imágenes de Mercado Libre -->
                            <div class="images-ml" id="imagesMl" style="display: none;">
                                <div class="ml-info" id="mlInfo" style="font-size: 11px; color: #64748b; margin-bottom: 8px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 11px;">
                                        <input type="checkbox" id="selectAllMl" onchange="toggleSelectAll()">
                                        Seleccionar todas
                                    </label>
                                    <span class="count" id="selectedCount" style="font-size: 11px; color: #94a3b8;">0 seleccionadas</span>
                                </div>
                                <div class="images-grid" id="mlImagesGrid"></div>
                            </div>

                            <!-- Sin imágenes -->
                            <div class="no-images-msg" id="noImagesMsg" style="display: none; text-align: center; color: #64748b; font-size: 12px; padding: 10px;">
                                No se encontraron imágenes en ML
                            </div>

                            <!-- Imágenes actuales en WooCommerce -->
                            <div class="images-woo" id="imagesWoo" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #334155;">
                                <div style="font-size: 11px; color: #22c55e; margin-bottom: 8px;">📦 Ya tiene en WooCommerce:</div>
                                <div class="images-grid" id="wooImagesGrid"></div>
                            </div>
                        </div>

                        <!-- Elementos ocultos para compatibilidad -->
                        <div style="display: none;">
                            <span id="precioActual">-</span>
                            <span id="stockActual">-</span>
                            <span id="pesoSistema">-</span>
                            <span id="altoSistema">-</span>
                            <span id="anchoSistema">-</span>
                            <span id="profundidadSistema">-</span>
                            <span id="pesoWoo">-</span>
                            <span id="altoWoo">-</span>
                            <span id="anchoWoo">-</span>
                            <span id="profundidadWoo">-</span>
                            <div id="dimensionesWoo"></div>
                            <div id="descriptionOptions"></div>
                            <div id="sigeCategoriasInfo"></div>
                            <input type="checkbox" id="checkCategorias">
                            <span id="descCountBadge"></span>
                            <div id="btnBuscarImagenes"></div>
                        </div>

                        <!-- Botón Actualizar -->
                        <button class="success" onclick="actualizarTodoSeleccionado()" id="btnGlobalUpdate" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 600;">
                            ✓ Actualizar Producto
                        </button>
                    </div>

                </div>

            <!-- Log section removida - ahora usamos toast notifications -->
        </div>
    </div>

    <script>
        const API_KEY = '<?= $clienteId ?>-sync-2024';
        const API_BASE = '/api';

        let productoActual = null;
        let wooProducto = null;
        let datosML = null;
        let esAlta = false; // true si es producto nuevo, false si es modificación
        let stats = { buscados: 0, publicados: 0, sinPublicar: 0 };
        let descripcionSeleccionada = null;
        let descripcionParaPublicar = null;
        let dimensionesML = null;
        let buscarProductoRequestId = 0;
        let activeOperations = 0;
        let searchDebounceTimer = null;

        function appendImageVersion(url) {
            if (!url) return '';
            const separator = url.includes('?') ? '&' : '?';
            return `${url}${separator}v=${Date.now()}`;
        }

        function setUiBusyState(isBusy) {
            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.disabled = isBusy;
            }
            const publishBtn = document.getElementById('btnPublicarDirecto');
            if (publishBtn && isBusy && publishBtn.textContent !== '⏳ Publicando...') {
                publishBtn.disabled = true;
            }
        }

        function startOperation() {
            activeOperations++;
            setUiBusyState(true);
        }

        function endOperation() {
            activeOperations = Math.max(0, activeOperations - 1);
            if (activeOperations === 0) {
                setUiBusyState(false);
            }
        }

        function debouncedBuscarProducto() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                buscarProducto();
            }, 250);
        }

        // Limpiar estado del producto anterior
        function limpiarEstadoAnterior() {
            // Limpiar variables globales de ML
            descripcionParaPublicar = null;
            dimensionesML = null;
            imagenesML = [];
            imagenesParaPublicar = [];
            window.imagenesPublish = [];
            window.imagenesDisponibles = [];
            descripcionSeleccionada = null;

            // Limpiar grilla de imágenes ML
            const mlGrid = document.getElementById('mlImagesGrid');
            if (mlGrid) mlGrid.innerHTML = '';

            // Limpiar lista de imágenes en publicación
            const pubImagenes = document.getElementById('publishImagenesLista');
            if (pubImagenes) pubImagenes.innerHTML = '';

            // Resetear contador de imágenes
            const pubImagenesCount = document.getElementById('pubImagenesCount');
            if (pubImagenesCount) pubImagenesCount.textContent = 'Buscando...';

            const selectedCountPublish = document.getElementById('selectedCountPublish');
            if (selectedCountPublish) selectedCountPublish.textContent = '0 seleccionadas';

            const selectAllMlPublish = document.getElementById('selectAllMlPublish');
            if (selectAllMlPublish) selectAllMlPublish.checked = false;

            const selectAllMl = document.getElementById('selectAllMl');
            if (selectAllMl) selectAllMl.checked = false;

            // Resetear botón de publicación directa (evitar arrastre de estado visual)
            const btnPublicar = document.getElementById('btnPublicarDirecto');
            if (btnPublicar) {
                btnPublicar.disabled = false;
                btnPublicar.textContent = '🚀 Publicar en WooCommerce';
                btnPublicar.className = 'success';
            }

            // Resetear contenedor de descripción
            const descContainer = document.getElementById('publishDescripcionContent');
            if (descContainer) descContainer.innerHTML = '';

            // Resetear contenedor de dimensiones
            const dimContainer = document.getElementById('dimensionesContent');
            if (dimContainer) dimContainer.innerHTML = '';

            // Ocultar secciones
            document.getElementById('publishSection').style.display = 'none';
            document.getElementById('updateSection').style.display = 'none';
        }

        // Buscar producto
        async function buscarProducto(allowWhenBusy = false) {
            if (activeOperations > 0 && !allowWhenBusy) return;
            const sku = document.getElementById('skuInput').value.trim();
            if (!sku) {
                addLog('Ingresá un SKU para buscar', 'error');
                return;
            }
            const requestId = ++buscarProductoRequestId;
            startOperation();

            // Limpiar estado del producto anterior
            limpiarEstadoAnterior();

            const btn = document.getElementById('searchBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Buscando...';

            document.getElementById('productInfo').classList.remove('visible');
            addLog(`Buscando SKU: ${sku}...`, '');

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000);

                // Buscar producto en SIGE y WooCommerce
                const response = await fetch(`${API_BASE}/product-search.php?sku=${encodeURIComponent(sku)}&api_key=${API_KEY}`, {
                    signal: controller.signal,
                    cache: 'no-store'
                });
                clearTimeout(timeoutId);

                const responseText = await response.text();
                if (requestId !== buscarProductoRequestId) return;
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    addLog(`✗ Error parseando JSON del producto`, 'error');
                    addLog(`Respuesta (primeros 500 chars): ${responseText.substring(0, 500)}`, 'error');
                    console.error('Respuesta completa del servidor:', responseText);
                    throw new Error(`El servidor devolvió HTML en lugar de JSON. Ver consola para detalles.`);
                }

                btn.disabled = false;
                btn.textContent = 'Buscar';

                if (!data.success) {
                    addLog(`✗ ${data.error || 'Producto no encontrado'}`, 'error');
                    return;
                }

                productoActual = data.producto;
                wooProducto = data.woo_producto;

                // Determinar si es alta o modificación
                esAlta = (wooProducto === null);

                stats.buscados++;
                if (wooProducto) {
                    stats.publicados++;
                } else {
                    stats.sinPublicar++;
                }
                updateStats();

                addLog(`✓ Datos cargados, mostrando producto...`, 'success');
                mostrarProducto();
                const tipo = esAlta ? '(ALTA)' : '(MODIFICACIÓN)';
                addLog(`✓ Producto encontrado ${tipo}: ${productoActual.nombre}`, 'success');

            } catch (error) {
                btn.disabled = false;
                btn.textContent = 'Buscar';

                if (error.name === 'AbortError') {
                    addLog(`✗ Tiempo de espera agotado (15s). ¿Servidor muy lento?`, 'error');
                } else {
                    addLog(`✗ Error: ${error.message}`, 'error');
                }
                console.error('Error completo:', error);
            } finally {
                endOperation();
            }
        }

        function mostrarProducto() {
            document.getElementById('prodNombre').textContent = productoActual.nombre;
            document.getElementById('prodSku').textContent = productoActual.sku;
            document.getElementById('prodPrecio').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('prodPrecioSinIva').textContent = '$ ' + Number(productoActual.precio_sin_iva).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('prodStock').textContent = productoActual.stock + ' unidades';

            // Descripción larga
            const descLargaItem = document.getElementById('descLargaItem');
            const descLargaEl = document.getElementById('prodDescLarga');
            if (productoActual.descripcion_larga && productoActual.descripcion_larga.trim()) {
                descLargaEl.textContent = productoActual.descripcion_larga;
                descLargaItem.style.display = 'block';
            } else {
                descLargaItem.style.display = 'none';
            }

            // Atributos (guardamos pero no mostramos, los datos están en la tabla)
            const attrDiv = document.getElementById('prodAtributos');
            if (attrDiv && productoActual.atributos && productoActual.atributos.length > 0) {
                attrDiv.innerHTML = productoActual.atributos.map(a =>
                    `<div class="attr-item"><div class="attr-name">${a.nombre}</div><div class="attr-value">${a.valor}</div></div>`
                ).join('');
            }

            // Mostrar categorías de SIGE (automáticas)
            const sigeCategoriasInfo = document.getElementById('sigeCategoriasInfo');
            if (sigeCategoriasInfo) {
                let catHtml = '';
                if (productoActual.supracategoria) {
                    catHtml += `<div style="display: flex; align-items: center; gap: 8px;">
                        <span style="background: #3b82f6; color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px;">📁 ${productoActual.supracategoria}</span>
                        <span style="color: #64748b;">Supracategoría</span>
                    </div>`;
                }
                if (productoActual.categoria) {
                    catHtml += `<div style="display: flex; align-items: center; gap: 8px;">
                        <span style="background: #22c55e; color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px;">📂 ${productoActual.categoria}</span>
                        <span style="color: #64748b;">Categoría</span>
                    </div>`;
                }
                if (productoActual.marca) {
                    catHtml += `<div style="display: flex; align-items: center; gap: 8px;">
                        <span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px;">🏷️ ${productoActual.marca}</span>
                        <span style="color: #64748b;">Marca</span>
                    </div>`;
                }
                if (!catHtml) {
                    catHtml = '<div style="color: #64748b; font-size: 13px;">Sin categorías en SIGE</div>';
                }
                sigeCategoriasInfo.innerHTML = catHtml;
            }

            // Estado y acciones - Nuevo diseño con banner
            const statusBanner = document.getElementById('statusBanner');
            const statusText = document.getElementById('statusText');
            const statusSubtext = document.getElementById('statusSubtext');
            const statusAction = document.getElementById('statusAction');
            const statusEl = document.getElementById('prodStatus');
            const actionsEl = document.getElementById('productActions');

            if (wooProducto) {
                if (wooProducto.status === 'publish') {
                    // Producto PUBLICADO
                    statusBanner.className = 'product-status-banner published';
                    statusText.textContent = '✓ Publicado en la web';
                    statusSubtext.innerHTML = `<a href="${wooProducto.permalink}" target="_blank" style="color: white; text-decoration: underline;">Ver producto en la tienda →</a>`;
                    statusAction.textContent = '⏸️ Pausar';
                    statusAction.style.display = 'block';
                    statusAction.onclick = desactivarProducto;

                    statusEl.className = 'status-badge status-publish';
                    statusEl.textContent = 'Publicado';
                } else {
                    // Producto PAUSADO/DRAFT
                    statusBanner.className = 'product-status-banner draft';
                    statusText.textContent = '⏸️ Producto pausado';
                    statusSubtext.textContent = 'El producto existe pero no está visible en la tienda';
                    statusAction.textContent = '▶️ Activar';
                    statusAction.style.display = 'block';
                    statusAction.onclick = activarProducto;

                    statusEl.className = 'status-badge status-draft';
                    statusEl.textContent = 'Desactivado';
                }

                // Mostrar sección de actualización
                document.getElementById('updateSection').style.display = 'block';
                document.getElementById('publishSection').style.display = 'none';

                // Llenar tabla resumen de actualización
                llenarResumenActualizacion();

                // Buscar imágenes automáticamente de ML
                buscarImagenesAuto();

                // Llenar datos de opciones de actualización
                document.getElementById('precioActual').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('stockActual').textContent = productoActual.stock + ' unidades';
                document.getElementById('pesoSistema').textContent = productoActual.peso ? productoActual.peso + ' kg' : '-';
                document.getElementById('altoSistema').textContent = productoActual.alto ? productoActual.alto + ' cm' : '-';
                document.getElementById('anchoSistema').textContent = productoActual.ancho ? productoActual.ancho + ' cm' : '-';
                document.getElementById('profundidadSistema').textContent = productoActual.profundidad ? productoActual.profundidad + ' cm' : '-';

                if (wooProducto.weight || wooProducto.dimensions) {
                    const dimensionesWooDiv = document.getElementById('dimensionesWoo');
                    if (dimensionesWooDiv) {
                        dimensionesWooDiv.style.display = 'block';
                        document.getElementById('pesoWoo').textContent = wooProducto.weight || '-';
                        document.getElementById('altoWoo').textContent = wooProducto.dimensions?.height || '-';
                        document.getElementById('anchoWoo').textContent = wooProducto.dimensions?.width || '-';
                        document.getElementById('profundidadWoo').textContent = wooProducto.dimensions?.length || '-';
                    }
                }

            } else {
                // Producto NO PUBLICADO - Nuevo
                statusBanner.className = 'product-status-banner new';
                statusText.textContent = '🆕 Producto no publicado';
                statusSubtext.textContent = 'Este producto no está en la tienda. Podés publicarlo ahora.';
                statusAction.style.display = 'none';

                statusEl.className = 'status-badge status-not-in-woo';
                statusEl.textContent = 'No publicado';

                // Mostrar sección de publicación
                document.getElementById('publishSection').style.display = 'block';
                document.getElementById('updateSection').style.display = 'none';

                // Asegurar estado limpio del botón de publicación
                const btnPublicar = document.getElementById('btnPublicarDirecto');
                if (btnPublicar) {
                    btnPublicar.disabled = false;
                    btnPublicar.textContent = '🚀 Publicar en WooCommerce';
                    btnPublicar.className = 'success';
                }
                llenarResumenPublicacion();

                // Buscar imágenes automáticamente
                buscarImagenesParaPublicar();
            }

            document.getElementById('productInfo').classList.add('visible');

            // Preparar opciones tanto para nuevos como publicados
            // Esperar a que el DOM esté listo
            setTimeout(() => {
                // Mostrar opciones de descripción
                mostrarDescripcionOptions();

                // TEMPORALMENTE DESHABILITADO - Búsqueda automática de imágenes
                // Siempre mostrar botón para búsqueda manual
                const btnBuscarImagenes = document.getElementById('btnBuscarImagenes');
                if (btnBuscarImagenes) {
                    btnBuscarImagenes.style.display = 'block';
                }

                // Solo cargar categorías para productos existentes (modificación)
                if (!esAlta) {
                    addLog('Cargando categorías...', '');
                    cargarCategorias();
                    addLog('✓ Carga completa', 'success');
                    actualizarBotonGlobal();
                } else {
                    addLog('✓ Listo para publicar', 'success');
                }
            }, 200);
        }

        // ==========================================
        // FUNCIONES PARA PUBLICACIÓN DIRECTA (ALTA)
        // ==========================================

        let imagenesParaPublicar = []; // Imágenes seleccionadas para publicar

        // Función para seleccionar/deseleccionar imágenes
        function toggleImagenPublicar(idx) {
            if (!window.imagenesDisponibles || !window.imagenesDisponibles[idx]) return;

            const img = window.imagenesDisponibles[idx];
            img.selected = !img.selected;

            // Actualizar visual
            const container = document.querySelector(`.img-selectable[data-idx="${idx}"]`);
            if (container) {
                const imgEl = container.querySelector('img');
                const check = container.querySelector('.img-check');

                if (img.selected) {
                    imgEl.style.border = '2px solid #22c55e';
                    imgEl.style.opacity = '1';
                    check.style.display = 'block';
                } else {
                    imgEl.style.border = '2px solid #475569';
                    imgEl.style.opacity = '0.5';
                    check.style.display = 'none';
                }
            }

            // Actualizar array de imágenes a publicar
            imagenesParaPublicar = window.imagenesDisponibles
                .filter(i => i.selected)
                .map(i => i.url);

            // Actualizar contador
            const countEl = document.getElementById('imagenesCount');
            if (countEl) {
                countEl.textContent = `${imagenesParaPublicar.length} seleccionadas`;
            }

            const countSpan = document.getElementById('pubImagenesCount');
            if (countSpan) {
                if (imagenesParaPublicar.length > 0) {
                    countSpan.innerHTML = `<span style="color: #22c55e;">✓ ${imagenesParaPublicar.length} imagen(es)</span>`;
                } else {
                    countSpan.innerHTML = `<span style="color: #f59e0b;">Sin imágenes seleccionadas</span>`;
                }
            }
        }

        function llenarResumenPublicacion() {
            if (!productoActual) return;

            // SKU y Stock
            document.getElementById('pubSku').textContent = productoActual.sku || '-';
            document.getElementById('pubStock').textContent = productoActual.stock + ' unidades';

            // Precios
            document.getElementById('pubPrecio').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('pubPrecioSinIva').textContent = '$ ' + Number(productoActual.precio_sin_iva).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Categoría (supra > cat)
            let cat = [];
            if (productoActual.supracategoria) cat.push(productoActual.supracategoria);
            if (productoActual.categoria) cat.push(productoActual.categoria);
            document.getElementById('pubCategoria').textContent = cat.length ? cat.join(' → ') : 'Sin categoría';

            // Marca
            document.getElementById('pubMarca').textContent = productoActual.marca || 'Sin marca';

            // Peso
            document.getElementById('pubPeso').textContent = productoActual.peso ? productoActual.peso + ' kg' : 'No especificado';

            // Dimensiones en una línea
            let dims = [];
            if (productoActual.alto) dims.push(productoActual.alto + ' alto');
            if (productoActual.ancho) dims.push(productoActual.ancho + ' ancho');
            if (productoActual.profundidad) dims.push(productoActual.profundidad + ' prof.');
            document.getElementById('pubDimensiones').textContent = dims.length ? dims.join(' × ') + ' cm' : 'No especificadas';

            // Descripción larga
            document.getElementById('pubDescLarga').textContent = productoActual.descripcion_larga || '⏳ Buscando descripción...';

            // Llenar datos de ML si faltan
            llenarDatosML();
        }

        function llenarResumenActualizacion() {
            if (!productoActual) return;

            // SKU y Stock
            document.getElementById('updSku').textContent = productoActual.sku || '-';
            document.getElementById('updStock').textContent = productoActual.stock + ' unidades';

            // Precios
            document.getElementById('updPrecio').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('updPrecioSinIva').textContent = '$ ' + Number(productoActual.precio_sin_iva).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Categoría (supra > cat)
            let cat = [];
            if (productoActual.supracategoria) cat.push(productoActual.supracategoria);
            if (productoActual.categoria) cat.push(productoActual.categoria);
            document.getElementById('updCategoria').textContent = cat.length ? cat.join(' → ') : 'Sin categoría';

            // Marca
            document.getElementById('updMarca').textContent = productoActual.marca || 'Sin marca';

            // Peso - primero SIGE, si no WooCommerce
            let peso = productoActual.peso;
            if ((!peso || peso == '0' || peso == '') && wooProducto && wooProducto.weight && wooProducto.weight != '' && wooProducto.weight != '0') {
                peso = wooProducto.weight;
            }
            document.getElementById('updPeso').textContent = (peso && peso != '0' && peso != '') ? peso + ' kg' : 'No especificado';

            // Dimensiones - primero SIGE, si no WooCommerce
            let alto = productoActual.alto;
            let ancho = productoActual.ancho;
            let profundidad = productoActual.profundidad;

            if (wooProducto && wooProducto.dimensions) {
                const d = wooProducto.dimensions;
                if ((!alto || alto == '0' || alto == '') && d.height && d.height != '' && d.height != '0') alto = d.height;
                if ((!ancho || ancho == '0' || ancho == '') && d.width && d.width != '' && d.width != '0') ancho = d.width;
                if ((!profundidad || profundidad == '0' || profundidad == '') && d.length && d.length != '' && d.length != '0') profundidad = d.length;
            }

            let dims = [];
            if (alto && alto != '0' && alto != '') dims.push(alto + ' alto');
            if (ancho && ancho != '0' && ancho != '') dims.push(ancho + ' ancho');
            if (profundidad && profundidad != '0' && profundidad != '') dims.push(profundidad + ' prof.');
            document.getElementById('updDimensiones').textContent = dims.length ? dims.join(' × ') + ' cm' : 'No especificadas';

            // Para descripción larga, mostrar la de WooCommerce si existe
            let descLarga = productoActual.descripcion_larga || '';
            if (wooProducto && wooProducto.description) {
                // Limpiar HTML de la descripción de WooCommerce
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = wooProducto.description;
                const descWoo = tempDiv.textContent || tempDiv.innerText || '';
                if (descWoo.trim()) {
                    descLarga = descWoo.trim();
                }
            }
            document.getElementById('updDescLarga').textContent = descLarga || '(Sin descripción)';
        }

        async function llenarDatosML() {
            const descContainer = document.getElementById('publishDescripcionContent');
            const dimContainer = document.getElementById('dimensionesContent');

            // Verificar qué tenemos en SIGE
            const tieneDescSige = !!productoActual.descripcion_larga;
            const tieneDimSige = productoActual.peso || productoActual.alto || productoActual.ancho || productoActual.profundidad;

            // Mostrar datos de SIGE si los hay
            if (tieneDescSige && descContainer) {
                descripcionParaPublicar = productoActual.descripcion_larga;
                const desc = productoActual.descripcion_larga;
                descContainer.innerHTML = `
                    <div style="background: #0f172a; padding: 10px; border-radius: 6px; border-left: 3px solid #22c55e;">
                        <div style="font-size: 11px; color: #22c55e; margin-bottom: 5px;">✓ Descripción de SIGE (${desc.length} caracteres)</div>
                        <div style="color: #e2e8f0; font-size: 12px; white-space: pre-wrap;">${desc}</div>
                    </div>
                `;
                // Actualizar también el resumen
                document.getElementById('pubDescLarga').textContent = desc;
            }

            if (tieneDimSige && dimContainer) {
                let dims = [];
                if (productoActual.peso) dims.push(`${productoActual.peso}kg`);
                if (productoActual.alto) dims.push(`${productoActual.alto}cm alto`);
                if (productoActual.ancho) dims.push(`${productoActual.ancho}cm ancho`);
                if (productoActual.profundidad) dims.push(`${productoActual.profundidad}cm prof`);
                dimContainer.innerHTML = `<span style="color: #22c55e;">✓ SIGE: ${dims.join(' | ')}</span>`;
            }

            // Si ya tenemos todo de SIGE, no buscar en ML
            if (tieneDescSige && tieneDimSige) return;

            // Mostrar estado de búsqueda
            if (!tieneDescSige && descContainer) {
                descContainer.innerHTML = `<div style="color: #94a3b8; font-size: 12px;">⏳ Buscando en ML...</div>`;
            }
            if (!tieneDimSige && dimContainer) {
                dimContainer.innerHTML = `⏳ Buscando en ML...`;
            }
            addLog('Buscando datos en ML...', '');

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000);

                const response = await fetch(`${API_BASE}/product-search.php?api_key=${API_KEY}&sku=${encodeURIComponent(productoActual.sku)}&search_ml=true`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();

                // Procesar descripción si no la tenemos de SIGE
                if (!tieneDescSige && descContainer) {
                    if (data.ml_data?.descripcion) {
                        descripcionParaPublicar = data.ml_data.descripcion;
                        const desc = data.ml_data.descripcion;
                        descContainer.innerHTML = `
                            <div style="background: #0f172a; padding: 10px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                                <div style="font-size: 11px; color: #3b82f6; margin-bottom: 5px;">✓ Descripción de ML (${desc.length} caracteres)</div>
                                <div style="color: #e2e8f0; font-size: 12px; white-space: pre-wrap;">${desc}</div>
                            </div>
                        `;
                        // Actualizar también el resumen
                        document.getElementById('pubDescLarga').textContent = desc;
                        addLog('✓ Descripción encontrada en ML', 'success');
                    } else {
                        descripcionParaPublicar = null;
                        descContainer.innerHTML = `
                            <div style="color: #f59e0b; font-size: 12px;">
                                ⚠️ Sin descripción. Se usará el nombre del producto.
                            </div>
                        `;
                        // Actualizar también el resumen
                        document.getElementById('pubDescLarga').textContent = '(Sin descripción - se usará el nombre)';
                        addLog('⚠️ Sin descripción en SIGE ni ML', 'warning');
                    }
                }

                // Procesar dimensiones si no las tenemos de SIGE
                if (!tieneDimSige && dimContainer) {
                    if (data.ml_data && (data.ml_data.peso || data.ml_data.alto || data.ml_data.ancho)) {
                        dimensionesML = data.ml_data;
                        let dims = [];
                        if (data.ml_data.peso) dims.push(`${data.ml_data.peso}kg`);
                        if (data.ml_data.alto) dims.push(`${data.ml_data.alto}cm alto`);
                        if (data.ml_data.ancho) dims.push(`${data.ml_data.ancho}cm ancho`);
                        if (data.ml_data.profundidad) dims.push(`${data.ml_data.profundidad}cm prof`);
                        dimContainer.innerHTML = `<span style="color: #3b82f6;">✓ ML: ${dims.join(' | ')}</span>`;
                        addLog('✓ Dimensiones encontradas en ML', 'success');
                    } else {
                        dimensionesML = null;
                        dimContainer.innerHTML = `<span style="color: #f59e0b;">⚠️ Sin dimensiones</span>`;
                        addLog('⚠️ Sin dimensiones en SIGE ni ML', 'warning');
                    }
                }

            } catch (error) {
                if (!tieneDescSige && descContainer) {
                    descripcionParaPublicar = null;
                    descContainer.innerHTML = `
                        <div style="color: #f59e0b; font-size: 12px;">
                            ⚠️ Error buscando en ML. Se usará el nombre.
                        </div>
                    `;
                    // Actualizar también el resumen
                    document.getElementById('pubDescLarga').textContent = '(Sin descripción - se usará el nombre)';
                }
                if (!tieneDimSige && dimContainer) {
                    dimensionesML = null;
                    dimContainer.innerHTML = `<span style="color: #f59e0b;">⚠️ Sin dimensiones</span>`;
                }
            }
        }

        async function buscarImagenesParaPublicar() {
            const loadingEl = document.getElementById('loadingImagesPublish');
            const imagesEl = document.getElementById('imagesMlPublish');
            const noImagesEl = document.getElementById('noImagesMsgPublish');
            const gridEl = document.getElementById('mlImagesGridPublish');
            const infoEl = document.getElementById('mlInfoPublish');
            const countEl = document.getElementById('selectedCountPublish');
            const selectAllEl = document.getElementById('selectAllMlPublish');

            // Mostrar loading
            loadingEl.style.display = 'block';
            imagesEl.style.display = 'none';
            noImagesEl.style.display = 'none';

            addLog('Buscando imágenes en ML...', '');

            try {
                const response = await fetch(`${API_BASE}/image-search.php?api_key=${API_KEY}&sku=${encodeURIComponent(productoActual.sku)}`);
                const data = await response.json();

                loadingEl.style.display = 'none';

                if (data.success && data.imagenes?.mercadolibre?.imagenes?.length > 0) {
                    const imagenes = data.imagenes.mercadolibre.imagenes;
                    const imagenesMostradas = imagenes.slice(0, 8);
                    const encontradoPor = data.imagenes.mercadolibre.encontrado_por || 'ML';
                    const productoML = data.imagenes.mercadolibre.producto?.nombre || '';

                    addLog(`✓ ${imagenes.length} imagen(es) encontradas (${encontradoPor})`, 'success');

                    // Guardar imágenes disponibles (todas seleccionadas por defecto)
                    window.imagenesPublish = imagenesMostradas.map(img => ({ url: img.url, selected: true }));
                    imagenesParaPublicar = imagenesMostradas.map(img => img.url);

                    // Info del producto ML
                    infoEl.innerHTML = productoML
                        ? `📦 <span style="color: #94a3b8;">${productoML.substring(0, 80)}${productoML.length > 80 ? '...' : ''}</span> <span style="color: #3b82f6;">(${encontradoPor})</span>`
                        : `<span style="color: #3b82f6;">Encontrado por ${encontradoPor}</span>`;

                    // Renderizar grid de imágenes
                    gridEl.innerHTML = imagenesMostradas.map((img, idx) => `
                        <div class="image-item selected" data-idx="${idx}" onclick="toggleImagePublish(${idx})" style="position: relative; cursor: pointer;">
                            <img src="${appendImageVersion(img.url)}" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 2px solid #22c55e;">
                            <div class="check-badge" style="position: absolute; top: -4px; right: -4px; background: #22c55e; color: white; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px;">✓</div>
                        </div>
                    `).join('');

                    // Actualizar contador
                    countEl.textContent = `${imagenesMostradas.length} seleccionadas`;
                    selectAllEl.checked = true;

                    imagesEl.style.display = 'block';
                } else {
                    addLog('⚠️ No se encontraron imágenes en ML', 'warning');
                    noImagesEl.style.display = 'block';
                    imagenesParaPublicar = [];
                }
            } catch (error) {
                loadingEl.style.display = 'none';
                noImagesEl.style.display = 'block';
                noImagesEl.innerHTML = `<span style="color: #ef4444;">Error buscando imágenes: ${error.message}</span>`;
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        // Toggle imagen individual en publicación
        function toggleImagePublish(idx) {
            if (!window.imagenesPublish || !window.imagenesPublish[idx]) return;

            window.imagenesPublish[idx].selected = !window.imagenesPublish[idx].selected;
            const isSelected = window.imagenesPublish[idx].selected;

            const gridEl = document.getElementById('mlImagesGridPublish');
            const item = gridEl.children[idx];
            if (item) {
                const img = item.querySelector('img');
                const badge = item.querySelector('.check-badge');
                if (isSelected) {
                    item.classList.add('selected');
                    img.style.border = '2px solid #22c55e';
                    badge.style.display = 'flex';
                } else {
                    item.classList.remove('selected');
                    img.style.border = '2px solid #334155';
                    badge.style.display = 'none';
                }
            }

            // Actualizar array de imágenes para publicar
            imagenesParaPublicar = window.imagenesPublish
                .filter(img => img.selected)
                .map(img => img.url);

            // Actualizar contador
            const countEl = document.getElementById('selectedCountPublish');
            countEl.textContent = `${imagenesParaPublicar.length} seleccionadas`;

            // Actualizar checkbox "seleccionar todas"
            const selectAllEl = document.getElementById('selectAllMlPublish');
            selectAllEl.checked = imagenesParaPublicar.length === window.imagenesPublish.length;
        }

        // Toggle seleccionar todas en publicación
        function toggleSelectAllPublish() {
            const selectAllEl = document.getElementById('selectAllMlPublish');
            const selectAll = selectAllEl.checked;

            if (window.imagenesPublish) {
                window.imagenesPublish.forEach((img, idx) => {
                    img.selected = selectAll;
                    const gridEl = document.getElementById('mlImagesGridPublish');
                    const item = gridEl.children[idx];
                    if (item) {
                        const imgEl = item.querySelector('img');
                        const badge = item.querySelector('.check-badge');
                        if (selectAll) {
                            item.classList.add('selected');
                            imgEl.style.border = '2px solid #22c55e';
                            badge.style.display = 'flex';
                        } else {
                            item.classList.remove('selected');
                            imgEl.style.border = '2px solid #334155';
                            badge.style.display = 'none';
                        }
                    }
                });

                imagenesParaPublicar = selectAll
                    ? window.imagenesPublish.map(img => img.url)
                    : [];

                const countEl = document.getElementById('selectedCountPublish');
                countEl.textContent = `${imagenesParaPublicar.length} seleccionadas`;
            }
        }

        async function publicarProductoDirecto() {
            if (activeOperations > 0) return;
            startOperation();
            const btn = document.getElementById('btnPublicarDirecto');
            btn.disabled = true;
            btn.textContent = '⏳ Publicando...';

            addLog(`Publicando ${productoActual.sku}...`, '');

            try {
                // Preparar datos para publicar
                const publishData = {
                    sku: productoActual.sku
                };

                // Agregar descripción de ML si la hay (y no hay en SIGE)
                if (descripcionParaPublicar && !productoActual.descripcion_larga) {
                    publishData.descripcion_ml = descripcionParaPublicar;
                    addLog(`📝 Incluyendo descripción de ML...`, '');
                }

                // Agregar imágenes si hay
                if (imagenesParaPublicar.length > 0) {
                    publishData.images = imagenesParaPublicar.map(url => ({ src: url }));
                    addLog(`📷 Incluyendo ${imagenesParaPublicar.length} imagen(es)...`, '');
                }

                const response = await fetch(`${API_BASE}/product-publish.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(publishData)
                });

                const data = await response.json();

                if (data.success) {
                    addLog(`✓ ${data.message}`, 'success');
                    addLog(`✓ ID: ${data.product.id} | Precio: $${data.product.regular_price}`, 'success');
                    if (data.product.categories && data.product.categories.length > 0) {
                        const catNames = data.product.categories.map(c => c.name).join(', ');
                        addLog(`✓ Categorías: ${catNames}`, 'success');
                    }

                    // Confirmar estado real en backend para evitar falsos "Publicado"
                    addLog('Validando estado en WooCommerce...', '');
                    await buscarProducto(true);

                    if (wooProducto && wooProducto.status === 'publish') {
                        btn.textContent = '✓ Publicado';
                        btn.className = 'success';
                    } else {
                        btn.disabled = false;
                        btn.textContent = '🚀 Reintentar';
                        btn.className = '';
                        addLog('⚠ El producto no quedó publicado todavía. Reintentá en unos segundos.', 'warning');
                    }
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
                btn.disabled = false;
                btn.textContent = '🚀 Reintentar';
            } finally {
                endOperation();
            }
        }

        // ==========================================
        // FUNCIONES DE OPCIONES DE ACTUALIZACIÓN
        // ==========================================

        function toggleUpdateOption(option) {
            const checkbox = document.getElementById('check' + option.charAt(0).toUpperCase() + option.slice(1));
            const optionDiv = document.getElementById('option' + option.charAt(0).toUpperCase() + option.slice(1));

            // Verificar que los elementos existen
            if (!checkbox || !optionDiv) {
                return;
            }

            // Toggle checkbox
            checkbox.checked = !checkbox.checked;

            // Toggle clase active
            if (checkbox.checked) {
                optionDiv.classList.add('active');
            } else {
                optionDiv.classList.remove('active');
            }

            // Actualizar botón global
            actualizarBotonGlobal();
        }

        // Función para toggle de checkboxes con actualización visual
        function toggleCheckLabel(name) {
            const checkbox = document.getElementById('check' + name);
            const label = document.getElementById('label' + name);
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                if (label) {
                    if (checkbox.checked) {
                        label.style.background = '#3b82f620';
                        label.style.borderColor = '#3b82f6';
                    } else {
                        label.style.background = '#0f172a';
                        label.style.borderColor = '#334155';
                    }
                }
            }
        }

        // Función para buscar imágenes automáticamente
        async function buscarImagenesAuto() {
            if (!productoActual) return;

            const loading = document.getElementById('loadingImages');
            const mlDiv = document.getElementById('imagesMl');
            const wooDiv = document.getElementById('imagesWoo');
            const noMsg = document.getElementById('noImagesMsg');

            if (!loading) return;

            loading.style.display = 'block';
            if (mlDiv) mlDiv.style.display = 'none';
            if (wooDiv) wooDiv.style.display = 'none';
            if (noMsg) noMsg.style.display = 'none';
            imagenesML = [];

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);

                const response = await fetch(`${API_BASE}/image-search.php?sku=${encodeURIComponent(productoActual.sku)}&api_key=${API_KEY}`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();
                loading.style.display = 'none';

                if (!data.success) {
                    if (noMsg) {
                        noMsg.textContent = data.error || 'Sin imágenes en ML';
                        noMsg.style.display = 'block';
                    }
                    return;
                }

                // Imágenes de WooCommerce
                if (data.woocommerce && data.woocommerce.tiene_imagenes && wooDiv) {
                    const wooGrid = document.getElementById('wooImagesGrid');
                    if (wooGrid) {
                        wooGrid.innerHTML = data.woocommerce.imagenes.map(img => `
                            <div class="image-card">
                                <img src="${appendImageVersion(img.src)}" alt="${img.name || 'Imagen'}">
                            </div>
                        `).join('');
                        wooDiv.style.display = 'block';
                    }
                }

                // Imágenes de ML
                const ml = data.imagenes?.mercadolibre;
                if (ml && ml.imagenes && ml.imagenes.length > 0 && mlDiv) {
                    imagenesML = ml.imagenes;

                    const mlInfo = document.getElementById('mlInfo');
                    if (mlInfo) {
                        mlInfo.textContent = `${ml.imagenes.length} imágenes de ML (${ml.encontrado_por})`;
                    }

                    const mlGrid = document.getElementById('mlImagesGrid');
                    if (mlGrid) {
                        mlGrid.innerHTML = ml.imagenes.map((img, idx) => `
                            <div class="image-card" data-idx="${idx}" onclick="toggleImageSelect(this)">
                                <img src="${appendImageVersion(img.url)}" alt="Imagen ${idx + 1}">
                                <div class="img-check">✓</div>
                            </div>
                        `).join('');
                    }

                    mlDiv.style.display = 'block';
                    updateImageCount();

                    // Actualizar badge
                    const badge = document.getElementById('imgCountBadge');
                    if (badge) {
                        badge.textContent = ml.imagenes.length;
                        badge.style.display = 'inline';
                    }
                } else if (!data.woocommerce?.tiene_imagenes && noMsg) {
                    noMsg.style.display = 'block';
                }

            } catch (error) {
                loading.style.display = 'none';
                if (noMsg) {
                    noMsg.textContent = 'Error buscando imágenes';
                    noMsg.style.display = 'block';
                }
            }
        }

        // ==========================================
        // FUNCIONES DE DESCRIPCIÓN
        // ==========================================

        function mostrarDescripcionOptions() {
            const optionsDiv = document.getElementById('descriptionOptions');

            // Verificar que el elemento existe
            if (!optionsDiv) {
                console.error('No se encontró el elemento descriptionOptions');
                return;
            }

            let options = [];

            // Opción 1: Descripción de BD (Sistema)
            if (productoActual && productoActual.descripcion_larga) {
                addLog(`📝 Descripción de BD encontrada (${productoActual.descripcion_larga.length} caracteres)`, 'success');
                options.push({
                    source: 'bd',
                    label: esAlta ? 'Base de Datos (Sistema) ✓' : 'Base de Datos (Sistema)',
                    text: productoActual.descripcion_larga,
                    selected: esAlta // Auto-seleccionar solo en ALTA
                });
            } else {
                addLog(`⚠ No hay descripción en BD`, '');
            }

            // Opción 2: Descripción actual en WooCommerce
            if (wooProducto && wooProducto.description) {
                addLog(`📦 Descripción de WooCommerce encontrada (${wooProducto.description.length} caracteres)`, 'success');
                options.push({
                    source: 'woo',
                    label: 'WooCommerce (Actual)',
                    text: wooProducto.description,
                    selected: false
                });
            }

            // Renderizar opciones (o mensaje si no hay)
            if (options.length === 0) {
                optionsDiv.innerHTML = '<p style="color: #64748b; font-size: 12px;"><span class="spinner" style="display: inline-block; width: 12px; height: 12px; margin-right: 6px;"></span>Buscando en Mercado Libre...</p>';
                window.descriptionOptionsData = [];
                descripcionSeleccionada = null;
            } else {
                // Renderizar opciones existentes
                optionsDiv.innerHTML = options.map((opt, idx) => {
                    const charCount = opt.text ? opt.text.length : 0;
                    const fullText = opt.text || '<span class="empty">Sin descripción</span>';

                    return `
                        <div class="desc-option ${opt.selected ? 'selected' : ''}" data-idx="${idx}" onclick="seleccionarDescripcion(this, ${idx})">
                            <div class="desc-option-header">
                                <span class="desc-option-source ${opt.source}">${opt.label}</span>
                                <div class="desc-option-check">${opt.selected ? '✓' : ''}</div>
                            </div>
                            <div class="desc-option-text" id="desc-text-${idx}" style="white-space: pre-wrap;">${fullText}</div>
                            <div class="desc-char-count">${charCount} caracteres</div>
                        </div>
                    `;
                }).join('');

                // Guardar opciones y selección
                window.descriptionOptionsData = options;

                // Expandir la sección de descripción para que sea visible
                const optionDescripcion = document.getElementById('optionDescripcion');
                if (optionDescripcion && options.length > 0) {
                    optionDescripcion.classList.add('active');

                    // Actualizar badge con contador
                    const badge = document.getElementById('descCountBadge');
                    if (badge) {
                        badge.textContent = options.length;
                        badge.style.display = 'inline-block';
                    }

                    addLog(`📋 ${options.length} descripción(es) disponible(s)`, 'success');
                }

                if (esAlta && options.length > 0 && options[0].selected) {
                    descripcionSeleccionada = 0;

                    // Auto-activar checkbox de descripción en ALTA
                    const checkDescripcion = document.getElementById('checkDescripcion');
                    if (checkDescripcion) {
                        checkDescripcion.checked = true;
                    }
                } else {
                    descripcionSeleccionada = null;
                }
            }

            // Buscar en ML solo si NO hay descripciones disponibles o es ALTA
            const tieneDescripcionDisponible = options.length > 0;
            if (!tieneDescripcionDisponible || esAlta) {
                if (productoActual.part_number || productoActual.sku) {
                    buscarDescripcionML();
                }
            }

            // Actualizar botón global
            actualizarBotonGlobal();
        }

        function toggleDescExpand(idx) {
            const textEl = document.getElementById(`desc-text-${idx}`);
            const btnEl = event.target;
            const option = window.descriptionOptionsData[idx];

            if (!textEl || !option) return;

            if (textEl.classList.contains('expanded')) {
                // Colapsar
                const preview = option.text.substring(0, 200) + '...';
                textEl.textContent = preview;
                textEl.classList.remove('expanded');
                btnEl.textContent = 'Ver completo ▼';
            } else {
                // Expandir
                textEl.textContent = option.text;
                textEl.classList.add('expanded');
                btnEl.textContent = 'Ver menos ▲';
            }
        }

        function seleccionarDescripcion(element, idx) {
            // Deseleccionar todas
            document.querySelectorAll('.desc-option').forEach(opt => {
                opt.classList.remove('selected');
                opt.querySelector('.desc-option-check').textContent = '';
            });

            // Seleccionar la clickeada
            element.classList.add('selected');
            element.querySelector('.desc-option-check').textContent = '✓';
            descripcionSeleccionada = idx;

            // Auto-activar checkbox de descripción
            const checkDescripcion = document.getElementById('checkDescripcion');
            const optionDescripcion = document.getElementById('optionDescripcion');
            if (checkDescripcion && optionDescripcion && !checkDescripcion.checked) {
                checkDescripcion.checked = true;
                optionDescripcion.classList.add('active');
            }

            // Actualizar estado del botón global
            actualizarBotonGlobal();
        }

        async function buscarDescripcionML() {
            try {
                addLog('Buscando descripción en Mercado Libre...', '');

                // Timeout de 8 segundos
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);

                const response = await fetch(`${API_BASE}/product-search.php?sku=${encodeURIComponent(productoActual.sku)}&search_ml=true&api_key=${API_KEY}`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();

                if (data.success && data.ml_data && data.ml_data.descripcion) {
                    // Agregar opción de ML
                    const optionsDiv = document.getElementById('descriptionOptions');

                    // Limpiar mensaje de "Buscando..." si existe
                    if (optionsDiv && optionsDiv.querySelector('p')) {
                        optionsDiv.innerHTML = '';
                    }

                    const mlOption = {
                        source: 'ml',
                        label: `Mercado Libre (${data.ml_data.encontrado_por})`,
                        text: data.ml_data.descripcion,
                        selected: esAlta // Auto-seleccionar en ALTA
                    };

                    window.descriptionOptionsData = window.descriptionOptionsData || [];
                    const mlIdx = window.descriptionOptionsData.length;
                    window.descriptionOptionsData.push(mlOption);

                    // Agregar al DOM completo
                    const charCount = mlOption.text ? mlOption.text.length : 0;

                    const mlHtml = `
                        <div class="desc-option ${mlOption.selected ? 'selected' : ''}" data-idx="${mlIdx}" onclick="seleccionarDescripcion(this, ${mlIdx})">
                            <div class="desc-option-header">
                                <span class="desc-option-source ml">${mlOption.label}</span>
                                <div class="desc-option-check">${mlOption.selected ? '✓' : ''}</div>
                            </div>
                            <div class="desc-option-text" id="desc-text-${mlIdx}" style="white-space: pre-wrap;">${mlOption.text}</div>
                            <div class="desc-char-count">${charCount} caracteres</div>
                        </div>
                    `;
                    optionsDiv.insertAdjacentHTML('beforeend', mlHtml);

                    // Asegurar que la sección esté expandida
                    const optionDescripcion = document.getElementById('optionDescripcion');
                    if (optionDescripcion && !optionDescripcion.classList.contains('active')) {
                        optionDescripcion.classList.add('active');
                    }

                    // Actualizar badge con nuevo total
                    const badge = document.getElementById('descCountBadge');
                    if (badge) {
                        const totalOpciones = window.descriptionOptionsData.length;
                        badge.textContent = totalOpciones;
                        badge.style.display = 'inline-block';
                    }

                    // Solo auto-seleccionar ML en ALTA si NO hay descripción de BD
                    if (esAlta && !productoActual.descripcion_larga) {
                        // Deseleccionar las anteriores
                        document.querySelectorAll('.desc-option').forEach((opt, i) => {
                            if (i < mlIdx) {
                                opt.classList.remove('selected');
                                opt.querySelector('.desc-option-check').textContent = '';
                            }
                        });

                        // Seleccionar la de ML
                        const mlOptElement = document.querySelector(`.desc-option[data-idx="${mlIdx}"]`);
                        if (mlOptElement) {
                            mlOptElement.classList.add('selected');
                            mlOptElement.querySelector('.desc-option-check').textContent = '✓';
                        }

                        descripcionSeleccionada = mlIdx;

                        // Auto-activar checkbox de descripción en ALTA
                        const checkDescripcion = document.getElementById('checkDescripcion');
                        const optionDescripcion = document.getElementById('optionDescripcion');
                        if (checkDescripcion && optionDescripcion && !checkDescripcion.checked) {
                            checkDescripcion.checked = true;
                            optionDescripcion.classList.add('active');
                        }
                    }

                    addLog(`✓ Descripción encontrada en ML (${data.ml_data.encontrado_por}, ${charCount} caracteres)`, 'success');

                    // Actualizar botón global
                    actualizarBotonGlobal();
                } else {
                    // No se encontró descripción en ML
                    const optionsDiv = document.getElementById('descriptionOptions');
                    if (optionsDiv && optionsDiv.querySelector('p')) {
                        optionsDiv.innerHTML = '<p style="color: #64748b; font-size: 12px;">⚠ No se encontraron descripciones disponibles en BD, WooCommerce ni Mercado Libre.</p>';
                    }
                    addLog(`⚠ No se encontró descripción en Mercado Libre`, '');
                }
            } catch (error) {
                console.error('Error buscando descripción en ML:', error);
                const optionsDiv = document.getElementById('descriptionOptions');

                if (error.name === 'AbortError') {
                    if (optionsDiv && optionsDiv.querySelector('p')) {
                        optionsDiv.innerHTML = '<p style="color: #f59e0b; font-size: 12px;">⏱ Tiempo de espera agotado buscando en Mercado Libre</p>';
                    }
                    addLog(`⏱ Tiempo de espera agotado buscando descripción en ML`, '');
                } else {
                    if (optionsDiv && optionsDiv.querySelector('p')) {
                        optionsDiv.innerHTML = '<p style="color: #ef4444; font-size: 12px;">✗ Error buscando en Mercado Libre</p>';
                    }
                    addLog(`✗ Error buscando descripción en ML: ${error.message}`, 'error');
                }
            }
        }

        // Función para mostrar/habilitar el botón global según lo seleccionado
        function actualizarBotonGlobal() {
            const btn = document.getElementById('btnGlobalUpdate');
            if (!btn) return;

            // El botón siempre está habilitado porque precio/stock siempre está tildado
            btn.disabled = false;

            // Actualizar contador de imágenes seleccionadas
            const imagenesSeleccionadas = document.querySelectorAll('#mlImagesGrid .image-card.selected');
            const badge = document.getElementById('imgCountBadge');
            if (badge) {
                if (imagenesSeleccionadas.length > 0) {
                    badge.textContent = imagenesSeleccionadas.length;
                    badge.style.display = 'inline';
                    // Auto-tildar imágenes si hay seleccionadas
                    const checkImagenes = document.getElementById('checkImagenes');
                    if (checkImagenes && !checkImagenes.checked) {
                        checkImagenes.checked = true;
                        toggleSimpleCheck('checkImagenes');
                    }
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Función para publicar producto nuevo con datos seleccionados
        async function publicarProductoConDatos() {
            // Si NO está en WooCommerce, publicar con los datos seleccionados
            if (!wooProducto) {
                await actualizarTodoSeleccionado();
            } else {
                // Si ya está publicado, solo actualizar
                await actualizarTodoSeleccionado();
            }
        }

        // Función principal que actualiza todo lo seleccionado (o publica si es nuevo)
        async function actualizarTodoSeleccionado() {
            const btn = document.getElementById('btnGlobalUpdate');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Procesando...';

            const checkPrecioStock = document.getElementById('checkPrecioStock').checked;
            const checkImagenes = document.getElementById('checkImagenes').checked;

            const imagenesSeleccionadas = document.querySelectorAll('#mlImagesGrid .image-card.selected');
            const tieneImagenes = checkImagenes && imagenesSeleccionadas.length > 0;

            let exitoso = true;
            let actualizados = [];
            let productId = wooProducto ? wooProducto.id : null;

            try {
                // Si es producto NUEVO, primero publicarlo
                if (!wooProducto) {
                    addLog(`Publicando producto nuevo ${productoActual.sku}...`, '');

                    const response = await fetch(`${API_BASE}/product-publish.php?api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sku: productoActual.sku })
                    });

                    const data = await response.json();

                    if (data.success) {
                        productId = data.product.id;
                        const precioPublicado = data.product.regular_price;
                        addLog(`✓ Producto publicado (ID: ${productId}, Precio: $${precioPublicado})`, 'success');
                        actualizados.push('producto creado');

                        // SIEMPRE actualizar precio/stock después de publicar producto nuevo
                        // para asegurar que tenga el precio correcto de la BD
                        const precioConIva = parseFloat(productoActual.precio);
                        const precioSinIva = parseFloat(productoActual.precio_sin_iva);
                        const stock = parseInt(productoActual.stock);

                        addLog(`Verificando precio/stock: BD dice $${precioConIva.toFixed(2)}, WC tiene $${precioPublicado}`, '');

                        // Actualizar si el precio es diferente o si el checkbox está marcado
                        if (precioConIva.toFixed(2) !== precioPublicado || checkPrecioStock) {
                            addLog(`Actualizando precio a $${precioConIva.toFixed(2)} y stock a ${stock}...`, '');

                            const updateResponse = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    id: productId,
                                    regular_price: precioConIva.toFixed(2),
                                    precio_sin_iva: precioSinIva.toFixed(2),
                                    stock_quantity: stock
                                })
                            });

                            const updateData = await updateResponse.json();

                            if (updateData.success) {
                                addLog(`✓ Precio y stock actualizados correctamente`, 'success');
                                actualizados.push('precio/stock');
                            } else {
                                addLog(`✗ Error actualizando precio/stock: ${updateData.error || updateData.errors?.join(', ')}`, 'error');
                                exitoso = false;
                            }
                        }
                    } else {
                        // Verificar si el error es porque el producto ya existe
                        const errorMsg = data.error || '';
                        const esErrorDuplicado = errorMsg.includes('ya está en la tabla') ||
                                                errorMsg.includes('already exists') ||
                                                errorMsg.includes('woocommerce_rest_product_not_created');

                        if (esErrorDuplicado) {
                            // El producto existe pero no lo encontramos - intentar recuperación avanzada
                            addLog(`⚠ WooCommerce dice que el producto ya existe. Iniciando búsqueda avanzada...`, '');

                            try {
                                // Intentar búsqueda directa con WooCommerce sin filtros
                                addLog(`🔍 Buscando en WooCommerce directamente (SKU: "${productoActual.sku}")...`, '');

                                const directSearchResponse = await fetch(`${API_BASE}/product-search-direct.php?sku=${encodeURIComponent(productoActual.sku)}&api_key=${API_KEY}`);
                                const directData = await directSearchResponse.json();

                                if (directData.success && directData.product_id) {
                                    // Lo encontramos con búsqueda directa!
                                    productId = directData.product_id;

                                    addLog(`✓ Producto encontrado (ID: ${productId})`, 'success');
                                    addLog(`ℹ El producto existía con status: ${directData.status}. Continuando con actualización...`, '');

                                    // Ahora actualizar precio/stock y activarlo
                                    const precioConIva = parseFloat(productoActual.precio);
                                    const precioSinIva = parseFloat(productoActual.precio_sin_iva);
                                    const stock = parseInt(productoActual.stock);

                                    addLog(`Actualizando precio a $${precioConIva.toFixed(2)}, stock a ${stock} y activando...`, '');

                                    const updateResponse = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            id: productId,
                                            regular_price: precioConIva.toFixed(2),
                                            precio_sin_iva: precioSinIva.toFixed(2),
                                            stock_quantity: stock,
                                            status: 'publish' // Activarlo si estaba en draft/trash
                                        })
                                    });

                                    const updateData = await updateResponse.json();

                                    if (updateData.success) {
                                        addLog(`✓ Producto actualizado y activado correctamente`, 'success');
                                        actualizados.push('producto recuperado y actualizado');

                                        // Actualizar variable global para que las siguientes operaciones funcionen
                                        wooProducto = { id: productId, status: 'publish' };
                                    } else {
                                        addLog(`✗ Error actualizando: ${updateData.error || updateData.errors?.join(', ')}`, 'error');
                                        exitoso = false;
                                    }
                                } else {
                                    // No lo encontramos ni con búsqueda directa
                                    addLog(`✗ No se pudo localizar el producto con SKU "${productoActual.sku}"`, 'error');
                                    addLog(`💡 El producto podría tener un SKU ligeramente diferente en WooCommerce`, '');
                                    addLog(`💡 Buscá manualmente en WooCommerce Admin y verificá el SKU exacto`, '');
                                    exitoso = false;
                                }
                            } catch (searchError) {
                                addLog(`✗ Error en búsqueda avanzada: ${searchError.message}`, 'error');
                                exitoso = false;
                            }
                        } else {
                            // Otro tipo de error
                            addLog(`✗ Error publicando: ${data.error}`, 'error');
                            exitoso = false;
                        }
                    }
                }

                // 1. Actualizar precio y stock (SOLO para productos que YA existían)
                if (checkPrecioStock && wooProducto && productId && exitoso) {
                    const precioConIva = parseFloat(productoActual.precio);
                    const precioSinIva = parseFloat(productoActual.precio_sin_iva);
                    const stock = parseInt(productoActual.stock);

                    addLog(`Actualizando precio ($${precioConIva.toFixed(2)}) y stock (${stock})...`, '');

                    const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: productId,
                            regular_price: precioConIva.toFixed(2),
                            precio_sin_iva: precioSinIva.toFixed(2),
                            stock_quantity: stock
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        addLog(`✓ Precio y stock actualizados`, 'success');
                        actualizados.push('precio/stock');
                    } else {
                        addLog(`✗ Error actualizando precio/stock: ${data.error || data.errors?.join(', ')}`, 'error');
                        exitoso = false;
                    }
                }

                // 1.5. Actualizar dimensiones y peso si está marcado
                const checkDimensiones = document.getElementById('checkDimensiones').checked;
                if (checkDimensiones && productId && exitoso) {
                    const updateData = { id: productId };

                    if (productoActual.peso && productoActual.peso > 0) {
                        updateData.weight = productoActual.peso;
                    }

                    if (productoActual.alto && productoActual.alto > 0) {
                        updateData.alto = productoActual.alto;
                    }
                    if (productoActual.ancho && productoActual.ancho > 0) {
                        updateData.ancho = productoActual.ancho;
                    }
                    if (productoActual.profundidad && productoActual.profundidad > 0) {
                        updateData.profundidad = productoActual.profundidad;
                    }

                    addLog(`Actualizando dimensiones y peso...`, '');

                    const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(updateData)
                    });

                    const data = await response.json();

                    if (data.success) {
                        addLog(`✓ Dimensiones y peso actualizados`, 'success');
                        actualizados.push('dimensiones');
                    } else {
                        addLog(`✗ Error actualizando dimensiones: ${data.error || data.errors?.join(', ')}`, 'error');
                        exitoso = false;
                    }
                }

                // 2. Actualizar descripción si está marcado el checkbox
                const checkDescripcionMarcado = document.getElementById('checkDescripcion').checked;
                if (checkDescripcionMarcado && exitoso && productId && productoActual.descripcion_larga) {
                    addLog(`Actualizando descripción desde BD...`, '');

                    const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: productId,
                            description: productoActual.descripcion_larga,
                            short_description: productoActual.nombre
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        addLog(`✓ Descripción actualizada`, 'success');
                        actualizados.push('descripción');
                    } else {
                        addLog(`✗ Error actualizando descripción: ${data.error}`, 'error');
                        exitoso = false;
                    }
                }

                // 3. Actualizar imágenes si están seleccionadas
                if (tieneImagenes && exitoso && productId) {
                    const imagenes = Array.from(imagenesSeleccionadas).map(card => {
                        const idx = parseInt(card.dataset.idx);
                        return imagenesML[idx].url;
                    });

                    addLog(`Actualizando imágenes (${imagenes.length})...`, '');

                    const response = await fetch(`${API_BASE}/image-upload.php?api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            sku: productoActual.sku,
                            imagenes: imagenes,
                            reemplazar: true
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        addLog(`✓ Imágenes actualizadas: ${data.total_imagenes || 0}`, 'success');
                        actualizados.push(`${data.total_imagenes || 0} imágenes`);
                    } else {
                        addLog(`✗ Error actualizando imágenes: ${data.error}`, 'error');
                        exitoso = false;
                    }
                }

                // 4. Actualizar categorías si están seleccionadas
                const checkCategorias = document.getElementById('checkCategorias').checked;
                const tieneCategorias = checkCategorias && categoriasSeleccionadas.length > 0;

                if (tieneCategorias && exitoso && productId) {
                    addLog(`Actualizando categorías (${categoriasSeleccionadas.length})...`, '');

                    const response = await fetch(`${API_BASE}/categories.php?action=assign&api_key=${API_KEY}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            product_id: productId,
                            category_ids: categoriasSeleccionadas
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        addLog(`✓ Categorías actualizadas: ${data.categories.length}`, 'success');
                        actualizados.push(`${data.categories.length} categorías`);
                    } else {
                        addLog(`✗ Error actualizando categorías: ${data.error}`, 'error');
                        exitoso = false;
                    }
                }

                // 5. Recargar producto si todo salió bien
                if (exitoso && actualizados.length > 0) {
                    addLog(`✓ Actualización completa: ${actualizados.join(', ')}`, 'success');
                    setTimeout(() => buscarProducto(), 2000);
                }

            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                const textoBoton = wooProducto ? '✓ Actualizar Seleccionado' : '🚀 Publicar con Selección';
                btn.textContent = textoBoton;
            }
        }

        async function publicarProducto() {
            if (!productoActual) return;

            const precio = parseFloat(productoActual.precio);
            addLog(`Publicando ${productoActual.sku} (Precio: $${precio.toFixed(2)})...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-publish.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sku: productoActual.sku })
                });
                const data = await response.json();

                if (data.success) {
                    addLog(`✓ Publicado correctamente (ID: ${data.product.id}, Precio: $${data.product.regular_price})`, 'success');
                    buscarProducto();
                } else {
                    addLog(`✗ Error: ${data.error}`, 'error');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }


        async function activarProducto() {
            await cambiarEstado('publish');
        }

        async function desactivarProducto() {
            if (!confirm('¿Desactivar este producto de la web?')) return;
            await cambiarEstado('draft');
        }

        async function cambiarEstado(nuevoEstado) {
            if (!wooProducto) return;

            const accion = nuevoEstado === 'publish' ? 'Activando' : 'Desactivando';
            addLog(`${accion} producto ${productoActual.sku}...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: wooProducto.id, status: nuevoEstado })
                });
                const data = await response.json();

                if (data.success) {
                    const msg = nuevoEstado === 'publish' ? 'Producto activado y visible en la web' : 'Producto desactivado';
                    addLog(`✓ ${msg}`, 'success');
                    buscarProducto();
                } else {
                    addLog(`✗ Error: ${data.error}`, 'error');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        function updateStats() {
            // Cards eliminadas - función vacía
        }

        function addLog(message, type = '') {
            // Solo console.log para debug
            console.log(`[${type || 'info'}] ${message}`);
        }

        // ==========================================
        // FUNCIONES DE IMÁGENES
        // ==========================================

        let imagenesML = [];

        async function buscarImagenes() {
            if (!productoActual) return;

            const loading = document.getElementById('loadingImages');
            const wooDiv = document.getElementById('imagesWoo');
            const mlDiv = document.getElementById('imagesMl');
            const noMsg = document.getElementById('noImagesMsg');
            const btnBuscar = document.getElementById('btnBuscarImagenes');

            // Verificar que los elementos existan
            if (!loading || !wooDiv || !mlDiv || !noMsg) {
                return;
            }

            // Ocultar botón de búsqueda
            if (btnBuscar) {
                btnBuscar.style.display = 'none';
            }

            // Reset
            wooDiv.style.display = 'none';
            mlDiv.style.display = 'none';
            noMsg.style.display = 'none';
            loading.style.display = 'block';
            imagenesML = [];

            try {
                // Timeout de 10 segundos para imágenes
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);

                const response = await fetch(`${API_BASE}/image-search.php?sku=${encodeURIComponent(productoActual.sku)}&api_key=${API_KEY}`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                const data = await response.json();

                loading.style.display = 'none';

                if (!data.success) {
                    noMsg.style.display = 'block';
                    noMsg.textContent = data.error || 'Error al buscar imágenes';
                    return;
                }

                // Mostrar imágenes de WooCommerce
                if (data.woocommerce && data.woocommerce.tiene_imagenes) {
                    const wooGrid = document.getElementById('wooImagesGrid');
                    if (wooGrid) {
                        wooGrid.innerHTML = data.woocommerce.imagenes.map(img => `
                            <div class="image-card">
                                <img src="${appendImageVersion(img.src)}" alt="${img.name || 'Imagen'}">
                                <div class="img-source">WooCommerce</div>
                            </div>
                        `).join('');
                        wooDiv.style.display = 'block';

                        // Expandir sección de imágenes
                        const optionImagenes = document.getElementById('optionImagenes');
                        if (optionImagenes && !optionImagenes.classList.contains('active')) {
                            optionImagenes.classList.add('active');
                        }

                        // Actualizar badge
                        const imgBadge = document.getElementById('imgCountBadge');
                        if (imgBadge) {
                            imgBadge.textContent = data.woocommerce.cantidad_imagenes;
                            imgBadge.style.display = 'inline-block';
                        }
                    }
                }

                // Mostrar imágenes de ML
                const ml = data.imagenes.mercadolibre;
                if (ml && ml.imagenes && ml.imagenes.length > 0) {
                    imagenesML = ml.imagenes;

                    const mlInfo = document.getElementById('mlInfo');
                    if (mlInfo) {
                        mlInfo.textContent = `Encontrado por: ${ml.encontrado_por} | Producto: ${ml.producto.nombre}`;
                    }

                    const mlGrid = document.getElementById('mlImagesGrid');
                    if (!mlGrid) return;

                    mlGrid.innerHTML = ml.imagenes.map((img, idx) => `
                        <div class="image-card ${esAlta ? 'selected' : ''}" data-idx="${idx}" onclick="toggleImageSelect(this)">
                            <img src="${appendImageVersion(img.url)}" alt="Imagen ${idx + 1}">
                            <div class="img-check">${esAlta ? '✓' : ''}</div>
                            <div class="img-source">ML</div>
                        </div>
                    `).join('');

                    mlDiv.style.display = 'block';

                    // Expandir sección de imágenes automáticamente
                    const optionImagenes = document.getElementById('optionImagenes');
                    if (optionImagenes && !optionImagenes.classList.contains('active')) {
                        optionImagenes.classList.add('active');
                    }

                    // Actualizar badge de contador
                    const imgBadge = document.getElementById('imgCountBadge');
                    if (imgBadge) {
                        imgBadge.textContent = ml.imagenes.length;
                        imgBadge.style.display = 'inline-block';
                    }

                    // Auto-seleccionar solo si es ALTA
                    const selectAllMl = document.getElementById('selectAllMl');
                    if (selectAllMl) {
                        selectAllMl.checked = esAlta;
                    }

                    if (esAlta) {
                        // Marcar todas
                        document.querySelectorAll('#mlImagesGrid .image-card').forEach(card => {
                            card.classList.add('selected');
                        });

                        // Auto-activar checkbox de imágenes en ALTA
                        const checkImagenes = document.getElementById('checkImagenes');
                        if (checkImagenes && !checkImagenes.checked) {
                            checkImagenes.checked = true;
                        }
                    } else {
                        // Desmarcar todas en modificación
                        document.querySelectorAll('#mlImagesGrid .image-card').forEach(card => {
                            card.classList.remove('selected');
                        });
                    }
                    updateImageCount();

                    addLog(`✓ Encontradas ${ml.imagenes.length} imágenes en ML`, 'success');
                } else if (!data.woocommerce?.tiene_imagenes) {
                    noMsg.style.display = 'block';
                }

            } catch (error) {
                loading.style.display = 'none';
                noMsg.style.display = 'block';

                if (error.name === 'AbortError') {
                    noMsg.textContent = '⏱ Tiempo de espera agotado buscando imágenes';
                    addLog('⏱ Tiempo de espera agotado buscando imágenes', '');
                } else {
                    noMsg.textContent = 'Error: ' + error.message;
                    addLog('✗ Error buscando imágenes: ' + error.message, 'error');
                }
            }
        }

        function toggleImageSelect(card) {
            card.classList.toggle('selected');
            updateImageCount();

            // Auto-activar checkbox de imágenes si se selecciona alguna
            const imagenesSeleccionadas = document.querySelectorAll('#mlImagesGrid .image-card.selected');
            if (imagenesSeleccionadas.length > 0) {
                const checkImagenes = document.getElementById('checkImagenes');
                const optionImagenes = document.getElementById('optionImagenes');
                if (checkImagenes && optionImagenes && !checkImagenes.checked) {
                    checkImagenes.checked = true;
                    optionImagenes.classList.add('active');
                }
            }
        }

        function toggleSelectAll() {
            const checked = document.getElementById('selectAllMl').checked;
            const cards = document.querySelectorAll('#mlImagesGrid .image-card');
            cards.forEach(card => {
                if (checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            updateImageCount();
        }

        function updateImageCount() {
            const selected = document.querySelectorAll('#mlImagesGrid .image-card.selected');
            const count = selected.length;
            const countEl = document.getElementById('selectedCount');
            if (countEl) {
                countEl.textContent = `${count} seleccionada${count !== 1 ? 's' : ''}`;
            }

            // Actualizar estado del botón global
            actualizarBotonGlobal();
        }


        // ==========================================
        // FUNCIONES DE DIMENSIONES (para updateSection)
        // ==========================================

        async function llenarDimensiones() {
            // Verificar si faltan dimensiones en BD
            const faltaPeso = !productoActual.peso || productoActual.peso <= 0;
            const faltaDimensiones = (!productoActual.alto || productoActual.alto <= 0) &&
                                      (!productoActual.ancho || productoActual.ancho <= 0) &&
                                      (!productoActual.profundidad || productoActual.profundidad <= 0);

            // Verificar si tiene alguna dimensión de BD
            const tieneDimensionesBD = productoActual.peso || productoActual.alto || productoActual.ancho || productoActual.profundidad;

            // Mostrar dimensiones de BD
            document.getElementById('pesoSistema').textContent = productoActual.peso ? productoActual.peso + ' kg' : '-';
            document.getElementById('altoSistema').textContent = productoActual.alto ? productoActual.alto + ' cm' : '-';
            document.getElementById('anchoSistema').textContent = productoActual.ancho ? productoActual.ancho + ' cm' : '-';
            document.getElementById('profundidadSistema').textContent = productoActual.profundidad ? productoActual.profundidad + ' cm' : '-';

            // Si es ALTA y ya tiene dimensiones de BD, auto-marcar
            if (esAlta && tieneDimensionesBD) {
                const checkDimensiones = document.getElementById('checkDimensiones');
                const optionDimensiones = document.getElementById('optionDimensiones');
                if (checkDimensiones && optionDimensiones && !checkDimensiones.checked) {
                    checkDimensiones.checked = true;
                    optionDimensiones.classList.add('active');
                    addLog('✓ Dimensiones de BD marcadas para publicación', 'success');
                }
            }

            // Si faltan dimensiones o peso Y es producto ALTA, buscar en ML
            if ((faltaPeso || faltaDimensiones) && esAlta) {
                try {
                    addLog('Buscando dimensiones en Mercado Libre...', '');

                    // Timeout de 8 segundos
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 8000);

                    const response = await fetch(`${API_BASE}/product-search.php?sku=${encodeURIComponent(productoActual.sku)}&search_ml=true&api_key=${API_KEY}`, {
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);

                    const data = await response.json();

                    if (data.success && data.ml_data) {
                        dimensionesML = data.ml_data;

                        // Completar dimensiones faltantes con datos de ML
                        const mlBadge = '<span style="background: #3b82f6; color: white; padding: 1px 6px; border-radius: 8px; font-size: 9px; margin-left: 4px; font-weight: 600;">ML</span>';

                        if (faltaPeso && dimensionesML.peso) {
                            productoActual.peso = dimensionesML.peso;
                            document.getElementById('pesoSistema').innerHTML = dimensionesML.peso + ' kg ' + mlBadge;
                        }

                        if (faltaDimensiones) {
                            if (dimensionesML.alto && (!productoActual.alto || productoActual.alto <= 0)) {
                                productoActual.alto = dimensionesML.alto;
                                document.getElementById('altoSistema').innerHTML = dimensionesML.alto + ' cm ' + mlBadge;
                            }
                            if (dimensionesML.ancho && (!productoActual.ancho || productoActual.ancho <= 0)) {
                                productoActual.ancho = dimensionesML.ancho;
                                document.getElementById('anchoSistema').innerHTML = dimensionesML.ancho + ' cm ' + mlBadge;
                            }
                            if (dimensionesML.profundidad && (!productoActual.profundidad || productoActual.profundidad <= 0)) {
                                productoActual.profundidad = dimensionesML.profundidad;
                                document.getElementById('profundidadSistema').innerHTML = dimensionesML.profundidad + ' cm ' + mlBadge;
                            }
                        }

                        // Si encontró dimensiones en ML y es ALTA, auto-marcar
                        if ((dimensionesML.peso || dimensionesML.alto || dimensionesML.ancho || dimensionesML.profundidad) && esAlta) {
                            const checkDimensiones = document.getElementById('checkDimensiones');
                            const optionDimensiones = document.getElementById('optionDimensiones');
                            if (checkDimensiones && optionDimensiones && !checkDimensiones.checked) {
                                checkDimensiones.checked = true;
                                optionDimensiones.classList.add('active');
                                addLog('✓ Dimensiones de ML marcadas para publicación', 'success');
                            }
                        }

                        // Si no es ALTA, simplemente informar
                        if (!esAlta) {
                            addLog(`ℹ Dimensiones encontradas en ML (${dimensionesML.encontrado_por})`, '');
                        }

                    } else {
                        if (faltaPeso || faltaDimensiones) {
                            addLog(`⚠ No se encontraron dimensiones en ML`, '');
                        }
                    }
                } catch (error) {
                    console.error('Error buscando dimensiones en ML:', error);
                    if (error.name === 'AbortError') {
                        addLog(`⏱ Tiempo de espera agotado buscando en ML`, '');
                    } else {
                        addLog(`⚠ Error buscando en ML: ${error.message}`, '');
                    }
                }
            }
        }

        // ==========================================
        // FUNCIONES DE CATEGORÍAS
        // ==========================================

        let categoriasDisponibles = [];
        let categoriasSeleccionadas = [];
        let categoriasSugeridas = [];

        function sugerirCategorias(nombreProducto) {
            const nombre = nombreProducto.toLowerCase();
            const sugerencias = [];

            // Palabras clave para detectar categorías relevantes
            const keywords = {
                'notebook': ['notebooks', 'computadoras', 'laptop', 'portatiles'],
                'mouse': ['mouse', 'mouses', 'periféricos', 'accesorios computadora'],
                'teclado': ['teclados', 'periféricos', 'accesorios computadora'],
                'monitor': ['monitores', 'pantallas', 'displays'],
                'impresora': ['impresoras', 'impresión', 'oficina'],
                'cartucho': ['cartuchos', 'tinta', 'impresión', 'consumibles'],
                'toner': ['toners', 'impresión', 'consumibles'],
                'tablet': ['tablets', 'tabletas', 'móviles'],
                'celular': ['celulares', 'smartphones', 'móviles', 'teléfonos'],
                'cable': ['cables', 'conectividad', 'accesorios'],
                'auricular': ['auriculares', 'audio', 'headphones'],
                'pendrive': ['pendrives', 'usb', 'almacenamiento'],
                'disco': ['discos', 'almacenamiento', 'hdd', 'ssd'],
                'memoria': ['memorias', 'ram', 'componentes'],
                'procesador': ['procesadores', 'cpu', 'componentes'],
                'placa': ['placas', 'componentes', 'mother'],
                'fuente': ['fuentes', 'componentes', 'alimentación'],
                'gabinete': ['gabinetes', 'case', 'componentes'],
                'silla': ['sillas', 'muebles', 'oficina', 'gamer'],
                'escritorio': ['escritorios', 'muebles', 'oficina']
            };

            // Buscar palabras clave en el nombre del producto
            for (const [palabra, categorias] of Object.entries(keywords)) {
                if (nombre.includes(palabra)) {
                    categorias.forEach(catNombre => {
                        const cat = categoriasDisponibles.find(c =>
                            c.name.toLowerCase().includes(catNombre) ||
                            c.slug.toLowerCase().includes(catNombre)
                        );
                        if (cat && !sugerencias.find(s => s.id === cat.id)) {
                            sugerencias.push(cat);
                        }
                    });
                }
            }

            return sugerencias.slice(0, 5); // Máximo 5 sugerencias
        }

        function filtrarCategorias(query) {
            const items = document.querySelectorAll('#categoriesList .category-item');
            const searchLower = query.toLowerCase();

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchLower)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        async function cargarCategorias() {
            const categoriesList = document.getElementById('categoriesList');
            const currentCategoriesDiv = document.getElementById('currentCategories');
            const currentCategoriesList = document.getElementById('currentCategoriesList');
            const suggestedCategoriesDiv = document.getElementById('suggestedCategories');
            const suggestedCategoriesList = document.getElementById('suggestedCategoriesList');

            try {
                // Cargar todas las categorías disponibles
                const response = await fetch(`${API_BASE}/categories.php?action=list&api_key=${API_KEY}`);
                const data = await response.json();

                if (data.success && data.categories) {
                    categoriasDisponibles = data.categories;

                    // Sugerir categorías basadas en el nombre del producto
                    categoriasSugeridas = sugerirCategorias(productoActual.nombre);

                    // Si el producto está publicado, cargar sus categorías actuales
                    if (wooProducto && wooProducto.id) {
                        const catResponse = await fetch(`${API_BASE}/categories.php?action=get_product_categories&product_id=${wooProducto.id}&api_key=${API_KEY}`);
                        const catData = await catResponse.json();

                        if (catData.success && catData.categories && catData.categories.length > 0) {
                            // Guardar categorías actuales
                            categoriasSeleccionadas = catData.categories.map(c => c.id);

                            // Mostrar categorías actuales
                            currentCategoriesDiv.style.display = 'block';
                            currentCategoriesList.innerHTML = catData.categories.map(cat =>
                                `<span style="background: #22c55e; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px;">${cat.name}</span>`
                            ).join('');

                            // Actualizar badge
                            const badge = document.getElementById('catCountBadge');
                            if (badge) {
                                badge.textContent = catData.categories.length;
                                badge.style.display = 'inline-block';
                            }
                        }
                    }

                    // Mostrar categorías sugeridas
                    if (categoriasSugeridas.length > 0) {
                        suggestedCategoriesDiv.style.display = 'block';
                        suggestedCategoriesList.innerHTML = categoriasSugeridas.map(cat => {
                            const isSelected = categoriasSeleccionadas.includes(cat.id);
                            return `
                                <button onclick="toggleCategoria(${cat.id}); event.preventDefault();"
                                        style="background: ${isSelected ? '#22c55e' : '#1e293b'};
                                               color: ${isSelected ? 'white' : '#94a3b8'};
                                               border: 1px solid ${isSelected ? '#22c55e' : '#334155'};
                                               padding: 6px 12px;
                                               border-radius: 12px;
                                               font-size: 12px;
                                               cursor: pointer;
                                               transition: all 0.2s;">
                                    ${isSelected ? '✓ ' : ''}${cat.name}
                                </button>
                            `;
                        }).join('');

                        // Auto-seleccionar primera sugerencia en ALTA si no tiene categorías
                        if (esAlta && categoriasSeleccionadas.length === 0 && categoriasSugeridas.length > 0) {
                            toggleCategoria(categoriasSugeridas[0].id);
                            addLog(`✓ Categoría sugerida: "${categoriasSugeridas[0].name}"`, 'success');
                        }
                    }

                    // Mostrar lista completa de categorías
                    categoriesList.innerHTML = categoriasDisponibles.map(cat => {
                        const isSelected = categoriasSeleccionadas.includes(cat.id);
                        const isParent = cat.parent === 0;
                        const isSuggested = categoriasSugeridas.find(s => s.id === cat.id);

                        return `
                            <div class="category-item" style="padding: 8px; border-bottom: 1px solid #334155; cursor: pointer; display: flex; justify-content: space-between; align-items: center; ${isParent ? 'font-weight: 600;' : 'padding-left: 20px;'}${isSuggested ? ' background: rgba(34, 197, 94, 0.1);' : ''}" onclick="toggleCategoria(${cat.id})">
                                <span style="color: ${isParent ? '#e2e8f0' : '#94a3b8'};">${isSuggested ? '✨ ' : ''}${cat.name} <span style="color: #64748b; font-size: 11px;">(${cat.count})</span></span>
                                <input type="checkbox" ${isSelected ? 'checked' : ''} id="cat_${cat.id}" onclick="event.stopPropagation(); toggleCategoria(${cat.id})" style="cursor: pointer;">
                            </div>
                        `;
                    }).join('');

                    updateCategoriasCount();

                } else {
                    categoriesList.innerHTML = '<p style="color: #ef4444; padding: 10px;">Error cargando categorías</p>';
                }

            } catch (error) {
                console.error('Error cargando categorías:', error);
                categoriesList.innerHTML = '<p style="color: #ef4444; padding: 10px;">Error: ' + error.message + '</p>';
            }
        }

        function toggleCategoria(catId) {
            const checkbox = document.getElementById(`cat_${catId}`);

            // Toggle selección
            const isCurrentlySelected = categoriasSeleccionadas.includes(catId);

            if (isCurrentlySelected) {
                categoriasSeleccionadas = categoriasSeleccionadas.filter(id => id !== catId);
                if (checkbox) checkbox.checked = false;
            } else {
                if (!categoriasSeleccionadas.includes(catId)) {
                    categoriasSeleccionadas.push(catId);
                }
                if (checkbox) checkbox.checked = true;
            }

            // Actualizar botones de sugerencias
            const suggestedButtons = document.querySelectorAll('#suggestedCategoriesList button');
            suggestedButtons.forEach(btn => {
                const btnCatId = parseInt(btn.getAttribute('onclick').match(/toggleCategoria\((\d+)\)/)[1]);
                const isSelected = categoriasSeleccionadas.includes(btnCatId);

                btn.style.background = isSelected ? '#22c55e' : '#1e293b';
                btn.style.color = isSelected ? 'white' : '#94a3b8';
                btn.style.borderColor = isSelected ? '#22c55e' : '#334155';
                btn.textContent = (isSelected ? '✓ ' : '') + categoriasDisponibles.find(c => c.id === btnCatId)?.name;
            });

            updateCategoriasCount();

            // Auto-activar checkbox de categorías si hay alguna seleccionada
            if (categoriasSeleccionadas.length > 0) {
                const checkCategorias = document.getElementById('checkCategorias');
                const optionCategorias = document.getElementById('optionCategorias');
                if (checkCategorias && optionCategorias && !checkCategorias.checked) {
                    checkCategorias.checked = true;
                    optionCategorias.classList.add('active');
                }
            }

            actualizarBotonGlobal();
        }

        function updateCategoriasCount() {
            const countEl = document.getElementById('selectedCategoriesCount');
            if (countEl) {
                countEl.textContent = categoriasSeleccionadas.length;
            }
        }

        // Init
        addLog('Sistema listo', 'success');
    </script>
</body>
</html>
