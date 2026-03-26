<?php
/**
 * Template de Admin Productos
 *
 * Variables disponibles:
 * - $clienteId: ID del cliente
 * - $userName: Nombre del usuario
 * - $apiKey: API Key del cliente
 */
?>
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

            <!-- Sección de Imágenes -->
            <div class="images-section" id="imagesSection">
                <h3>🖼️ Imágenes del Producto</h3>

                <!-- Imágenes actuales en WooCommerce -->
                <div class="images-woo" id="imagesWoo" style="display: none;">
                    <h4>📦 Imágenes en WooCommerce</h4>
                    <div class="images-grid" id="wooImagesGrid"></div>
                </div>

                <!-- Imágenes de Mercado Libre -->
                <div class="images-ml" id="imagesMl" style="display: none;">
                    <h4>🛒 Imágenes de Mercado Libre</h4>
                    <div class="ml-info" id="mlInfo"></div>
                    <div class="select-all-row">
                        <input type="checkbox" id="selectAllMl" onchange="toggleSelectAll()">
                        <label for="selectAllMl">Seleccionar todas</label>
                    </div>
                    <div class="images-grid" id="mlImagesGrid"></div>
                    <div class="image-actions">
                        <button class="success" onclick="subirImagenes(false)" id="btnAgregar" disabled>➕ Agregar</button>
                        <button onclick="subirImagenes(true)" id="btnReemplazar" disabled>🔄 Reemplazar</button>
                        <span class="count" id="selectedCount">0 seleccionadas</span>
                    </div>
                </div>

                <!-- Sin imágenes -->
                <div class="no-images-msg" id="noImagesMsg" style="display: none;">
                    No se encontraron imágenes en Mercado Libre para este producto.
                </div>

                <!-- Cargando -->
                <div class="loading-images" id="loadingImages" style="display: none;">
                    <span class="spinner"></span> Buscando imágenes...
                </div>
            </div>
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
