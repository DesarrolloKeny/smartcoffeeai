<?php
// Asegúrate de que las rutas sean correctas
session_start();
require_once '../vendor/autoload.php'; 
require_once 'conexion.php'; // Tu archivo de conexión PDO

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Recibir y Sanitizar el Correo
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['email'])) {
    header('Location: ../usuarios/password.php');
    exit;
}

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$redirect_url = '../usuarios/password.php'; // Ruta a la página de Recuperación de Contraseña

try {
    // 2. Verificar que el Email exista en la tabla de usuarios
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Redirigir siempre con éxito por seguridad (para no revelar qué emails existen)
        header("Location: $redirect_url?status=success");
        exit;
    }

    // 3. Generar Código y Expiración
    $token = strval(mt_rand(100000, 999999)); // Código de 6 dígitos
    $expires_at = date('Y-m-d H:i:s', time() + 900); // Expira en 15 minutos (900 segundos)

    // 4. Guardar/Actualizar el Token en la DB
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (email, token, expires_at) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
    ");
    $stmt->execute([$email, $token, $expires_at]);


    // 5. Configurar y Enviar el Correo
    $mail = new PHPMailer(true);

    // Configuración del Servidor SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.tucorreo.com'; // EJEMPLO: smtp.gmail.com o smtp.sendgrid.net
    $mail->SMTPAuth   = true;
    $mail->Username   = 'Kenygarrido24@gmail.com'; // Tu email SMTP
    $mail->Password   = 'TuContraseñaODemo'; // Tu contraseña o clave de aplicación
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // o ENCRYPTION_SMTPS
    $mail->Port       = 587; // o 465 si usas SMTPS

    // Contenido del correo
    $mail->setFrom('no-reply@smartcoffeeai.com', 'SmartCoffee AI');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Restablecimiento de Contraseña - Codigo de Verificacion';
    
    $body = "
        <h2 style='color:#00bcd4;'>Código de Verificación SmartCoffee AI</h2>
        <p>Has solicitado restablecer tu contraseña. Utiliza el siguiente código para verificar tu identidad:</p>
        <p style='font-size: 24px; font-weight: bold; background-color: #f0f0f0; padding: 10px; border-radius: 5px; display: inline-block;'>$token</p>
        <p>Este código es válido por 15 minutos. Si no solicitaste este cambio, ignora este correo.</p>
        <p>Atentamente,<br>El equipo de SmartCoffee AI</p>
    ";

    $mail->Body = $body;
    $mail->send();

    // Redirigir a la página de verificación, pasando el email
    header("Location: ../usuarios/verificacion_codigo?email=" . urlencode($email));
    exit;

} catch (Exception $e) {
    // Si hay un error de envío del correo
    error_log("Error al enviar correo: " . $e->getMessage());
    header("Location: $redirect_url?status=error_send");
    exit;
} catch (PDOException $e) {
    // Si hay un error de DB
    error_log("Error de DB: " . $e->getMessage());
    header("Location: $redirect_url?status=error_send");
    exit;
}
?>