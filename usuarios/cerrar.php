<?php
// Archivo: ../controlador/logout.php
// Maneja el cierre de sesión y previene volver atrás

session_start();  // Iniciar sesión para poder destruirla

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Headers para prevenir cache y evitar volver atrás
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Redirigir al login
header('Location: ../index.php');
exit;
?>