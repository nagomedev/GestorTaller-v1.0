<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('pedidos', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = '';
$ok = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$pedidoId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

function volver(string $url, string $mensaje): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: ' . $url);
    exit();
}

/* Acciones */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        require_permiso('pedidos', 'crear');
        $proveedor = trim($_POST['proveedor'] ?? '');
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $observaciones = trim($_POST['observaciones'] ?? '') ?: null;
        if ($proveedor === '') {
            $error = 'El proveedor es obligatorio.';
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO pedidos (proveedor, fecha, estado, creado_por) VALUES (?,?, 'pendiente', ?)");
            mysqli_stmt_bind_param($stmt, "ssi", $proveedor, $fecha, $userId);
            mysqli_stmt_execute($stmt);
            volver('pedidos.php?id=' . mysqli_insert_id($conexion), 'Pedido creado.');
        }
    }

    if ($accion === 'anadir_linea') {
        require_permiso('pedidos', 'editar');
        $id = (int) ($_POST['pedido_id'] ?? 0);
        $recambio_id = (int) ($_POST['recambio_id'] ?? 0);
        $cantidad = (int) ($_POST['cantidad'] ?? 1);
        $precio = (float) str_replace(',', '.', $_POST['precio_unitario'] ?? '0');
        if ($recambio_id > 0 && $cantidad > 0) {
            $subtotal = $cantidad * $precio;
            $stmt = mysqli_prepare($conexion, "INSERT INTO pedidos_lineas (pedido_id, recambio_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "iiidd", $id, $recambio_id, $cantidad, $precio, $subtotal);
            mysqli_stmt_execute($stmt);
            // actualizar total
            $conexion->query("UPDATE pedidos p SET total = (SELECT COALESCE(SUM(subtotal),0) FROM pedidos_lineas WHERE pedido_id=p.id) WHERE p.id = $id");
            volver('pedidos.php?id=' . $id, 'Línea añadida.');
        }
        $error = 'Datos inválidos.';
        $pedidoId = $id;
    }

    if ($accion === 'borrar_linea') {
        require_permiso('pedidos', 'editar');
        $linea_id = (int) ($_POST['linea_id'] ?? 0);
        $id = (int) ($_POST['pedido_id'] ?? 0);
        $stmt = mysqli_prepare($conexion, "DELETE FROM pedidos_lineas WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $linea_id);
        mysqli_stmt_execute($stmt);
        $conexion->query("UPDATE pedidos p SET total = (SELECT COALESCE(SUM(subtotal),0) FROM pedidos_lineas WHERE pedido_id=p.id) WHERE p.id = $id");
        volver('pedidos.php?id=' . $id, 'Línea eliminada.');
    }

    if ($accion === 'recibir') {
        require_permiso('pedidos', 'editar');
        $id = (int) ($_POST['pedido_id'] ?? 0);
        // obtener pedido
        $p = $conexion->query("SELECT estado FROM pedidos WHERE id=$id")->fetch_assoc();
        if ($p && $p['estado'] === 'pendiente') {
            mysqli_begin_transaction($conexion);
            try {
                // sumar stock
                $res = $conexion->query("SELECT recambio_id, cantidad FROM pedidos_lineas WHERE pedido_id=$id");
                while ($l = $res->fetch_assoc()) {
                    $stmt = mysqli_prepare($conexion, "UPDATE recambios SET stock = stock + ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ii", $l['cantidad'], $l['recambio_id']);
                    mysqli_stmt_execute($stmt);
                }
                $conexion->query("UPDATE pedidos SET estado='recibido' WHERE id=$id");
                mysqli_commit($conexion);
                volver('pedidos.php?id=' . $id, 'Pedido recibido. Stock actualizado.');
            } catch (Throwable $e) {
                mysqli_rollback($conexion);
                $error = 'Error al recibir el pedido.';
                $pedidoId = $id;
            }
        } else {
            $error = 'Solo se pueden recibir pedidos pendientes.';
            $pedidoId = $id;
        }
    }

    if ($accion === 'cancelar') {
        require_permiso('pedidos', 'editar');
        $id = (int) ($_POST['pedido_id'] ?? 0);
        $conexion->query("UPDATE pedidos SET estado='cancelado' WHERE id=$id AND estado='pendiente'");
        volver('pedidos.php?id=' . $id, 'Pedido cancelado.');
    }
}

/* Datos */
if ($pedidoId > 0) {
    $pedido = $conexion->query("SELECT p.*, u.nombre AS creado_por FROM pedidos p LEFT JOIN usuarios u ON u.id=p.creado_por WHERE p.id=$pedidoId")->fetch_assoc();
    $lineas = $conexion->query("SELECT pl.*, r.referencia, r.descripcion FROM pedidos_lineas pl JOIN recambios r ON r.id=pl.recambio_id WHERE pl.pedido_id=$pedidoId")->fetch_all(MYSQLI_ASSOC);
    $recambios = $conexion->query("SELECT id, referencia, descripcion, precio_compra FROM recambios ORDER BY referencia")->fetch_all(MYSQLI_ASSOC);
} else {
    $pedidos = $conexion->query("SELECT p.*, u.nombre AS creado_por FROM pedidos p LEFT JOIN usuarios u ON u.id=p.creado_por ORDER BY p.fecha DESC, p.id DESC")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedidos · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main>
        <?php if ($ok): ?><p class="mensaje mensaje--ok"><?= e($ok) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="mensaje mensaje--error"><?= e($error) ?></p><?php endif; ?>

        <?php if ($pedidoId > 0 && isset($pedido)): ?>
            <div class="seccion-cabecera">
                <h1>Pedido #<?= (int)$pedido['id'] ?></h1>
                <a href="pedidos.php">← Volver a pedidos</a>
            </div>
            <section class="panel" style="margin-bottom:1.5rem">
                <p><strong>Proveedor:</strong> <?= e($pedido['proveedor']) ?><br>
                <strong>Fecha:</strong> <?= e($pedido['fecha']) ?><br>
                <strong>Estado:</strong> <?= e($pedido['estado']) ?><br>
                <strong>Creado por:</strong> <?= e($pedido['creado_por'] ?? '—') ?><br>
                <strong>Observaciones:</strong> <?= e($pedido['observaciones'] ?? '—') ?><br>
                <strong>Total:</strong> <?= number_format($pedido['total'], 2, ',', '.') ?> €</p>
                <?php if ($pedido['estado'] === 'pendiente'): ?>
                    <div style="display:flex; gap:.5rem">
                        <form method="POST"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="accion" value="recibir"><input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>"><button type="submit">Marcar recibido (sumar stock)</button></form>
                        <form method="POST" onsubmit="return confirm('¿Cancelar pedido?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>"><button type="submit">Cancelar</button></form>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($pedido['estado'] === 'pendiente' && puede('pedidos','editar')): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Añadir línea</h2>
                <form method="POST" class="formulario-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="anadir_linea">
                    <input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>">
                    <div style="flex:2">
                        <label>Recambio</label>
                        <select name="recambio_id" required>
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($recambios as $rc): ?><option value="<?= (int)$rc['id'] ?>"><?= e($rc['referencia']) ?> - <?= e($rc['descripcion']) ?> (P.compra: <?= $rc['precio_compra'] ?>€)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex-basis:100px"><label>Cantidad</label><input type="number" name="cantidad" value="1" min="1"></div>
                    <div style="flex-basis:140px"><label>Precio unitario</label><input type="number" step="0.01" name="precio_unitario" required></div>
                    <button type="submit">Añadir</button>
                </form>
            </section>
            <?php endif; ?>

            <h2>Líneas</h2>
            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Ref.</th><th>Descripción</th><th>Cantidad</th><th>Precio unit.</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lineas as $l): ?>
                        <tr>
                            <td><?= e($l['referencia']) ?></td>
                            <td><?= e($l['descripcion']) ?></td>
                            <td><?= (int)$l['cantidad'] ?></td>
                            <td><?= number_format($l['precio_unitario'], 2, ',', '.') ?> €</td>
                            <td><?= number_format($l['subtotal'], 2, ',', '.') ?> €</td>
                            <td>
                                <?php if ($pedido['estado'] === 'pendiente' && puede('pedidos','editar')): ?>
                                <form method="POST" onsubmit="return confirm('¿Quitar línea?')">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="borrar_linea">
                                    <input type="hidden" name="pedido_id" value="<?= (int)$pedido['id'] ?>">
                                    <input type="hidden" name="linea_id" value="<?= (int)$l['id'] ?>">
                                    <button type="submit">Quitar</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="seccion-cabecera"><h1>Pedidos de recambios</h1></div>
            <?php if (puede('pedidos','crear')): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Nuevo pedido</h2>
                <form method="POST" class="formulario-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="crear">
                    <div><label>Proveedor</label><input type="text" name="proveedor" required></div>
                    <div style="flex-basis:200px"><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>"></div>
                    <div style="flex:2"><label>Observaciones</label><input type="text" name="observaciones"></div>
                    <button type="submit">Crear pedido</button>
                </form>
            </section>
            <?php endif; ?>

            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Nº</th><th>Proveedor</th><th>Fecha</th><th>Estado</th><th>Total</th><th>Creado por</th></tr></thead>
                    <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><a href="pedidos.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a></td>
                            <td><?= e($p['proveedor']) ?></td>
                            <td><?= e($p['fecha']) ?></td>
                            <td><?= e($p['estado']) ?></td>
                            <td><?= number_format($p['total'], 2, ',', '.') ?> €</td>
                            <td><?= e($p['creado_por'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
