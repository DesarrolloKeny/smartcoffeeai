<?php
session_start();  // Agregar esto al inicio para manejar sesiones
include_once('../controlador/conexion.php');  // Ajusta ruta
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
// Definir $rol desde la sesión (ajusta si el nombre de la clave es diferente, ej. $_SESSION['user_rol'])
$rol = $_SESSION['rol'] ?? 'vendedor';  // Por defecto 'vendedor' si no está definido

// Procesar eliminación si se pasa 'delete_id' via GET
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ?");
        if ($stmt->execute([$id])) {
            $delete_success = true;
        } else {
            $delete_error = "Error al eliminar.";
        }
    } catch (PDOException $e) {
        $delete_error = "Error: " . $e->getMessage();
    }
}

// Procesar creación/edición si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_POST['id_usuario'] ?? null;
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $clave = $_POST['clave'] ?? '';
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;  // Nuevo: checkbox para activo

    try {
        if (empty($nombre) || empty($correo) || empty($rol)) {
            throw new Exception("Nombre, correo y rol son obligatorios.");
        }

        if ($id_usuario) {
            // Editar (incluye activo)
            $sql = "UPDATE usuarios SET nombre = :nombre, apellido = :apellido, correo = :correo, rol = :rol, activo = :activo";
            $params = [':nombre' => $nombre, ':apellido' => $apellido, ':correo' => $correo, ':rol' => $rol, ':activo' => $activo];
            if (!empty($clave)) {
                $sql .= ", clave = :clave";
                $params[':clave'] = password_hash($clave, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id_usuario = :id_usuario";
            $params[':id_usuario'] = $id_usuario;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_message = "Usuario actualizado correctamente.";
        } else {
            // Crear (activo por defecto 1)
            if (empty($clave)) {
                throw new Exception("La clave es obligatoria para nuevos usuarios.");
            }
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, clave, rol, activo) VALUES (:nombre, :apellido, :correo, :clave, :rol, :activo)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':correo' => $correo,
                ':clave' => password_hash($clave, PASSWORD_DEFAULT),
                ':rol' => $rol,
                ':activo' => 1  // Nuevo usuario activo por defecto
            ]);
            $success_message = "Usuario creado correctamente.";
        }
    } catch (PDOException $e) {
        $error_message = "Error de DB: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Mostrar alerts
if (isset($delete_success) && $delete_success) {
    echo "<script>Swal.fire('Eliminado!', 'El usuario ha sido eliminado.', 'success');</script>";
} elseif (isset($delete_error)) {
    echo "<script>Swal.fire('Error', '$delete_error', 'error');</script>";
}
if (isset($success_message)) {
    echo "<script>Swal.fire('Éxito', '$success_message', 'success');</script>";
} elseif (isset($error_message)) {
    echo "<script>Swal.fire('Error', '$error_message', 'error');</script>";
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
                        <h2 class="mb-4">Gestión de Usuarios</h2>
                        
                        <!-- Formulario para Crear/Editar Usuario -->
                        <div class="row justify-content-center mb-5">
                            <div class="col-lg-8">
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-header">
                                        <h5 id="formTitle">Crear Nuevo Usuario</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="usuarioForm" method="POST">
                                            <input type="hidden" id="id_usuario" name="id_usuario">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label>Nombre</label>
                                                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label>Apellido</label>
                                                    <input type="text" id="apellido" name="apellido" class="form-control">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label>Correo</label>
                                                    <input type="email" id="correo" name="correo" class="form-control" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label>Rol</label>
                                                    <select id="rol" name="rol" class="form-control" required>
                                                        <option value="vendedor">Vendedor</option>
                                                        <option value="admin">Admin</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label>Activo</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                                        <label class="form-check-label" for="activo">Usuario activo</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3" id="claveField">
                                                <label>Clave</label>
                                                <input type="password" id="clave" name="clave" class="form-control" required>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <button type="submit" class="btn btn-primary" id="submitBtn">Crear Usuario</button>
                                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display:none;" onclick="cancelEdit()">Cancelar Edición</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Usuarios Registrados -->
                        <div class="row justify-content-center">
                            <div class="col-lg-12">
                                <h4 class="text-center mb-4">Usuarios Registrados</h4>
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-body">
                                        <table id="usuariosTable" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Apellido</th>
                                                    <th>Correo</th>
                                                    <th>Rol</th>
                                                    <th>Activo</th>
                                                    <th>Fecha Creación</th>
                                                    <th>Última Sesión</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                try {
                                                    $stmt = $pdo->query("SELECT id_usuario, nombre, apellido, correo, rol, activo, fecha_creacion, ultima_sesion FROM usuarios ORDER BY id_usuario DESC");
                                                    $count = $stmt->rowCount();
                                                    if ($count > 0) {
                                                        while ($row = $stmt->fetch()) {
                                                            $activo_text = $row['activo'] ? 'Sí' : 'No';
                                                            $row_json = json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT);
                                                            echo "<tr>
                                                                <td>{$row['id_usuario']}</td>
                                                                <td>" . htmlspecialchars($row['nombre']) . "</td>
                                                                <td>" . htmlspecialchars($row['apellido']) . "</td>
                                                                <td>" . htmlspecialchars($row['correo']) . "</td>
                                                                <td>" . htmlspecialchars($row['rol']) . "</td>
                                                                <td>{$activo_text}</td>
                                                                <td>{$row['fecha_creacion']}</td>
                                                                <td>" . ($row['ultima_sesion'] ?? 'Nunca') . "</td>
                                                                <td>
                                                                    <button class='btn btn-warning btn-sm me-2' onclick='editUsuario({$row_json})'>Editar</button>
                                                                    <button class='btn btn-danger btn-sm' onclick='deleteUsuario({$row['id_usuario']})'>Eliminar</button>
                                                                </td>
                                                            </tr>";
                                                        }
                                                    } else {
                                                        echo "<tr><td colspan='9'>No hay usuarios registrados.</td></tr>";
                                                    }
                                                } catch (PDOException $e) {
                                                    echo "<tr><td colspan='9'>Error al cargar usuarios: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
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
        <script>
    // Inicializar DataTable
    document.addEventListener('DOMContentLoaded', () => {
        // Asegúrate de que simpleDatatables esté cargado correctamente
        if (typeof simpleDatatables !== 'undefined') {
             new simpleDatatables.DataTable("#usuariosTable");
        }
    });

    // Función para llenar el formulario y pasar a modo Edición
    function editUsuario(row) {
        document.getElementById('id_usuario').value = row.id_usuario;
        document.getElementById('nombre').value = row.nombre;
        document.getElementById('apellido').value = row.apellido;
        document.getElementById('correo').value = row.correo;
        document.getElementById('rol').value = row.rol;
        document.getElementById('activo').checked = row.activo == 1; // 1 = checked, 0 = unchecked
        
        // Ocultar campo de clave (para no obligar a cambiarla en la edición)
        document.getElementById('claveField').style.display = 'none';
        document.getElementById('clave').required = false;
        
        document.getElementById('formTitle').textContent = 'Editar Usuario';
        document.getElementById('submitBtn').textContent = 'Actualizar Usuario';
        document.getElementById('cancelBtn').style.display = 'inline-block';


        
    }

    // Función para restaurar el formulario a modo Creación
    function cancelEdit() {
        document.getElementById('usuarioForm').reset();
        document.getElementById('id_usuario').value = '';
        document.getElementById('activo').checked = true; // Por defecto activo
        document.getElementById('claveField').style.display = 'block'; // Mostrar la clave
        document.getElementById('clave').required = true; // Clave requerida para crear
        document.getElementById('formTitle').textContent = 'Crear Nuevo Usuario';
        document.getElementById('submitBtn').textContent = 'Crear Usuario';
        document.getElementById('cancelBtn').style.display = 'none';
    }

    // Función para manejar la eliminación (desactivación) de un usuario con SweetAlert2
    function deleteUsuario(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "El usuario será marcado como inactivo (activo = 0). Podrás reactivarlo editándolo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, deshabilitar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redireccionar al script PHP para que procese la eliminación (update activo=0)
                // Asumiendo que este archivo se llama GestionUsuario.php
                window.location.href = 'GestionUsuario.php?delete_id=' + id; 
            }
        });
    }
</script>
</body>
</html>