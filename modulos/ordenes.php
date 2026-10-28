<?php
require __DIR__ . '/../includes/auth.php';
require_permiso('ordenes', 'ver');
require __DIR__ . '/../bd/conexionbd.php';

// Errores de MySQL como excepciones (necesario para poder deshacer la transacción)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const ESTADOS = ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'listo' => 'Listo'];

$esMecanico = ($_SESSION['rol'] === 'mecanico');
$userId     = (int) $_SESSION['user_id'];
$error      = '';
$ok         = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function volver(string $mensaje): void
{
    $_SESSION['flash'] = $mensaje;
    header('Location: ordenes.php');
    exit();
}

/** Devuelve null si el campo está vacío, o el texto recortado. */
function opcional(string $clave): ?string
{
    $v = trim($_POST[$clave] ?? '');
    return $v === '' ? null : $v;
}

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';

    /* ===== Crear orden: con vehículo existente o dándolo de alta ===== */
    if ($accion === 'crear') {
        require_permiso('ordenes', 'crear');

        $modo        = $_POST['modo'] ?? 'existente';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $vehiculoId  = 0;
        $matricula   = '';
        $nuevo       = [];   // datos del vehículo/cliente nuevo

        if ($descripcion === '') {
            $error = 'Describe el trabajo a realizar.';
        } elseif ($modo === 'existente') {
            $vehiculoId = (int) ($_POST['vehiculo_id'] ?? 0);
            $stmt = mysqli_prepare($conexion, "SELECT matricula FROM vehiculos WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $vehiculoId);
            mysqli_stmt_execute($stmt);
            $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$fila) {
                $error = 'Elige un vehículo de la lista.';
            } else {
                $matricula = $fila['matricula'];
            }
        } elseif ($modo === 'nuevo') {
            require_permiso('vehiculos', 'crear');

            $matricula = strtoupper(preg_replace('/[\s-]/', '', $_POST['matricula'] ?? ''));
            $anioTexto = trim($_POST['anio'] ?? '');
            $anio      = $anioTexto === '' ? null : (int) $anioTexto;
            $clienteId = (int) ($_POST['cliente_id'] ?? 0);   // 0 = cliente nuevo

            if ($matricula === '') {
                $error = 'La matrícula es obligatoria.';
            } elseif ($anio !== null && ($anio < 1950 || $anio > (int) date('Y') + 1)) {
                $error = 'El año del vehículo no es válido.';
            } else {
                // ¿La matrícula ya existe?
                $stmt = mysqli_prepare($conexion, "SELECT id FROM vehiculos WHERE matricula = ?");
                mysqli_stmt_bind_param($stmt, "s", $matricula);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $error = 'La matrícula ' . $matricula . ' ya está registrada. Elígela en "Vehículo ya registrado".';
                } elseif ($clienteId === 0) {
                    require_permiso('clientes', 'crear');
                    if (trim($_POST['cliente_nombre'] ?? '') === '') {
                        $error = 'Escribe el nombre del cliente nuevo.';
                    }
                } else {
                    $stmt = mysqli_prepare($conexion, "SELECT id FROM clientes WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $clienteId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) === 0) {
                        $error = 'El cliente elegido no existe.';
                    }
                }
            }

            if ($error === '') {
                $nuevo = [
                    'cliente_id'      => $clienteId,
                    'cliente_nombre'  => trim($_POST['cliente_nombre'] ?? ''),
                    'cliente_tel'     => opcional('cliente_telefono'),
                    'marca'           => opcional('marca'),
                    'modelo'          => opcional('modelo'),
                    'anio'            => $anio,
                ];
            }
        } else {
            $error = 'Modo no válido.';
        }

        // Todo o nada: si falla algo, no queda ni cliente ni vehículo a medias
        if ($error === '') {
            mysqli_begin_transaction($conexion);
            try {
                if ($modo === 'nuevo') {
                    $cid = $nuevo['cliente_id'];
                    if ($cid === 0) {
                        $stmt = mysqli_prepare($conexion, "INSERT INTO clientes (nombre, telefono) VALUES (?, ?)");
                        mysqli_stmt_bind_param($stmt, "ss", $nuevo['cliente_nombre'], $nuevo['cliente_tel']);
                        mysqli_stmt_execute($stmt);
                        $cid = mysqli_insert_id($conexion);
                    }

                    $stmt = mysqli_prepare($conexion, "INSERT INTO vehiculos (cliente_id, matricula, marca, modelo, anio) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "isssi", $cid, $matricula, $nuevo['marca'], $nuevo['modelo'], $nuevo['anio']);
                    mysqli_stmt_execute($stmt);
                    $vehiculoId = mysqli_insert_id($conexion);
                }

                $stmt = mysqli_prepare($conexion, "INSERT INTO ordenes (vehiculo_id, matricula, descripcion) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iss", $vehiculoId, $matricula, $descripcion);
                mysqli_stmt_execute($stmt);
                $ordenId = mysqli_insert_id($conexion);

                mysqli_commit($conexion);
            } catch (Throwable $t) {
                mysqli_rollback($conexion);
                error_log('Error al crear orden: ' . $t->getMessage());
                $error = 'No se pudo crear la orden.';
            }

            if ($error === '') {
                volver('Orden n.º ' . $ordenId . ' creada para ' . $matricula
                    . (puede('ordenes', 'asignar') ? '. Falta asignarla a un mecánico.' : '.'));
            }
        }
    }

    /* ===== Asignar (o quitar) mecánico: solo quien tiene permiso "asignar" ===== */
    if ($accion === 'asignar') {
        require_permiso('ordenes', 'asignar');
        $id         = (int) ($_POST['id'] ?? 0);
        $mecanicoId = (int) ($_POST['mecanico_id'] ?? 0);   // 0 = sin asignar

        if ($mecanicoId === 0) {
            $mecanicoId = null;
        } else {
            // Solo se puede asignar a usuarios con rol mecánico
            $stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE id = ? AND rol = 'mecanico'");
            mysqli_stmt_bind_param($stmt, "i", $mecanicoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) === 0) {
                $error = 'Ese usuario no es mecánico.';
            }
        }

        if ($error === '') {
            $stmt = mysqli_prepare($conexion, "UPDATE ordenes SET mecanico_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $mecanicoId, $id);
            mysqli_stmt_execute($stmt);
            volver($mecanicoId === null ? 'Orden sin asignar.' : 'Mecánico asignado.');
        }
    }

    /* ===== Cambiar estado ===== */
    if ($accion === 'estado') {
        require_permiso('ordenes', 'editar');
        $id     = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        if (isset(ESTADOS[$estado])) {
            // Un mecánico solo puede tocar las órdenes que tiene asignadas
            if ($esMecanico) {
                $stmt = mysqli_prepare($conexion, "UPDATE ordenes SET estado = ? WHERE id = ? AND mecanico_id = ?");
                mysqli_stmt_bind_param($stmt, "sii", $estado, $id, $userId);
            } else {
                $stmt = mysqli_prepare($conexion, "UPDATE ordenes SET estado = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $estado, $id);
            }
            mysqli_stmt_execute($stmt);
        }
        volver('Estado actualizado.');
    }

    /* ===== Borrar ===== */
    if ($accion === 'borrar') {
        require_permiso('ordenes', 'borrar');
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conexion, "DELETE FROM ordenes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        volver('Orden borrada.');
    }
}

/* ---------- Datos para pintar la página ---------- */
$puedeCrear   = puede('ordenes', 'crear');
$puedeAsignar = puede('ordenes', 'asignar');
$verClientes  = puede('clientes', 'ver');

// Listado: el mecánico solo ve las suyas
$sql = "SELECT o.id, o.matricula, o.descripcion, o.estado, o.creada_en, o.mecanico_id,
               u.nombre AS mecanico, v.marca, v.modelo, c.id AS cliente_id, c.nombre AS cliente
        FROM ordenes o
        LEFT JOIN usuarios  u ON u.id = o.mecanico_id
        LEFT JOIN vehiculos v ON v.id = o.vehiculo_id
        LEFT JOIN clientes  c ON c.id = v.cliente_id";

if ($esMecanico) {
    $stmt = mysqli_prepare($conexion, $sql . " WHERE o.mecanico_id = ? ORDER BY o.creada_en DESC");
    mysqli_stmt_bind_param($stmt, "i", $userId);
} else {
    $stmt = mysqli_prepare($conexion, $sql . " ORDER BY o.creada_en DESC");
}
mysqli_stmt_execute($stmt);
$ordenes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$sinAsignar = count(array_filter($ordenes, fn($o) => $o['mecanico_id'] === null && $o['estado'] !== 'listo'));

// Listas para los formularios
$vehiculos = $clientes = $mecanicos = [];
if ($puedeCrear) {
    $vehiculos = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT v.id, v.matricula, v.marca, v.modelo, c.nombre AS cliente
         FROM vehiculos v JOIN clientes c ON c.id = v.cliente_id
         ORDER BY v.matricula"
    ), MYSQLI_ASSOC);
    $clientes = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT id, nombre FROM clientes ORDER BY nombre"
    ), MYSQLI_ASSOC);
}
if ($puedeAsignar) {
    $mecanicos = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT id, nombre FROM usuarios WHERE rol = 'mecanico' ORDER BY nombre"
    ), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Órdenes · Gestor del Taller</title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="../includes/dashboard.css">
</head>

<body>
    <?php require __DIR__ . '/../includes/menu.php'; ?>

    <main>
        <div class="seccion-cabecera">
            <h1>Órdenes de reparación</h1>
        </div>

        <?php if ($ok): ?>
            <p class="mensaje mensaje--ok"><?= e($ok) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="mensaje mensaje--error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($puedeAsignar && $sinAsignar > 0): ?>
            <p class="mensaje">
                Hay <strong><?= $sinAsignar ?></strong> <?= $sinAsignar === 1 ? 'orden' : 'órdenes' ?> sin mecánico asignado.
            </p>
        <?php endif; ?>

        <?php if ($puedeCrear): ?>
            <section class="panel" style="margin-bottom:1.5rem">
                <h2>Nueva orden</h2>
                <form method="POST" class="formulario-orden" id="form-orden">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="crear">

                    <div class="opciones">
                        <label><input type="radio" name="modo" value="existente" checked>Vehículo ya registrado</label>
                        <?php if (puede('vehiculos', 'crear')): ?>
                            <label><input type="radio" name="modo" value="nuevo">Vehículo nuevo</label>
                        <?php endif; ?>
                    </div>

                    <!-- Vehículo existente -->
                    <div id="bloque-existente" class="formulario-linea">
                        <div style="flex-basis:420px">
                            <label for="vehiculo_id">Vehículo</label>
                            <select id="vehiculo_id" name="vehiculo_id">
                                <option value="0">— Elige un vehículo —</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?= (int) $v['id'] ?>">
                                        <?= e($v['matricula']) ?> — <?= e(trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? ''))) ?> (<?= e($v['cliente']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Vehículo nuevo -->
                    <div id="bloque-nuevo" hidden>
                        <div class="formulario-linea">
                            <div>
                                <label for="matricula">Matrícula</label>
                                <input type="text" id="matricula" name="matricula">
                            </div>
                            <div>
                                <label for="marca">Marca</label>
                                <input type="text" id="marca" name="marca">
                            </div>
                            <div>
                                <label for="modelo">Modelo</label>
                                <input type="text" id="modelo" name="modelo">
                            </div>
                            <div style="flex-basis:110px">
                                <label for="anio">Año</label>
                                <input type="number" id="anio" name="anio" min="1950" max="<?= (int) date('Y') + 1 ?>">
                            </div>
                        </div>
                        <div class="formulario-linea">
                            <div>
                                <label for="cliente_id">Cliente</label>
                                <select id="cliente_id" name="cliente_id">
                                    <?php foreach ($clientes as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (puede('clientes', 'crear')): ?>
                                        <option value="0">— Cliente nuevo —</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div id="bloque-cliente-nuevo" hidden class="formulario-linea" style="flex:2 1 420px">
                                <div>
                                    <label for="cliente_nombre">Nombre del cliente nuevo</label>
                                    <input type="text" id="cliente_nombre" name="cliente_nombre">
                                </div>
                                <div>
                                    <label for="cliente_telefono">Teléfono</label>
                                    <input type="text" id="cliente_telefono" name="cliente_telefono">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="formulario-linea">
                        <div style="flex-basis:420px">
                            <label for="descripcion">Trabajo a realizar</label>
                            <input type="text" id="descripcion" name="descripcion" required>
                        </div>
                        <button type="submit">Crear orden</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if (!$ordenes): ?>
            <p class="mensaje">No hay órdenes que mostrar.</p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Vehículo</th>
                            <?php if ($verClientes): ?><th>Cliente</th><?php endif; ?>
                            <th>Trabajo</th>
                            <th>Mecánico</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <?php if (puede('ordenes', 'borrar')): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $o):
                            $coche = trim(($o['marca'] ?? '') . ' ' . ($o['modelo'] ?? ''));
                        ?>
                            <tr>
                                <td><?= (int) $o['id'] ?></td>
                                <td>
                                    <strong><?= e($o['matricula']) ?></strong>
                                    <?php if ($coche !== ''): ?><br><?= e($coche) ?><?php endif; ?>
                                </td>
                                <?php if ($verClientes): ?>
                                    <td>
                                        <?php if ($o['cliente_id']): ?>
                                            <a href="clientes.php?id=<?= (int) $o['cliente_id'] ?>"><?= e($o['cliente']) ?></a>
                                            <?php else: ?>—<?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><?= e($o['descripcion']) ?></td>

                                <td>
                                    <?php if ($puedeAsignar): ?>
                                        <form method="POST">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="asignar">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <select name="mecanico_id" aria-label="Mecánico de la orden <?= (int) $o['id'] ?>">
                                                <option value="0">Sin asignar</option>
                                                <?php foreach ($mecanicos as $m): ?>
                                                    <option value="<?= (int) $m['id'] ?>" <?= (int) $o['mecanico_id'] === (int) $m['id'] ? 'selected' : '' ?>>
                                                        <?= e($m['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit">Asignar</button>
                                        </form>
                                    <?php else: ?>
                                        <?= e($o['mecanico'] ?? 'Sin asignar') ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    // Edita el estado quien tiene permiso; el mecánico solo en sus órdenes (ya filtradas)
                                    if (puede('ordenes', 'editar')): ?>
                                        <form method="POST">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="estado">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <select name="estado" aria-label="Estado de la orden <?= (int) $o['id'] ?>">
                                                <?php foreach (ESTADOS as $valor => $texto): ?>
                                                    <option value="<?= $valor ?>" <?= $o['estado'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit">Guardar</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="estado estado--<?= e(str_replace('_', '-', $o['estado'])) ?>">
                                            <?= e(ESTADOS[$o['estado']] ?? $o['estado']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('d/m/Y', strtotime($o['creada_en']))) ?></td>
                                <?php if (puede('ordenes', 'borrar')): ?>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('¿Borrar la orden <?= (int) $o['id'] ?>?')">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="borrar">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <button type="submit">Borrar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($puedeCrear): ?>
        <script>
            // Muestra solo los campos del modo elegido (el servidor valida igualmente)
            (function() {
                var f = document.getElementById('form-orden');
                var existente = document.getElementById('bloque-existente');
                var nuevo = document.getElementById('bloque-nuevo');
                var clienteSel = document.getElementById('cliente_id');
                var clienteNuevo = document.getElementById('bloque-cliente-nuevo');

                function actualizar() {
                    var esNuevo = f.elements['modo'].value === 'nuevo';
                    existente.hidden = esNuevo;
                    nuevo.hidden = !esNuevo;
                    clienteNuevo.hidden = clienteSel.value !== '0';
                }
                f.addEventListener('change', actualizar);
                actualizar();
            })();
        </script>
    <?php endif; ?>
</body>

</html>