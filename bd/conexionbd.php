<?php
$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "gestor_taller";

$conexion = new mysqli($servidor, $usuario, $password, $base_datos);

if ($conexion->connect_error) {
    error_log("Error de conexión: " . $conexion->connect_error);
    die("Error al conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");
