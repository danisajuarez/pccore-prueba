/**
 * Admin Productos - JavaScript
 * Extraído de admin-productos.php
 */

// Variables globales (API_KEY y API_BASE se definen en el HTML)
let productoActual = null;
let wooProducto = null;
let stats = { buscados: 0, publicados: 0, sinPublicar: 0 };
let imagenesML = [];

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
    document.getElementById('prodPrecio').textContent = '$ ' + Number(productoActual.precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('prodPrecioSinIva').textContent = '$ ' + Number(productoActual.precio_sin_iva).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

    // Buscar imágenes automáticamente
    buscarImagenes();
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
            // Actualizar wooProducto directamente con los datos de la respuesta
            wooProducto = {
                id: data.product.id,
                status: data.product.status,
                permalink: data.product.permalink,
                regular_price: data.product.regular_price,
                stock_quantity: data.product.stock_quantity
            };
            // Actualizar estadísticas
            stats.publicados++;
            stats.sinPublicar = Math.max(0, stats.sinPublicar - 1);
            updateStats();
            // Actualizar UI inmediatamente
            mostrarProducto();
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
            precio_sin_iva: productoActual.precio_sin_iva,
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
            precio_sin_iva: productoActual.precio_sin_iva,
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

// ==========================================
// FUNCIONES DE IMÁGENES
// ==========================================

async function buscarImagenes() {
    if (!productoActual) return;

    const section = document.getElementById('imagesSection');
    const loading = document.getElementById('loadingImages');
    const wooDiv = document.getElementById('imagesWoo');
    const mlDiv = document.getElementById('imagesMl');
    const noMsg = document.getElementById('noImagesMsg');

    // Reset
    wooDiv.style.display = 'none';
    mlDiv.style.display = 'none';
    noMsg.style.display = 'none';
    loading.style.display = 'block';
    section.classList.add('visible');
    imagenesML = [];

    try {
        const response = await fetch(`${API_BASE}/image-search.php?sku=${encodeURIComponent(productoActual.sku)}&api_key=${API_KEY}`);
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
            wooGrid.innerHTML = data.woocommerce.imagenes.map(img => `
                <div class="image-card">
                    <img src="${img.src}" alt="${img.name || 'Imagen'}">
                    <div class="img-source">WooCommerce</div>
                </div>
            `).join('');
            wooDiv.style.display = 'block';
        }

        // Mostrar imágenes de ML
        const ml = data.imagenes.mercadolibre;
        if (ml && ml.imagenes && ml.imagenes.length > 0) {
            imagenesML = ml.imagenes;

            document.getElementById('mlInfo').textContent =
                `Encontrado por: ${ml.encontrado_por} | Producto: ${ml.producto.nombre}`;

            const mlGrid = document.getElementById('mlImagesGrid');
            mlGrid.innerHTML = ml.imagenes.map((img, idx) => `
                <div class="image-card" data-idx="${idx}" onclick="toggleImageSelect(this)">
                    <img src="${img.url}" alt="Imagen ${idx + 1}">
                    <div class="img-check">✓</div>
                    <div class="img-source">ML</div>
                </div>
            `).join('');

            mlDiv.style.display = 'block';
            document.getElementById('selectAllMl').checked = false;
            updateImageCount();

            addLog(`✓ Encontradas ${ml.imagenes.length} imágenes en ML`, 'success');
        } else if (!data.woocommerce?.tiene_imagenes) {
            noMsg.style.display = 'block';
        }

    } catch (error) {
        loading.style.display = 'none';
        noMsg.style.display = 'block';
        noMsg.textContent = 'Error: ' + error.message;
    }
}

function toggleImageSelect(card) {
    card.classList.toggle('selected');
    updateImageCount();
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
    document.getElementById('selectedCount').textContent = `${count} seleccionada${count !== 1 ? 's' : ''}`;
    document.getElementById('btnAgregar').disabled = count === 0;
    document.getElementById('btnReemplazar').disabled = count === 0;
}

async function subirImagenes(reemplazar) {
    const selected = document.querySelectorAll('#mlImagesGrid .image-card.selected');
    if (selected.length === 0) {
        addLog('Seleccioná al menos una imagen', 'error');
        return;
    }

    const imagenes = Array.from(selected).map(card => {
        const idx = parseInt(card.dataset.idx);
        return imagenesML[idx].url;
    });

    const btn = reemplazar ? document.getElementById('btnReemplazar') : document.getElementById('btnAgregar');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    addLog(`Subiendo ${imagenes.length} imagen(es)...`, '');

    try {
        const response = await fetch(`${API_BASE}/image-upload.php?api_key=${API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sku: productoActual.sku,
                imagenes: imagenes,
                reemplazar: reemplazar
            })
        });

        const data = await response.json();

        if (data.success) {
            addLog(`✓ Imágenes subidas OK (${data.total_imagenes || imagenes.length} en total)`, 'success');
            // Esperar un poco y recargar imágenes
            setTimeout(() => buscarImagenes(), 2000);
        } else {
            addLog(`✗ Error: ${data.error}`, 'error');
        }
    } catch (error) {
        addLog(`✗ Error: ${error.message}`, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
        updateImageCount();
    }
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    addLog('Sistema listo', 'success');
});
