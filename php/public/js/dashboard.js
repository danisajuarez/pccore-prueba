/**
 * Dashboard - JavaScript
 * Extraído de index.php
 */

// Variables globales (API_KEY se define en el HTML)
const SYNC_INTERVAL = 10 * 60 * 1000; // 10 minutos en ms
let countdown = 600; // 10 minutos en segundos
let countdownInterval;
let syncInterval;
let syncRunning = false;
let totalSynced = 0;
let totalNotInWoo = 0;
let totalFailed = 0;

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
    const mins = Math.floor(countdown / 60);
    const secs = countdown % 60;
    document.getElementById('nextSync').textContent =
        `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Auto sync desde base de datos - procesa lotes hasta terminar
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
    document.getElementById('lastSync').textContent = new Date().toLocaleTimeString();
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
            document.getElementById('apiStatus').textContent = 'Online';
            addLog('API conectada correctamente', 'success');
        }
    } catch (e) {
        document.getElementById('statusDot').classList.add('error');
        document.getElementById('statusText').textContent = 'Error';
        document.getElementById('apiStatus').textContent = 'Offline';
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
