<?php

/**
 * Incluir SIEMPRE el primero en cada página protegida, antes de cualquier HTML.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/roles.php';

/** Escapa texto para imprimirlo en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Redirige al login si no hay sesión. */
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

/** ¿El usuario actual puede hacer esta acción en este módulo? */
function puede(string $modulo, string $accion = 'ver'): bool
{
    $rol = $_SESSION['rol'] ?? '';
    return in_array($accion, PERMISOS[$rol][$modulo] ?? [], true);
}

/** Corta la petición con 403 si no tiene permiso. */
function require_permiso(string $modulo, string $accion = 'ver'): void
{
    require_login();
    if (!puede($modulo, $accion)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>Sin permiso</title>'
            . '<link rel="stylesheet" href="' . BASE_URL . 'includes/style.css">'
            . '<link rel="stylesheet" href="' . BASE_URL . 'includes/dashboard.css"></head><body>';
        require __DIR__ . '/menu.php';
        echo '<main><p class="mensaje mensaje--error">No tienes permiso para esta acción.</p>'
            . '<a class="btn" href="' . BASE_URL . 'index.php">Volver al panel</a></main></body></html>';
        exit();
    }
}

/** Módulos que el rol actual puede ver (para menú y dashboard). */
function modulos_visibles(): array
{
    return array_filter(MODULOS, fn($clave) => puede($clave, 'ver'), ARRAY_FILTER_USE_KEY);
}

/* ---- CSRF para formularios POST ---- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_validar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(400);
        exit('Petición no válida.');
    }
}
