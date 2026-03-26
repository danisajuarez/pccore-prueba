/**
 * Nuevo Producto - Búsqueda en Mercado Libre desde el navegador
 * Usa proxy CORS para evitar bloqueos
 */

// Proxy CORS - probar sin proxy primero para ver el error real
const CORS_PROXY = ''; // Deshabilitado temporalmente

// Estado global
let mlResultados = [];
let productoSeleccionado = null;
let imagenesSeleccionadas = [];

/**
 * Buscar en Mercado Libre
 */
async function buscarEnML() {
    const query = document.getElementById('mlSearchInput').value.trim();
    if (!query) {
        log('Ingresa un texto para buscar', 'warning');
        return;
    }

    mostrarLoading('Buscando en Mercado Libre...');
    log(`Buscando: "${query}"...`);

    try {
        // Buscar en la API de ML
        const mlUrl = `https://api.mercadolibre.com/sites/MLA/search?q=${encodeURIComponent(query)}&limit=20`;
        const url = CORS_PROXY ? CORS_PROXY + encodeURIComponent(mlUrl) : mlUrl;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            mode: 'cors',
        });

        if (!response.ok) {
            // Mostrar más detalles del error
            const errorText = await response.text();
            console.log('Error response:', errorText);
            throw new Error(`Error ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (!data.results || data.results.length === 0) {
            log('No se encontraron resultados', 'warning');
            document.getElementById('mlResults').style.display = 'none';
            ocultarLoading();
            return;
        }

        mlResultados = data.results;
        log(`Se encontraron ${data.paging.total} productos`, 'success');

        mostrarResultados(data.results);
        ocultarLoading();

    } catch (error) {
        console.error('Error buscando en ML:', error);
        log(`Error: ${error.message}`, 'error');
        ocultarLoading();
    }
}

/**
 * Mostrar resultados de ML
 */
function mostrarResultados(results) {
    const container = document.getElementById('mlResultsGrid');
    const section = document.getElementById('mlResults');

    container.innerHTML = results.map((item, index) => `
        <div class="ml-result-item" onclick="seleccionarProducto(${index})">
            <img src="${item.thumbnail}" alt="${item.title}" loading="lazy">
            <div class="ml-result-info">
                <div class="ml-result-title">${item.title}</div>
                <div class="ml-result-price">$${item.price.toLocaleString('es-AR')}</div>
                ${item.shipping?.free_shipping ? '<span class="ml-free-shipping">Envio gratis</span>' : ''}
            </div>
        </div>
    `).join('');

    section.style.display = 'block';
}

/**
 * Seleccionar un producto de los resultados
 */
async function seleccionarProducto(index) {
    const item = mlResultados[index];
    if (!item) return;

    mostrarLoading('Obteniendo detalles del producto...');
    log(`Seleccionado: ${item.title}`);

    try {
        // Obtener detalles completos del item usando proxy CORS
        const itemUrl = CORS_PROXY + encodeURIComponent(`https://api.mercadolibre.com/items/${item.id}`);
        const itemResponse = await fetch(itemUrl);
        const itemData = await itemResponse.json();

        // Obtener descripción
        let descripcion = '';
        try {
            const descUrl = CORS_PROXY + encodeURIComponent(`https://api.mercadolibre.com/items/${item.id}/description`);
            const descResponse = await fetch(descUrl);
            if (descResponse.ok) {
                const descData = await descResponse.json();
                descripcion = descData.plain_text || '';
            }
        } catch (e) {
            // Sin descripción disponible
        }

        // Guardar producto seleccionado
        productoSeleccionado = {
            ml_id: item.id,
            titulo: item.title,
            precio: item.price,
            descripcion: descripcion,
            imagenes: itemData.pictures || [],
            atributos: itemData.attributes || [],
            catalog_product_id: item.catalog_product_id
        };

        // Extraer dimensiones de atributos si existen
        const dimensiones = extraerDimensiones(itemData.attributes || []);

        // Llenar formulario
        document.getElementById('formNombre').value = item.title;
        document.getElementById('formPrecio').value = item.price;
        document.getElementById('formDescripcion').value = descripcion.substring(0, 2000);
        document.getElementById('formPeso').value = dimensiones.peso || '';
        document.getElementById('formAlto').value = dimensiones.alto || '';
        document.getElementById('formAncho').value = dimensiones.ancho || '';
        document.getElementById('formLargo').value = dimensiones.largo || '';

        // Mostrar preview
        document.getElementById('selectedTitle').textContent = item.title;
        document.getElementById('selectedPrice').textContent = `Precio ML: $${item.price.toLocaleString('es-AR')}`;

        // Mostrar imágenes para seleccionar
        mostrarImagenesSeleccion(itemData.pictures || []);

        // Ocultar resultados, mostrar formulario
        document.getElementById('mlResults').style.display = 'none';
        document.getElementById('selectedProduct').style.display = 'block';

        log(`Producto cargado con ${itemData.pictures?.length || 0} imagenes`, 'success');
        ocultarLoading();

    } catch (error) {
        console.error('Error obteniendo detalles:', error);
        log(`Error: ${error.message}`, 'error');
        ocultarLoading();
    }
}

/**
 * Extraer dimensiones de los atributos de ML
 */
function extraerDimensiones(atributos) {
    const dims = { peso: null, alto: null, ancho: null, largo: null };

    for (const attr of atributos) {
        const id = (attr.id || '').toUpperCase();
        const value = attr.value_name || '';

        if (id.includes('WEIGHT') || id.includes('PESO')) {
            dims.peso = extraerNumero(value);
        }
        if (id.includes('HEIGHT') || id.includes('ALTO')) {
            dims.alto = extraerNumero(value);
        }
        if (id.includes('WIDTH') || id.includes('ANCHO')) {
            dims.ancho = extraerNumero(value);
        }
        if (id.includes('DEPTH') || id.includes('LENGTH') || id.includes('LARGO') || id.includes('PROF')) {
            dims.largo = extraerNumero(value);
        }
    }

    return dims;
}

/**
 * Extraer número de un string
 */
function extraerNumero(str) {
    const match = str.match(/[\d.,]+/);
    if (match) {
        return parseFloat(match[0].replace(',', '.'));
    }
    return null;
}

/**
 * Mostrar imágenes para seleccionar
 */
function mostrarImagenesSeleccion(pictures) {
    const container = document.getElementById('formImagenes');
    imagenesSeleccionadas = pictures.map(p => p.url || p.secure_url);

    // Mostrar preview
    document.getElementById('selectedImages').innerHTML = pictures.slice(0, 3).map(pic =>
        `<img src="${pic.url || pic.secure_url}" alt="Preview">`
    ).join('');

    // Mostrar todas para seleccionar
    container.innerHTML = pictures.map((pic, i) => `
        <label class="image-select-item">
            <input type="checkbox" checked data-index="${i}" onchange="toggleImagen(${i})">
            <img src="${pic.url || pic.secure_url}" alt="Imagen ${i + 1}">
        </label>
    `).join('');
}

/**
 * Toggle selección de imagen
 */
function toggleImagen(index) {
    const pictures = productoSeleccionado?.imagenes || [];
    const url = pictures[index]?.url || pictures[index]?.secure_url;

    if (!url) return;

    const idx = imagenesSeleccionadas.indexOf(url);
    if (idx > -1) {
        imagenesSeleccionadas.splice(idx, 1);
    } else {
        imagenesSeleccionadas.push(url);
    }
}

/**
 * Limpiar selección y volver a búsqueda
 */
function limpiarSeleccion() {
    productoSeleccionado = null;
    imagenesSeleccionadas = [];

    document.getElementById('selectedProduct').style.display = 'none';
    document.getElementById('mlResults').style.display = 'block';
    document.getElementById('newProductForm').reset();
}

/**
 * Enviar formulario para crear producto
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('newProductForm');
    if (form) {
        form.addEventListener('submit', crearProducto);
    }

    // Cargar categorías
    cargarCategorias();
});

/**
 * Cargar categorías de WooCommerce
 */
async function cargarCategorias() {
    try {
        const response = await fetch(`${API_BASE}/categories.php`, {
            headers: { 'X-API-Key': API_KEY }
        });

        if (response.ok) {
            const data = await response.json();
            const select = document.getElementById('formCategoria');

            if (data.categories) {
                data.categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.id;
                    option.textContent = cat.name;
                    select.appendChild(option);
                });
            }
        }
    } catch (e) {
        console.log('No se pudieron cargar categorías');
    }
}

/**
 * Crear producto en WooCommerce
 */
async function crearProducto(e) {
    e.preventDefault();

    const sku = document.getElementById('formSku').value.trim();
    const nombre = document.getElementById('formNombre').value.trim();
    const precio = document.getElementById('formPrecio').value;

    if (!sku || !nombre || !precio) {
        log('Completa los campos obligatorios (SKU, nombre, precio)', 'error');
        return;
    }

    mostrarLoading('Creando producto en WooCommerce...');
    log('Enviando producto a WooCommerce...');

    const payload = {
        sku: sku,
        nombre: nombre,
        precio: parseFloat(precio),
        stock: parseInt(document.getElementById('formStock').value) || 0,
        descripcion: document.getElementById('formDescripcion').value,
        categoria_id: document.getElementById('formCategoria').value || null,
        peso: document.getElementById('formPeso').value || null,
        alto: document.getElementById('formAlto').value || null,
        ancho: document.getElementById('formAncho').value || null,
        largo: document.getElementById('formLargo').value || null,
        imagenes: imagenesSeleccionadas,
        ml_id: productoSeleccionado?.ml_id || null
    };

    try {
        const response = await fetch(`${API_BASE}/product-create.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_KEY
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            log(`Producto creado exitosamente! ID: ${data.woo_id}`, 'success');

            // Limpiar formulario
            document.getElementById('newProductForm').reset();
            document.getElementById('selectedProduct').style.display = 'none';
            document.getElementById('mlResults').style.display = 'none';
            document.getElementById('mlSearchInput').value = '';

            productoSeleccionado = null;
            imagenesSeleccionadas = [];
            mlResultados = [];

        } else {
            log(`Error: ${data.error || 'Error desconocido'}`, 'error');
        }

        ocultarLoading();

    } catch (error) {
        console.error('Error creando producto:', error);
        log(`Error: ${error.message}`, 'error');
        ocultarLoading();
    }
}

/**
 * Utilidades de UI
 */
function mostrarLoading(texto) {
    document.getElementById('loadingText').textContent = texto;
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function ocultarLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function log(mensaje, tipo = 'info') {
    const container = document.getElementById('logsContainer');
    const now = new Date();
    const time = now.toTimeString().split(' ')[0];

    const colors = {
        info: '#94a3b8',
        success: '#22c55e',
        warning: '#f59e0b',
        error: '#ef4444'
    };

    const entry = document.createElement('div');
    entry.className = 'log-entry';
    entry.innerHTML = `<span class="time">${time}</span> <span style="color: ${colors[tipo]}">${mensaje}</span>`;

    container.insertBefore(entry, container.firstChild);

    // Limitar a 50 entradas
    while (container.children.length > 50) {
        container.removeChild(container.lastChild);
    }
}
