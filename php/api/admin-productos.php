<?php
require_once __DIR__ . '/../config.php';
checkSession();

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(strtoupper($CLIENTE_ID)) ?> - Admin Productos</title>
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
            padding: 12px 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #334155;
            margin-bottom: 12px;
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
            grid-template-columns: 1fr 380px;
            gap: 16px;
        }

        .search-section {
            background: #1e293b;
            border-radius: 8px;
            padding: 14px 16px;
            border: 1px solid #334155;
        }

        .search-section h2 {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            font-size: 14px;
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
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
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

        .log-section {
            background: #1e293b;
            border-radius: 8px;
            padding: 14px 16px;
            border: 1px solid #334155;
            height: fit-content;
        }

        .log-section h2 {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .logs {
            background: #0f172a;
            border-radius: 6px;
            padding: 10px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 11px;
        }

        .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid #1e293b;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-entry.success {
            color: #22c55e;
        }

        .log-entry.error {
            color: #ef4444;
        }

        .log-entry .time {
            color: #64748b;
            margin-right: 8px;
        }

        /* Producto info */
        .product-info {
            display: none;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #334155;
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
            <div class="logo"><?= htmlspecialchars(strtoupper($CLIENTE_ID)) ?> <span style="font-size: 10px; color: #64748b; font-weight: normal;">Admin Productos</span></div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="nav-links">
                    <a href="/">Sincronizador</a>
                    <a href="/api/admin-productos.php" class="active">Productos</a>
                    <a href="/api/logout.php" class="logout">Salir</a>
                </div>
                <div class="status" id="statusIndicator">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="statusText"><?= htmlspecialchars($_SESSION['user_nombre'] ?? $_SESSION['user']) ?></span>
                </div>
            </div>
        </header>

        <div class="cards">
            <div class="card">
                <h2>📦 Productos Buscados</h2>
                <div class="card-value" id="totalBuscados">0</div>
            </div>
            <div class="card">
                <h2>✅ Publicados</h2>
                <div class="card-value success" id="totalPublicados">0</div>
            </div>
            <div class="card">
                <h2>⏳ Sin Publicar</h2>
                <div class="card-value warning" id="totalSinPublicar">0</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="search-section">
                <h2>🔍 Buscar Producto por SKU</h2>
                <div class="search-box">
                    <input type="text" id="skuInput" placeholder="Ingresá el SKU del producto..." onkeypress="if(event.key==='Enter')buscarProducto()">
                    <button onclick="buscarProducto()" id="searchBtn">Buscar</button>
                </div>

                <div class="product-info" id="productInfo">
                    <div class="product-header">
                        <div>
                            <div class="product-name" id="prodNombre">-</div>
                            <div class="product-sku">SKU: <span id="prodSku">-</span></div>
                        </div>
                        <span class="status-badge" id="prodStatus">-</span>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Precio con IVA</div>
                            <div class="info-value price" id="prodPrecio">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Precio sin IVA</div>
                            <div class="info-value" id="prodPrecioSinIva">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Stock</div>
                            <div class="info-value" id="prodStock">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Peso</div>
                            <div class="info-value" id="prodPeso">-</div>
                        </div>
                        <div class="info-item full">
                            <div class="info-label">Dimensiones (Alto x Ancho x Prof)</div>
                            <div class="info-value" id="prodDimensiones">-</div>
                        </div>
                    </div>

                    <div class="attributes-section" id="attrSection">
                        <div class="info-label" style="margin-bottom: 8px;">Atributos</div>
                        <div class="attributes-grid" id="prodAtributos"></div>
                    </div>

                    <div class="product-actions" id="productActions"></div>
                </div>
            </div>

            <div class="log-section">
                <h2>Registro de Actividad</h2>
                <div class="logs" id="logsContainer">
                    <div class="log-entry">
                        <span class="time">--:--:--</span>
                        Esperando búsqueda...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?= API_KEY ?>';
        const API_BASE = '/api';

        let productoActual = null;
        let wooProducto = null;
        let stats = { buscados: 0, publicados: 0, sinPublicar: 0 };

        // Buscar producto
        async function buscarProducto() {
            const sku = document.getElementById('skuInput').value.trim();
            if (!sku) {
                addLog('Ingresá un SKU para buscar', 'error');
                return;
            }

            const btn = document.getElementById('searchBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Buscando...';

            document.getElementById('productInfo').classList.remove('visible');
            addLog(`Buscando SKU: ${sku}...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-search.php?sku=${encodeURIComponent(sku)}&api_key=${API_KEY}`);
                const data = await response.json();

                btn.disabled = false;
                btn.textContent = 'Buscar';

                if (!data.success) {
                    addLog(`✗ ${data.error || 'Producto no encontrado'}`, 'error');
                    return;
                }

                productoActual = data.producto;
                wooProducto = data.woo_producto;

                stats.buscados++;
                if (wooProducto) {
                    stats.publicados++;
                } else {
                    stats.sinPublicar++;
                }
                updateStats();

                mostrarProducto();
                addLog(`✓ Producto encontrado: ${productoActual.nombre}`, 'success');

            } catch (error) {
                btn.disabled = false;
                btn.textContent = 'Buscar';
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        function mostrarProducto() {
            document.getElementById('prodNombre').textContent = productoActual.nombre;
            document.getElementById('prodSku').textContent = productoActual.sku;
            document.getElementById('prodPrecio').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR');
            document.getElementById('prodPrecioSinIva').textContent = '$ ' + Number(productoActual.precio_sin_iva).toLocaleString('es-AR');
            document.getElementById('prodStock').textContent = productoActual.stock + ' unidades';
            document.getElementById('prodPeso').textContent = productoActual.peso ? productoActual.peso + ' kg' : 'No especificado';

            const dims = [];
            if (productoActual.alto) dims.push(productoActual.alto);
            if (productoActual.ancho) dims.push(productoActual.ancho);
            if (productoActual.profundidad) dims.push(productoActual.profundidad);
            document.getElementById('prodDimensiones').textContent = dims.length === 3 ? dims.join(' x ') + ' cm' : 'No especificadas';

            // Atributos
            const attrSection = document.getElementById('attrSection');
            const attrDiv = document.getElementById('prodAtributos');
            if (productoActual.atributos && productoActual.atributos.length > 0) {
                attrDiv.innerHTML = productoActual.atributos.map(a =>
                    `<div class="attr-item"><div class="attr-name">${a.nombre}</div><div class="attr-value">${a.valor}</div></div>`
                ).join('');
                attrSection.classList.add('visible');
            } else {
                attrSection.classList.remove('visible');
            }

            // Estado y acciones
            const statusEl = document.getElementById('prodStatus');
            const actionsEl = document.getElementById('productActions');

            if (wooProducto) {
                if (wooProducto.status === 'publish') {
                    statusEl.className = 'status-badge status-publish';
                    statusEl.textContent = 'Publicado';
                    actionsEl.innerHTML = `
                        <button onclick="sincronizarTodo()">🔄 Sincronizar Todo</button>
                        <button class="secondary" onclick="sincronizarProducto()">💰 Precio/Stock</button>
                        <button class="warning" onclick="desactivarProducto()">⏸️ Desactivar</button>
                        <a href="${wooProducto.permalink}" target="_blank" class="link-btn">🌐 Ver en Web</a>
                    `;
                } else {
                    statusEl.className = 'status-badge status-draft';
                    statusEl.textContent = 'Desactivado';
                    actionsEl.innerHTML = `
                        <button class="success" onclick="activarProducto()">▶️ Activar</button>
                        <button onclick="sincronizarTodo()">🔄 Sincronizar Todo</button>
                    `;
                }
            } else {
                statusEl.className = 'status-badge status-not-in-woo';
                statusEl.textContent = 'No publicado';
                actionsEl.innerHTML = `
                    <button class="success" onclick="publicarProducto()">🚀 Publicar en WooCommerce</button>
                `;
            }

            document.getElementById('productInfo').classList.add('visible');
        }

        async function publicarProducto() {
            if (!productoActual) return;
            if (!confirm(`¿Publicar "${productoActual.nombre}" en WooCommerce?`)) return;

            addLog(`Publicando ${productoActual.sku}...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-publish.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sku: productoActual.sku })
                });
                const data = await response.json();

                if (data.success) {
                    addLog(`✓ Publicado OK (ID: ${data.product.id})`, 'success');
                    buscarProducto();
                } else {
                    addLog(`✗ Error: ${data.error}`, 'error');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        async function sincronizarTodo() {
            if (!wooProducto || !productoActual) return;

            addLog(`Sincronizando todo para ${productoActual.sku}...`, '');

            try {
                const datos = {
                    id: wooProducto.id,
                    name: productoActual.nombre,
                    regular_price: productoActual.precio,
                    stock_quantity: parseInt(productoActual.stock),
                    short_description: productoActual.nombre,
                    description: productoActual.descripcion_larga || productoActual.nombre
                };

                if (productoActual.peso) datos.weight = productoActual.peso;
                if (productoActual.alto) datos.alto = productoActual.alto;
                if (productoActual.ancho) datos.ancho = productoActual.ancho;
                if (productoActual.profundidad) datos.profundidad = productoActual.profundidad;
                if (productoActual.atributos?.length > 0) datos.atributos = productoActual.atributos;

                const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datos)
                });
                const data = await response.json();

                if (data.success) {
                    addLog(`✓ Sincronizado: precio, stock, dimensiones, atributos`, 'success');
                } else {
                    addLog(`✗ Error: ${data.error || data.errors?.join(', ')}`, 'error');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        async function sincronizarProducto() {
            if (!wooProducto || !productoActual) return;

            addLog(`Sincronizando precio/stock para ${productoActual.sku}...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: wooProducto.id,
                        regular_price: productoActual.precio,
                        stock_quantity: parseInt(productoActual.stock)
                    })
                });
                const data = await response.json();

                if (data.success) {
                    addLog(`✓ Precio y stock sincronizados`, 'success');
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
            addLog(`${accion} ${productoActual.sku}...`, '');

            try {
                const response = await fetch(`${API_BASE}/product-update.php?api_key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: wooProducto.id, status: nuevoEstado })
                });
                const data = await response.json();

                if (data.success) {
                    const msg = nuevoEstado === 'publish' ? 'Activado' : 'Desactivado';
                    addLog(`✓ ${msg} correctamente`, 'success');
                    buscarProducto();
                } else {
                    addLog(`✗ Error: ${data.error}`, 'error');
                }
            } catch (error) {
                addLog(`✗ Error: ${error.message}`, 'error');
            }
        }

        function updateStats() {
            document.getElementById('totalBuscados').textContent = stats.buscados;
            document.getElementById('totalPublicados').textContent = stats.publicados;
            document.getElementById('totalSinPublicar').textContent = stats.sinPublicar;
        }

        function addLog(message, type = '') {
            const container = document.getElementById('logsContainer');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `<span class="time">${time}</span>${message}`;
            container.insertBefore(entry, container.firstChild);

            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }

        // Init
        addLog('Sistema listo', 'success');
    </script>
</body>
</html>
