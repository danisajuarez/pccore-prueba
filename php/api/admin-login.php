<?php
/**
 * Login Admin Productos - Multi-tenant
 *
 * Valida usuario contra BD SIGE del cliente (sige_usu_usuario)
 * Requiere que el cliente ya esté autenticado (login master previo)
 */

require_once __DIR__ . '/../bootstrap.php';

// Primero debe estar logueado como cliente
if (!isAuthenticated()) {
    header('Location: /api/login.php');
    exit();
}

$clienteConfig = getClienteConfig();
$clienteNombre = $clienteConfig['nombre'] ?? 'Sistema';
$error = '';

// Si ya está logueado como admin, redirigir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /api/admin-productos.php');
    exit();
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario)) {
        $error = 'Ingresá tu usuario';
    } elseif (empty($password)) {
        $error = 'Ingresá tu contraseña';
    } else {
        try {
            $db = getSigeConnection();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("
                SELECT USU_IDUsuario, USU_LogUsu, USU_DatosUsu, USU_Habilitado
                FROM sige_usu_usuario
                WHERE USU_LogUsu = ? AND USU_PassWord = ?
            ");

            $stmt->bind_param('ss', $usuario, $password);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if ($row['USU_Habilitado'] !== 'S') {
                    $error = 'Usuario deshabilitado';
                } else {
                    // Login exitoso - guardar en sesión
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $row['USU_IDUsuario'];
                    $_SESSION['admin_user'] = $row['USU_LogUsu'];
                    $_SESSION['admin_user_nombre'] = $row['USU_DatosUsu'];

                    header('Location: /api/admin-productos.php');
                    exit();
                }
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }

            $stmt->close();
        } catch (Exception $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= htmlspecialchars($clienteNombre) ?></title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: #1e293b;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #334155;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 24px;
            color: #3b82f6;
            margin-bottom: 5px;
        }

        .logo span {
            font-size: 12px;
            color: #64748b;
        }

        .cliente-badge {
            background: #334155;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: #94a3b8;
            display: inline-block;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            font-size: 14px;
            color: #e2e8f0;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        input::placeholder {
            color: #64748b;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #2563eb;
        }

        .error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .help-text {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            font-size: 12px;
            color: #64748b;
        }

        .help-text a {
            color: #3b82f6;
            text-decoration: none;
        }

        .help-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Admin Productos</h1>
            <span>Ingresá tus credenciales de SIGE</span>
            <div class="cliente-badge"><?= htmlspecialchars($clienteNombre) ?></div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text"
                       id="usuario"
                       name="usuario"
                       placeholder="Tu usuario de SIGE"
                       required
                       autofocus
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password"
                       id="password"
                       name="password"
                       placeholder="Tu contraseña de SIGE"
                       required>
            </div>

            <button type="submit">Ingresar</button>
        </form>

        <div class="help-text">
            <a href="/">Volver al Sincronizador</a>
        </div>
    </div>
</body>
</html>
