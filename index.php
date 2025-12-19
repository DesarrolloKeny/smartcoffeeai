<?php
// Solo manejar errores pasados via GET desde el controlador
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 1:
            $error_message = 'Correo o contraseña incorrectos.';
            break;
        case 2:
            $error_message = 'Tu cuenta está inactiva. Contacta al administrador.';
            break;
        case 3:
            $error_message = 'Por favor, ingresa correo y contraseña.';
            break;
        case 4:
            $error_message = 'Error en el servidor. Inténtalo de nuevo.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Login SmartCoffee AI" />
    <meta name="author" content="SmartCoffee AI Team" />
    <title>SmartCoffee AI</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
    /* Fondo Oscuro con Efecto Tecnológico */
    body {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); 
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e0f7fa; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* ------------------------------------- */
    /* Estilo Glassmorfismo (Tarjetas de Vidrio) */
    /* ------------------------------------- */
    #login-container {
        background: rgba(255, 255, 255, 0.15); 
        border-radius: 16px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px); 
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3); 
        padding: 2.5rem;
        max-width: 400px;
        width: 90%;
        text-align: center;
        position: relative;
    }
    
    /* ------------------------------------- */
    /* Estilos del Logo */
    /* ------------------------------------- */
    #login-logo {
        max-width: 80px; 
        height: auto;
        margin: 0 auto 1.5rem auto;
        display: block;
        border-radius: 50%;
        padding: 5px; 
        /* Se usa la URL estática del logo de SmartCoffee AI aquí */
        background: rgba(255, 255, 255, 0.9); 
        box-shadow: 0 0 15px rgba(0, 188, 212, 0.5); 
    }

    /* ------------------------------------- */
    /* 🚨 CORRECCIÓN DE CONTRASTE FINAL 🚨 */
    /* ------------------------------------- */
    .form-floating input.form-control {
        /* Fondo del input más opaco */
        background: rgba(255, 255, 255, 0.2); 
        border: 1px solid rgba(255, 255, 255, 0.4); 
        color: #fff !important; /* Texto escrito: Blanco puro (ALTO CONTRASTE) */
        border-radius: 10px;
    }
    
    .form-floating > label {
        /* Color del label flotante: CASI OPACA (Solución de contraste) */
        color: rgba(224, 247, 250, 1.0) !important; 
        font-weight: 500; 
        opacity: 0.95; 
        z-index: 2; /* Asegura que esté sobre el fondo del input */
    }
    
    .form-floating input.form-control:focus {
        background: rgba(255, 255, 255, 0.3); 
        color: #fff !important;
        border-color: #00bcd4; 
        box-shadow: 0 0 0 0.25rem rgba(0, 188, 212, 0.5); 
    }
    /* Se mantiene el placeholder para navegadores que no soportan form-floating */
    .form-floating input.form-control::placeholder { 
        color: rgba(224, 247, 250, 0.6);
    }
    
    /* ------------------------------------- */
    /* Estilos de Botones (Turquesa Neumorfismo) */
    /* ------------------------------------- */
    .btn-turquesa {
        background-color: #00bcd4; 
        border: none;
        color: #0f2027; 
        font-weight: bold;
        border-radius: 25px;
        padding: 10px 0;
        transition: background-color 0.3s ease, transform 0.1s ease;
        box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.5), -5px -5px 10px rgba(255, 255, 255, 0.1);
    }
    .btn-turquesa:hover {
        background-color: #00a6b8;
        color: #0f2027;
    }
    .btn-turquesa:active {
        transform: scale(0.98);
        box-shadow: inset 2px 2px 5px rgba(0, 0, 0, 0.5), inset -2px -2px 5px rgba(255, 255, 255, 0.1);
    }
    
    /* Enlaces secundarios */
    a.small {
        color: #00bcd4; 
        text-decoration: none;
        transition: color 0.3s ease;
    }
    a.small:hover {
        color: #e0f7fa; 
        text-decoration: none;
    }

    /* Título */
    .login-title {
        color: #e0f7fa; 
        font-weight: 300;
    }
    .login-title span {
        color: #00bcd4; 
        font-weight: 600;
    }

    /* Alerta de Error */
    .alert-danger {
        background-color: rgba(220, 53, 69, 0.8);
        border-color: rgba(220, 53, 69, 0.9);
        color: #fff;
    }

    </style>
</head>
<body>
    <div id="login-container" class="shadow-lg">
        
        <img src="assets/img/logo.png" alt="SmartCoffee AI Logo" id="login-logo" />
        
        <h3 class="mb-4 fw-semibold login-title">Bienvenido a <span style="color: #00bcd4;">SmartCoffee AI</span></h3>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="controlador/login.php"> 
            
            <div class="form-floating mb-3">
                <input class="form-control" id="inputEmail" name="inputEmail" type="email" placeholder="Correo Electrónico" value="<?php echo htmlspecialchars($_POST['inputEmail'] ?? ''); ?>" required />
             </div>
            
            <div class="form-floating mb-3">
                <input class="form-control" id="inputPassword" name="inputPassword" type="password" placeholder="Contraseña" required />
                 
            </div>
            
            <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
                <a class="small" href="usuarios/password.php">¿Olvidaste tu contraseña?</a>
                
                <button type="submit" class="btn btn-turquesa px-4">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
            </div>
        </form>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>