Matriz de roles, módulos y test de puede().
<?php require __DIR__ . '/_header.php'; ?>
<h2>Constantes cargadas</h2>
<div class="grid">
    <div class="card">
        <h3>ROLES</h3>
        <?php dh(ROLES); ?>
    </div>
    <div class="card">
        <h3>MODULOS</h3>
        <?php dh(MODULOS); ?>
    </div>
</div>

<h2>Matriz PERMISOS</h2>
<div class="card" style="overflow:auto">
    <?php
    $modulosLista = array_keys(MODULOS);
    echo "<table><thead><tr><th>Rol</th>";
    foreach ($modulosLista as $m) echo "<th>".htmlspecialchars($m)."</th>";
    echo "</tr></thead><tbody>";
    foreach (PERMISOS as $rol => $mods) {
        echo "<tr><td><strong>".htmlspecialchars($rol)."</strong><br><small>".htmlspecialchars(ROLES[$rol]??'')."</small></td>";
        foreach ($modulosLista as $m) {
            $acciones = $mods[$m] ?? [];
            if (empty($acciones)) {
                echo "<td class='bad'>—</td>";
            } else {
                echo "<td class='ok'>".htmlspecialchars(implode(', ', $acciones))."</td>";
            }
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
    ?>
</div>

<h2>Simulador puede($modulo, $accion)</h2>
<div class="card">
    <?php if (isset($_SESSION['rol'])): ?>
        <p>Probando con rol de sesión: <strong><?= htmlspecialchars($_SESSION['rol']) ?></strong></p>
        <table>
            <thead><tr><th>Módulo</th><th>ver</th><th>crear</th><th>editar</th><th>borrar</th><th>asignar</th></tr></thead>
            <tbody>
                <?php foreach (array_keys(MODULOS) as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m) ?></td>
                        <?php foreach (['ver','crear','editar','borrar','asignar'] as $a): ?>
                            <td><?= puede($m,$a) ? "<span class='ok'>SI</span>" : "<span class='bad'>NO</span>" ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:1rem"><strong>modulos_visibles():</strong> <?= count(modulos_visibles()) ?> módulos visibles</p>
        <?php dh(modulos_visibles(), 'Array'); ?>
    <?php else: ?>
        <p class='warn'>Inicia sesión para probar <code>puede()</code> con el rol real.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>