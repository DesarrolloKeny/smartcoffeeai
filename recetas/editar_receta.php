<?php
session_start();
include_once('../controlador/conexion.php');  // Ajusta ruta
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$receta = null;
$error = '';
$success = '';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT r.*, p.nombre AS producto FROM recetas r JOIN productos p ON r.id_producto = p.id_producto WHERE r.id_receta = ?");
    $stmt->execute([$id]);
    $receta = $stmt->fetch();
    if (!$receta) {
        die("Receta no encontrada.");
    }
} else {
    die("ID no proporcionado.");
}

// Procesar actualización si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto = trim($_POST['producto'] ?? '');
    $bebestible = $_POST['bebestible'] ?? 'No';
    $capacidad = (int)$_POST['capacidad'] ?? 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($producto) || $capacidad <= 0) {
        $error = "Producto y capacidad son obligatorios.";
    } else {
        // Actualizar receta (asumiendo que producto es el nombre, pero en realidad es id_producto; ajusta si es necesario)
        $stmt = $pdo->prepare("UPDATE recetas SET bebestible = ?, capacidad_ml = ?, activo = ? WHERE id_receta = ?");
        if ($stmt->execute([$bebestible === 'Sí' ? 1 : 0, $capacidad, $activo, $id])) {
            $success = "Receta actualizada correctamente.";
        } else {
            $error = "Error al actualizar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Para alerts llamativos -->
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
                            <a class="nav-link" href="../Dashboard/panel.html">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                 Panel
                            </a>
                            <div class="sb-sidenav-menu-heading">Gestión</div>
                            <a class="nav-link" href="../venta/venta.html">
                                <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                                Ventas
                            </a>
                            <a class="nav-link" href="../productos/GestionProducto.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-coffee"></i></div>
                                Productos
                            </a>
                            <a class="nav-link active" href="../recetas/GestionResetas.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                                Recetas
                            </a>
                            <a class="nav-link" href="../stock/GestionStock.html">
                                <div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>
                               Stock
                            </a>
                            <a class="nav-link" href="../cliente/GestionCliente.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                Clientes
                            </a>
                            <div class="sb-sidenav-menu-heading">Addons</div>
                            <a class="nav-link" href="reportes.html">
                                <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                                Reportes
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
                        <h1 class="mt-4">Editar Receta</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="../Dashboard/panel.html">Panel</a></li>
                            <li class="breadcrumb-item"><a href="../recetas/GestionResetas.php">Recetas</a></li>
                            <li class="breadcrumb-item active">Editar Receta</li>
                        </ol>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <!-- Formulario de Edición -->
                        <div class="row justify-content-center">
                            <div class="col-lg-6">
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-header">
                                        <h3 class="text-center font-weight-light my-4">
                                            <i class="fas fa-coffee me-2"></i>Editar Receta <?php echo htmlspecialchars($receta['nombre']); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <form id="editarRecetaForm" method="POST">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputProducto" name="producto" type="text" placeholder="Producto" value="<?php echo htmlspecialchars($receta['producto']); ?>" required />
                                                <label for="inputProducto">Producto</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputBebestible" name="bebestible" required>
                                                    <option value="Sí" <?php echo $receta['bebestible'] ? 'selected' : ''; ?>>Sí</option>
                                                    <option value="No" <?php echo !$receta['bebestible'] ? 'selected' : ''; ?>>No</option>
                                                </select>
                                                <label for="inputBebestible">Bebestible</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputCapacidad" name="capacidad" type="number" placeholder="Capacidad" value="<?php echo htmlspecialchars($receta['capacidad_ml']); ?>" min="1" required />
                                                <label for="inputCapacidad">Capacidad (ml)</label>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputActivo" name="activo" type="checkbox" <?php echo $receta['activo'] ? 'checked' : ''; ?> />
                                                <label class="form-check-label" for="inputActivo">Activo</label>
                                            </div>
                                            <p class="text-muted"><small>Creado: <?php echo htmlspecialchars($receta['fecha_creacion']); ?></small></p>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="btn btn-secondary" href="../recetas/GestionRecetas.php">Cancelar</a>
                                                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                                            </div>
                                        </form>
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
                            <div>
                               
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script>
            // Validación y envío del formulario
            document.getElementById('editarRecetaForm').addEventListener('submit', function(event) {
                event.preventDefault();
                const producto = document.getElementById('inputProducto').value.trim();
                const capacidad = document.getElementById('inputCapacidad').value;

                if (!producto) {
                    Swal.fire('Error', 'El campo Producto es obligatorio.', 'error');
                    return;
                }
                if (capacidad <= 0) {
                    Swal.fire('Error', 'La capacidad debe ser un número positivo.', 'error');
                    return;
                }

                // Enviar formulario
                this.submit();
            });
        </script>
    </body>
</html>
