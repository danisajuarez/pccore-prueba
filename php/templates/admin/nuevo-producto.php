<?php
/**
 * Template de Nuevo Producto (búsqueda en ML desde frontend)
 */
?>
<div class="main-grid">
    <div class="search-section">
        <h2>Agregar Producto Nuevo</h2>
        <p style="color: #64748b; margin-bottom: 16px;">
            Busca en Mercado Libre por nombre o codigo de barras (EAN) para traer imagenes y datos.
        </p>

        <div class="search-box">
            <input type="text" id="mlSearchInput" placeholder="Nombre del producto o codigo EAN..." onkeypress="if(event.key==='Enter')buscarEnML()">
            <button onclick="buscarEnML()" id="searchBtn">Buscar en ML</button>
        </div>

        <!-- Resultados de ML -->
        <div id="mlResults" class="ml-results" style="display: none;">
            <h3>Resultados de Mercado Libre</h3>
            <div id="mlResultsGrid" class="ml-results-grid"></div>
            <div id="mlPagination" class="ml-pagination"></div>
        </div>

        <!-- Producto Seleccionado -->
        <div id="selectedProduct" class="selected-product" style="display: none;">
            <h3>Producto Seleccionado</h3>

            <div class="selected-preview">
                <div class="selected-images" id="selectedImages"></div>
                <div class="selected-info">
                    <div class="selected-title" id="selectedTitle"></div>
                    <div class="selected-price" id="selectedPrice"></div>
                </div>
            </div>

            <form id="newProductForm" class="new-product-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>SKU (tu codigo interno) *</label>
                        <input type="text" id="formSku" required placeholder="Ej: MOUSE-LOG-001">
                    </div>
                    <div class="form-group">
                        <label>Precio de Venta *</label>
                        <input type="number" id="formPrecio" required step="0.01" placeholder="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Inicial</label>
                        <input type="number" id="formStock" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select id="formCategoria">
                            <option value="">Sin categoria</option>
                        </select>
                    </div>
                </div>

                <div class="form-group full">
                    <label>Nombre del Producto *</label>
                    <input type="text" id="formNombre" required>
                </div>

                <div class="form-group full">
                    <label>Descripcion</label>
                    <textarea id="formDescripcion" rows="4"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Peso (kg)</label>
                        <input type="number" id="formPeso" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Alto (cm)</label>
                        <input type="number" id="formAlto" step="0.1" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Ancho (cm)</label>
                        <input type="number" id="formAncho" step="0.1" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Largo (cm)</label>
                        <input type="number" id="formLargo" step="0.1" placeholder="0">
                    </div>
                </div>

                <h4>Imagenes a subir</h4>
                <div id="formImagenes" class="form-images"></div>

                <div class="form-actions">
                    <button type="button" onclick="limpiarSeleccion()" class="secondary">Cancelar</button>
                    <button type="submit" class="success">Crear Producto en WooCommerce</button>
                </div>
            </form>
        </div>

        <!-- Loading -->
        <div id="loadingOverlay" class="loading-overlay" style="display: none;">
            <div class="spinner"></div>
            <span id="loadingText">Buscando...</span>
        </div>
    </div>

    <div class="log-section">
        <h2>Registro de Actividad</h2>
        <div class="logs" id="logsContainer">
            <div class="log-entry">
                <span class="time">--:--:--</span>
                Listo para buscar productos...
            </div>
        </div>
    </div>
</div>
