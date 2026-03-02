<?php
require_once __DIR__ . '/../config.php';

// Esta página no usa checkAuth() porque es interfaz web
// Pero las APIs que llama sí requieren API Key
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Productos - PC Core</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        body {
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .product-info {
            display: none;
        }
        .product-info.visible {
            display: block;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-publish {
            background: #d4edda;
            color: #155724;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .status-not-in-woo {
            background: #f8d7da;
            color: #721c24;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-top: 15px;
            display: none;
        }
        .message.visible {
            display: block;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .loading {
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Administrar Productos</h1>

        <div class="card">
            <h3>Buscar Producto</h3>
            <div class="search-box">
                <input type="text" id="skuInput" placeholder="Ingresá el SKU del producto" onkeypress="if(event.key==='Enter')buscarProducto()">
                <button class="btn btn-primary" onclick="buscarProducto()">Buscar</button>
            </div>
        </div>

        <div class="card product-info" id="productInfo">
            <h3>Datos del Producto</h3>

            <div class="info-grid">
                <span class="info-label">SKU:</span>
                <span class="info-value" id="prodSku">-</span>

                <span class="info-label">Nombre:</span>
                <span class="info-value" id="prodNombre">-</span>

                <span class="info-label">Precio:</span>
                <span class="info-value" id="prodPrecio">-</span>

                <span class="info-label">Stock:</span>
                <span class="info-value" id="prodStock">-</span>

                <span class="info-label">Estado Web:</span>
                <span class="info-value" id="prodEstado">-</span>
            </div>

            <div class="actions" id="actions">
                <!-- Botones se agregan dinámicamente -->
            </div>

            <div class="message" id="message"></div>
        </div>
    </div>

    <script>
        const API_KEY = 'pccore-sync-2024';
        const API_BASE = '/api';

        let productoActual = null;
        let wooProducto = null;

        async function buscarProducto() {
            const sku = document.getElementById('skuInput').value.trim();
            if (!sku) {
                alert('Ingresá un SKU');
                return;
            }

            const productInfo = document.getElementById('productInfo');
            const actions = document.getElementById('actions');

            productInfo.classList.remove('visible');
            hideMessage();

            // Buscar en BD local
            try {
                const response = await fetch(`${API_BASE}/product-search.php?sku=${encodeURIComponent(sku)}`, {
                    headers: { 'X-Api-Key': API_KEY }
                });
                const data = await response.json();

                if (!data.success) {
                    showMessage('Producto no encontrado en la base de datos', 'error');
                    return;
                }

                productoActual = data.producto;
                wooProducto = data.woo_producto;

                // Mostrar datos
                document.getElementById('prodSku').textContent = productoActual.sku;
                document.getElementById('prodNombre').textContent = productoActual.nombre;
                document.getElementById('prodPrecio').textContent = '$' + Number(productoActual.precio).toLocaleString('es-AR');
                document.getElementById('prodStock').textContent = productoActual.stock + ' unidades';

                // Estado y botones
                actions.innerHTML = '';

                if (wooProducto) {
                    // Ya existe en WooCommerce
                    const statusClass = wooProducto.status === 'publish' ? 'status-publish' : 'status-draft';
                    const statusText = wooProducto.status === 'publish' ? 'Publicado' : 'Desactivado';
                    document.getElementById('prodEstado').innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span> (ID: ${wooProducto.id})`;

                    if (wooProducto.status === 'publish') {
                        actions.innerHTML = `
                            <button class="btn btn-warning" onclick="desactivarProducto()">Desactivar de la Web</button>
                            <button class="btn btn-primary" onclick="sincronizarProducto()">Sincronizar Precio/Stock</button>
                            <a href="${wooProducto.permalink}" target="_blank" class="btn btn-success">Ver en la Web</a>
                        `;
                    } else {
                        actions.innerHTML = `
                            <button class="btn btn-success" onclick="activarProducto()">Activar en la Web</button>
                            <button class="btn btn-primary" onclick="sincronizarProducto()">Sincronizar Precio/Stock</button>
                        `;
                    }
                } else {
                    // No existe en WooCommerce
                    document.getElementById('prodEstado').innerHTML = '<span class="status-badge status-not-in-woo">No publicado</span>';
                    actions.innerHTML = `
                        <button class="btn btn-success" onclick="publicarProducto()">Publicar en WooCommerce</button>
                    `;
                }

                productInfo.classList.add('visible');

            } catch (error) {
                showMessage('Error al buscar: ' + error.message, 'error');
            }
        }

        async function publicarProducto() {
            if (!productoActual) return;

            if (!confirm(`¿Publicar "${productoActual.nombre}" en WooCommerce?`)) return;

            try {
                const response = await fetch(`${API_BASE}/product-publish.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': API_KEY
                    },
                    body: JSON.stringify({ sku: productoActual.sku })
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Producto publicado correctamente (ID: ' + data.product.id + ')', 'success');
                    buscarProducto(); // Refrescar
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
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

            try {
                const response = await fetch(`${API_BASE}/product-update.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': API_KEY
                    },
                    body: JSON.stringify({
                        id: wooProducto.id,
                        status: nuevoEstado
                    })
                });
                const data = await response.json();

                if (data.success) {
                    const msg = nuevoEstado === 'publish' ? 'Producto activado' : 'Producto desactivado';
                    showMessage(msg + ' correctamente', 'success');
                    buscarProducto(); // Refrescar
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            }
        }

        async function sincronizarProducto() {
            if (!wooProducto || !productoActual) return;

            try {
                const response = await fetch(`${API_BASE}/sync.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': API_KEY
                    },
                    body: JSON.stringify({
                        sku: productoActual.sku,
                        regular_price: productoActual.precio,
                        stock_quantity: parseInt(productoActual.stock)
                    })
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Precio y stock sincronizados', 'success');
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            }
        }

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message visible ' + type;
        }

        function hideMessage() {
            document.getElementById('message').className = 'message';
        }
    </script>
</body>
</html>
