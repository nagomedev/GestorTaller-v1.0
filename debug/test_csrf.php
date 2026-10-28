Test de helpers CSRF.
<?php require __DIR__ . '/_header.php'; ?>
<h2>CSRF Helpers</h2>
<div class="grid">
    <div class="card">
        <h3>csrf_token()</h3>
        <?php
        try {
            $t = csrf_token();
            echo "<p class='ok'>Generado OK</p>";
            echo "<code style='word-break:break-all'>{$t}</code>";
            echo "<p style='margin-top:.5rem'><small>Guardado en <code>\$_SESSION['csrf']</code></small></p>";
        } catch (Throwable $e) {
            echo "<p class='bad'>ERROR: ".$e->getMessage()."</p>";
        }
        ?>
    </div>
    <div class="card">
        <h3>csrf_campo()</h3>
        <p>Salida HTML:</p>
        <pre><code><?= htmlspecialchars(csrf_campo()) ?></code></pre>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <h3>Test hash_equals</h3>
    <?php
    $token = $_SESSION['csrf'] ?? '';
    echo "<p>Token en sesión: " . ($token ? "<span class='ok'>Presente</span>" : "<span class='bad'>Ausente</span>") . "</p>";
    echo "<p><code>hash_equals()</code> disponible: " . (function_exists('hash_equals') ? "<span class='ok'>Sí</span>" : "<span class='bad'>No</span>") . "</p>";
    ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>