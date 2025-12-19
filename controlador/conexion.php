<?php
// Configuración de la base de datos
$host = 'localhost';           // Servidor (cambia si es remoto)
$db = 'smartcoffee';           // Nombre de la base de datos
$user = 'root';          // Tu usuario de MySQL
$pass = '';         // Tu contraseña

try {
    // Crear conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    
    // Configurar para mostrar errores y usar excepciones
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
      
    // Ejemplo opcional: INSERT simple
    // $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, edad) VALUES (?, ?)");
    // $stmt->execute(['Juan', 25]);
    // echo " Registro insertado.";
    
} catch (PDOException $e) {
    // Manejo de errores
    die("Error de conexión: " . $e->getMessage());
}
?>
