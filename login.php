<?php
// Lógica primero: nada de HTML antes de session_start() / header()
session_start();

// Si ya hay sesión, directo al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require __DIR__ . '/bd/conexionbd.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['usuario'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conexion, "SELECT id, nombre, password, rol FROM usuarios WHERE nombre = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);

    if ($fila && password_verify($password_raw, $fila['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = $fila['id'];
        $_SESSION['username'] = $fila['nombre'];
        $_SESSION['rol']      = $fila['rol'];
        header('Location: index.php');
        exit();
    }

    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestor del Taller</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body>
    <div class="tarjeta">
        <h1>Iniciar sesión</h1>

        <?php require __DIR__ . '/includes/cabecera.php'; ?>

        <?php if ($error): ?>
            <p class="mensaje mensaje--error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST" class="formularioLogin">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autofocus>
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Ingresar</button>
        </form>

        <p class="enlace">¿Sin acceso? Pídeselo a un administrador.</p>
    </div>
</body>

</html>