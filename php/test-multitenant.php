<?php
/**
 * Test Multi-tenant Login
 *
 * Prueba el nuevo flujo de autenticación:
 * 1. Login contra BD Master (sige_two_terwoo)
 * 2. Cargar config del cliente en sesión
 * 3. Conexión dinámica a BD SIGE del cliente
 */

// Cargar bootstrap (inicializa todo)
require_once __DIR__ . '/bootstrap.php';

use App\Container;
use App\Auth\SessionManager;
use App\Auth\AuthService;
use App\Database\DatabaseService;
use App\Database\MasterDatabase;

$auth = Container::get(AuthService::class);

$message = '';
$messageType = '';

// Procesar logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: test-multitenant.php');
    exit;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if ($auth->login($clienteId, $password)) {
        $message = "Login exitoso!";
        $messageType = 'success';
    } else {
        $message = "ID o password incorrectos";
        $messageType = 'error';
    }
}

// Probar conexión a BD SIGE si está logueado
$sigeTest = null;
if (isAuthenticated()) {
    try {
        $db = getSigeConnection();
        $result = $db->fetchOne("SELECT COUNT(*) as total FROM sige_art_articulo");
        $sigeTest = [
            'success' => true,
            'credentials' => $db->getCredentials(),
            'articulos' => $result['total'] ?? 0
        ];
    } catch (Exception $e) {
        $sigeTest = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Multi-tenant</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            padding: 40px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #3b82f6; margin-bottom: 20px; }
        h2 { color: #94a3b8; margin: 20px 0 10px; font-size: 14px; text-transform: uppercase; }

        .card {
            background: #1e293b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #334155;
        }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #94a3b8; }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #334155;
            border-radius: 4px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #2563eb; }
        button.logout { background: #ef4444; }

        .message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .message.success { background: #166534; color: #bbf7d0; }
        .message.error { background: #991b1b; color: #fecaca; }

        .config-table { width: 100%; border-collapse: collapse; }
        .config-table td {
            padding: 8px;
            border-bottom: 1px solid #334155;
        }
        .config-table td:first-child {
            color: #94a3b8;
            width: 200px;
        }

        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .status.ok { background: #166534; }
        .status.error { background: #991b1b; }

        pre {
            background: #0f172a;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Multi-tenant Login</h1>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!isAuthenticated()): ?>
            <!-- FORMULARIO DE LOGIN -->
            <div class="card">
                <h2>Iniciar Sesión</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>ID Cliente (TER_IdTercero)</label>
                        <input type="number" name="cliente_id" required placeholder="Ej: 1">
                    </div>
                    <div class="form-group">
                        <label>Password (TWO_Pass)</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit">Ingresar</button>
                </form>
            </div>

            <div class="card">
                <h2>Clientes en BD Master</h2>
                <p style="color: #94a3b8; margin-bottom: 10px;">Consultando sige_two_terwoo...</p>
                <?php
                try {
                    $conn = MasterDatabase::getConnection();
                    $result = $conn->query("SELECT TER_IdTercero, TER_RazonSocialTer, TWO_Activo, TWO_ServidorDBAnt FROM sige_two_terwoo ORDER BY TER_IdTercero");
                    if ($result && $result->num_rows > 0) {
                        echo "<table class='config-table'>";
                        echo "<tr><td><strong>ID</strong></td><td><strong>Nombre</strong></td><td><strong>Activo</strong></td><td><strong>Servidor SIGE</strong></td></tr>";
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['TER_IdTercero'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['TER_RazonSocialTer'] ?? 'Sin nombre') . "</td>";
                            echo "<td>" . ($row['TWO_Activo'] ?? 'N') . "</td>";
                            echo "<td>" . htmlspecialchars($row['TWO_ServidorDBAnt'] ?? 'No configurado') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p style='color: #fbbf24;'>No hay clientes registrados en sige_two_terwoo</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: #ef4444;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                ?>
            </div>

        <?php else: ?>
            <!-- SESIÓN ACTIVA -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0;">Sesión Activa</h2>
                    <a href="?logout=1"><button class="logout">Cerrar Sesión</button></a>
                </div>
            </div>

            <div class="card">
                <h2>Datos del Usuario</h2>
                <table class="config-table">
                    <?php $user = $auth->user(); ?>
                    <tr><td>ID</td><td><?= $user['id'] ?></td></tr>
                    <tr><td>Nombre</td><td><?= htmlspecialchars($user['nombre'] ?? 'N/A') ?></td></tr>
                    <tr><td>Cliente ID</td><td><?= $user['cliente_id'] ?></td></tr>
                </table>
            </div>

            <div class="card">
                <h2>Configuración del Cliente (en Sesión)</h2>
                <table class="config-table">
                    <?php
                    $config = getClienteConfig();
                    foreach ($config as $key => $value) {
                        if ($key === 'db_pass' || $key === 'woo_db_pass') {
                            $value = '********';
                        }
                        echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value ?? 'null') . "</td></tr>";
                    }
                    ?>
                </table>
            </div>

            <div class="card">
                <h2>Test Conexión BD SIGE</h2>
                <?php if ($sigeTest): ?>
                    <?php if ($sigeTest['success']): ?>
                        <p><span class="status ok">CONECTADO</span></p>
                        <table class="config-table" style="margin-top: 10px;">
                            <tr><td>Host</td><td><?= htmlspecialchars($sigeTest['credentials']['host']) ?></td></tr>
                            <tr><td>Base de datos</td><td><?= htmlspecialchars($sigeTest['credentials']['name']) ?></td></tr>
                            <tr><td>Puerto</td><td><?= $sigeTest['credentials']['port'] ?></td></tr>
                            <tr><td>Artículos encontrados</td><td><strong><?= number_format($sigeTest['articulos']) ?></strong></td></tr>
                        </table>
                    <?php else: ?>
                        <p><span class="status error">ERROR</span></p>
                        <pre><?= htmlspecialchars($sigeTest['error']) ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>$_SESSION (Debug)</h2>
                <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
