Comprobación de conexión, BD, charset y listado de tablas.
<?php require __DIR__ . '/_header.php'; ?>
<h2>Conexión a base de datos</h2>

<div class="card">
    <?php
    try {
        if ($conexion->connect_error) {
            echo "<p class='bad'>ERROR: " . $conexion->connect_error . "</p>";
        } else {
            echo "<p class='ok'>✓ Conexión OK a <strong>{$base_datos}</strong> en {$servidor}</p>";
            echo "<p>Host info: " . $conexion->host_info . "</p>";
            echo "<p>Charset: " . $conexion->character_set_name() . "</p>";
            echo "<p>Server version: " . $conexion->server_info . "</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='bad'>Excepción: " . $e->getMessage() . "</p>";
    }
    ?>
</div>

<h2>Tablas existentes</h2>
<div class="card">
    <?php
    $res = $conexion->query("SHOW TABLES");
    if ($res && $res->num_rows > 0) {
        echo "<table><thead><tr><th>Tabla</th></tr></thead><tbody>";
        while ($row = $res->fetch_array()) {
            echo "<tr><td>" . htmlspecialchars($row[0]) . "</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='warn'>No se han encontrado tablas en <strong>{$base_datos}</strong>.</p>";
    }
    ?>
</div>

<h2>Estado por tabla (conteo de filas)</h2>
<div class="card">
    <?php
    $tablas = ['usuarios','clientes','vehiculos','ordenes'];
    echo "<table><thead><tr><th>Tabla</th><th>Filas</th><th>Estado</th></tr></thead><tbody>";
    foreach ($tablas as $t) {
        try {
            $q = $conexion->query("SELECT COUNT(*) as c FROM `$t`");
            $c = $q ? $q->fetch_assoc()['c'] : 0;
            echo "<tr><td>$t</td><td>$c</td><td class='ok'>OK</td></tr>";
        } catch (Throwable $e) {
            echo "<tr><td>$t</td><td>-</td><td class='bad'>FALTA / ERROR</td></tr>";
        }
    }
    echo "</tbody></table>";
    ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>