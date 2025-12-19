<?php
session_start();  // Iniciar sesión
include_once('../controlador/conexion.php');  // Ajusta ruta a tu conexión DB (e.g., 'conexion.php' si está en el mismo directorio)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['inputEmail'] ?? '');
    $clave = $_POST['inputPassword'] ?? '';
    $remember = isset($_POST['inputRememberPassword']);

    if (empty($correo) || empty($clave)) {
        header('Location: ../login.php?error=3');  // Error: campos vacíos
        exit;
    }

    try {
        // Buscar usuario por correo
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, apellido, correo, clave, rol, activo FROM usuarios WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($clave, $usuario['clave'])) {
            if ($usuario['activo'] == 1) {
                // Login exitoso: iniciar sesión
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['apellido'] = $usuario['apellido'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['rol'] = $usuario['rol'];

                // Actualizar última sesión (opcional)
                $pdo->prepare("UPDATE usuarios SET ultima_sesion = NOW() WHERE id_usuario = ?")->execute([$usuario['id_usuario']]);

                // Redirigir al panel
                header('Location: ../Dashboard/panel.php');
                exit;
            } else {
                header('Location:../index.php?error=2');  // Error: cuenta inactiva
                exit;
            }
        } else {
            header('Location: ../index.php?error=1');  // Error: credenciales incorrectas
            exit;
        }
    } catch (PDOException $e) {
        error_log('Error en login: ' . $e->getMessage());  // Log para depuración
        header('Location: ../index.php?error=4');  // Error: servidor
        exit;
    }
} else {
    // Si no es POST, redirigir al login
    header('Location: ../index.php');
    exit;
}
?>
