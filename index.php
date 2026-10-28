<?php
// Lógica primero: nada de HTML antes de session_start() / header()
require __DIR__ . '/includes/auth.php';
require_login();

$modulos = modulos_visibles();
$rolEtiqueta = ROLES[$_SESSION['rol']] ?? $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel · Gestor del Taller</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/dashboard.css">
</head>

<body>
    <?php require __DIR__ . '/includes/menu.php'; ?>

    <main>
        <h1 class="mensajeInicioSesion">Bienvenido, <?= e($_SESSION['username']) ?></h1>
        <p>Puesto de trabajo: <?= e($rolEtiqueta) ?></p>

        <?php if (!$modulos): ?>
            <p class="mensaje mensaje--error">Tu rol todavía no tiene módulos asignados. Habla con un administrador.</p>
        <?php else: ?>
            <div class="rejilla modulos">
                <?php foreach ($modulos as $clave => $m): ?>
                    <a class="modulo" href="<?= e($m['url']) ?>">
                        <h2><?= e($m['titulo']) ?></h2>
                        <p><?= e($m['desc']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>