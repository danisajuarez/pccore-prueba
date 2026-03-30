<?php
// Sincronizador - No requiere login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar configuración básica sin verificar sesión
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    require_once __DIR__ . '/bootstrap.php';
    $appConfig = \App\Container::get(\App\Config\AppConfig::class);
    $CLIENTE_ID = $appConfig->getClienteId();
} else {
    // Fallback
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
        $CLIENTE_ID = strtolower($matches[1]);
    } elseif (isset($_GET['cliente'])) {
        $CLIENTE_ID = strtolower($_GET['cliente']);
    } else {
        $CLIENTE_ID = 'pccore';
    }
}

// API Key para el sincronizador
$API_KEY = $CLIENTE_ID . '-sync-2024';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Core - Sync Panel</title>
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

        .search-section {
            background: #1e293b;
            border-radius: 8px;
            padding: 14px 16px;
            border: 1px solid #334155;
            height: fit-content;
        }

        .search-section h2 {
            margin-bottom: 10px;
            font-size: 14px;
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

        button.secondary {
            background: #475569;
        }

        button.secondary:hover {
            background: #64748b;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 16px;
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
            max-height: 280px;
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
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <div class="logo"><?= htmlspecialchars(strtoupper($CLIENTE_ID)) ?> <span style="font-size: 10px; color: #64748b; font-weight: normal;">Sync Panel</span></div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="nav-links">
                    <a href="/" class="active">Sincronizador</a>
                    <a href="/api/login.php" target="_blank">Productos</a>
                </div>
                <div class="status" id="statusIndicator">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="statusText">Sistema</span>
                </div>
            </div>
        </header>

        <div class="main-grid">
            <div class="search-section">
                <h2>🔄 Sync Automática (BD → Woo)</h2>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button onclick="runAutoSync()" id="autoSyncBtn">Sincronizar Ahora</button>
                    <span id="autoSyncStatus" style="color: #94a3b8; font-size: 12px;"></span>
                </div>
            </div>

            <div class="log-section">
                <h2>Registro de Actividad</h2>
                <div class="logs" id="logsContainer">
                    <div class="log-entry">
                        <span class="time">--:--:--</span>
                        Esperando actividad...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?= $API_KEY ?>';
        const SYNC_INTERVAL = 10 * 60 * 1000; // 10 minutos en ms
                let countdown = 600; // 10 minutos en segundos
        let countdownInterval;
        let syncInterval;

        // Iniciar automáticamente
        window.onload = function() {
            checkHealth();
            startCountdown();
            // Primera sync automática al cargar
            setTimeout(() => runAutoSync(), 2000);
            // Sync cada 10 minutos
            syncInterval = setInterval(runAutoSync, SYNC_INTERVAL);
        };

        // Countdown timer
        function startCountdown() {
            countdown = 600;
            updateCountdownDisplay();
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                countdown--;
                if (countdown <= 0) countdown = 600;
                updateCountdownDisplay();
            }, 1000);
        }

        function updateCountdownDisplay() {
            // Cards eliminadas - no hacer nada
        }

        // Auto sync desde base de datos - procesa lotes hasta terminar
        let syncRunning = false;
        let totalSynced = 0;
        let totalNotInWoo = 0;
        let totalFailed = 0;

        async function runAutoSync() {
            if (syncRunning) return;
            syncRunning = true;

            const btn = document.getElementById('autoSyncBtn');
            const status = document.getElementById('autoSyncStatus');

            btn.disabled = true;
            totalSynced = 0;
            totalNotInWoo = 0;
            totalFailed = 0;

            addLog('🔄 Iniciando sincronización...', '');

            await processBatch(btn, status);
        }

        async function processBatch(btn, status) {
            btn.innerHTML = `<span class="spinner"></span>Sincronizando... (${totalSynced} procesados)`;

            try {
                const res = await fetch(`api/auto-sync.php?key=${API_KEY}`);
                const data = await res.json();

                if (data.success) {
                    if (data.message) {
                        // Sin cambios
                        addLog(`✓ ${data.message}`, 'success');
                        status.textContent = data.message;
                        status.style.color = '#22c55e';
                        finishSync(btn, status);
                        return;
                    }

                    // Acumular contadores
                    totalSynced += (data.successful || 0);
                    totalNotInWoo += (data.not_in_woo || 0);
                    totalFailed += (data.failed || 0);
                    const remaining = data.remaining || 0;

                    // Log del lote
                    addLog(`✓ Lote: ${data.successful || 0} OK, ${data.not_in_woo || 0} no en Woo | Pendientes: ${remaining}`, 'success');

                    // Log detalles resumidos (solo primeros 5)
                    const detalles = data.details || [];
                    detalles.slice(0, 5).forEach(r => {
                        if (r.status === 'updated') {
                            addLog(`  → ${r.sku}: $${r.price}`, 'success');
                        } else if (r.status === 'not_in_woo') {
                            addLog(`  → ${r.sku}: no en Woo`, '');
                        }
                    });
                    if (detalles.length > 5) {
                        addLog(`  ... y ${detalles.length - 5} más`, '');
                    }

                    // Si quedan pendientes, continuar
                    if (remaining > 0) {
                        status.textContent = `Procesando... ${totalSynced} sincronizados, ${remaining} pendientes`;
                        // Esperar 2 segundos entre lotes
                        await new Promise(r => setTimeout(r, 2000));
                        await processBatch(btn, status);
                    } else {
                        // Terminado
                        finishSync(btn, status);
                    }
                } else {
                    addLog(`✗ Error: ${data.error}`, 'error');
                    status.textContent = data.error;
                    status.style.color = '#ef4444';
                    finishSync(btn, status);
                }
            } catch (e) {
                addLog(`✗ Error: ${e.message}`, 'error');
                status.textContent = e.message;
                status.style.color = '#ef4444';
                finishSync(btn, status);
            }
        }

        function finishSync(btn, status) {
            syncRunning = false;
            btn.disabled = false;
            btn.textContent = 'Sincronizar Ahora';
            countdown = 600;

            if (totalSynced > 0 || totalNotInWoo > 0) {
                const finalMsg = `Completado: ${totalSynced} sync, ${totalNotInWoo} no en Woo, ${totalFailed} errores`;
                addLog(`🏁 ${finalMsg}`, 'success');
                status.textContent = finalMsg;
                status.style.color = '#22c55e';
            }
        }


        // Health check
        async function checkHealth() {
            try {
                const res = await fetch('api/health.php');
                const data = await res.json();

                if (data.status === 'ok') {
                    document.getElementById('statusDot').classList.remove('error');
                    document.getElementById('statusText').textContent = 'Conectado';
                    addLog('API conectada correctamente', 'success');
                }
            } catch (e) {
                document.getElementById('statusDot').classList.add('error');
                document.getElementById('statusText').textContent = 'Error';
                addLog('Error conectando a la API: ' + e.message, 'error');
            }
        }

        // Agregar log
        function addLog(message, type = '') {
            const container = document.getElementById('logsContainer');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `<span class="time">${time}</span>${message}`;
            container.insertBefore(entry, container.firstChild);

            // Mantener solo últimos 50 logs
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }
    </script>
</body>
</html>
