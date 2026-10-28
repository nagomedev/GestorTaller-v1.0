<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('clientes', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

$error = '';
$ok    = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Si viene ?id= se muestra la ficha de ese cliente; si no, el listado
$clienteId = (int) ($_GET['id'] ?? 0);

function volver(string $url, string $mensaje): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: ' . $url);
    exit();
}

/** Devuelve null si el valor está vacío, o el texto recortado. */
function opcional(string $clave): ?string
{
    $v = trim($_POST[$clave] ?? '');
    return $v === '' ? null : $v;
}

/** Valida los datos del cliente. Devuelve un mensaje de error o ''. */
function validar_cliente(string $nombre, ?string $email): string
{
    if ($nombre === '') {
        return 'El nombre del cliente es obligatorio.';
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El email no tiene un formato válido.';
    }
    return '';
}

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    /* Crear cliente */
    if ($accion === 'crear_cliente') {
        require_permiso('clientes', 'crear');
        $nombre   = trim($_POST['nombre'] ?? '');
        $nif      = opcional('nif');
        $telefono = opcional('telefono');
        $email    = opcional('email');

        $error = validar_cliente($nombre, $email);
        if ($error === '') {
            $stmt = mysqli_prepare($conexion, "INSERT INTO clientes (nombre, nif, telefono, email) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $nombre, $nif, $telefono, $email);
            mysqli_stmt_execute($stmt);
            volver('clientes.php?id=' . mysqli_insert_id($conexion), 'Cliente creado. Ya puedes añadirle vehículos.');
        }
    }

    /* Editar cliente */
    if ($accion === 'editar_cliente') {
        require_permiso('clientes', 'editar');
        $id       = (int) ($_POST['id'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $nif      = opcional('nif');
        $telefono = opcional('telefono');
        $email    = opcional('email');

        $clienteId = $id;
        $error = validar_cliente($nombre, $email);
        if ($error === '') {
            $stmt = mysqli_prepare($conexion, "UPDATE clientes SET nombre = ?, nif = ?, telefono = ?, email = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $nif, $telefono, $email, $id);
            mysqli_stmt_execute($stmt);
            volver('clientes.php?id=' . $id, 'Datos del cliente guardados.');
        }
    }

    /* Borrar cliente (borra también sus vehículos) */
    if ($accion === 'borrar_cliente') {
        require_permiso('clientes', 'borrar');
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conexion, "DELETE FROM clientes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        volver('clientes.php', 'Cliente borrado.');
    }

    /* Añadir vehículo a un cliente */
    if ($accion === 'crear_vehiculo') {
        require_permiso('vehiculos', 'crear');
        $id        = (int) ($_POST['cliente_id'] ?? 0);
        $matricula = strtoupper(preg_replace('/[\s-]/', '', $_POST['matricula'] ?? ''));
        $marca     = opcional('marca');
        $modelo    = opcional('modelo');
        $anioTexto = trim($_POST['anio'] ?? '');
        $anio      = $anioTexto === '' ? null : (int) $anioTexto;

        $clienteId = $id;
        if ($matricula === '') {
            $error = 'La matrícula es obligatoria.';
        } elseif ($anio !== null && ($anio < 1950 || $anio > (int) date('Y') + 1)) {
            $error = 'El año del vehículo no es válido.';
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT id FROM vehiculos WHERE matricula = ?");
            mysqli_stmt_bind_param($stmt, "s", $matricula);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'Ya existe un vehículo con la matrícula ' . $matricula . '.';
            } else {
                $stmt = mysqli_prepare($conexion, "INSERT INTO vehiculos (cliente_id, matricula, marca, modelo, anio) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "isssi", $id, $matricula, $marca, $modelo, $anio);
                mysqli_stmt_execute($stmt);
                volver('clientes.php?id=' . $id, 'Vehículo ' . $matricula . ' añadido.');
            }
        }
    }

    /* Quitar vehículo (solo si pertenece a ese cliente) */
    if ($accion === 'borrar_vehiculo') {
        require_permiso('vehiculos', 'borrar');
        $id        = (int) ($_POST['id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $stmt = mysqli_prepare($conexion, "DELETE FROM vehiculos WHERE id = ? AND cliente_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $clienteId);
        mysqli_stmt_execute($stmt);
        volver('clientes.php?id=' . $clienteId, 'Vehículo eliminado.');
    }
}

/* ---------- Datos para pintar la página ---------- */
$cliente   = null;
$vehiculos = [];
$clientes  = [];
$busqueda  = trim($_GET['q'] ?? '');
$verCoches = puede('vehiculos', 'ver');

if ($clienteId > 0) {
    $stmt = mysqli_prepare($conexion, "SELECT id, nombre, nif, telefono, email FROM clientes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $clienteId);
    mysqli_stmt_execute($stmt);
    $cliente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($cliente && $verCoches) {
        $stmt = mysqli_prepare($conexion, "SELECT id, matricula, marca, modelo, anio FROM vehiculos WHERE cliente_id = ? ORDER BY matricula");
        mysqli_stmt_bind_param($stmt, "i", $clienteId);
        mysqli_stmt_execute($stmt);
        $vehiculos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
} else {
    $sql = "SELECT c.id, c.nombre, c.nif, c.telefono, c.email,
                   (SELECT COUNT(*) FROM vehiculos v WHERE v.cliente_id = c.id) AS coches
            FROM clientes c";

    if ($busqueda !== '') {
        // Busca por nombre, NIF, teléfono o matrícula de alguno de sus coches
        $like = '%' . $busqueda . '%';
        $stmt = mysqli_prepare($conexion, $sql . "
            WHERE c.nombre LIKE ? OR c.nif LIKE ? OR c.telefono LIKE ?
               OR EXISTS (SELECT 1 FROM vehiculos v WHERE v.cliente_id = c.id AND v.matricula LIKE ?)
            ORDER BY c.nombre");
        mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);
    } else {
        $stmt = mysqli_prepare($conexion, $sql . " ORDER BY c.nombre");
    }
    mysqli_stmt_execute($stmt);
    $clientes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clientes · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>

<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>

    <main>
        <?php if ($ok): ?>
            <p class="mensaje mensaje--ok"><?= e($ok) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="mensaje mensaje--error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($clienteId > 0): ?>

            <?php if (!$cliente): ?>
                <p class="mensaje mensaje--error">Ese cliente no existe.</p>
                <a class="btn" href="clientes.php">Volver a clientes</a>

            <?php else: ?>
                <!-- ================= FICHA DEL CLIENTE ================= -->
                <div class="seccion-cabecera">
                    <h1><?= e($cliente['nombre']) ?></h1>
                    <a href="clientes.php">← Volver a clientes</a>
                </div>

                <section class="panel" style="margin-bottom:1.5rem">
                    <h2>Datos del cliente</h2>
                    <?php if (puede('clientes', 'editar')): ?>
                        <form method="POST" class="formulario-linea">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="editar_cliente">
                            <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                            <div>
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" required value="<?= e($cliente['nombre']) ?>">
                            </div>
                            <div>
                                <label for="nif">NIF</label>
                                <input type="text" id="nif" name="nif" value="<?= e($cliente['nif']) ?>">
                            </div>
                            <div>
                                <label for="telefono">Teléfono</label>
                                <input type="text" id="telefono" name="telefono" value="<?= e($cliente['telefono']) ?>">
                            </div>
                            <div>
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?= e($cliente['email']) ?>">
                            </div>
                            <button type="submit">Guardar cambios</button>
                        </form>
                    <?php else: ?>
                        <p>NIF: <?= e($cliente['nif'] ?? '—') ?><br>
                            Teléfono: <?= e($cliente['telefono'] ?? '—') ?><br>
                            Email: <?= e($cliente['email'] ?? '—') ?></p>
                    <?php endif; ?>
                </section>

                <?php if ($verCoches): ?>
                    <!-- ================= VEHÍCULOS DEL CLIENTE ================= -->
                    <h2>Vehículos (<?= count($vehiculos) ?>)</h2>

                    <?php if (puede('vehiculos', 'crear')): ?>
                        <section class="panel" style="margin-bottom:1rem">
                            <form method="POST" class="formulario-linea">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="crear_vehiculo">
                                <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
                                <div>
                                    <label for="matricula">Matrícula</label>
                                    <input type="text" id="matricula" name="matricula" required>
                                </div>
                                <div>
                                    <label for="marca">Marca</label>
                                    <input type="text" id="marca" name="marca">
                                </div>
                                <div>
                                    <label for="modelo">Modelo</label>
                                    <input type="text" id="modelo" name="modelo">
                                </div>
                                <div style="flex-basis:110px">
                                    <label for="anio">Año</label>
                                    <input type="number" id="anio" name="anio" min="1950" max="<?= (int) date('Y') + 1 ?>">
                                </div>
                                <button type="submit">Añadir vehículo</button>
                            </form>
                        </section>
                    <?php endif; ?>

                    <?php if (!$vehiculos): ?>
                        <p class="mensaje">Este cliente todavía no tiene vehículos.</p>
                    <?php else: ?>
                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Matrícula</th>
                                        <th>Marca</th>
                                        <th>Modelo</th>
                                        <th>Año</th>
                                        <?php if (puede('vehiculos', 'borrar')): ?><th></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehiculos as $v): ?>
                                        <tr>
                                            <td><?= e($v['matricula']) ?></td>
                                            <td><?= e($v['marca'] ?? '—') ?></td>
                                            <td><?= e($v['modelo'] ?? '—') ?></td>
                                            <td><?= e($v['anio'] !== null ? (string) $v['anio'] : '—') ?></td>
                                            <?php if (puede('vehiculos', 'borrar')): ?>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('¿Quitar el vehículo <?= e($v['matricula']) ?>?')">
                                                        <?= csrf_campo() ?>
                                                        <input type="hidden" name="accion" value="borrar_vehiculo">
                                                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                                        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
                                                        <button type="submit">Quitar</button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (puede('clientes', 'borrar')): ?>
                    <form method="POST" style="margin-top:2rem"
                        onsubmit="return confirm('¿Borrar a este cliente y todos sus vehículos? No se puede deshacer.')">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="borrar_cliente">
                        <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                        <button type="submit">Borrar cliente</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <!-- ================= LISTADO DE CLIENTES ================= -->
            <div class="seccion-cabecera">
                <h1>Clientes</h1>
            </div>

            <form method="GET" class="formulario-linea" style="margin-bottom:1.5rem">
                <div>
                    <label for="q">Buscar por nombre, NIF, teléfono o matrícula</label>
                    <input type="text" id="q" name="q" value="<?= e($busqueda) ?>">
                </div>
                <button type="submit">Buscar</button>
            </form>

            <?php if (puede('clientes', 'crear')): ?>
                <section class="panel" style="margin-bottom:1.5rem">
                    <h2>Nuevo cliente</h2>
                    <form method="POST" class="formulario-linea">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="crear_cliente">
                        <div>
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        <div>
                            <label for="nif">NIF</label>
                            <input type="text" id="nif" name="nif">
                        </div>
                        <div>
                            <label for="telefono">Teléfono</label>
                            <input type="text" id="telefono" name="telefono">
                        </div>
                        <div>
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>
                        <button type="submit">Crear cliente</button>
                    </form>
                </section>
            <?php endif; ?>

            <?php if (!$clientes): ?>
                <p class="mensaje">
                    <?= $busqueda !== '' ? 'Ningún cliente coincide con la búsqueda.' : 'Todavía no hay clientes.' ?>
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>NIF</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <?php if ($verCoches): ?><th>Vehículos</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                                <tr>
                                    <td><a href="clientes.php?id=<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></a></td>
                                    <td><?= e($c['nif'] ?? '—') ?></td>
                                    <td><?= e($c['telefono'] ?? '—') ?></td>
                                    <td><?= e($c['email'] ?? '—') ?></td>
                                    <?php if ($verCoches): ?><td><?= (int) $c['coches'] ?></td><?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>

</html>