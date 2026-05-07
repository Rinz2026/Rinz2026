<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';

if (estaLogueado()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        if (login($username, $password, $conn)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Animeflix</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0f0f0f;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 40px 36px;
            width: 100%;
            max-width: 380px;
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: #dc2626;
            letter-spacing: 2px;
        }

        .logo p {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .field input {
            width: 100%;
            padding: 10px 14px;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color .2s;
        }

        .field input:focus {
            border-color: #dc2626;
        }

        .error {
            background: rgba(220,38,38,.1);
            border: 1px solid rgba(220,38,38,.3);
            border-radius: 8px;
            color: #f87171;
            font-size: 13px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }

        .btn {
            width: 100%;
            padding: 11px;
            background: #dc2626;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .btn:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <h1>RINZ</h1>
            <p>Panel de administración</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Usuario</label>
                <input type="text" name="username" autocomplete="username" required>
            </div>
            <div class="field">
                <label>Contraseña</label>
                <input type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>