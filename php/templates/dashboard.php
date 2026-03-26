<?php
/**
 * Template del Dashboard
 *
 * Variables disponibles:
 * - $clienteId: ID del cliente
 * - $userName: Nombre del usuario
 * - $apiKey: API Key del cliente
 */
?>
<div class="cards">
    <div class="card">
        <h2>📦 Estado API</h2>
        <div class="card-value" id="apiStatus">--</div>
    </div>
    <div class="card">
        <h2>⏱️ Próxima Sync</h2>
        <div class="card-value" id="nextSync">10:00</div>
    </div>
    <div class="card">
        <h2>🔄 Última Sync</h2>
        <div class="card-value" id="lastSync">--</div>
    </div>
</div>

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
