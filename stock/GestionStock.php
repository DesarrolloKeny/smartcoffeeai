<?php
session_start();  // Iniciar sesión
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.php');  // Redirigir al login si no está logueado
    exit;
}

// Obtener rol del usuario y validar
$rol = $_SESSION['rol'] ?? 'vendedor';
if (!in_array($rol, ['vendedor', 'admin'])) {
    header('Location: ../login.php?error=4');  // Rol inválido
    exit;
}

// Incluir conexión a la DB
include_once('../controlador/conexion.php');

// Procesar eliminación si se pasa 'delete_id' via GET (solo admin)
if (isset($_GET['delete_id']) && $rol === 'admin') {
    $id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM stock_insumos WHERE id_stock = ?");
        if ($stmt->execute([$id])) {
            $delete_success = true;
        } else {
            $delete_error = "Error al eliminar.";
        }
    } catch (PDOException $e) {
        $delete_error = "Error: " . $e->getMessage();
    }
} elseif (isset($_GET['delete_id']) && $rol !== 'admin') {
    $delete_error = "No tienes permisos para eliminar.";
}

// Mostrar alert de eliminación (usando SweetAlert2)
if (isset($delete_success) && $delete_success) {
    echo "<script>Swal.fire('Eliminado!', 'El movimiento de stock ha sido eliminado.', 'success');</script>";
} elseif (isset($delete_error)) {
    echo "<script>Swal.fire('Error', '$delete_error', 'error');</script>";
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
                        <!-- Sección de Registro de Movimiento de Stock (solo admin) -->
                        <?php if ($rol === 'admin'): ?>
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-6">
                                <h4 class="text-center mb-4">Registro de Movimiento de Stock</h4>
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-body">
                                        <form action="../controlador/registroStock.php" method="POST">
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputInsumo" name="id_insumo" required>
                                                    <option value="">Selecciona un insumo</option>
                                                    <?php
                                                    try {
                                                        $stmt = $pdo->query("SELECT id_insumo, nombre FROM insumos WHERE activo = 1 ORDER BY nombre");
                                                        while ($row = $stmt->fetch()) {
                                                            echo "<option value='{$row['id_insumo']}'>{$row['nombre']}</option>";
                                                        }
                                                    } catch (PDOException $e) {
                                                        echo "<option value=''>Error al cargar insumos</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <label for="inputInsumo">Insumo</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputCantidad" name="cantidad" type="number" step="0.01" placeholder="Cantidad" required />
                                                <label for="inputCantidad">Cantidad (positiva para entrada, negativa para salida)</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputTipo" name="tipo_movimiento" required>
                                                    <option value="">Selecciona tipo</option>
                                                    <option value="entrada">Entrada</option>
                                                    <option value="salida">Salida</option>
                                                </select>
                                                <label for="inputTipo">Tipo de Movimiento</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputOrigen" name="origen" type="text" placeholder="Origen" />
                                                <label for="inputOrigen">Origen (ej. venta, compra)</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="#stock-section">Ver movimientos de stock</a>
                                                <br>
                                                <a class="small" href="insumos.php">Registro de Insumos</a>
                                                
                                                <button class="btn btn-primary" type="submit">Registrar Movimiento</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-6">
                                <h4 class="text-center mb-4">Vista de Stock (Solo Lectura)</h4>
                                <p class="text-center">Como vendedor, solo puedes consultar el stock. Contacta a un administrador para modificaciones.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Sección de Lista de Movimientos de Stock en Cards -->
                        <div id="stock-section" class="row justify-content-center">
                            <div class="col-lg-12">
                                <h4 class="text-center mb-4">Movimientos de Stock Registrados</h4>
                                <div class="input-group mb-4">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar por insumo o origen...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="filterCards()">Buscar</button>
                                </div>
                                <div class="row" id="stockContainer">
                                    <?php
                                    try {
                                        // Consulta para obtener movimientos de stock con nombre de insumo
                                        $stmt = $pdo->query("SELECT s.id_stock, i.nombre AS insumo, s.cantidad, s.tipo_movimiento AS tipo, s.origen, s.fecha 
                                                             FROM stock_insumos s 
                                                             JOIN insumos i ON s.id_insumo = i.id_insumo 
                                                             ORDER BY s.fecha DESC");
                                        
                                        if ($stmt->rowCount() > 0) {
                                            while ($row = $stmt->fetch()) {
                                                echo "<div class='col-md-4 mb-4 stock-card' data-insumo='{$row['insumo']}' data-origen='{$row['origen']}'>
                                                        <div class='card h-100 shadow-sm'>
                                                            <div class='card-body'>
                                                                <h5 class='card-title'>Movimiento de {$row['insumo']}</h5>
                                                                <p class='card-text'><strong>Cantidad:</strong> {$row['cantidad']}</p>
                                                                <p class='card-text'><strong>Tipo:</strong> {$row['tipo']}</p>
                                                                <p class='card-text'><strong>Origen:</strong> {$row['origen']}</p>
                                                                <p class='card-text'><small class='text-muted'>Fecha: {$row['fecha']}</small></p>
                                                            </div>
                                                            <div class='card-footer'>";
                                                if ($rol === 'admin') {
                                                    echo "<button class='btn btn-warning btn-sm me-2' onclick='updateStock({$row['id_stock']})'>Actualizar</button>
                                                          <button class='btn btn-danger btn-sm' onclick='deleteStock({$row['id_stock']})'>Eliminar</button>";
                                                } else {
                                                    echo "<span class='text-muted'>Solo lectura</span>";
                                                }
                                                echo "</div>
                                                        </div>
                                                      </div>";
                                            }
                                        } else {
                                            echo "<div class='col-12 text-center'>No hay movimientos de stock registrados.</div>";
                                        }
                                        
                                    } catch (PDOException $e) {
                                        echo "<div class='col-12 text-center text-danger'>Error al cargar datos: " . $e->getMessage() . "</div>";
                                    }
                                    ?>
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="../assets/demo/chart-area-demo.js"></script>
        <script src="../assets/demo/chart-bar-demo.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="../js/datatables-simple-demo.js"></script>
        <script>
            function filterCards() {
                const input = document.getElementById('searchInput').value.toLowerCase();
                const cards = document.querySelectorAll('.stock-card');

                cards.forEach(card => {
                    const insumo = card.getAttribute('data-insumo').toLowerCase();
                    const origen = card.getAttribute('data-origen').toLowerCase();
                    const match = insumo.includes(input) || origen.includes(input);
                    card.style.display = match ? '' : 'none';
                });
            }

            function updateStock(id) {
                Swal.fire({
                    title: '¿Actualizar Movimiento?',
                    text: '¿Estás seguro de que quieres actualizar este movimiento?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, actualizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'editar_stock.php?id=' + id;
                    }
                });
            }

            function deleteStock(id) {
                Swal.fire({
                    title: '¿Eliminar Movimiento?',
                    text: 'Esta acción no se puede deshacer. ¿Estás seguro?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'GestionStock.php?delete_id=' + id;
                    }
                });
            }
        </script>
    </body>
</html>
