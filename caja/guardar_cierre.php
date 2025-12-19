<?php
session_start(); // CRÍTICO: Iniciar sesión

// Se asume que conexion.php define la variable $pdo (conexión PDO)
require_once "../controlador/conexion.php"; // Asegura que $pdo esté disponible
require_once "funciones_caja.php";

// CRÍTICO: Validar sesión y rol (Solo Admin debería cerrar caja)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../login.php?error=acceso_denegado');
    exit;
}

// Validación básica de POST
if (
    !isset($_POST["id_caja"]) || 
    !isset($_POST["monto_contado"]) || 
    !isset($_POST["total_esperado"])
) {
    // Redirigir si faltan datos
    header("Location: caja.php?error=datos_faltantes");
    exit;
}

// Conversión de datos
$monto_contado = floatval($_POST["monto_contado"]);
$total_esperado = floatval($_POST["total_esperado"]);

$data = [
    "id_caja" => $_POST["id_caja"],
    "monto_contado" => $monto_contado,       
    "total_esperado" => $total_esperado,     
    "id_usuario" => $_SESSION["id_usuario"],
    "efectivo_final" => $monto_contado,      
    "cierre" => $monto_contado,              
    "diferencia" => $monto_contado - $total_esperado
];

// Llama a la función, AHORA PASANDO $pdo
if (guardarCierreCaja($pdo, $data)) {
    header("Location: caja.php?mensaje=caja_cerrada&fecha=" . date('Y-m-d'));
} else {
    header("Location: caja.php?error=fallo_cierre");
}

exit;
?>