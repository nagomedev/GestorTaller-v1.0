Listado de usuarios, roles y debug de sesión.
<?php require __DIR__ . '/_header.php'; ?>
<h2>Sesión actual</h2>
<div class="card">
    <?php if (isset($_SESSION['user_id'])): ?>
        <p class='ok'>✓ Hay sesión iniciada</p>
        <ul>
            <li><strong>user_id:</strong> <?= htmlspecialchars($_SESSION['user_id']) ?></li>
            <li><strong>username:</strong> <?= htmlspecialchars($_SESSION['username'] ?? '') ?></li>
            <li><strong>rol:</strong> <?= htmlspecialchars($_SESSION['rol'] ?? '') ?> (<?= htmlspecialchars(ROLES[$_SESSION['rol']] ?? 'DESCONOCIDO') ?>)</li>
        </ul>
        <p><strong>BASE_URL:</strong> <?= BASE_URL ?></p>
    <?php else: ?>
        <p class='warn'>Sin sesión activa. Abre <a href="../login.php">login.php</a> para iniciar sesión.</p>
    <?php endif; ?>
</div>

<h2>Usuarios registrados</h2>
<div class="card">
    <?php
    try {
        $res = $conexion->query("SELECT id, nombre, rol, LEFT(password, 30) as pass_preview, CHAR_LENGTH(password) as pass_len FROM usuarios ORDER BY id");
        if ($res && $res->num_rows > 0) {
            echo "<table><thead><tr><th>ID</th><th>Nombre</th><th>Rol</th><th>Pass preview</th><th>Longitud hash</th><th>password_verify OK?</th></tr></thead><tbody>";
            while ($u = $res->fetch_assoc()) {
                $hashOk = (strpos($u['pass_preview'], '$') === 0) ? 'Sí ($2y/$2b)' : 'Revisar';
                echo "<tr>
                    <td>{$u['id']}</td>
                    <td>".htmlspecialchars($u['nombre'])."</td>
                    <td>".htmlspecialchars($u['rol'])." <small>(".htmlspecialchars(ROLES[$u['rol']]??'').")</small></td>
                    <td><code>{$u['pass_preview']}...</code></td>
                    <td>{$u['pass_len']}</td>
                    <td class='".($hashOk==='Sí ($2y/$2b)'?'ok':'warn')."'>$hashOk</td>
                </tr>";
            }
            echo "</tbody></table>";
            echo "<p style='margin-top:.5rem'><small>Los hashes deben empezar por <code>$2y$</code> si se han generado con <code>password_hash()</code>.</small></p>";
        } else {
            echo "<p class='warn'>No hay usuarios en la tabla <code>usuarios</code>.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='bad'>ERROR: " . $e->getMessage() . "</p>";
    }
    ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>