<?php
// Asegúrate de que las rutas sean correctas
session_start();
require_once 'conexion.php'; // Tu archivo de conexión PDO

// 1. Recibir y Sanitizar Datos
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['codigo']) || !isset($_POST['email'])) {
    header('Location: ../usuarios/verificacion_codigo.php');
    exit;
}

$codigo_ingresado = filter_var($_POST['codigo'], FILTER_SANITIZE_NUMBER_INT);
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$redirect_url_base = '../usuarios/verificacion_codigo.php'; 
$redirect_url_error = $redirect_url_base . "?email=" . urlencode($email);

try {
    // 2. Buscar el token y verificar que no haya expirado
    $stmt = $pdo->prepare("
        SELECT token, expires_at 
        FROM password_resets 
        WHERE email = ? AND token = ?
    ");
    $stmt->execute([$email, $codigo_ingresado]);
    $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset_record) {
        // Token no encontrado o ya usado
        header("Location: $redirect_url_error&status=invalid");
        exit;
    }

    $expiration_time = strtotime($reset_record['expires_at']);
    $current_time = time();

    if ($current_time > $expiration_time) {
        // Token Expirado
        // Opcional: Eliminar el token expirado de la DB
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        header("Location: $redirect_url_error&status=expired");
        exit;
    }

    // 3. ¡Código Válido y Vigente!
    
    // Eliminar el token de la DB inmediatamente para evitar reuso
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    
    // Generar un nuevo token de sesión temporal para la siguiente etapa (Restablecer Contraseña Final)
    $final_token = bin2hex(random_bytes(32)); 
    // Guardar este final_token en la DB con el email (o usar la sesión)
    // Recomendación: Usar la sesión por simplicidad, pero con cuidado.
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $final_token;
    
    // Redirigir a la página final de restablecimiento de contraseña
    header("Location: ../usuarios/restablecer_password_final.php?token=" . $final_token);
    exit;

} catch (PDOException $e) {
    error_log("Error de DB en verificación: " . $e->getMessage());
    header("Location: $redirect_url_error&status=expired"); // Mensaje genérico de error
    exit;
}
?> 