<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('vehiculos', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = '';
$ok = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$vehiculoId = (int) ($_GET['id'] ?? 0);
$busqueda = trim($_GET['q'] ?? '');

function volver(string $url, string $mensaje): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: ' . $url);
    exit();
}

function opcional(string $clave): ?string
{
    $v = trim($_POST[$clave] ?? '');
    return $v === '' ? null : $v;
}

/* Acciones POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'editar') {
        require_permiso('vehiculos', 'editar');
        $id = (int) ($_POST['id'] ?? 0);
        $matricula = strtoupper(preg_replace('/[\s-]/', '', $_POST['matricula'] ?? ''));
        $marca = opcional('marca');
        $modelo = opcional('modelo');
        $anioTexto = trim($_POST['anio'] ?? '');
        $anio = $anioTexto === '' ? null : (int) $anioTexto;

        if ($matricula === '') {
            $error = 'La matrícula es obligatoria.';
        } elseif ($anio !== null && ($anio < 1950 || $anio > (int)date('Y') + 1)) {
            $error = 'El año del vehículo no es válido.';
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE vehiculos SET matricula = ?, marca = ?, modelo = ?, anio = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssii", $matricula, $marca, $modelo, $anio, $id);
            mysqli_stmt_execute($stmt);
            volver('vehiculos.php?id=' . $id, 'Vehículo actualizado.');
        }
        $vehiculoId = $id;
    }

    if ($accion === 'borrar') {
        require_permiso('vehiculos', 'borrar');
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = mysqli_prepare($conexion, "DELETE FROM vehiculos WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            volver('vehiculos.php', 'Vehículo eliminado.');
        } catch (Throwable $e) {
            $error = 'No se puede borrar el vehículo (puede tener órdenes asociadas).';
            $vehiculoId = $id;
        }
    }
}

/* Datos */
$vehiculo = null;
$cliente = null;
$ordenes = [];

if ($vehiculoId > 0) {
    $stmt = mysqli_prepare($conexion, "SELECT v.*, c.nombre AS cliente_nombre, c.id AS cliente_id, c.telefono, c.email 
                                        FROM vehiculos v 
                                        JOIN clientes c ON c.id = v.cliente_id 
                                        WHERE v.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $vehiculoId);
    mysqli_stmt_execute($stmt);
    $vehiculo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($vehiculo) {
        $stmt = mysqli_prepare($conexion, "SELECT o.id, o.descripcion, o.estado, o.creada_en, u.nombre AS mecanico
                                            FROM ordenes o
                                            LEFT JOIN usuarios u ON u.id = o.mecanico_id
                                            WHERE o.vehiculo_id = ?
                                            ORDER BY o.creada_en DESC");
        mysqli_stmt_bind_param($stmt, "i", $vehiculoId);
        mysqli_stmt_execute($stmt);
        $ordenes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
} else {
    $sql = "SELECT v.id, v.matricula, v.marca, v.modelo, v.anio, c.id AS cliente_id, c.nombre AS cliente
            FROM vehiculos v
            JOIN clientes c ON c.id = v.cliente_id";
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        $stmt = mysqli_prepare($conexion, $sql . " WHERE v.matricula LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR c.nombre LIKE ?
                                                   ORDER BY v.matricula");
        mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);
    } else {
        $stmt = mysqli_prepare($conexion, $sql . " ORDER BY v.matricula");
    }
    mysqli_stmt_execute($stmt);
    $vehiculos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vehículos · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main>
        <?php if ($ok): ?><p class="mensaje mensaje--ok"><?= e($ok) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="mensaje mensaje--error"><?= e($error) ?></p><?php endif; ?>

        <?php if ($vehiculoId > 0): ?>
            <?php if (!$vehiculo): ?>
                <p class="mensaje mensaje--error">Ese vehículo no existe.</p>
                <a class="btn" href="vehiculos.php">Volver a vehículos</a>
            <?php else: ?>
                <div class="seccion-cabecera">
                    <h1><?= e($vehiculo['matricula']) ?></h1>
                    <a href="vehiculos.php">← Volver a vehículos</a>
                </div>

                <section class="panel" style="margin-bottom:1.5rem">
                    <h2>Datos del vehículo</h2>
                    <?php if (puede('vehiculos', 'editar')): ?>
                        <form method="POST" class="formulario-linea">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="editar">
                            <input type="hidden" name="id" value="<?= (int)$vehiculo['id'] ?>">
                            <div><label for="matricula">Matrícula</label><input type="text" id="matricula" name="matricula" required value="<?= e($vehiculo['matricula']) ?>"></div>
                            <div><label for="marca">Marca</label><input type="text" id="marca" name="marca" value="<?= e($vehiculo['marca']) ?>"></div>
                            <div><label for="modelo">Modelo</label><input type="text" id="modelo" name="modelo" value="<?= e($vehiculo['modelo']) ?>"></div>
                            <div style="flex-basis:110px"><label for="anio">Año</label><input type="number" id="anio" name="anio" min="1950" max="<?= (int)date('Y')+1 ?>" value="<?= e($vehiculo['anio']) ?>"></div>
                            <button type="submit">Guardar</button>
                        </form>
                    <?php else: ?>
                        <p>Marca/Modelo: <?= e(trim(($vehiculo['marca']??'').' '.($vehiculo['modelo']??''))) ?: '—' ?><br>Año: <?= e($vehiculo['anio'] ?? '—') ?></p>
                    <?php endif; ?>
                </section>

                <section class="panel" style="margin-bottom:1.5rem">
                    <h2>Propietario</h2>
                    <p><strong><a href="clientes.php?id=<?= (int)$vehiculo['cliente_id'] ?>"><?= e($vehiculo['cliente_nombre']) ?></a></strong><br>
                    Teléfono: <?= e($vehiculo['telefono'] ?? '—') ?><br>
                    Email: <?= e($vehiculo['email'] ?? '—') ?></p>
                </section>

                <h2>Historial de órdenes (<?= count($ordenes) ?>)</h2>
                <?php if (!$ordenes): ?>
                    <p class="mensaje">No hay órdenes para este vehículo.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table>
                            <thead><tr><th>Nº</th><th>Trabajo</th><th>Mecánico</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                            <?php foreach ($ordenes as $o): ?>
                                <tr>
                                    <td><a href="ordenes.php"><?= (int)$o['id'] ?></a></td>
                                    <td><?= e($o['descripcion']) ?></td>
                                    <td><?= e($o['mecanico'] ?? 'Sin asignar') ?></td>
                                    <td><span class="estado estado--<?= e(str_replace('_','-',$o['estado'])) ?>"><?= e($o['estado']) ?></span></td>
                                    <td><?= e(date('d/m/Y', strtotime($o['creada_en']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (puede('vehiculos', 'borrar')): ?>
                    <form method="POST" style="margin-top:2rem" onsubmit="return confirm('¿Borrar este vehículo? Si tiene órdenes no se podrá eliminar.')">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="borrar">
                        <input type="hidden" name="id" value="<?= (int)$vehiculo['id'] ?>">
                        <button type="submit">Borrar vehículo</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="seccion-cabecera"><h1>Vehículos</h1></div>
            <form method="GET" class="formulario-linea" style="margin-bottom:1.5rem">
                <div><label for="q">Buscar por matrícula, marca, modelo o cliente</label><input type="text" id="q" name="q" value="<?= e($busqueda) ?>"></div>
                <button type="submit">Buscar</button>
            </form>

            <?php if (!$vehiculos): ?>
                <p class="mensaje"><?= $busqueda !== '' ? 'Sin resultados.' : 'No hay vehículos registrados.' ?></p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead><tr><th>Matrícula</th><th>Marca/Modelo</th><th>Año</th><th>Cliente</th></tr></thead>
                        <tbody>
                        <?php foreach ($vehiculos as $v): 
                            $coche = trim(($v['marca']??'').' '.($v['modelo']??''));
                        ?>
                            <tr>
                                <td><a href="vehiculos.php?id=<?= (int)$v['id'] ?>"><strong><?= e($v['matricula']) ?></strong></a></td>
                                <td><?= e($coche ?: '—') ?></td>
                                <td><?= e($v['anio'] ?? '—') ?></td>
                                <td><a href="clientes.php?id=<?= (int)$v['cliente_id'] ?>"><?= e($v['cliente']) ?></a></td>
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
