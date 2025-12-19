<?php
// Iniciar sesión y definir $rol para evitar errores de variable indefinida
session_start();
$rol = $_SESSION['rol'] ?? 'user'; // Define $rol desde la sesión con fallback a 'user'
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
// Incluir conexión a la DB al inicio (asumiendo PDO, como en productos)
include_once('../controlador/conexion.php');

// Procesar eliminación de movimientos de stock si se pasa 'delete_id' via GET
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM stock_insumos WHERE id_stock = ?");
        if ($stmt->execute([$id])) {
            $delete_success_stock = true;
        } else {
            $delete_error_stock = "Error al eliminar movimiento de stock.";
        }
    } catch (PDOException $e) {
        $delete_error_stock = "Error: " . $e->getMessage();
    }
}

// Procesar eliminación de insumos si se pasa 'delete_id_insumo' via GET
if (isset($_GET['delete_id_insumo'])) {
    $id = $_GET['delete_id_insumo'];
    try {
        // Mejor práctica: Usar una transacción si hay tablas relacionadas
        $pdo->beginTransaction();
        
        // 1. Opcional: Eliminar movimientos de stock relacionados (si aplica)
        //$stmt_stock = $pdo->prepare("DELETE FROM stock_insumos WHERE id_insumo = ?");
        //$stmt_stock->execute([$id]);

        // 2. Eliminar el insumo
        $stmt = $pdo->prepare("DELETE FROM insumos WHERE id_insumo = ?");
        
        if ($stmt->execute([$id])) {
            $pdo->commit();
            $delete_success_insumo = true;
        } else {
            $pdo->rollBack();
            $delete_error_insumo = "Error al eliminar insumo.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $delete_error_insumo = "Error: " . $e->getMessage();
    }
}

// Mostrar alerts de eliminación (usando SweetAlert2)
// Nota: Estos scripts deben estar después de cargar SweetAlert2
if (isset($delete_success_stock) && $delete_success_stock) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Eliminado!', 'El movimiento de stock ha sido eliminado.', 'success');
            });
          </script>";
} elseif (isset($delete_error_stock)) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Error', '$delete_error_stock', 'error');
            });
          </script>";
}

if (isset($delete_success_insumo) && $delete_success_insumo) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Eliminado!', 'El insumo ha sido eliminado.', 'success');
            });
          </script>";
} elseif (isset($delete_error_insumo)) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Error', '$delete_error_insumo', 'error');
            });
          </script>";
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> </head>
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
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-6">
                                <h4 class="text-center mb-4" id="formTitle">Registro de Insumo</h4>
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-body">
                                        <form id="insumoForm" action="../controlador/registrar_insumo.php" method="POST">
                                            <input type="hidden" name="id_insumo" id="id_insumo" value=""> <div class="form-floating mb-3">
                                                <input class="form-control" id="inputNombre" name="nombre" type="text" placeholder="Nombre del insumo" required />
                                                <label for="inputNombre">Nombre del insumo</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <select class="form-control" id="inputUnidad" name="unidad" required>
                                                    <option value="">Selecciona unidad</option>
                                                    <option value="kg">kg</option>
                                                    <option value="litros">litros</option>
                                                    <option value="unidades">unidades</option>
                                                    <option value="gramos">gramos</option>
                                                    <option value="mililitros">mililitros</option>
                                                    <option value="cajas">cajas</option>
                                                    <option value="paquetes">paquetes</option>
                                                </select>
                                                <label for="inputUnidad">Unidad (kg, litros, unidades, etc.)</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputStock" name="stock" type="number" step="0.01" placeholder="Stock inicial" required />
                                                <label for="inputStock">Stock</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputAlertaStock" name="alerta_stock" type="number" step="0.01" placeholder="Alerta de stock" />
                                                <label for="inputAlertaStock">Alerta de stock (Nivel mínimo)</label>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" id="inputActivo" name="activo" type="checkbox" value="1" checked />
                                                <label class="form-check-label" for="inputActivo">Insumo activo</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="#insumos-section">Ver insumos</a>
                                                <br>
                                                <a class="small" href="GestionStock.php">Ver movimientos de stock</a>
                                                <button class="btn btn-primary" id="submitBtn" type="submit">Registrar Insumo</button>
                                                <button class="btn btn-secondary ms-2" id="cancelBtn" type="button" style="display:none;" onclick="resetForm()">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="insumos-section" class="row justify-content-center mb-5">
                            <div class="col-lg-12">
                                <h4 class="text-center mb-4">Insumos Registrados</h4>
                                <div class="input-group mb-4">
                                    <input type="text" id="searchInsumosInput" class="form-control" placeholder="Buscar por nombre o unidad...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="filterInsumosCards()">Buscar</button>
                                </div>
                                <div class="row" id="insumosContainer">
                                    <?php
                                    try {
                                        // Consulta para obtener insumos
                                        $stmt = $pdo->query("SELECT id_insumo, nombre, unidad, stock, alerta_stock, activo, fecha_actualizacion 
                                                             FROM insumos ORDER BY id_insumo DESC");
                                        
                                        if ($stmt->rowCount() > 0) {
                                            while ($row = $stmt->fetch()) {
                                                // Convertir valores booleanos a texto
                                                $activo_text = $row['activo'] ? 'Sí' : 'No';
                                                
                                                // Generar card
                                                echo "<div class='col-md-4 mb-4 insumo-card' data-nombre='{$row['nombre']}' data-unidad='{$row['unidad']}'>
                                                        <div class='card h-100 shadow-sm'>
                                                            <div class='card-body'>
                                                                <h5 class='card-title'>{$row['nombre']}</h5>
                                                                <p class='card-text'><strong>Unidad:</strong> {$row['unidad']}</p>
                                                                <p class='card-text'><strong>Stock:</strong> {$row['stock']}</p>
                                                                <p class='card-text'><strong>Alerta de stock:</strong> {$row['alerta_stock']}</p>
                                                                <p class='card-text'><strong>Activo:</strong> {$activo_text}</p>
                                                                <p class='card-text'><small class='text-muted'>Actualizado: {$row['fecha_actualizacion']}</small></p>
                                                            </div>
                                                            <div class='card-footer'>
                                                                <button class='btn btn-warning btn-sm me-2' onclick='updateInsumo({$row['id_insumo']})'>Actualizar</button>
                                                                <button class='btn btn-danger btn-sm' onclick='deleteInsumo({$row['id_insumo']})'>Eliminar</button>
                                                            </div>
                                                        </div>
                                                      </div>";
                                            }
                                        } else {
                                            echo "<div class='col-12 text-center'>No hay insumos registrados.</div>";
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

    function filterInsumosCards() {
        const input = document.getElementById('searchInsumosInput').value.toLowerCase();
        const cards = document.querySelectorAll('.insumo-card');

        cards.forEach(card => {
            const nombre = card.getAttribute('data-nombre').toLowerCase();
            const unidad = card.getAttribute('data-unidad').toLowerCase();
            const match = nombre.includes(input) || unidad.includes(input);
            card.style.display = match ? '' : 'none';
        });
    }

    /**
     * Limpia el formulario y lo restablece al modo de Registro.
     */
    function resetForm() {
        document.getElementById('insumoForm').reset();
        document.getElementById('id_insumo').value = '';
        document.getElementById('insumoForm').action = '../controlador/registrar_insumo.php'; 
        document.getElementById('formTitle').innerText = 'Registro de Insumo';
        document.getElementById('submitBtn').innerText = 'Registrar Insumo';
        document.getElementById('cancelBtn').style.display = 'none';
        // Asegura que el checkbox esté marcado por defecto al registrar
        document.getElementById('inputActivo').checked = true; 
    }

    /**
     * Carga los datos de un insumo específico en el formulario para su edición.
     * La llamada AJAX apunta a registroStock.php (corregido).
     * @param {number} id - El ID del insumo a actualizar.
     */
    function updateInsumo(id) {
        
        // La ruta fue corregida a registroStock.php según tu indicación
        fetch('../controlador/registroStock.php?id=' + id) 
            .then(response => {
                // Si la respuesta no es OK (ej: 404, 500)
                if (!response.ok) { 
                    throw new Error('Error de red, o el servidor no pudo procesar la solicitud para el ID: ' + id);
                }
                // Si la respuesta no es JSON válido, aquí ocurrirá el error "Unexpected end of JSON input"
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    Swal.fire('Error', data.error, 'error');
                    return;
                }
                
                // 1. Llenar el formulario con los datos
                document.getElementById('id_insumo').value = data.id_insumo;
                document.getElementById('inputNombre').value = data.nombre;
                document.getElementById('inputUnidad').value = data.unidad;
                document.getElementById('inputStock').value = data.stock;
                document.getElementById('inputAlertaStock').value = data.alerta_stock;
                // El campo activo viene como 1 o 0
                document.getElementById('inputActivo').checked = (data.activo == 1); 
                
                // 2. Cambiar la acción del formulario y el título
                document.getElementById('insumoForm').action = '../controlador/actualizar_insumo.php'; 
                document.getElementById('formTitle').innerText = 'Actualización de Insumo (ID: ' + id + ')';
                document.getElementById('submitBtn').innerText = 'Guardar Cambios';
                document.getElementById('cancelBtn').style.display = 'inline-block';
                
                // 3. Desplazar hacia el formulario
                document.getElementById('formTitle').scrollIntoView({ behavior: 'smooth' });

            })
            .catch(error => {
                // Mensaje de error mejorado para incluir la referencia al problema de JSON
                Swal.fire('Error de Carga', 'No se pudieron cargar los datos del insumo: ' + error.message + '. Revise que registroStock.php devuelva JSON limpio.', 'error');
            });
    }

    /**
     * Confirma y ejecuta la eliminación de un insumo.
     * @param {number} id - El ID del insumo a eliminar.
     */
    function deleteInsumo(id) {
        Swal.fire({
            title: '¿Eliminar Insumo?',
            text: 'Se eliminará el insumo. Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirecciona al script PHP con el ID para la eliminación
                window.location.href = 'insumos.php?delete_id_insumo=' + id;
            }
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
                window.location.href = 'insumos.php?id=' + id; 
            }
        });
    }

    function deleteStock(id) {
        Swal.fire({
            title: '¿Eliminar Movimiento?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true, // <-- Corrección de sintaxis: se agregó la coma
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