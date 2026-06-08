<?php
/**
 * Admin Pedidos - Multi-tenant
 * Vista de pedidos WooCommerce para el cliente.
 */
require_once __DIR__ . '/../bootstrap.php';

requireAuth('/api/login.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /api/admin-login.php');
    exit();
}

$clienteConfig = getClienteConfig();
$clienteNombre = $clienteConfig['nombre'] ?? 'Sistema';
$userName = $_SESSION['admin_user_nombre'] ?? $_SESSION['admin_user'] ?? 'Usuario';

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(strtoupper($clienteNombre)) ?> - Pedidos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .container { max-width: 1400px; margin: 0 auto; padding: 8px 16px; }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #334155;
            margin-bottom: 16px;
        }

        .logo { font-size: 20px; font-weight: bold; color: #3b82f6; }

        .nav-links { display: flex; gap: 8px; }
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
        .nav-links a:hover { background: #334155; }
        .nav-links a.active { background: #3b82f6; border-color: #3b82f6; }
        .nav-links a.logout { background: #dc2626; border-color: #dc2626; }
        .nav-links a.logout:hover { background: #b91c1c; }

        /* Toolbar */
        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .toolbar select, .toolbar input[type=date], .toolbar input[type=text] {
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }
        .toolbar select:focus,
        .toolbar input:focus { border-color: #3b82f6; }
        .toolbar input[type=text] { min-width: 240px; }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-primary  { background: #3b82f6; color: #fff; }
        .btn-primary:hover  { background: #2563eb; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-secondary:hover { background: #475569; }
        .btn-sige  { background: #16a34a; color: #fff; }
        .btn-sige:hover  { background: #15803d; }
        .btn-sige:disabled { opacity: 0.6; cursor: default; }

        /* Tabla */
        .table-wrap {
            background: #1e293b;
            border-radius: 8px;
            border: 1px solid #334155;
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead { background: #0f172a; }
        th {
            padding: 10px 14px;
            text-align: left;
            color: #94a3b8;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #334155;
        }
        td { padding: 10px 14px; border-bottom: 1px solid #0f172a; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        tbody tr.data-row {
            background: #1e293b;
            transition: background 0.15s;
            cursor: pointer;
        }
        tbody tr.data-row:nth-child(4n+3) { background: #172033; }
        tbody tr.data-row:hover { background: #263248 !important; }
        tbody tr.data-row.row-active { background: #1e3a5f !important; }

        /* Fila de detalle expandido */
        tr.detail-expanded td {
            padding: 0;
            border-bottom: 2px solid #334155;
        }

        .detail-inline {
            padding: 20px 24px;
            background: #111827;
            border-top: 2px solid #3b82f6;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            animation: expandDown 0.2s ease;
        }

        @keyframes expandDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1024px) {
            .detail-inline { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .detail-inline { grid-template-columns: 1fr; }
        }

        .detail-col {}

        .detail-section { margin-bottom: 18px; }
        .detail-section h3 {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #1e293b;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 4px 0;
            font-size: 12px;
            gap: 10px;
        }
        .detail-label { color: #64748b; flex-shrink: 0; }
        .detail-value { color: #e2e8f0; text-align: right; word-break: break-word; }

        /* Items del pedido */
        .order-item {
            display: flex;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #1e293b;
        }
        .order-item:last-child { border-bottom: none; }
        .item-img {
            width: 44px; height: 44px;
            object-fit: contain;
            border-radius: 5px;
            background: #0f172a;
            border: 1px solid #334155;
            flex-shrink: 0;
        }
        .item-img-placeholder {
            width: 44px; height: 44px;
            border-radius: 5px;
            background: #0f172a;
            border: 1px solid #334155;
            display: flex; align-items: center; justify-content: center;
            color: #475569; font-size: 18px;
            flex-shrink: 0;
        }
        .item-info { flex: 1; min-width: 0; }
        .item-name { font-size: 12px; color: #f1f5f9; margin-bottom: 2px; line-height: 1.3; }
        .item-meta { font-size: 11px; color: #64748b; }
        .item-total { font-size: 13px; font-weight: 600; color: #22c55e; white-space: nowrap; }

        /* Totales */
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
            color: #94a3b8;
        }
        .total-row.grand {
            font-size: 15px;
            font-weight: 700;
            color: #f1f5f9;
            padding-top: 8px;
            border-top: 1px solid #334155;
            margin-top: 4px;
        }

        /* Botón guardar individual */
        .btn-sige-sm {
            margin-top: 14px;
            padding: 6px 14px;
            font-size: 12px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-sige-sm:hover { background: #15803d; }
        .btn-sige-sm:disabled { opacity: 0.6; cursor: default; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-processing { background: #1d4ed8; color: #bfdbfe; }
        .badge-completed  { background: #166534; color: #bbf7d0; }
        .badge-pending    { background: #854d0e; color: #fef08a; }
        .badge-on-hold    { background: #6b21a8; color: #e9d5ff; }
        .badge-cancelled  { background: #7f1d1d; color: #fecaca; }
        .badge-refunded   { background: #374151; color: #d1d5db; }
        .badge-failed     { background: #450a0a; color: #fca5a5; }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }
        .pagination button {
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #e2e8f0;
            font-size: 13px;
            cursor: pointer;
        }
        .pagination button:hover { background: #334155; }
        .pagination button.current { background: #3b82f6; border-color: #3b82f6; }
        .pagination button:disabled { opacity: 0.4; cursor: default; }

        /* Estado vacío / carga */
        .state-msg {
            text-align: center;
            padding: 48px 20px;
            color: #64748b;
            font-size: 14px;
        }
        .state-msg .icon { font-size: 36px; margin-bottom: 10px; }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 24px; right: 24px;
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 999;
            max-width: 320px;
        }
        #toast.show  { opacity: 1; }
        #toast.error { border-color: #ef4444; color: #fca5a5; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div class="logo"><?= htmlspecialchars(strtoupper($clienteNombre)) ?></div>
        <nav class="nav-links">
            <a href="/api/admin-productos.php">Productos</a>
            <a href="/api/admin-pedidos.php" class="active">Pedidos</a>
            <a href="/api/logout.php" class="logout">Salir (<?= htmlspecialchars($userName) ?>)</a>
        </nav>
    </header>

    <!-- Toolbar -->
    <div class="toolbar">
        <select id="filtroEstado">
            <option value="">Todos los estados</option>
            <option value="pending">Pendiente</option>
            <option value="processing">En proceso</option>
            <option value="on-hold">En espera</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
            <option value="refunded">Reembolsado</option>
            <option value="failed">Fallido</option>
        </select>

        <input type="date" id="filtroDesde" title="Desde">
        <input type="date" id="filtroHasta" title="Hasta">
        <input type="text" id="filtroBusqueda" placeholder="Buscar por número, email o nombre...">

        <button class="btn btn-primary" onclick="cargarPedidos(1)">Buscar</button>
        <button class="btn btn-secondary" onclick="limpiarFiltros()">Limpiar</button>
        <button class="btn btn-sige" id="btnDescargarSige" onclick="descargarEnSige()" style="display:none">⬇ Descargar todos en SIGE</button>

        <span id="contadorPedidos" style="margin-left:auto;color:#64748b;font-size:13px;"></span>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody id="tablaPedidos">
                <tr>
                    <td colspan="6">
                        <div class="state-msg">
                            <div class="icon">📦</div>
                            Seleccioná los filtros y presioná Buscar
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="paginacion"></div>
</div>

<div id="toast"></div>

<script>
const API_KEY = '<?= htmlspecialchars(getClienteId() . '-sync-2024') ?>';
let paginaActual   = 1;
let pedidosActuales = [];
let detalleAbierto  = null;

// Setear fecha hasta = hoy al cargar
document.getElementById('filtroHasta').value = new Date().toISOString().split('T')[0];

// ─── Cargar pedidos ───────────────────────────────────────────────────────────

async function cargarPedidos(pagina = 1) {
    paginaActual = pagina;
    detalleAbierto = null;

    const estado   = document.getElementById('filtroEstado').value;
    const desde    = document.getElementById('filtroDesde').value;
    const hasta    = document.getElementById('filtroHasta').value;
    const busqueda = document.getElementById('filtroBusqueda').value.trim();
    const tbody    = document.getElementById('tablaPedidos');

    tbody.innerHTML = `<tr><td colspan="6"><div class="state-msg"><div class="icon">⏳</div>Cargando pedidos...</div></td></tr>`;
    document.getElementById('paginacion').innerHTML = '';
    document.getElementById('contadorPedidos').textContent = '';
    document.getElementById('btnDescargarSige').style.display = 'none';

    const params = new URLSearchParams({ action: 'list', page: pagina, per_page: 20 });
    if (estado)   params.set('status', estado);
    if (desde)    params.set('after',  desde + 'T00:00:00');
    if (hasta)    params.set('before', hasta + 'T23:59:59');
    if (busqueda) params.set('search', busqueda);

    try {
        const res  = await fetch('/api/orders.php?' + params.toString(), { headers: { 'X-Api-Key': API_KEY } });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Error desconocido');

        pedidosActuales = data.pedidos;
        renderTabla(data.pedidos);
        renderPaginacion(pagina, data.pedidos.length, data.per_page);
        document.getElementById('contadorPedidos').textContent =
            data.pedidos.length === 0 ? 'Sin resultados' : `${data.pedidos.length} pedido(s)`;
        if (data.pedidos.length > 0) {
            document.getElementById('btnDescargarSige').style.display = '';
            document.getElementById('btnDescargarSige').textContent   = '⬇ Descargar todos en SIGE';
            document.getElementById('btnDescargarSige').disabled      = false;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="state-msg" style="color:#f87171"><div class="icon">⚠️</div>${e.message}</div></td></tr>`;
        toast(e.message, true);
    }
}

// ─── Render tabla ─────────────────────────────────────────────────────────────

function renderTabla(pedidos) {
    const tbody = document.getElementById('tablaPedidos');

    if (!pedidos.length) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="state-msg"><div class="icon">🔍</div>No se encontraron pedidos</div></td></tr>`;
        return;
    }

    tbody.innerHTML = pedidos.map(p => {
        const fecha    = formatFecha(p.date_created);
        const cliente  = p.billing.nombre || p.billing.email || 'Invitado';
        const email    = p.billing.email ? `<div style="font-size:11px;color:#64748b">${escHtml(p.billing.email)}</div>` : '';
        const prods    = p.items.map(i => i.nombre).join(', ');
        const resumen  = prods.length > 60 ? prods.substring(0, 57) + '...' : prods;
        const total    = formatMonto(p.total, p.currency_symbol);

        return `<tr class="data-row" onclick="verDetalle(${p.id}, this)">
            <td><strong style="color:#3b82f6">#${p.number}</strong></td>
            <td style="white-space:nowrap;color:#94a3b8">${fecha}</td>
            <td><div>${escHtml(cliente)}</div>${email}</td>
            <td style="color:#94a3b8;font-size:12px">${escHtml(resumen)}</td>
            <td>${badgeEstado(p.status)}</td>
            <td><strong style="color:#22c55e">${total}</strong></td>
        </tr>`;
    }).join('');
}

// ─── Detalle inline ───────────────────────────────────────────────────────────

async function verDetalle(id, trEl) {
    // Toggle: cerrar si ya está abierto
    const existente = document.getElementById('detail-row-' + id);
    if (existente) {
        existente.remove();
        trEl.classList.remove('row-active');
        detalleAbierto = null;
        return;
    }

    // Cerrar otro abierto
    if (detalleAbierto !== null) {
        document.getElementById('detail-row-' + detalleAbierto)?.remove();
        document.querySelector('tr.data-row.row-active')?.classList.remove('row-active');
    }

    detalleAbierto = id;
    trEl.classList.add('row-active');

    // Insertar fila con loading
    const detailTr = document.createElement('tr');
    detailTr.id        = 'detail-row-' + id;
    detailTr.className = 'detail-expanded';
    detailTr.innerHTML = `<td colspan="6"><div class="detail-inline"><div class="state-msg" style="padding:24px">⏳ Cargando...</div></div></td>`;
    trEl.insertAdjacentElement('afterend', detailTr);

    try {
        const res  = await fetch(`/api/orders.php?action=detail&id=${id}`, { headers: { 'X-Api-Key': API_KEY } });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        detailTr.querySelector('.detail-inline').innerHTML = renderDetalleInline(data.pedido);
    } catch (e) {
        detailTr.querySelector('.detail-inline').innerHTML =
            `<div class="state-msg" style="color:#f87171;padding:24px">⚠️ ${escHtml(e.message)}</div>`;
    }
}

function renderDetalleInline(p) {
    const sym = p.currency_symbol || '$';

    // ── Columna 1: Resumen + Pago + MP ──
    const mpHtml = p.mp_info && p.mp_info.cuotas ? `
        <div class="detail-section">
            <h3>Mercado Pago</h3>
            <div class="detail-row"><span class="detail-label">Cuotas</span><span class="detail-value">${p.mp_info.cuotas}x de ${sym}${formatNum(p.mp_info.valor_cuota || 0)}</span></div>
            ${p.mp_info.tarjeta_ultimos4 ? `<div class="detail-row"><span class="detail-label">Tarjeta</span><span class="detail-value">•••• ${p.mp_info.tarjeta_ultimos4}</span></div>` : ''}
        </div>` : '';

    const col1 = `
        <div class="detail-col">
            <div class="detail-section">
                <h3>Resumen</h3>
                <div class="detail-row"><span class="detail-label">Estado</span><span class="detail-value">${badgeEstado(p.status)}</span></div>
                <div class="detail-row"><span class="detail-label">Fecha</span><span class="detail-value">${formatFecha(p.date_created)}</span></div>
                ${p.date_paid ? `<div class="detail-row"><span class="detail-label">Pagado</span><span class="detail-value">${formatFecha(p.date_paid)}</span></div>` : ''}
                <div class="detail-row"><span class="detail-label">Método de pago</span><span class="detail-value">${escHtml(p.payment_title)}</span></div>
            </div>
            ${mpHtml}
            ${p.customer_note ? `<div class="detail-section"><h3>Nota del cliente</h3><p style="font-size:12px;color:#cbd5e1;line-height:1.5">${escHtml(p.customer_note)}</p></div>` : ''}
        </div>`;

    // ── Columna 2: Cliente + Facturación + Envío ──
    const col2 = `
        <div class="detail-col">
            <div class="detail-section">
                <h3>Cliente</h3>
                <div class="detail-row"><span class="detail-label">Nombre</span><span class="detail-value">${escHtml(p.billing.nombre || '—')}</span></div>
                ${p.billing.dni ? `<div class="detail-row"><span class="detail-label">DNI</span><span class="detail-value">${escHtml(p.billing.dni)}</span></div>` : ''}
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${escHtml(p.billing.email || '—')}</span></div>
                <div class="detail-row"><span class="detail-label">Teléfono</span><span class="detail-value">${escHtml(p.billing.telefono || '—')}</span></div>
                ${p.billing.empresa ? `<div class="detail-row"><span class="detail-label">Empresa</span><span class="detail-value">${escHtml(p.billing.empresa)}</span></div>` : ''}
            </div>
            <div class="detail-section">
                <h3>Facturación</h3>
                <div class="detail-row"><span class="detail-label">Dirección</span><span class="detail-value">${escHtml(p.billing.direccion || '—')}</span></div>
                <div class="detail-row"><span class="detail-label">Ciudad</span><span class="detail-value">${escHtml(p.billing.ciudad)} (${escHtml(p.billing.cp)})</span></div>
                <div class="detail-row"><span class="detail-label">Provincia</span><span class="detail-value">${escHtml(p.billing.provincia || p.billing.pais)}</span></div>
            </div>
            ${p.shipping.direccion ? `
            <div class="detail-section">
                <h3>Envío</h3>
                <div class="detail-row"><span class="detail-label">Nombre</span><span class="detail-value">${escHtml(p.shipping.nombre || p.billing.nombre)}</span></div>
                <div class="detail-row"><span class="detail-label">Dirección</span><span class="detail-value">${escHtml(p.shipping.direccion)}</span></div>
                <div class="detail-row"><span class="detail-label">Ciudad</span><span class="detail-value">${escHtml(p.shipping.ciudad)} (${escHtml(p.shipping.cp)})</span></div>
            </div>` : ''}
        </div>`;

    // ── Columna 3: Productos + Totales + Botón SIGE ──
    const itemsHtml = p.items.map(i => `
        <div class="order-item">
            ${i.imagen
                ? `<img class="item-img" src="${escHtml(i.imagen)}" alt="" loading="lazy">`
                : `<div class="item-img-placeholder">📦</div>`}
            <div class="item-info">
                <div class="item-name">${escHtml(i.nombre)}</div>
                <div class="item-meta">SKU: ${escHtml(i.sku || '—')} · Cant: ${i.cantidad}</div>
            </div>
            <div class="item-total">${sym}${formatNum(i.total)}</div>
        </div>`).join('');

    const envioHtml = p.envio_lineas.map(e =>
        `<div class="total-row"><span>${escHtml(e.metodo)}</span><span>${sym}${formatNum(e.total)}</span></div>`
    ).join('') || `<div class="total-row"><span style="color:#64748b">Sin envío</span><span>—</span></div>`;

    const col3 = `
        <div class="detail-col">
            <div class="detail-section">
                <h3>Productos (${p.items.length})</h3>
                ${itemsHtml}
            </div>
            <div class="detail-section">
                <h3>Totales</h3>
                <div class="total-row"><span>Subtotal</span><span>${sym}${formatNum(p.subtotal)}</span></div>
                ${envioHtml}
                ${parseFloat(p.total_tax) > 0 ? `<div class="total-row"><span>Impuestos</span><span>${sym}${formatNum(p.total_tax)}</span></div>` : ''}
                <div class="total-row grand"><span>TOTAL</span><span>${sym}${formatNum(p.total)}</span></div>
            </div>
            <button class="btn-sige-sm" onclick="descargarUno(${p.id}, this)">⬇ Guardar en SIGE</button>
        </div>`;

    return col1 + col2 + col3;
}

// ─── Descargar individual ─────────────────────────────────────────────────────

async function descargarUno(id, btn) {
    btn.disabled    = true;
    btn.textContent = '⏳ Guardando...';
    try {
        const res  = await fetch(`/api/orders.php?action=detail&id=${id}`, { headers: { 'X-Api-Key': API_KEY } });
        const data = await res.json();
        if (data.success) {
            btn.textContent = '✓ Guardado en SIGE';
            toast('Pedido #' + id + ' guardado en SIGE');
        } else {
            throw new Error(data.error || 'Error');
        }
    } catch (e) {
        btn.textContent = '⚠ Error';
        btn.disabled    = false;
        toast(e.message, true);
    }
}

// ─── Descargar todos en lote ──────────────────────────────────────────────────

async function descargarEnSige() {
    if (!pedidosActuales.length) return;

    const btn    = document.getElementById('btnDescargarSige');
    const total  = pedidosActuales.length;
    btn.disabled = true;

    let ok = 0, errores = 0;

    for (const p of pedidosActuales) {
        btn.textContent = `⏳ Guardando ${ok + errores + 1}/${total}...`;
        try {
            const res  = await fetch(`/api/orders.php?action=detail&id=${p.id}`, { headers: { 'X-Api-Key': API_KEY } });
            const data = await res.json();
            if (data.success) ok++; else errores++;
        } catch (e) {
            errores++;
        }
    }

    btn.textContent = errores === 0
        ? `✓ ${ok} guardado(s) en SIGE`
        : `⚠ ${ok} OK / ${errores} errores`;

    toast(errores === 0
        ? `${ok} pedido(s) descargados en SIGE correctamente`
        : `${ok} guardados, ${errores} con error`, errores > 0);

    setTimeout(() => {
        btn.textContent = '⬇ Descargar todos en SIGE';
        btn.disabled    = false;
    }, 4000);
}

// ─── Paginación ───────────────────────────────────────────────────────────────

function renderPaginacion(pagina, count, perPage) {
    const div    = document.getElementById('paginacion');
    const hayMas = count >= perPage;
    let html = '';
    if (pagina > 1)  html += `<button onclick="cargarPedidos(${pagina - 1})">← Anterior</button>`;
    html += `<button class="current" disabled>Página ${pagina}</button>`;
    if (hayMas)      html += `<button onclick="cargarPedidos(${pagina + 1})">Siguiente →</button>`;
    div.innerHTML = html;
}

// ─── Limpiar filtros ──────────────────────────────────────────────────────────

function limpiarFiltros() {
    document.getElementById('filtroEstado').value   = '';
    document.getElementById('filtroDesde').value    = '';
    document.getElementById('filtroHasta').value    = new Date().toISOString().split('T')[0];
    document.getElementById('filtroBusqueda').value = '';
    pedidosActuales = [];
    detalleAbierto  = null;
    document.getElementById('tablaPedidos').innerHTML =
        `<tr><td colspan="6"><div class="state-msg"><div class="icon">📦</div>Seleccioná los filtros y presioná Buscar</div></td></tr>`;
    document.getElementById('paginacion').innerHTML      = '';
    document.getElementById('contadorPedidos').textContent = '';
    document.getElementById('btnDescargarSige').style.display = 'none';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function badgeEstado(status) {
    const map = {
        processing: ['badge-processing', 'En proceso'],
        completed:  ['badge-completed',  'Completado'],
        pending:    ['badge-pending',     'Pendiente'],
        'on-hold':  ['badge-on-hold',     'En espera'],
        cancelled:  ['badge-cancelled',   'Cancelado'],
        refunded:   ['badge-refunded',    'Reembolsado'],
        failed:     ['badge-failed',      'Fallido'],
    };
    const [cls, label] = map[status] || ['badge-refunded', status];
    return `<span class="badge ${cls}">${label}</span>`;
}

function formatFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
}

function formatMonto(val, sym) { return (sym || '$') + formatNum(val); }

function formatNum(val) {
    return parseFloat(val || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, isError = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className   = 'show' + (isError ? ' error' : '');
    setTimeout(() => { el.className = ''; }, 4000);
}
</script>
</body>
</html>
