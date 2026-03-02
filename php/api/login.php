<?php
require_once __DIR__ . '/../config.php';

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    $usuario = validateLogin($user, $pass);
    if ($usuario) {
        $_SESSION['logged_in'] = true;
        $_SESSION['cliente_id'] = $CLIENTE_ID;
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $usuario['USU_IDUsuario'];
        $_SESSION['user_nombre'] = $usuario['USU_DatosUsu'] ?? $user;
        header('Location: /');
        exit();
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

// Si ya está logueado, redirigir
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SESSION['cliente_id'] === $CLIENTE_ID) {
    header('Location: /');
    exit();
}

// No enviar headers JSON para esta página
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(strtoupper($CLIENTE_ID)) ?></title>
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

        .cliente-badge {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #334155;
        }

        .cliente-badge span {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Administrador</h1>
            <span>Sistema de Productos</span>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="?cliente=<?= htmlspecialchars($CLIENTE_ID) ?>">
            <div class="form-group">
                <label for="user">Usuario</label>
                <input type="text" id="user" name="user" placeholder="Ingresa tu usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="pass">Contraseña</label>
                <input type="password" id="pass" name="pass" placeholder="Ingresa tu contraseña" required>
            </div>

            <button type="submit">Iniciar Sesión</button>
        </form>

        <div class="cliente-badge">
            <span><?= htmlspecialchars(strtoupper($CLIENTE_ID)) ?></span>
        </div>
    </div>
</body>
</html>
