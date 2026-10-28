<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('recambios', 'ver');

// Antes de la conexión, para que también afecte a los errores de conexión
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../bd/conexionbd.php';

$error = '';
$ok = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$busqueda = trim($_GET['q'] ?? '');
$url_pagina = 'recambios.php' . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '');

/* Valores para repoblar el formulario de creación si hay error */
$nuevo = [
    'referencia' => '', 'descripcion' => '', 'precio_compra' => '0',
    'precio_venta' => '0', 'stock' => '0', 'stock_minimo' => '0', 'proveedor' => '',
];

function volver(string $mensaje, string $url): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: ' . $url);
    exit();
}

function leer_recambio(): array
{
    $num = fn(string $k): float => (float) str_replace(',', '.', $_POST[$k] ?? '0');
    return [
        'referencia'    => strtoupper(trim($_POST['referencia'] ?? '')),
        'descripcion'   => trim($_POST['descripcion'] ?? ''),
        'precio_compra' => $num('precio_compra'),
        'precio_venta'  => $num('precio_venta'),
        'stock'         => (int) ($_POST['stock'] ?? 0),
        'stock_minimo'  => (int) ($_POST['stock_minimo'] ?? 0),
        'proveedor'     => trim($_POST['proveedor'] ?? '') ?: null,
    ];
}

function validar_recambio(array $d): string
{
    if ($d['referencia'] === '' || $d['descripcion'] === '') {
        return 'Referencia y descripción son obligatorios.';
    }
    if ($d['precio_compra'] < 0 || $d['precio_venta'] < 0 || $d['stock'] < 0 || $d['stock_minimo'] < 0) {
        return 'Precios y stock no pueden ser negativos.';
    }
    return '';
}

/* Acciones */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        require_permiso('recambios', 'crear');
        $d = leer_recambio();
        $error = validar_recambio($d);

        if ($error === '') {
            try {
                $stmt = mysqli_prepare($conexion, "INSERT INTO recambios (referencia, descripcion, precio_compra, precio_venta, stock, stock_minimo, proveedor) VALUES (?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, "ssddiis",
                    $d['referencia'], $d['descripcion'], $d['precio_compra'],
                    $d['precio_venta'], $d['stock'], $d['stock_minimo'], $d['proveedor']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                volver('Recambio añadido.', $url_pagina);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $error = 'Ya existe un recambio con esa referencia.';
                } else {
                    throw $e;
                }
            }
        }

        // Si llegamos aquí hubo error: conservar lo que escribió el usuario
        $nuevo = [
            'referencia'    => $d['referencia'],
            'descripcion'   => $d['descripcion'],
            'precio_compra' => (string) $d['precio_compra'],
            'precio_venta'  => (string) $d['precio_venta'],
            'stock'         => (string) $d['stock'],
            'stock_minimo'  => (string) $d['stock_minimo'],
            'proveedor'     => $d['proveedor'] ?? '',
        ];
    }

    if ($accion === 'editar') {
        require_permiso('recambios', 'editar');
        $id = (int) ($_POST['id'] ?? 0);
        $d = leer_recambio();
        $error = validar_recambio($d);

        if ($error === '') {
            try {
                $stmt = mysqli_prepare($conexion, "UPDATE recambios SET referencia=?, descripcion=?, precio_compra=?, precio_venta=?, stock=?, stock_minimo=?, proveedor=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "ssddiisi",
                    $d['referencia'], $d['descripcion'], $d['precio_compra'],
                    $d['precio_venta'], $d['stock'], $d['stock_minimo'], $d['proveedor'], $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                volver('Recambio actualizado.', $url_pagina);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $error = 'Ya existe otro recambio con esa referencia.';
                } else {
                    throw $e;
                }
            }
        }
    }

    if ($accion === 'borrar') {
        require_permiso('recambios', 'borrar');
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = mysqli_prepare($conexion, "DELETE FROM recambios WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            volver('Recambio eliminado.', $url_pagina);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1451) {
                $error = 'No se puede borrar el recambio: está en uso en pedidos.';
            } else {
                throw $e;
            }
        }
    }
}

/* Listado */
$sql = "SELECT * FROM recambios";
if ($busqueda !== '') {
    // Escapar los comodines de LIKE para que se busquen literalmente
    $like = '%' . addcslashes($busqueda, '%_\\') . '%';
    $stmt = mysqli_prepare($conexion, $sql . " WHERE referencia LIKE ? OR descripcion LIKE ? OR proveedor LIKE ? ORDER BY referencia");
    mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
} else {
    $stmt = mysqli_prepare($conexion, $sql . " ORDER BY referencia");
}
mysqli_stmt_execute($stmt);
$recambios = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recambios · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main>
        <div class="seccion-cabecera"><h1>Recambios</h1></div>
        <?php if ($ok): ?><p class="mensaje mensaje--ok"><?= e($ok) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="mensaje mensaje--error"><?= e($error) ?></p><?php endif; ?>

        <form method="GET" class="formulario-linea" style="margin-bottom:1.5rem">
            <div>
                <label for="q">Buscar por referencia, descripción o proveedor</label>
                <input type="text" id="q" name="q" value="<?= e($busqueda) ?>">
            </div>
            <button type="submit">Buscar</button>
        </form>

        <?php if (puede('recambios', 'crear')): ?>
        <section class="panel" style="margin-bottom:1.5rem">
            <h2>Nuevo recambio</h2>
            <form method="POST" action="<?= e($url_pagina) ?>" class="formulario-linea">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="crear">
                <div><label for="n_referencia">Referencia</label><input type="text" id="n_referencia" name="referencia" value="<?= e($nuevo['referencia']) ?>" required></div>
                <div style="flex:2"><label for="n_descripcion">Descripción</label><input type="text" id="n_descripcion" name="descripcion" value="<?= e($nuevo['descripcion']) ?>" required></div>
                <div><label for="n_precio_compra">Precio compra</label><input type="number" step="0.01" min="0" id="n_precio_compra" name="precio_compra" value="<?= e($nuevo['precio_compra']) ?>"></div>
                <div><label for="n_precio_venta">Precio venta</label><input type="number" step="0.01" min="0" id="n_precio_venta" name="precio_venta" value="<?= e($nuevo['precio_venta']) ?>"></div>
                <div style="flex-basis:100px"><label for="n_stock">Stock</label><input type="number" min="0" id="n_stock" name="stock" value="<?= e($nuevo['stock']) ?>"></div>
                <div style="flex-basis:130px"><label for="n_stock_minimo">Stock mínimo</label><input type="number" min="0" id="n_stock_minimo" name="stock_minimo" value="<?= e($nuevo['stock_minimo']) ?>"></div>
                <div><label for="n_proveedor">Proveedor</label><input type="text" id="n_proveedor" name="proveedor" value="<?= e($nuevo['proveedor']) ?>"></div>
                <button type="submit">Crear</button>
            </form>
        </section>
        <?php endif; ?>

        <div class="tabla-contenedor">
            <table>
                <thead><tr><th>Ref.</th><th>Descripción</th><th>P. Compra</th><th>P. Venta</th><th>Stock</th><th>Stock mín.</th><th>Proveedor</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recambios as $r):
                    $rid = (int) $r['id'];
                    $bajo = $r['stock'] <= $r['stock_minimo'] && $r['stock_minimo'] > 0;
                ?>
                    <tr style="<?= $bajo ? 'background:#fff4f4' : '' ?>">
                        <td><strong><?= e($r['referencia']) ?></strong></td>
                        <td><?= e($r['descripcion']) ?></td>
                        <td><?= number_format((float) $r['precio_compra'], 2, ',', '.') ?> €</td>
                        <td><?= number_format((float) $r['precio_venta'], 2, ',', '.') ?> €</td>
                        <td><?= $bajo ? '<strong style="color:#b3261e">' . (int) $r['stock'] . '</strong>' : (int) $r['stock'] ?></td>
                        <td><?= (int) $r['stock_minimo'] ?></td>
                        <td><?= e($r['proveedor'] ?? '—') ?></td>
                        <td>
                            <?php if (puede('recambios', 'editar')): ?>
                            <details>
                                <summary>Editar</summary>
                                <form method="POST" action="<?= e($url_pagina) ?>" class="formulario-linea" style="margin-top:.5rem">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="editar">
                                    <input type="hidden" name="id" value="<?= $rid ?>">
                                    <div><label for="e_referencia_<?= $rid ?>">Ref.</label><input type="text" id="e_referencia_<?= $rid ?>" name="referencia" value="<?= e($r['referencia']) ?>" required></div>
                                    <div style="flex:2"><label for="e_descripcion_<?= $rid ?>">Desc.</label><input type="text" id="e_descripcion_<?= $rid ?>" name="descripcion" value="<?= e($r['descripcion']) ?>" required></div>
                                    <div><label for="e_precio_compra_<?= $rid ?>">P. Compra</label><input type="number" step="0.01" min="0" id="e_precio_compra_<?= $rid ?>" name="precio_compra" value="<?= e((string) $r['precio_compra']) ?>"></div>
                                    <div><label for="e_precio_venta_<?= $rid ?>">P. Venta</label><input type="number" step="0.01" min="0" id="e_precio_venta_<?= $rid ?>" name="precio_venta" value="<?= e((string) $r['precio_venta']) ?>"></div>
                                    <div style="flex-basis:100px"><label for="e_stock_<?= $rid ?>">Stock</label><input type="number" min="0" id="e_stock_<?= $rid ?>" name="stock" value="<?= (int) $r['stock'] ?>"></div>
                                    <div style="flex-basis:130px"><label for="e_stock_minimo_<?= $rid ?>">Stock mín.</label><input type="number" min="0" id="e_stock_minimo_<?= $rid ?>" name="stock_minimo" value="<?= (int) $r['stock_minimo'] ?>"></div>
                                    <div><label for="e_proveedor_<?= $rid ?>">Proveedor</label><input type="text" id="e_proveedor_<?= $rid ?>" name="proveedor" value="<?= e($r['proveedor'] ?? '') ?>"></div>
                                    <button type="submit">Guardar</button>
                                </form>
                            </details>
                            <?php endif; ?>
                            <?php if (puede('recambios', 'borrar')): ?>
                            <form method="POST" action="<?= e($url_pagina) ?>" onsubmit="return confirm('¿Borrar recambio?')" style="display:inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="borrar">
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <button type="submit">Borrar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>