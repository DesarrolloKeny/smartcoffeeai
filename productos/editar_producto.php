<?php
include_once('../controlador/conexion.php');  // Ajusta ruta
session_start();
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario'; 
$rol = $_SESSION['rol'] ?? 'vendedor';
$producto = null;
$error = '';
$success = '';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    if (!$producto) {
        die("Producto no encontrado.");
    }
} else {
    die("ID no proporcionado.");
}

// Procesar actualización si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float)$_POST['precio'] ?? 0;
    $categoria = trim($_POST['categoria'] ?? '');
    $stock = (int)$_POST['stock'] ?? 0;
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || $precio <= 0 || $stock < 0) {
        $error = "Nombre, precio y stock son obligatorios.";
    } else {
        $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, categoria = ?, stock = ?, destacado = ?, activo = ? WHERE id_producto = ?");
        if ($stmt->execute([$nombre, $descripcion, $precio, $categoria, $stock, $destacado, $activo, $id])) {
            $success = "Producto actualizado correctamente.";
        } else {
            $error = "Error al actualizar.";
        }
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        <h1 class="mt-4">Editar Producto</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="../Dashboard/panel.html">Panel</a></li>
                            <li class="breadcrumb-item"><a href="../productos/GestionProducto.php">Productos</a></li>
                            <li class="breadcrumb-item active">Editar Producto</li>
                        </ol>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="row justify-content-center">
                            <div class="col-lg-6">
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-header">
                                        <h3 class="text-center font-weight-light my-4">
                                            <i class="fas fa-edit me-2"></i>Editar Producto <?php echo htmlspecialchars($producto['nombre']); ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <form id="editarProductoForm" method="POST">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputNombre" name="nombre" type="text" placeholder="Nombre del producto" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required />
                                                <label for="inputNombre">Nombre del producto</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <textarea class="form-control" id="inputDescripcion" name="descripcion" placeholder="Descripción" rows="3"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                                                <label for="inputDescripcion">Descripción</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputPrecio" name="precio" type="number" step="0.01" placeholder="Precio" value="<?php echo htmlspecialchars($producto['precio']); ?>" required />
                                                <label for="inputPrecio">Precio</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputCategoria" name="categoria" type="text" placeholder="Categoría" value="<?php echo htmlspecialchars($producto['categoria']); ?>" />
                                                <label for="inputCategoria">Categoría</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputStock" name="stock" type="number" placeholder="Stock" value="<?php echo htmlspecialchars($producto['stock']); ?>" required />
                                                <label for="inputStock">Stock</label>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputDestacado" name="destacado" type="checkbox" value="1" <?php echo $producto['destacado'] ? 'checked' : ''; ?> />
                                                <label class="form-check-label" for="inputDestacado">Producto destacado</label>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputActivo" name="activo" type="checkbox" value="1" <?php echo $producto['activo'] ? 'checked' : ''; ?> />
                                                <label class="form-check-label" for="inputActivo">Producto activo</label>
                                            </div>
                                            <p class="text-muted"><small>Creado: <?php echo htmlspecialchars($producto['fecha_creacion']); ?></small></p>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="btn btn-secondary" href="../productos/GestionProducto.php">Cancelar</a>
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
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script>
            document.getElementById('editarProductoForm').addEventListener('submit', function(event) {
                event.preventDefault();
                const nombre = document.getElementById('inputNombre').value.trim();
                const precio = parseFloat(document.getElementById('inputPrecio').value);
                const stock = parseInt(document.getElementById('inputStock').value);
                if (!nombre || precio <= 0 || stock < 0) {
                    Swal.fire('Error', 'Nombre, precio y stock son obligatorios.', 'error');
                    return;
                }
                this.submit();
            });
        </script>
    </body>
</html>
