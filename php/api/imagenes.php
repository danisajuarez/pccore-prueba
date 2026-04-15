<?php
/**
 * Panel de Administración de Imágenes
 * Busca imágenes en ML y las sube a WooCommerce
 */
session_start();

// Cargar configuración sin headers JSON
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Detectar cliente
function getClienteId() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
        return strtolower($matches[1]);
    }
    if (isset($_GET['cliente'])) {
        return strtolower($_GET['cliente']);
    }
    return 'pccore';
}

$CLIENTE_ID = getClienteId();

// Verificar sesión
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /api/login.php');
    exit();
}

// API Key para las llamadas
$API_KEY = $CLIENTE_ID . '-sync-2024';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Imágenes - <?= strtoupper($CLIENTE_ID) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        h1 {
            font-size: 1.8rem;
            color: #00d4ff;
        }

        .nav-links a {
            color: #aaa;
            text-decoration: none;
            margin-left: 20px;
        }

        .nav-links a:hover {
            color: #00d4ff;
        }

        .search-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
        }

        .search-form input {
            flex: 1;
            padding: 15px 20px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 1rem;
        }

        .search-form input::placeholder {
            color: #888;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,212,255,0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #00cc66, #009944);
            color: #fff;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,204,102,0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .result-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 25px;
            display: none;
        }

        .product-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-item label {
            display: block;
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 5px;
        }

        .info-item span {
            font-size: 1rem;
            color: #fff;
        }

        .images-section h3 {
            margin-bottom: 20px;
            color: #00d4ff;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .image-card {
            position: relative;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }

        .image-card:hover {
            transform: scale(1.02);
        }

        .image-card.selected {
            border: 3px solid #00d4ff;
        }

        .image-card img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            background: #fff;
        }

        .image-card .checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background: rgba(0,0,0,0.5);
            border: 2px solid #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-card.selected .checkbox {
            background: #00d4ff;
            border-color: #00d4ff;
        }

        .image-card.selected .checkbox::after {
            content: '✓';
            color: #fff;
            font-weight: bold;
        }

        .image-source {
            padding: 10px;
            font-size: 0.75rem;
            color: #888;
        }

        .actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .actions .info {
            color: #888;
            font-size: 0.9rem;
        }

        .loading {
            text-align: center;
            padding: 50px;
        }

        .loading .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: #00d4ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(0,204,102,0.2);
            border: 1px solid #00cc66;
            color: #00cc66;
        }

        .alert-error {
            background: rgba(255,77,77,0.2);
            border: 1px solid #ff4d4d;
            color: #ff4d4d;
        }

        .alert-info {
            background: rgba(0,212,255,0.2);
            border: 1px solid #00d4ff;
            color: #00d4ff;
        }

        .woo-images {
            background: rgba(0,153,204,0.1);
            border: 1px solid rgba(0,153,204,0.3);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .woo-images h4 {
            color: #00d4ff;
            margin-bottom: 15px;
        }

        .select-all-container {
            margin-bottom: 15px;
        }

        .select-all-container label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #aaa;
        }

        .select-all-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🖼️ Administración de Imágenes</h1>
            <nav class="nav-links">
                <a href="/api/admin-productos.php">← Volver a Productos</a>
                <a href="/">Panel Principal</a>
                <a href="/api/logout.php">Cerrar Sesión</a>
            </nav>
        </header>

        <div class="search-box">
            <form class="search-form" id="searchForm">
                <input type="text" id="skuInput" placeholder="Ingrese SKU del producto..." autofocus>
                <button type="submit" class="btn btn-primary">🔍 Buscar Imágenes</button>
            </form>
        </div>

        <div id="alertContainer"></div>

        <div class="result-box" id="resultBox">
            <div class="loading" id="loading" style="display: none;">
                <div class="spinner"></div>
                <p>Buscando imágenes...</p>
            </div>

            <div id="resultContent"></div>
        </div>
    </div>

    <script>
        const API_KEY = '<?= $API_KEY ?>';

        document.getElementById('searchForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const sku = document.getElementById('skuInput').value.trim();
            if (!sku) return;

            await buscarImagenes(sku);
        });

        async function buscarImagenes(sku) {
            const resultBox = document.getElementById('resultBox');
            const loading = document.getElementById('loading');
            const resultContent = document.getElementById('resultContent');

            resultBox.style.display = 'block';
            loading.style.display = 'block';
            resultContent.innerHTML = '';
            clearAlerts();

            try {
                const response = await fetch(`/api/image-search.php?sku=${encodeURIComponent(sku)}&api_key=${API_KEY}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();

                loading.style.display = 'none';

                if (!data.success) {
                    showAlert('error', data.error || 'Error al buscar imágenes');
                    return;
                }

                renderResult(data);
            } catch (error) {
                loading.style.display = 'none';
                showAlert('error', 'Error de conexión: ' + error.message);
            }
        }

        function renderResult(data) {
            const resultContent = document.getElementById('resultContent');
            const articulo = data.articulo;
            const woo = data.woocommerce;
            const ml = data.imagenes.mercadolibre;

            let html = `
                <div class="product-info">
                    <div class="info-item">
                        <label>SKU</label>
                        <span>${articulo.sku}</span>
                    </div>
                    <div class="info-item">
                        <label>Nombre</label>
                        <span>${articulo.nombre}</span>
                    </div>
                    <div class="info-item">
                        <label>Part Number</label>
                        <span>${articulo.part_number || '-'}</span>
                    </div>
                    <div class="info-item">
                        <label>Estado WooCommerce</label>
                        <span>${woo ? (woo.tiene_imagenes ? `✅ ${woo.cantidad_imagenes} imagen(es)` : '⚠️ Sin imágenes') : '❌ No publicado'}</span>
                    </div>
                </div>
            `;

            // Mostrar imágenes actuales en WooCommerce
            if (woo && woo.tiene_imagenes) {
                html += `
                    <div class="woo-images">
                        <h4>📦 Imágenes actuales en WooCommerce</h4>
                        <div class="images-grid">
                            ${woo.imagenes.map(img => `
                                <div class="image-card">
                                    <img src="${img.src}" alt="${img.name}">
                                    <div class="image-source">WooCommerce</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Mostrar imágenes de Mercado Libre
            if (ml && ml.imagenes.length > 0) {
                html += `
                    <div class="images-section">
                        <h3>🛒 Imágenes encontradas en Mercado Libre</h3>
                        <p style="color: #888; margin-bottom: 15px;">
                            Encontrado por: <strong>${ml.encontrado_por}</strong> |
                            Producto: <strong>${ml.producto.nombre}</strong>
                        </p>

                        <div class="select-all-container">
                            <label>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                Seleccionar todas (${ml.imagenes.length} imágenes)
                            </label>
                        </div>

                        <div class="images-grid" id="mlImages">
                            ${ml.imagenes.map((img, idx) => `
                                <div class="image-card" data-url="${img.url}" onclick="toggleImage(this)">
                                    <img src="${img.url}" alt="Imagen ${idx + 1}">
                                    <div class="checkbox"></div>
                                    <div class="image-source">ML - ${img.width || '?'}x${img.height || '?'}</div>
                                </div>
                            `).join('')}
                        </div>

                        <div class="actions">
                            <button class="btn btn-success" onclick="subirImagenes('${articulo.sku}', false)" id="btnSubir" disabled>
                                ➕ Agregar seleccionadas a WooCommerce
                            </button>
                            <button class="btn btn-primary" onclick="subirImagenes('${articulo.sku}', true)" id="btnReemplazar" disabled>
                                🔄 Reemplazar todas las imágenes
                            </button>
                            <span class="info" id="selectedCount">0 imágenes seleccionadas</span>
                        </div>
                    </div>
                `;
            } else if (!data.imagenes.sige) {
                html += `
                    <div class="alert alert-info">
                        No se encontraron imágenes en Mercado Libre para este producto.
                        Intente buscar manualmente con otro término.
                    </div>
                `;
            }

            resultContent.innerHTML = html;

            // Si no está en WooCommerce, mostrar mensaje
            if (!woo) {
                showAlert('info', 'Este producto no está publicado en WooCommerce. Primero debe publicarlo desde el panel de productos.');
            }
        }

        function toggleImage(card) {
            card.classList.toggle('selected');
            updateSelectedCount();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            const cards = document.querySelectorAll('#mlImages .image-card');
            cards.forEach(card => {
                if (selectAll) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('#mlImages .image-card.selected');
            const count = selected.length;
            document.getElementById('selectedCount').textContent = `${count} imagen(es) seleccionada(s)`;
            document.getElementById('btnSubir').disabled = count === 0;
            document.getElementById('btnReemplazar').disabled = count === 0;
        }

        async function subirImagenes(sku, reemplazar) {
            const selected = document.querySelectorAll('#mlImages .image-card.selected');
            if (selected.length === 0) {
                showAlert('error', 'Seleccione al menos una imagen');
                return;
            }

            const imagenes = Array.from(selected).map(card => card.dataset.url);

            const btn = reemplazar ? document.getElementById('btnReemplazar') : document.getElementById('btnSubir');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Subiendo...';

            try {
                const response = await fetch(`/api/image-upload.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sku, imagenes, reemplazar })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `✅ ${data.message} - ${data.producto.imagenes.length} imagen(es) en total`);
                    // Recargar para ver los cambios
                    setTimeout(() => buscarImagenes(sku), 1500);
                } else {
                    showAlert('error', data.error || 'Error al subir imágenes');
                }
            } catch (error) {
                showAlert('error', 'Error de conexión: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        }

        function clearAlerts() {
            document.getElementById('alertContainer').innerHTML = '';
        }
    </script>
</body>
</html>
