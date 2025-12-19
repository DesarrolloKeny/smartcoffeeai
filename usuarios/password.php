<?php
// Manejar mensajes de éxito/error pasados via GET desde el controlador
$message = '';
$message_type = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message = 'Si la dirección de correo es correcta, hemos enviado un enlace para restablecer su contraseña.';
        $message_type = 'success';
    } elseif ($_GET['status'] == 'error_send') {
        $message = 'Hubo un error al intentar enviar el correo. Por favor, inténtelo de nuevo.';
        $message_type = 'danger';
    } elseif ($_GET['status'] == 'error_email') {
        $message = 'La dirección de correo electrónico proporcionada no está registrada.';
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Recuperación de Contraseña SmartCoffee AI" />
    <meta name="author" content="SmartCoffee AI Team" />
    <title>SmartCoffee AI</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
    /* Estilos adaptados del diseño Glassmorphism/Neumorphism */
    body {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); 
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e0f7fa; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    #recovery-container {
        background: rgba(255, 255, 255, 0.15); 
        border-radius: 16px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px); 
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3); 
        padding: 2.5rem;
        max-width: 450px;
        width: 90%;
        text-align: center;
    }

    .form-floating input.form-control {
        background: rgba(255, 255, 255, 0.2); 
        border: 1px solid rgba(255, 255, 255, 0.4); 
        color: #fff !important; 
        border-radius: 10px;
    }
    
    .form-floating > label {
        color: rgba(224, 247, 250, 1.0) !important; 
        font-weight: 500; 
        opacity: 0.95; 
        z-index: 2;
    }
    
    .form-floating input.form-control:focus {
        background: rgba(255, 255, 255, 0.3); 
        color: #fff !important;
        border-color: #00bcd4; 
        box-shadow: 0 0 0 0.25rem rgba(0, 188, 212, 0.5); 
    }

    /* Botón Turquesa Neumorfismo */
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
    
    /* Enlaces */
    a.small {
        color: #00bcd4; 
        text-decoration: none;
        transition: color 0.3s ease;
    }
    a.small:hover {
        color: #e0f7fa; 
    }
    
    /* Títulos */
    .recovery-title {
        color: #e0f7fa; 
        font-weight: 300;
        margin-top: 0;
        margin-bottom: 0.5rem;
    }
    .recovery-subtitle {
        color: #00bcd4; 
        font-weight: 600;
        font-size: 1.5rem;
    }

    /* Alertas */
    .alert-success {
        background-color: rgba(40, 167, 69, 0.8);
        border-color: rgba(40, 167, 69, 0.9);
        color: #fff;
    }
    .alert-danger {
        background-color: rgba(220, 53, 69, 0.8);
        border-color: rgba(220, 53, 69, 0.9);
        color: #fff;
    }

    </style>
</head>
<body>
    <div id="recovery-container" class="shadow-lg">
        
        <div class="mb-4">
            <i class="fas fa-lock fa-3x mb-2" style="color: #00bcd4;"></i>
            <h1 class="recovery-title">SmartCoffee AI</h1>
            <h4 class="recovery-subtitle">Restablecer Contraseña</h4>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <p class="text-center small mb-4 opacity-75">
            Ingresa tu dirección de correo electrónico asociada a tu cuenta para recibir las instrucciones de restablecimiento.
        </p>
        
        <form method="POST" action="controlador/procesar_recuperacion.php"> 
            
            <div class="form-floating mb-4">
                <input class="form-control" id="inputEmail" name="email" type="email" placeholder="Correo Electrónico" required />
             </div>
            
            <div class="d-grid gap-2 mb-4">
                <button type="submit" class="btn btn-turquesa btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Enviar Enlace de Recuperación
                </button>
            </div>
            
            <div class="text-center">
                <a class="small" href="../index.php">
                    <i class="fas fa-arrow-left me-1"></i> Volver a la página de ingreso
                </a>
            </div>
        </form>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
 