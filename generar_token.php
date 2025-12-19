<?php
// generar_token.php
header('Content-Type: application/json');

// Configuración de tu base de datos
$host = 'localhost';
$db   = 'smartcoffee';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Generar un token único y seguro
    $token = bin2hex(random_bytes(16)); 
    
    // 2. Establecer expiración (damos 30 segundos de vida por si el internet está lento)
    $expiracion = date('Y-m-d H:i:s', strtotime('+30 seconds'));

    // 3. Insertar en la tabla que creamos
    $stmt = $pdo->prepare("INSERT INTO tokens_chatbot (token, expira_en, usado) VALUES (?, ?, 0)");
    $stmt->execute([$token, $expiracion]);

    // 4. Construir la URL que el cliente escaneará
    // AJUSTA ESTA URL a la ubicación real de tu archivo chatbot.html
    $url_chatbot = "http://127.0.0.1/smartcoffee/chatbot.php?t=" . $token;

    echo json_encode(['status' => 'success', 'url' => $url_chatbot]);

} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>