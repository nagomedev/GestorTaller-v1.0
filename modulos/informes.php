<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('informes', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Datos */
$ordenes_estado = $conexion->query("SELECT estado, COUNT(*) as c FROM ordenes GROUP BY estado")->fetch_all(MYSQLI_ASSOC);
$sin_asignar = $conexion->query("SELECT COUNT(*) as c FROM ordenes WHERE mecanico_id IS NULL AND estado != 'listo'")->fetch_assoc()['c'];
$total_ordenes = $conexion->query("SELECT COUNT(*) as c FROM ordenes")->fetch_assoc()['c'];

$fact_pend = $conexion->query("SELECT COALESCE(SUM(total),0) as s FROM facturas WHERE estado='pendiente'")->fetch_assoc()['s'];
$fact_pag = $conexion->query("SELECT COALESCE(SUM(total),0) as s FROM facturas WHERE estado='pagada'")->fetch_assoc()['s'];

$stock_bajo = $conexion->query("SELECT COUNT(*) as c FROM recambios WHERE stock <= stock_minimo AND stock_minimo > 0")->fetch_assoc()['c'];
$recambios_bajo = $conexion->query("SELECT referencia, descripcion, stock, stock_minimo FROM recambios WHERE stock <= stock_minimo AND stock_minimo > 0 ORDER BY stock ASC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$top_mecanicos = $conexion->query("SELECT u.nombre, COUNT(*) as c FROM ordenes o JOIN usuarios u ON u.id=o.mecanico_id GROUP BY o.mecanico_id ORDER BY c DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$ultimos_clientes = $conexion->query("SELECT id, nombre, creado_en FROM clientes ORDER BY creado_en DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$ultimas_ordenes = $conexion->query("SELECT o.id, o.matricula, o.estado, o.creada_en FROM ordenes o ORDER BY o.creada_en DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informes · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main>
        <div class="seccion-cabecera"><h1>Informes</h1></div>

        <div class="rejilla" style="margin-bottom:1.5rem">
            <div class="panel"><h2><?= (int)$total_ordenes ?></h2><p>Órdenes totales</p></div>
            <div class="panel"><h2><?= (int)$sin_asignar ?></h2><p>Sin asignar</p></div>
            <div class="panel"><h2><?= number_format($fact_pend, 2, ',', '.') ?> €</h2><p>Facturas pendientes</p></div>
            <div class="panel"><h2><?= number_format($fact_pag, 2, ',', '.') ?> €</h2><p>Facturas pagadas</p></div>
            <div class="panel"><h2><?= (int)$stock_bajo ?></h2><p>Recambios con stock bajo</p></div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:1rem">
            <section class="panel">
                <h2>Órdenes por estado</h2>
                <?php foreach ($ordenes_estado as $e): ?>
                    <p><strong><?= e($e['estado']) ?>:</strong> <?= (int)$e['c'] ?></p>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <h2>Top mecánicos</h2>
                <?php if (!$top_mecanicos): ?><p>Sin datos.</p><?php else: foreach ($top_mecanicos as $m): ?>
                    <p><strong><?= e($m['nombre']) ?>:</strong> <?= (int)$m['c'] ?> órdenes</p>
                <?php endforeach; endif; ?>
            </section>

            <section class="panel">
                <h2>Stock bajo</h2>
                <?php if (!$recambios_bajo): ?><p class="mensaje mensaje--ok">Sin alertas de stock.</p><?php else: ?>
                    <ul>
                    <?php foreach ($recambios_bajo as $r): ?>
                        <li><strong><?= e($r['referencia']) ?></strong> - <?= e($r['descripcion']) ?> (Stock: <?= (int)$r['stock'] ?>/ Mín: <?= (int)$r['stock_minimo'] ?>)</li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2>Últimas órdenes</h2>
                <ul>
                <?php foreach ($ultimas_ordenes as $o): ?>
                    <li>#<?= (int)$o['id'] ?> - <?= e($o['matricula']) ?> - <?= e($o['estado']) ?> (<?= e(date('d/m/Y', strtotime($o['creada_en']))) ?>)</li>
                <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel">
                <h2>Últimos clientes</h2>
                <ul>
                <?php foreach ($ultimos_clientes as $c): ?>
                    <li><a href="clientes.php?id=<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></a> (<?= e(date('d/m/Y', strtotime($c['creado_en']))) ?>)</li>
                <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </main>
</body>
</html>
