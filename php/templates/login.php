<?php
/**
 * Template de Login
 *
 * Variables disponibles:
 * - $clienteId: ID del cliente
 * - $error: Mensaje de error (opcional)
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(strtoupper($clienteId)) ?> - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: #1e293b;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid #334155;
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 {
            color: #3b82f6;
            font-size: 24px;
            margin-bottom: 8px;
            text-align: center;
        }
        .login-box .subtitle {
            color: #64748b;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            font-size: 14px;
            color: #e2e8f0;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #2563eb;
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1><?= htmlspecialchars(strtoupper($clienteId)) ?></h1>
        <p class="subtitle">Iniciar sesión en el panel de sincronización</p>

        <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="user">Usuario</label>
                <input type="text" id="user" name="user" required autofocus>
            </div>
            <div class="form-group">
                <label for="pass">Contraseña</label>
                <input type="password" id="pass" name="pass" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
