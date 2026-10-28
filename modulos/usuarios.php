<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('usuarios', 'ver');   // solo el administrador llega aquí
require __DIR__ . '/../bd/conexionbd.php';

$miId  = (int) $_SESSION['user_id'];
$error = '';

// Mensaje de éxito guardado antes de redirigir
$ok = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function volver(string $mensaje): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: usuarios.php');
    exit();
}

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    /* Crear usuario */
    if ($accion === 'crear') {
        require_permiso('usuarios', 'crear');
        $nombre   = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol      = $_POST['rol'] ?? '';

        if ($nombre === '' || $password === '' || $rol === '') {
            $error = 'Rellena todos los campos.';
        } elseif (!isset(ROLES[$rol])) {
            $error = 'El rol elegido no es válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE nombre = ?");
            mysqli_stmt_bind_param($stmt, "s", $nombre);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'Ese nombre de usuario ya existe.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, password, rol) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sss", $nombre, $hash, $rol);

                if (mysqli_stmt_execute($stmt)) {
                    volver('Usuario «' . $nombre . '» creado.');
                }
                error_log('Error al crear usuario: ' . mysqli_error($conexion));
                $error = 'No se pudo crear el usuario.';
            }
        }
    }

    /* Cambiar rol */
    if ($accion === 'rol') {
        require_permiso('usuarios', 'editar');
        $id  = (int) ($_POST['id'] ?? 0);
        $rol = $_POST['rol'] ?? '';

        if ($id === $miId) {
            $error = 'No puedes cambiar tu propio rol.';
        } elseif (!isset(ROLES[$rol])) {
            $error = 'El rol elegido no es válido.';
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET rol = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $rol, $id);
            mysqli_stmt_execute($stmt);
            volver('Rol actualizado.');
        }
    }

    /* Borrar usuario */
    if ($accion === 'borrar') {
        require_permiso('usuarios', 'borrar');
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $miId) {
            $error = 'No puedes borrar tu propio usuario.';
        } else {
            $stmt = mysqli_prepare($conexion, "DELETE FROM usuarios WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            volver('Usuario borrado.');
        }
    }
}

/* ---------- Listado ---------- */
$resultado = mysqli_query($conexion, "SELECT id, nombre, rol FROM usuarios ORDER BY nombre");
$usuarios  = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>

<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>

    <main>
        <div class="seccion-cabecera">
            <h1>Usuarios</h1>
        </div>

        <?php if ($ok): ?>
            <p class="mensaje mensaje--ok"><?= e($ok) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="mensaje mensaje--error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if (puede('usuarios', 'crear')): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Nuevo usuario</h2>
                <form method="POST" class="formulario-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="crear">
                    <div>
                        <label for="usuario">Usuario</label>
                        <input type="text" id="usuario" name="usuario" required autocomplete="off">
                    </div>
                    <div>
                        <label for="password">Contraseña (mín. 8 caracteres)</label>
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol" required>
                            <?php foreach (ROLES as $valor => $texto): ?>
                                <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Crear usuario</button>
                </form>
            </section>
        <?php endif; ?>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <?php if (puede('usuarios', 'borrar')): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u):
                        $esYo = ((int) $u['id'] === $miId);
                    ?>
                        <tr>
                            <td><?= e($u['nombre']) ?><?= $esYo ? ' (tú)' : '' ?></td>
                            <td>
                                <?php if (puede('usuarios', 'editar') && !$esYo): ?>
                                    <form method="POST">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="rol">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <select name="rol" aria-label="Rol de <?= e($u['nombre']) ?>">
                                            <?php foreach (ROLES as $valor => $texto): ?>
                                                <option value="<?= e($valor) ?>" <?= $u['rol'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Guardar</button>
                                    </form>
                                <?php else: ?>
                                    <?= e(ROLES[$u['rol']] ?? $u['rol']) ?>
                                <?php endif; ?>
                            </td>
                            <?php if (puede('usuarios', 'borrar')): ?>
                                <td>
                                    <?php if (!$esYo): ?>
                                        <form method="POST" onsubmit="return confirm('¿Borrar a <?= e(addslashes($u['nombre'])) ?>?')">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="borrar">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit">Borrar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>