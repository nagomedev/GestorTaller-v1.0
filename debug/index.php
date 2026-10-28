Menú principal con accesos rápidos a todos los tests.
<?php require __DIR__ . '/_header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>1) Conexión BD</h2>
        <p>Comprueba conexión a MySQL, charset y tablas existentes.</p>
        <a class="btn" href="test_conexion.php">Ejecutar</a>
    </div>
    <div class="card">
        <h2>2) Usuarios & Sesión</h2>
        <p>Lista usuarios, roles, hashes y estado de sesión actual.</p>
        <a class="btn" href="test_usuarios.php">Ejecutar</a>
    </div>
    <div class="card">
        <h2>3) Roles & Permisos</h2>
        <p>Revisa ROLES, MODULOS, PERMISOS y simula <code>puede()</code>.</p>
        <a class="btn" href="test_permisos.php">Ejecutar</a>
    </div>
    <div class="card">
        <h2>4) Clientes & Vehículos</h2>
        <p>Inspecciona datos, relaciones y búsquedas.</p>
        <a class="btn" href="test_clientes.php">Ejecutar</a>
    </div>
    <div class="card">
        <h2>5) Órdenes (flujo completo)</h2>
        <p>Revisa estados, mecánicos, asignaciones y lógica de <code>ordenes.php</code>.</p>
        <a class="btn" href="test_ordenes.php">Ejecutar</a>
    </div>
    <div class="card">
        <h2>6) CSRF & Seguridad</h2>
        <p>Comprueba helpers <code>csrf_token()</code>, <code>csrf_campo()</code>.</p>
        <a class="btn" href="test_csrf.php">Ejecutar</a>
    </div>
</div>

<section class="card" style="margin-top:1.5rem">
    <h2>Notas</h2>
    <ul>
        <li>Estos scripts son <strong>solo lectura</strong> salvo <code>test_csrf.php</code> que genera tokens de sesión.</li>
        <li>Si hay errores de SQL verás el stack de MySQLi con <code>MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT</code>.</li>
        <li>Elimina la carpeta <code>debug/</code> antes de subir a producción.</li>
    </ul>
</section>
<?php require __DIR__ . '/_footer.php'; ?>