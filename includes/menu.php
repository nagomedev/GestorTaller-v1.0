<?php
// Barra superior para páginas con sesión iniciada. Requiere auth.php cargado.
$rolEtiqueta = ROLES[$_SESSION['rol'] ?? ''] ?? ($_SESSION['rol'] ?? '');
?>
<header class="cabecera barra">
    <a class="marca" href="<?= BASE_URL ?>index.php">Gestor del Taller</a>

    <nav class="navegacion" aria-label="Módulos">
        <?php foreach (modulos_visibles() as $clave => $m): ?>
            <a href="<?= BASE_URL . e($m['url']) ?>"><?= e($m['titulo']) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="usuario">
        <span><?= e($_SESSION['username'] ?? '') ?> · <?= e($rolEtiqueta) ?></span>
        <a class="cerrarSesion" href="<?= BASE_URL ?>logout.php">Cerrar sesión</a>
    </div>
</header>
