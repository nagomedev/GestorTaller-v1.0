Revisión de clientes/vehículos.
<?php require __DIR__ . '/_header.php'; ?>
<h2>Clientes (últimos 20)</h2>
<div class="card" style="overflow:auto">
    <?php
    try {
        $res = $conexion->query("SELECT c.id, c.nombre, c.nif, c.telefono, c.email, COUNT(v.id) as coches
                                 FROM clientes c
                                 LEFT JOIN vehiculos v ON v.cliente_id = c.id
                                 GROUP BY c.id
                                 ORDER BY c.id DESC
                                 LIMIT 20");
        if ($res && $res->num_rows > 0) {
            echo "<table><thead><tr><th>ID</th><th>Nombre</th><th>NIF</th><th>Teléfono</th><th>Email</th><th>Vehículos</th><th>Ficha</th></tr></thead><tbody>";
            while ($c = $res->fetch_assoc()) {
                echo "<tr>
                    <td>{$c['id']}</td>
                    <td>".htmlspecialchars($c['nombre'])."</td>
                    <td>".htmlspecialchars($c['nif']??'—')."</td>
                    <td>".htmlspecialchars($c['telefono']??'—')."</td>
                    <td>".htmlspecialchars($c['email']??'—')."</td>
                    <td>{$c['coches']}</td>
                    <td><a href='../modulos/clientes.php?id={$c['id']}'>Ver</a></td>
                </tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='warn'>Sin clientes.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='bad'>ERROR: ".$e->getMessage()."</p>";
    }
    ?>
</div>

<h2>Vehículos (últimos 20)</h2>
<div class="card" style="overflow:auto">
    <?php
    try {
        $res = $conexion->query("SELECT v.id, v.matricula, v.marca, v.modelo, v.anio, c.id as cliente_id, c.nombre as cliente
                                 FROM vehiculos v
                                 LEFT JOIN clientes c ON c.id = v.cliente_id
                                 ORDER BY v.id DESC
                                 LIMIT 20");
        if ($res && $res->num_rows > 0) {
            echo "<table><thead><tr><th>ID</th><th>Matrícula</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Cliente</th></tr></thead><tbody>";
            while ($v = $res->fetch_assoc()) {
                echo "<tr>
                    <td>{$v['id']}</td>
                    <td><strong>".htmlspecialchars($v['matricula'])."</strong></td>
                    <td>".htmlspecialchars($v['marca']??'—')."</td>
                    <td>".htmlspecialchars($v['modelo']??'—')."</td>
                    <td>".htmlspecialchars($v['anio']??'—')."</td>
                    <td><a href='../modulos/clientes.php?id={$v['cliente_id']}'>".htmlspecialchars($v['cliente']??'—')."</a></td>
                </tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='warn'>Sin vehículos.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='bad'>ERROR: ".$e->getMessage()."</p>";
    }
    ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>