<?php

/**
 * Único sitio donde se decide quién puede hacer qué.
 * Para dar o quitar acceso, solo se edita PERMISOS.
 */

// Carpeta de la app en el servidor (con barra final)
const BASE_URL = '/GestorTaller/';

// Etiqueta legible de cada rol (clave = valor guardado en usuarios.rol)
const ROLES = [
    'administrador' => 'Administrador',
    'jefeTaller'    => 'Jefe de taller',
    'taller'        => 'Taller',
    'mecanico'      => 'Mecánico',
    'jefeRecambios' => 'Jefe de recambios',
    'recambios'     => 'Recambios',
    'facturacion'   => 'Facturación',
];

// Módulos del dashboard: título, descripción corta y archivo
const MODULOS = [
    'ordenes'   => ['titulo' => 'Órdenes de reparación', 'desc' => 'Entradas, estado y trabajos del taller', 'url' => 'modulos/ordenes.php'],
    'clientes'  => ['titulo' => 'Clientes',              'desc' => 'Fichas y datos de contacto',            'url' => 'modulos/clientes.php'],
    'vehiculos' => ['titulo' => 'Vehículos',             'desc' => 'Matrículas e historial',                'url' => 'modulos/vehiculos.php'],
    'recambios' => ['titulo' => 'Recambios',             'desc' => 'Stock y referencias',                   'url' => 'modulos/recambios.php'],
    'pedidos'   => ['titulo' => 'Pedidos de recambios',  'desc' => 'Compras a proveedores',                 'url' => 'modulos/pedidos.php'],
    'facturas'  => ['titulo' => 'Facturación',           'desc' => 'Facturas y cobros',                     'url' => 'modulos/facturas.php'],
    'informes'  => ['titulo' => 'Informes',              'desc' => 'Resumen de actividad',                  'url' => 'modulos/informes.php'],
    'usuarios'  => ['titulo' => 'Usuarios',              'desc' => 'Altas, roles y bajas',                  'url' => 'modulos/usuarios.php'],
];

// Acciones posibles por módulo: ver, crear, editar, borrar
const PERMISOS = [
    'administrador' => [
        'ordenes'   => ['ver', 'crear', 'editar', 'borrar', 'asignar'],
        'clientes'  => ['ver', 'crear', 'editar', 'borrar'],
        'vehiculos' => ['ver', 'crear', 'editar', 'borrar'],
        'recambios' => ['ver', 'crear', 'editar', 'borrar'],
        'pedidos'   => ['ver', 'crear', 'editar', 'borrar'],
        'facturas'  => ['ver', 'crear', 'editar', 'borrar'],
        'informes'  => ['ver'],
        'usuarios'  => ['ver', 'crear', 'editar', 'borrar'],
    ],
    'jefeTaller' => [
        'ordenes'   => ['ver', 'crear', 'editar', 'borrar', 'asignar'],
        'clientes'  => ['ver', 'crear', 'editar'],
        'vehiculos' => ['ver', 'crear', 'editar'],
        'recambios' => ['ver'],
        'informes'  => ['ver'],
    ],
    'taller' => [
        'ordenes'   => ['ver', 'crear'],
        'clientes'  => ['ver', 'crear', 'editar'],
        'vehiculos' => ['ver', 'crear', 'editar'],
    ],
    'mecanico' => [
        'ordenes'   => ['ver', 'editar'],   // solo las suyas (ver ordenes.php)
        'recambios' => ['ver'],
    ],
    'jefeRecambios' => [
        'recambios' => ['ver', 'crear', 'editar', 'borrar'],
        'pedidos'   => ['ver', 'crear', 'editar', 'borrar'],
        'ordenes'   => ['ver'],
    ],
    'recambios' => [
        'recambios' => ['ver', 'editar'],
        'pedidos'   => ['ver', 'crear'],
    ],
    'facturacion' => [
        'facturas'  => ['ver', 'crear', 'editar'],
        'clientes'  => ['ver'],
        'ordenes'   => ['ver'],
        'informes'  => ['ver'],
    ],
];
