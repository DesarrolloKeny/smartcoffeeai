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
        $stmt = $pdo->prepare("DELETE FROM recetas WHERE id_receta = ?");
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
    echo "<script>Swal.fire('Eliminado!', 'La receta ha sido eliminada.', 'success');</script>";
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
                        <!-- Sección de Registro de Receta (solo admin) -->
                        <?php if ($rol === 'admin'): ?>
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-6">
                                <h4 class="text-center mb-4">Registro de Receta</h4>
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-body">
                                        <form action="../controlador/registroReceta.php" method="POST">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputNombre" name="nombre" type="text" placeholder="Nombre de la receta" required />
                                                <label for="inputNombre">Nombre de la receta</label>
                                            </div>
                                            
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputProducto" name="id_producto" required>
                                                    <option value="">Selecciona un producto</option>
                                                    <?php
                                                    // Consulta para obtener productos activos
                                                    $stmt = $pdo->query("SELECT id_producto, nombre FROM productos WHERE activo = 1 ORDER BY nombre");
                                                    while ($producto = $stmt->fetch()) {
                                                        echo "<option value='{$producto['id_producto']}'>{$producto['nombre']}</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <label for="inputProducto">Producto asociado</label>
                                            </div>

                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputBebestible" name="bebestible" type="checkbox" value="1" />
                                                <label class="form-check-label" for="inputBebestible">Es bebestible</label>
                                            </div>
                                            <div class="form-floating mb-3" id="capacidadField" style="display: none;">
                                                <input class="form-control" id="inputCapacidad" name="capacidad_ml" type="number" placeholder="Capacidad en ml" />
                                                <label for="inputCapacidad">Capacidad en ml</label>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputActivo" name="activo" type="checkbox" value="1" checked />
                                                <label class="form-check-label" for="inputActivo">Receta activa</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="#recetas-section">Ver recetas</a>
                                                <input class="btn btn-primary" type="submit" value="Registrar Receta" name="gt">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-6">
                                <h4 class="text-center mb-4">Vista de Recetas (Solo Lectura)</h4>
                                <p class="text-center">Como vendedor, solo puedes consultar las recetas. Contacta a un administrador para modificaciones.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Sección de Lista de Recetas en Cards -->
                        <div id="recetas-section" class="row justify-content-center">
                            <div class="col-lg-12">
                                <h4 class="text-center mb-4">Recetas Registradas</h4>
                                <div class="input-group mb-4">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o producto...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="filterCards()">Buscar</button>
                                </div>
                                <div class="row" id="recetasContainer">
                                    <?php
                                    try {
                                        // Consulta para obtener recetas con nombre de producto
                                        $stmt = $pdo->query("SELECT r.id_receta, r.nombre, p.nombre AS producto, r.bebestible, r.capacidad_ml, r.activo, r.fecha_creacion 
                                                             FROM recetas r 
                                                             JOIN productos p ON r.id_producto = p.id_producto 
                                                             ORDER BY r.id_receta DESC");
                                        
                                        if ($stmt->rowCount() > 0) {
                                            while ($row = $stmt->fetch()) {
                                                $bebestible_text = $row['bebestible'] ? 'Sí' : 'No';
                                                $capacidad = $row['bebestible'] ? $row['capacidad_ml'] . ' ml' : 'N/A';
                                                $activo_text = $row['activo'] ? 'Sí' : 'No';
                                                echo "<div class='col-md-4 mb-4 receta-card' data-nombre='{$row['nombre']}' data-producto='{$row['producto']}'>
                                                        <div class='card h-100 shadow-sm'>
                                                            <div class='card-body'>
                                                                <h5 class='card-title'>{$row['nombre']}</h5>
                                                                <p class='card-text'><strong>Producto:</strong> {$row['producto']}</p>
                                                                <p class='card-text'><strong>Bebestible:</strong> {$bebestible_text}</p>
                                                                <p class='card-text'><strong>Capacidad:</strong> {$capacidad}</p>
                                                                <p class='card-text'><strong>Activo:</strong> {$activo_text}</p>
                                                                <p class='card-text'><small class='text-muted'>Creado: {$row['fecha_creacion']}</small></p>
                                                            </div>
                                                            <div class='card-footer'>";
                                                if ($rol === 'admin') {
                                                    echo "<button class='btn btn-warning btn-sm me-2' onclick='updateRecipe({$row['id_receta']})'>Actualizar</button>
                                                          <button class='btn btn-danger btn-sm' onclick='deleteRecipe({$row['id_receta']})'>Eliminar</button>";
                                                } else {
                                                    echo "<span class='text-muted'>Solo lectura</span>";
                                                }
                                                echo "</div>
                                                        </div>
                                                      </div>";
                                            }
                                        } else {
                                            echo "<div class='col-12 text-center'>No hay recetas registradas.</div>";
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
            // Mostrar/ocultar campo de capacidad si es bebestible
            document.getElementById('inputBebestible').addEventListener('change', function() {
                document.getElementById('capacidadField').style.display = this.checked ? 'block' : 'none';
            });

            function filterCards() {
                const input = document.getElementById('searchInput').value.toLowerCase();
                const cards = document.querySelectorAll('.receta-card');

                cards.forEach(card => {
                    const nombre = card.getAttribute('data-nombre').toLowerCase();
                    const producto = card.getAttribute('data-producto').toLowerCase();
                    const match = nombre.includes(input) || producto.includes(input);
                    card.style.display = match ? '' : 'none';
                });
            }

            function updateRecipe(id) {
                Swal.fire({
                    title: '¿Actualizar Receta?',
                    text: '¿Estás seguro de que quieres actualizar esta receta?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, actualizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../recetas/editar_receta.php?id=' + id;
                    }
                });
            }

            function deleteRecipe(id) {
                Swal.fire({
                    title: '¿Eliminar Receta?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../recetas/GestionRecetas.php?delete_id=' + id;
                    }
                });
            }
        </script>
    </body>
</html>
