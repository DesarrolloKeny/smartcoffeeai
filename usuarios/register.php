<?php
 
session_start();
// Si la sesión existe, la usamos; si no, ponemos 'Invitado' o 'Admin'
$nombre_usuario = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Admin'; 

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');  // Redirigir al login si no está logueado
    exit;
}

// Incluir conexión a la DB
include_once('../controlador/conexion.php');

// Obtener datos del usuario logueado
$id_usuario = $_SESSION['id_usuario'];
$query = "SELECT id_usuario, nombre, apellido, correo, rol, activo, fecha_creacion, ultima_sesion FROM usuarios WHERE id_usuario = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die("Usuario no encontrado.");
}

// Obtener rol del usuario para el sidebar
$rol = $usuario['rol'];

// Manejo de edición de perfil
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_perfil') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $clave_actual = $_POST['clave_actual'] ?? '';
    $clave_nueva = $_POST['clave_nueva'] ?? '';
    $clave_confirmar = $_POST['clave_confirmar'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($correo)) {
        $mensaje = 'Nombre y correo son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo no válido.';
    } elseif (!empty($clave_nueva) && strlen($clave_nueva) < 6) {
        $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif (!empty($clave_nueva) && $clave_nueva !== $clave_confirmar) {
        $mensaje = 'Las contraseñas no coinciden.';
    } elseif (!empty($clave_nueva) && !password_verify($clave_actual, $usuario['clave'])) {
        $mensaje = 'Contraseña actual incorrecta.';
    } else {
        // Preparar actualización
        $update_fields = ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo];
        if (!empty($clave_nueva)) {
            $update_fields['clave'] = password_hash($clave_nueva, PASSWORD_DEFAULT);
        }

        // Actualizar BD
        $set_clause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update_fields)));
        $stmt_update = $pdo->prepare("UPDATE usuarios SET $set_clause WHERE id_usuario = ?");
        $params = array_values($update_fields);
        $params[] = $id_usuario;
        $stmt_update->execute($params);

        // Actualizar sesión si cambió nombre
        $_SESSION['nombre'] = $nombre;

        $mensaje = 'Perfil actualizado exitosamente.';
        // Recargar datos
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>SmartCoffee AI</title>
        <link rel="icon" type="image/png" href="../assets/img/logo.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Para alerts -->
        <style>
        :root { --primary-coffee: #6F4E37; --secondary-orange: #E67E22; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        #layoutSidenav_nav .sb-sidenav { background: linear-gradient(180deg, #212529 0%, #2c3e50 100%) !important; }
        .sb-sidenav-menu .nav-link.active { background: linear-gradient(45deg, var(--primary-coffee), var(--secondary-orange)) !important; color: #fff !important; }
        
        .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-left: auto; border: 2px solid rgba(255,255,255,0.2); }
        .dot-online { background-color: #2ecc71; box-shadow: 0 0 8px #2ecc71; }
        .dot-offline { background-color: #e74c3c; box-shadow: 0 0 8px #e74c3c; }

        .bg-gradient-primary { background: linear-gradient(45deg, #4e73df 0%, #224abe 100%); }
        .bg-gradient-success { background: linear-gradient(45deg, #1cc88a 0%, #13855c 100%); }
        .bg-gradient-danger { background: linear-gradient(45deg, #e74c3c 0%, #c0392b 100%); }
        .bg-gradient-warning { background: linear-gradient(45deg, #f6c23e 0%, #dda20a 100%); }
        
        .card-reporte { border: none; transition: transform 0.2s; border-radius: 12px; }
        .icon-circle { height: 3rem; width: 3rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); }

        .carrito-scroll { min-height: 400px; max-height: 400px; overflow-y: auto; background: #fff; border-radius: 12px; border: 1px solid #dee2e6; }
        .total-panel { background: #1a1d20; color: #fff; border-radius: 12px; padding: 20px; }
        .opacity-closed { filter: grayscale(1); opacity: 0.5; pointer-events: none; }
        
        .venta-item { transition: background 0.2s; border-bottom: 1px solid #eee; }
        .venta-item:hover { background-color: #f8f9fa !important; }
        .btn-print { color: #6c757d; transition: color 0.2s; }
        .btn-print:hover { color: #000; }
    </style>
<style>
    /* Contenedor circular blanco para el logo */
    .logo-circle {
        background-color: #ffffff;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px; /* Espacio entre el borde del círculo y el logo */
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        flex-shrink: 0; /* Evita que el círculo se deforme */
    }

    /* Ajuste de la imagen dentro del círculo */
    .logo-circle img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* Ajustes responsivos para móviles */
    @media (max-width: 576px) {
        .logo-circle {
            width: 32px;
            height: 32px;
            padding: 3px;
        }
        
        .navbar-brand {
            padding-left: 0.5rem !important;
        }
    }
</style>
</head>
    <body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark shadow">
    <a class="navbar-brand ps-3 d-flex align-items-center" href="../Dashboard/panel.php">
        <span class="logo-circle me-2">
            <img src="../assets/img/logo.png" alt="SmartCoffee Logo">
        </span>
        <span class="fw-bold d-none d-sm-inline text-white">
            SmartCoffee <span class="text-warning">AI</span>
        </span>
    </a>

    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0 text-white-50" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="ms-auto"></div>

    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle fa-lg"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item py-2" href="../usuarios/register.php"><i class="fas fa-id-card me-2 opacity-50"></i>Mi Perfil</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item py-2 text-danger" href="../usuarios/cerrar.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
            </ul>
        </li>
    </ul>
</nav>


        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="../Dashboard/panel.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Panel
                            </a>
                            <div class="sb-sidenav-menu-heading">Gestión</div>
                            <a class="nav-link" href="../venta/venta.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                                Ventas
                            </a>
                            <?php if ($rol === 'admin'): ?>
                                <a class="nav-link" href="../productos/GestionProducto.php">
                                    <div class="sb-nav-link-icon"><i class="fas fa-coffee"></i></div>
                                    Productos
                                </a>
                            <?php elseif ($rol === 'vendedor'): ?>
                                <a class="nav-link" href="../productos/GestionProducto.php" title="Solo lectura">
                                    <div class="sb-nav-link-icon"><i class="fas fa-coffee"></i></div>
                                    Productos
                                </a>
                            <?php endif; ?>
                            <?php if ($rol === 'admin' || $rol === 'vendedor'): ?>
                                <a class="nav-link" href="../recetas/GestionResetas.php">
                                    <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                                    Recetas
                                </a>
                            <?php endif; ?>
                            <?php if ($rol === 'admin'): ?>
                                <a class="nav-link" href="../stock/GestionStock.php">
                                    <div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>
                                    Stock
                                </a>
                            <?php elseif ($rol === 'vendedor'): ?>
                                <a class="nav-link" href="../stock/GestionStock.php" title="Solo lectura">
                                    <div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>
                                    Stock (Lectura)
                                </a>
                            <?php endif; ?>
                            <a class="nav-link" href="../cliente/GestionCliente.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                Clientes
                            </a>
                            <?php if ($rol === 'admin'): ?>
                                <a class="nav-link" href="../usuarios/GestionUsuario.php">
                                    <div class="sb-nav-link-icon"><i class="fas fa-user-cog"></i></div>
                                    Usuarios
                                </a>
                            <?php endif; ?>
                            <div class="sb-sidenav-menu-heading">Addons</div>
                            <?php if ($rol === 'admin'): ?>
                                <a class="nav-link" href="../Reporte/reporte.php">
                                    <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                                    Reportes
                                </a>
                            <?php elseif ($rol === 'vendedor'): ?>
                                <a class="nav-link" href="../Reporte/reporte.php" title="Solo sus ventas">
                                    <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                                    Reportes (Limitado)
                                </a>
                            <?php endif; ?>
                            <a class="nav-link" href="../caja/caja.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>
                                Caja
                            </a>
                            
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Conectado como:</div>
                        <?php echo htmlspecialchars($nombre_usuario); ?> <br><?php echo ucfirst($rol); ?>
                    </div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4 py-4">
                        <!-- Mostrar mensaje si hay -->
                        <?php if ($mensaje): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
                        <?php endif; ?>

                        <div class="container py-4">
                            <h2 class="mb-4 fw-bold text-primary"><i class="fas fa-user-circle"></i> Mi Perfil</h2>

                            <div class="row">
                                <!-- Información Personal -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información Personal</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">ID Usuario:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['id_usuario']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Nombre:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['nombre']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Apellido:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['apellido'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Correo:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['correo']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Rol:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Estado:</label>
                                                <p class="form-control-plaintext"><?php echo $usuario['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Eliminado</span>'; ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Fecha de Creación:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['fecha_creacion']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Última Sesión:</label>
                                                <p class="form-control-plaintext"><?php echo htmlspecialchars($usuario['ultima_sesion'] ?? 'Nunca'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Editar Perfil -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Perfil</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="accion" value="editar_perfil">
                                                <div class="mb-3">
                                                    <label for="nombre" class="form-label">Nombre</label>
                                                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="apellido" class="form-label">Apellido</label>
                                                    <input type="text" class="form-control" name="apellido" value="<?php echo htmlspecialchars($usuario['apellido'] ?? ''); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="correo" class="form-label">Correo</label>
                                                    <input type="email" class="form-control" name="correo" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>
                                                </div>
                                                <hr>
                                                <h6 class="text-muted">Cambiar Contraseña (Opcional)</h6>
                                                <div class="mb-3">
                                                    <label for="clave_actual" class="form-label">Contraseña Actual</label>
                                                    <input type="password" class="form-control" name="clave_actual" placeholder="Ingresa tu contraseña actual">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="clave_nueva" class="form-label">Nueva Contraseña</label>
                                                    <input type="password" class="form-control" name="clave_nueva" placeholder="Nueva contraseña (mín. 6 caracteres)">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="clave_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                                                    <input type="password" class="form-control" name="clave_confirmar" placeholder="Repite la nueva contraseña">
                                                </div>
                                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Guardar Cambios</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; SmartCoffee AI</div>
                            <div></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="../assets/demo/chart-area-demo.js"></script>
        <script src="../assets/demo/chart-bar-demo.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="../js/datatables-simple-demo.js"></script>
    </body>
</html>
