<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../bd/conexionbd.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function d($v, $label = '') {
    echo '<pre style="background:#111;color:#0f0;padding:1rem;overflow:auto;border-radius:6px;">';
    if ($label !== '') echo "<strong style='color:#fff;'>$label</strong>\n";
    var_dump($v);
    echo '</pre>';
}
function dh($v, $label = '') {
    echo '<pre style="background:#1e1e1e;color:#dcdcdc;padding:1rem;overflow:auto;border-radius:6px;">';
    if ($label !== '') echo "<strong>$label</strong>\n";
    print_r($v);
    echo '</pre>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DEBUG · GestorTaller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
    <style>
        body{background:#f7f7f7}
        main{max-width:1100px;margin:2rem auto;padding:0 1rem}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}
        .card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1rem;box-shadow:0 1px 2px rgba(0,0,0,.02)}
        .ok{color:#1a7f37}
        .bad{color:#d1242f}
        .warn{color:#b8860b}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.5rem;border:1px solid #eee;text-align:left}
        th{background:#fafafa}
    </style>
</head>
<body>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <h1>Debug / Diagnóstico</h1>
        <a class="btn" href="index.php">← Volver al menú debug</a>
    </div>