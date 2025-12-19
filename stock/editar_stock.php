<?php
session_start();  // Iniciar sesión
include_once('../controlador/conexion.php');  // Ajusta ruta si es '../controlador/conexion.php'
 // Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
// Inicializar variables
$mensaje = '';
$movimiento = null;
// Obtener rol del usuario y validar
$rol = $_SESSION['rol'] ?? 'vendedor';
// Si se pasa ID via GET, cargar datos del movimiento
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT s.id_stock, s.id_insumo, i.nombre AS insumo_nombre, s.cantidad, s.tipo_movimiento, s.origen 
                               FROM stock_insumos s 
                               JOIN insumos i ON s.id_insumo = i.id_insumo 
                               WHERE s.id_stock = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $movimiento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$movimiento) {
            $mensaje = "Movimiento no encontrado.";
        }
    } catch (PDOException $e) {
        $mensaje = "Error al cargar datos: " . $e->getMessage();
    }
}

// Procesar actualización si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_stock'])) {
    $id_stock = (int)$_POST['id_stock'];
    $id_insumo = (int)$_POST['id_insumo'];
    $cantidad = (float)$_POST['cantidad'];
    $tipo_movimiento = trim($_POST['tipo_movimiento']);
    $origen = trim($_POST['origen']);

    try {
        if ($id_insumo <= 0 || $cantidad == 0 || !in_array($tipo_movimiento, ['entrada', 'salida'])) {
            throw new Exception("ID de insumo, cantidad y tipo de movimiento son obligatorios. Tipo debe ser 'entrada' o 'salida'.");
        }

        // Verificar que el insumo existe
        $stmt_check = $pdo->prepare("SELECT id_insumo FROM insumos WHERE id_insumo = :id_insumo AND activo = 1");
        $stmt_check->bindParam(':id_insumo', $id_insumo);
        $stmt_check->execute();
        if ($stmt_check->rowCount() == 0) {
            throw new Exception("El insumo seleccionado no existe o no está activo.");
        }

        // Actualizar
        $sql = "UPDATE stock_insumos SET id_insumo = :id_insumo, cantidad = :cantidad, tipo_movimiento = :tipo_movimiento, origen = :origen 
                WHERE id_stock = :id_stock";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_insumo', $id_insumo);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':tipo_movimiento', $tipo_movimiento);
        $stmt->bindParam(':origen', $origen);
        $stmt->bindParam(':id_stock', $id_stock);
        $stmt->execute();

        header("Location: GestionStock.php?success=Movimiento actualizado correctamente.");
        exit;
    } catch (PDOException $e) {
        $mensaje = "Error de DB: " . $e->getMessage();
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
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
                    <?php else: ?>
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
                    <?php else: ?>
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
                    <?php else: ?>
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
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <h4 class="text-center mb-4">Editar Movimiento de Stock</h4>
                            <?php if ($mensaje): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje); ?></div>
                            <?php endif; ?>
                            <?php if ($movimiento): ?>
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-body">
                                        <form action="editar_stock.php" method="POST">
                                            <input type="hidden" name="id_stock" value="<?php echo $movimiento['id_stock']; ?>">
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputInsumo" name="id_insumo" required>
                                                    <option value="">Selecciona un insumo</option>
                                                    <?php
                                                    try {
                                                        $stmt_insumos = $pdo->query("SELECT id_insumo, nombre FROM insumos WHERE activo = 1 ORDER BY nombre");
                                                        while ($row = $stmt_insumos->fetch()) {
                                                            $selected = ($row['id_insumo'] == $movimiento['id_insumo']) ? 'selected' : '';
                                                            echo "<option value='{$row['id_insumo']}' $selected>{$row['nombre']}</option>";
                                                        }
                                                    } catch (PDOException $e) {
                                                        echo "<option value=''>Error al cargar insumos</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <label for="inputInsumo">Insumo</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputCantidad" name="cantidad" type="number" step="0.01" value="<?php echo htmlspecialchars($movimiento['cantidad']); ?>" required />
                                                <label for="inputCantidad">Cantidad (positiva para entrada, negativa para salida)</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputTipo" name="tipo_movimiento" required>
                                                    <option value="entrada" <?php echo ($movimiento['tipo_movimiento'] == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                                                    <option value="salida" <?php echo ($movimiento['tipo_movimiento'] == 'salida') ? 'selected' : ''; ?>>Salida</option>
                                                </select>
                                                <label for="inputTipo">Tipo de Movimiento</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputOrigen" name="origen" type="text" value="<?php echo htmlspecialchars($movimiento['origen']); ?>" />
                                                <label for="inputOrigen">Origen (ej. venta, compra)</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="GestionStock.php">Volver a Gestión de Stock</a>
                                                <button class="btn btn-primary" type="submit">Actualizar Movimiento</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No se pudo cargar el movimiento. <a href="GestionStock.php">Volver</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; SmartCoffee AI</div>
                        <div>
                            <a href="#">Privacy Policy</a>
                            &middot;
                            <a href="#">Terms &amp; Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../js/scripts.js"></script>
</body>
</html>
