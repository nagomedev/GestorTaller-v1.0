Inspección de órdenes, asignaciones y estados.
<?php require __DIR__ . '/_header.php'; ?>
<h2>Órdenes (todas)</h2>
<div class="card" style="overflow:auto">
    <?php
    try {
        $res = $conexion->query("SELECT o.id, o.matricula, o.descripcion, o.estado, o.creada_en, o.mecanico_id,
                                        u.nombre as mecanico, v.marca, v.modelo, c.id as cliente_id, c.nombre as cliente
                                 FROM ordenes o
                                 LEFT JOIN usuarios u ON u.id = o.mecanico_id
                                 LEFT JOIN vehiculos v ON v.id = o.vehiculo_id
                                 LEFT JOIN clientes c ON c.id = v.cliente_id
                                 ORDER BY o.id DESC
                                 LIMIT 50");
        if ($res && $res->num_rows > 0) {
            echo "<table><thead><tr><th>Nº</th><th>Vehículo</th><th>Cliente</th><th>Trabajo</th><th>Mecánico</th><th>Estado</th><th>Creada</th><th>Ver</th></tr></thead><tbody>";
            while ($o = $res->fetch_assoc()) {
                $coche = trim(($o['marca']??'').' '.($o['modelo']??''));
                echo "<tr>
                    <td>{$o['id']}</td>
                    <td><strong>".htmlspecialchars($o['matricula'])."</strong>".($coche?'<br><small>'.htmlspecialchars($coche).'</small>':'')."</td>
                    <td>".($o['cliente_id']?"<a href='../modulos/clientes.php?id={$o['cliente_id']}'>".htmlspecialchars($o['cliente'])."</a>":'—')."</td>
                    <td>".htmlspecialchars($o['descripcion'])."</td>
                    <td>".htmlspecialchars($o['mecanico']??'Sin asignar')."</td>
                    <td>".htmlspecialchars($o['estado'])."</td>
                    <td>".htmlspecialchars($o['creada_en'])."</td>
                    <td><a href='../modulos/ordenes.php'>Ir</a></td>
                </tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='warn'>Sin órdenes.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='bad'>ERROR: ".$e->getMessage()."</p>";
    }
    ?>
</div>

<div class="grid" style="margin-top:1rem">
    <div class="card">
        <h3>Por estado</h3>
        <?php
        try {
            $res = $conexion->query("SELECT estado, COUNT(*) as c FROM ordenes GROUP BY estado");
            while ($r=$res->fetch_assoc()) echo "<p><strong>".htmlspecialchars($r['estado']).":</strong> {$r['c']}</p>";
        } catch (Throwable $e) { echo "<p class='bad'>".$e->getMessage()."</p>"; }
        ?>
    </div>
    <div class="card">
        <h3>Sin asignar</h3>
        <?php
        try {
            $res = $conexion->query("SELECT COUNT(*) as c FROM ordenes WHERE mecanico_id IS NULL");
            $r=$res->fetch_assoc(); echo "<p><strong>{$r['c']}</strong> órdenes sin mecánico</p>";
        } catch (Throwable $e) { echo "<p class='bad'>".$e->getMessage()."</p>"; }
        ?>
    </div>
    <div class="card">
        <h3>Mecánicos disponibles</h3>
        <?php
        try {
            $res = $conexion->query("SELECT id,nombre FROM usuarios WHERE rol='mecanico' ORDER BY nombre");
            if ($res->num_rows===0) echo "<p class='warn'>No hay usuarios con rol 'mecanico'</p>";
            else { while ($m=$res->fetch_assoc()) echo "<p>#{$m['id']} ".htmlspecialchars($m['nombre'])."</p>"; }
        } catch (Throwable $e) { echo "<p class='bad'>".$e->getMessage()."</p>"; }
        ?>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>