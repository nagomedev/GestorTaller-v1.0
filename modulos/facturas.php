<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('facturas', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = '';
$ok = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$facturaId = (int) ($_GET['id'] ?? 0);
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

    if ($accion === 'crear_desde_orden') {
        require_permiso('facturas', 'crear');
        $orden_id = (int) ($_POST['orden_id'] ?? 0);
        // obtener orden
        $o = $conexion->query("SELECT o.*, c.id AS cliente_id FROM ordenes o LEFT JOIN vehiculos v ON v.id=o.vehiculo_id LEFT JOIN clientes c ON c.id=v.cliente_id WHERE o.id=$orden_id")->fetch_assoc();
        if (!$o || !$o['cliente_id']) {
            $error = 'No se puede facturar esta orden (falta cliente).';
        } else {
            // generar numero
            $num = $conexion->query("SELECT MAX(id)+1 as n FROM facturas")->fetch_assoc();
            $numero = 'FAC-' . str_pad($num['n'] ?? 1, 6, '0', STR_PAD_LEFT);
            $fecha = date('Y-m-d');
            $stmt = mysqli_prepare($conexion, "INSERT INTO facturas (numero, orden_id, cliente_id, fecha, subtotal, iva, total, estado, creado_por) VALUES (?,?,?,?,0,0,0,'pendiente',?)");
            mysqli_stmt_bind_param($stmt, "siiis", $numero, $orden_id, $o['cliente_id'], $fecha, $userId);
            mysqli_stmt_execute($stmt);
            $fid = mysqli_insert_id($conexion);
            // añadir línea por defecto
            $conexion->query("INSERT INTO facturas_lineas (factura_id, concepto, cantidad, precio_unitario, subtotal) VALUES ($fid, '".mysqli_real_escape_string($conexion, $o['descripcion'])."', 1, 0, 0)");
            volver('facturas.php?id=' . $fid, 'Factura creada desde orden.');
        }
    }

    if ($accion === 'crear_manual') {
        require_permiso('facturas', 'crear');
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        if ($cliente_id > 0) {
            $num = $conexion->query("SELECT MAX(id)+1 as n FROM facturas")->fetch_assoc();
            $numero = 'FAC-' . str_pad($num['n'] ?? 1, 6, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($conexion, "INSERT INTO facturas (numero, cliente_id, fecha, subtotal, iva, total, estado, creado_por) VALUES (?,?,?,?,0,0,0,'pendiente',?)");
            mysqli_stmt_bind_param($stmt, "sisi", $numero, $cliente_id, $fecha, $userId);
            mysqli_stmt_execute($stmt);
            volver('facturas.php?id=' . mysqli_insert_id($conexion), 'Factura creada.');
        } else {
            $error = 'Selecciona un cliente.';
        }
    }

    if ($accion === 'anadir_linea') {
        require_permiso('facturas', 'editar');
        $factura_id = (int) ($_POST['factura_id'] ?? 0);
        $concepto = trim($_POST['concepto'] ?? '');
        $cantidad = (float) str_replace(',', '.', $_POST['cantidad'] ?? '1');
        $precio = (float) str_replace(',', '.', $_POST['precio_unitario'] ?? '0');
        if ($concepto !== '' && $cantidad > 0) {
            $subtotal = $cantidad * $precio;
            $stmt = mysqli_prepare($conexion, "INSERT INTO facturas_lineas (factura_id, concepto, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "isddd", $factura_id, $concepto, $cantidad, $precio, $subtotal);
            mysqli_stmt_execute($stmt);
            // recalcular
            recalcular_factura($conexion, $factura_id);
            volver('facturas.php?id=' . $factura_id, 'Línea añadida.');
        }
        $error = 'Datos inválidos.';
        $facturaId = $factura_id;
    }

    if ($accion === 'borrar_linea') {
        require_permiso('facturas', 'editar');
        $linea_id = (int) ($_POST['linea_id'] ?? 0);
        $factura_id = (int) ($_POST['factura_id'] ?? 0);
        $stmt = mysqli_prepare($conexion, "DELETE FROM facturas_lineas WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $linea_id);
        mysqli_stmt_execute($stmt);
        recalcular_factura($conexion, $factura_id);
        volver('facturas.php?id=' . $factura_id, 'Línea eliminada.');
    }

    if ($accion === 'estado') {
        require_permiso('facturas', 'editar');
        $factura_id = (int) ($_POST['factura_id'] ?? 0);
        $estado = $_POST['estado'] ?? 'pendiente';
        if (in_array($estado, ['pendiente','pagada','anulada'])) {
            $conexion->query("UPDATE facturas SET estado='$estado' WHERE id=$factura_id");
            volver('facturas.php?id=' . $factura_id, 'Estado actualizado.');
        }
    }

    if ($accion === 'borrar') {
        require_permiso('facturas', 'borrar');
        $factura_id = (int) ($_POST['factura_id'] ?? 0);
        $conexion->query("DELETE FROM facturas WHERE id=$factura_id");
        volver('facturas.php', 'Factura eliminada.');
    }
}

function recalcular_factura($conexion, $factura_id) {
    $res = $conexion->query("SELECT SUM(subtotal) as s FROM facturas_lineas WHERE factura_id=$factura_id");
    $r = $res->fetch_assoc();
    $subtotal = (float) ($r['s'] ?? 0);
    $iva = $subtotal * 0.21;
    $total = $subtotal + $iva;
    $stmt = mysqli_prepare($conexion, "UPDATE facturas SET subtotal=?, iva=?, total=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "dddi", $subtotal, $iva, $total, $factura_id);
    mysqli_stmt_execute($stmt);
}

/* Datos */
if ($facturaId > 0) {
    $factura = $conexion->query("SELECT f.*, c.nombre AS cliente, c.nif, c.telefono, u.nombre AS creado_por FROM facturas f JOIN clientes c ON c.id=f.cliente_id LEFT JOIN usuarios u ON u.id=f.creado_por WHERE f.id=$facturaId")->fetch_assoc();
    $lineas = $conexion->query("SELECT * FROM facturas_lineas WHERE factura_id=$facturaId")->fetch_all(MYSQLI_ASSOC);
} else {
    $facturas = $conexion->query("SELECT f.*, c.nombre AS cliente FROM facturas f JOIN clientes c ON c.id=f.cliente_id ORDER BY f.fecha DESC, f.id DESC")->fetch_all(MYSQLI_ASSOC);
    $ordenes_listas = $conexion->query("SELECT o.id, o.matricula, o.descripcion FROM ordenes o WHERE o.estado='listo' AND NOT EXISTS (SELECT 1 FROM facturas f WHERE f.orden_id=o.id) ORDER BY o.id DESC")->fetch_all(MYSQLI_ASSOC);
    $clientes = $conexion->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facturación · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main>
        <?php if ($ok): ?><p class="mensaje mensaje--ok"><?= e($ok) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="mensaje mensaje--error"><?= e($error) ?></p><?php endif; ?>

        <?php if ($facturaId > 0 && isset($factura)): ?>
            <div class="seccion-cabecera">
                <h1><?= e($factura['numero']) ?></h1>
                <a href="facturas.php">← Volver a facturas</a>
            </div>
            <section class="panel" style="margin-bottom:1.5rem">
                <p><strong>Cliente:</strong> <?= e($factura['cliente']) ?> (NIF: <?= e($factura['nif'] ?? '—') ?>, Tel: <?= e($factura['telefono'] ?? '—') ?>)<br>
                <strong>Fecha:</strong> <?= e($factura['fecha']) ?><br>
                <strong>Creado por:</strong> <?= e($factura['creado_por'] ?? '—') ?><br>
                <strong>Estado:</strong> <?= e($factura['estado']) ?></p>
                <form method="POST" class="formulario-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="factura_id" value="<?= (int)$factura['id'] ?>">
                    <div style="flex-basis:200px">
                        <label>Cambiar estado</label>
                        <select name="estado">
                            <option value="pendiente" <?= $factura['estado']==='pendiente'?'selected':'' ?>>Pendiente</option>
                            <option value="pagada" <?= $factura['estado']==='pagada'?'selected':'' ?>>Pagada</option>
                            <option value="anulada" <?= $factura['estado']==='anulada'?'selected':'' ?>>Anulada</option>
                        </select>
                    </div>
                    <button type="submit">Guardar</button>
                </form>
            </section>

            <?php if ($factura['estado'] !== 'anulada' && puede('facturas','editar')): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Añadir línea</h2>
                <form method="POST" class="formulario-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="anadir_linea">
                    <input type="hidden" name="factura_id" value="<?= (int)$factura['id'] ?>">
                    <div style="flex:2"><label>Concepto</label><input type="text" name="concepto" required></div>
                    <div style="flex-basis:100px"><label>Cantidad</label><input type="number" step="0.01" name="cantidad" value="1"></div>
                    <div style="flex-basis:140px"><label>Precio unitario</label><input type="number" step="0.01" name="precio_unitario" required></div>
                    <button type="submit">Añadir</button>
                </form>
            </section>
            <?php endif; ?>

            <h2>Detalle</h2>
            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Concepto</th><th>Cantidad</th><th>Precio unit.</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lineas as $l): ?>
                        <tr>
                            <td><?= e($l['concepto']) ?></td>
                            <td><?= $l['cantidad'] ?></td>
                            <td><?= number_format($l['precio_unitario'], 2, ',', '.') ?> €</td>
                            <td><?= number_format($l['subtotal'], 2, ',', '.') ?> €</td>
                            <td>
                                <?php if ($factura['estado'] !== 'anulada' && puede('facturas','editar')): ?>
                                <form method="POST" onsubmit="return confirm('¿Quitar línea?')">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="borrar_linea">
                                    <input type="hidden" name="factura_id" value="<?= (int)$factura['id'] ?>">
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
            <section class="panel" style="margin-top:1rem; width:300px; margin-left:auto">
                <p style="text-align:right">Subtotal: <?= number_format($factura['subtotal'], 2, ',', '.') ?> €<br>
                IVA (21%): <?= number_format($factura['iva'], 2, ',', '.') ?> €<br>
                <strong>Total: <?= number_format($factura['total'], 2, ',', '.') ?> €</strong></p>
            </section>
            <?php if (puede('facturas','borrar')): ?>
            <form method="POST" onsubmit="return confirm('¿Borrar factura?')">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="borrar">
                <input type="hidden" name="factura_id" value="<?= (int)$factura['id'] ?>">
                <button type="submit">Borrar factura</button>
            </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="seccion-cabecera"><h1>Facturación</h1></div>
            <?php if (puede('facturas','crear')): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Crear factura</h2>
                <div style="display:flex; gap:2rem; flex-wrap:wrap">
                    <form method="POST" class="formulario-linea">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="crear_desde_orden">
                        <div style="flex-basis:320px">
                            <label>Desde orden lista (sin facturar)</label>
                            <select name="orden_id" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($ordenes_listas as $o): ?><option value="<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?> - <?= e($o['matricula']) ?> - <?= e($o['descripcion']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Crear desde orden</button>
                    </form>
                    <form method="POST" class="formulario-linea">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="crear_manual">
                        <div style="flex-basis:280px">
                            <label>Manual (seleccionar cliente)</label>
                            <select name="cliente_id" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex-basis:200px"><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>"></div>
                        <button type="submit">Crear manual</button>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Nº</th><th>Cliente</th><th>Fecha</th><th>Subtotal</th><th>IVA</th><th>Total</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($facturas as $f): ?>
                        <tr>
                            <td><a href="facturas.php?id=<?= (int)$f['id'] ?>"><?= e($f['numero']) ?></a></td>
                            <td><?= e($f['cliente']) ?></td>
                            <td><?= e($f['fecha']) ?></td>
                            <td><?= number_format($f['subtotal'], 2, ',', '.') ?> €</td>
                            <td><?= number_format($f['iva'], 2, ',', '.') ?> €</td>
                            <td><strong><?= number_format($f['total'], 2, ',', '.') ?> €</strong></td>
                            <td><?= e($f['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
