<?php
session_start();  // Iniciar sesión

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

// Incluir conexión a la DB (usa PDO, debe definir $pdo)
include_once('../controlador/conexion.php');

// Incluir las funciones de caja (DEBEN SER LAS CORREGIDAS CON PDO)
include_once('funciones_caja.php'); 

// Función para ejecutar consultas seguras con PDO
function ejecutarConsulta($pdo, $query, $params = []) {
    $stmt = $pdo->prepare($query);
    // CRÍTICO: La línea 26 del error ocurre aquí, si la consulta está mal.
    $stmt->execute($params); 
    return $stmt;
}

// Obtener la caja ABIERTA actual (CRÍTICO para movimientos y validación)
$caja_abierta_data = obtenerCajaAbierta($pdo);
$id_caja_abierta = $caja_abierta_data['id_caja'] ?? null;
$fecha_caja_abierta = $caja_abierta_data['fecha'] ?? null;

// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// Manejo de acciones POST (apertura, cierre, movimientos)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol === 'admin') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
        try {
            if ($accion === 'abrir_caja') {
                $fecha = $_POST['fecha'];
                $efectivo_inicial = $_POST['efectivo_inicial'];
                $id_usuario = $_SESSION['id_usuario'];

                // CRÍTICO: Bloquear apertura si ya hay una caja ABIERTA
                if ($id_caja_abierta) {
                    $mensaje = "ERROR: No se puede abrir una nueva caja. La caja del día **{$fecha_caja_abierta}** se encuentra aún abierta y debe ser cerrada primero.";
                } else {
                    // Verificar si ya hay una caja CERRADA para la fecha seleccionada
                    $stmt_check_closed = $pdo->prepare("SELECT id_caja FROM caja WHERE fecha = ? AND estado = 'cerrada'");
                    $stmt_check_closed->execute([$fecha]);
                    if ($stmt_check_closed->fetch()) {
                        $mensaje = "ERROR: Ya existe una caja cerrada para la fecha {$fecha}. No se permite la reapertura.";
                    } else {
                        // Insertar nueva caja
                        $stmt = $pdo->prepare("INSERT INTO caja (fecha, apertura, estado, id_usuario_apertura) VALUES (?, ?, 'abierta', ?)");
                        $stmt->execute([$fecha, $efectivo_inicial, $id_usuario]);
                        $mensaje = 'Caja abierta exitosamente. ¡Recargue para ver los datos de hoy!';
                        // Recargar para actualizar $caja_abierta_data
                        header("Location: caja.php?fecha=" . $fecha);
                        exit;
                    }
                }
            } elseif ($accion === 'cerrar_caja') {
                // El cierre solo actúa sobre la caja actual ABIERTA
                $monto_contado_fisico = floatval($_POST['monto_contado']); // Monto físico contado
                $fecha_cierre = $_POST['fecha_caja']; // Fecha de la caja a cerrar (e.g., 2025-12-15)
                $id_usuario = $_SESSION['id_usuario'];

                // 1. Obtener datos de la caja a cerrar
                $stmt_caja_info = $pdo->prepare("SELECT id_caja, apertura FROM caja WHERE fecha = ? AND estado = 'abierta' LIMIT 1");
                $stmt_caja_info->execute([$fecha_cierre]);
                $caja_info = $stmt_caja_info->fetch(PDO::FETCH_ASSOC);

                if (!$caja_info) {
                    $mensaje = 'ERROR: No hay ninguna caja abierta para la fecha ' . $fecha_cierre . '.';
                } else {
                    $id_caja = $caja_info['id_caja'];
                    $apertura = $caja_info['apertura'];

                    // 2. Calcular total esperado (apertura + ventas_efectivo + movimientos_netos)
                    $ventas = obtenerVentasPorFecha($pdo, $fecha_cierre); 
                    $totales_ventas = [];
                    foreach ($ventas as $v) {
                        $totales_ventas[$v['metodo_pago']] = $v['total'];
                    }
                    $ventas_efectivo = $totales_ventas['efectivo'] ?? 0;

                    // Movimientos Netos
                    $query_mov = $pdo->prepare("SELECT SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) AS movimientos_netos FROM movimientos_caja WHERE id_caja = ?");
                    $query_mov->execute([$id_caja]);
                    $movimientos_netos = $query_mov->fetchColumn() ?? 0;

                    $total_esperado = $apertura + $ventas_efectivo + $movimientos_netos;
                    $diferencia = $monto_contado_fisico - $total_esperado;
                    
                    // Preparamos los datos
                    $data_cierre = [
                        "id_caja" => $id_caja,
                        "monto_contado" => $monto_contado_fisico, 
                        "total_esperado" => $total_esperado, 
                        "id_usuario" => $id_usuario, // id_usuario_cierre en BD
                        "cierre" => $monto_contado_fisico, // Usamos monto_contado_fisico para la columna 'cierre'
                        "diferencia" => $diferencia
                    ];

                    // 3. Ejecutar función de cierre de caja
                    if (guardarCierreCaja($pdo, $data_cierre)) {
                        $mensaje = "Caja del día {$fecha_cierre} cerrada exitosamente. Diferencia: $" . number_format($diferencia, 2) . ".";
                        // Refrescar para limpiar el POST y actualizar $caja_abierta_data
                        header("Location: caja.php?fecha=" . date('Y-m-d'));
                        exit;
                    } else {
                         $mensaje = 'ERROR al guardar el cierre en la base de datos.';
                    }
                }

            } elseif ($accion === 'registrar_movimiento') {
                $tipo = $_POST['tipo'];
                $monto = $_POST['monto'];
                $descripcion = $_POST['descripcion'] ?? '';
                $origen = $_POST['origen'];
                $id_caja = $_POST['id_caja'];

                // Verificar que la caja esté abierta (usamos $id_caja_abierta)
                if (!$id_caja_abierta || $id_caja != $id_caja_abierta) {
                    $mensaje = 'La caja no está abierta o hay un conflicto de ID.';
                } else {
                    // Insertar movimiento
                    $stmt = $pdo->prepare("INSERT INTO movimientos_caja (id_caja, tipo, monto, descripcion, origen, id_usuario, hora) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$id_caja, $tipo, $monto, $descripcion, $origen, $_SESSION['id_usuario']]);
                    $mensaje = 'Movimiento registrado exitosamente.';
                    // Recargar para actualizar la vista de movimientos
                    header("Location: caja.php?fecha=" . $fecha_caja_abierta);
                    exit;
                }
            }
        } catch (PDOException $e) {
            $mensaje = 'Error PDO: ' . $e->getMessage();
        } catch (Exception $e) {
             $mensaje = 'Error General: ' . $e->getMessage();
        }
    }
}


// --- LÓGICA DE VISUALIZACIÓN ---

// Obtener fecha seleccionada (por defecto la caja abierta, o hoy)
$fecha_default = $fecha_caja_abierta ?? date('Y-m-d');
$fecha_seleccionada = $_GET['fecha'] ?? $fecha_default;

// Consultar totales de ventas por método de pago
$query_ventas = "
    SELECT metodo_pago, SUM(total) AS total_ventas
    FROM ventas
    WHERE DATE(fecha) = ?
    GROUP BY metodo_pago
    ORDER BY metodo_pago
";
$stmt_ventas = ejecutarConsulta($pdo, $query_ventas, [$fecha_seleccionada]);
$totales_ventas = [];
while ($row = $stmt_ventas->fetch(PDO::FETCH_ASSOC)) {
    $totales_ventas[$row['metodo_pago']] = $row['total_ventas'];
}

// Consultar datos de caja para la fecha (abierta o cerrada)
$query_caja_visual = "
    SELECT id_caja, apertura AS efectivo_inicial, estado, monto_contado, total_esperado, diferencia
    FROM caja
    WHERE fecha = ?
    LIMIT 1
";
$stmt_caja_visual = ejecutarConsulta($pdo, $query_caja_visual, [$fecha_seleccionada]);
$caja_data = $stmt_caja_visual->fetch(PDO::FETCH_ASSOC);


// Consultar movimientos de caja
$query_movimientos = "
    SELECT origen, SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) AS total_movimientos
    FROM movimientos_caja mc
    JOIN caja c ON mc.id_caja = c.id_caja
    WHERE c.fecha = ?
    GROUP BY origen
    ORDER BY origen
";
$stmt_movimientos = ejecutarConsulta($pdo, $query_movimientos, [$fecha_seleccionada]);
$totales_movimientos = [];
while ($row = $stmt_movimientos->fetch(PDO::FETCH_ASSOC)) {
    $totales_movimientos[$row['origen']] = $row['total_movimientos'];
}

// Calcular efectivo calculado y diferencia
$efectivo_inicial = $caja_data['efectivo_inicial'] ?? 0;
$ventas_efectivo = $totales_ventas['efectivo'] ?? 0;
$movimientos_netos = array_sum($totales_movimientos);


$efectivo_calculado = $efectivo_inicial + $ventas_efectivo + $movimientos_netos;


// Si la caja está cerrada, usamos los valores guardados
if ($caja_data && $caja_data['estado'] === 'cerrada') {
    $efectivo_final = $caja_data['monto_contado']; 
    $diferencia_cuadre = $caja_data['diferencia'];
    $efectivo_esperado_final = $caja_data['total_esperado'];
} else {
    // Si la caja está abierta o no existe, solo mostramos el cálculo potencial
    $efectivo_final = 0; // No se ha contado
    $diferencia_cuadre = 0;
    $efectivo_esperado_final = $efectivo_calculado; // Es el monto que debería haber
}

// Consultar movimientos reales para la tabla
$query_mov_real = "
    SELECT tipo, monto, descripcion, DATE_FORMAT(hora, '%H:%i') AS hora_format, origen, c.id_caja
    FROM movimientos_caja mc
    JOIN caja c ON mc.id_caja = c.id_caja  /* <-- CORRECCIÓN CRÍTICA DE mc->id_caja A mc.id_caja */
    WHERE c.fecha = ?
    ORDER BY hora DESC
";
$stmt_mov_real = ejecutarConsulta($pdo, $query_mov_real, [$fecha_seleccionada]);
$movimientos_registrados = $stmt_mov_real->fetchAll(PDO::FETCH_ASSOC);

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

<link rel="apple-touch-icon" href="../assets/img/logo.png">
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
                        <a class="nav-link active" href="../caja/caja.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>
                            Caja
                        </a>
                    </div>
                </div>
                <div class="sb-sidenav-footer">
                    <div class="small">Conectado como:</div>
                    <?php echo htmlspecialchars($nombre_usuario); ?> <br>
                    <?php echo ucfirst($rol); ?>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 py-4">
                    <h1 class="mt-4"><i class="fas fa-cash-register me-2"></i> Gestión de Caja</h1>

                    <?php if ($id_caja_abierta && $fecha_caja_abierta != date('Y-m-d')): ?>
                    <div class="alert alert-danger">
                        <strong>ALERTA:</strong> La caja del día **
                        <?php echo htmlspecialchars($fecha_caja_abierta); ?>** sigue **ABIERTA**. Debe cerrarla antes de
                        continuar.
                    </div>
                    <?php endif; ?>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-info">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($rol === 'admin'): ?>

                    <div class="container py-4">

                        <h2 class="mb-4 fw-bold">Operaciones y Cuadrado</h2>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="titulo-seccion mb-3">Seleccionar Fecha para Ver Cuadrado/Historial</h5>
                                <form method="GET" action="">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Fecha</label>
                                            <input type="date" name="fecha" class="form-control"
                                                value="<?php echo htmlspecialchars($fecha_seleccionada); ?>">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">Ver Datos</button>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <a href="historial_caja.php" class="btn btn-secondary w-100"><i
                                                    class="fas fa-history me-1"></i> Ver Historial de Cajas</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="titulo-seccion mb-3">Apertura de Caja</h5>
                                <?php if ($id_caja_abierta): ?>
                                <div class="alert alert-warning">
                                    La caja del día **
                                    <?php echo htmlspecialchars($fecha_caja_abierta); ?>** está abierta (ID:
                                    <?php echo $id_caja_abierta; ?>). Debe cerrarla primero.
                                </div>
                                <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="abrir_caja">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Fecha</label>
                                            <input type="date" name="fecha" class="form-control"
                                                value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Efectivo Inicial</label>
                                            <input type="number" name="efectivo_inicial" class="form-control"
                                                placeholder="0.00" step="0.01" required>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success w-100">Abrir Caja</button>
                                        </div>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="titulo-seccion mb-3">Cuadrado de Caja para
                                    <?php echo htmlspecialchars($fecha_seleccionada); ?>
                                    <?php if ($caja_data): ?>
                                    <span
                                        class="badge bg-<?php echo $caja_data['estado'] == 'abierta' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($caja_data['estado']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Sin Datos</span>
                                    <?php endif; ?>
                                </h5>

                                <?php if (!$caja_data): ?>
                                <p class="text-warning">No hay datos de caja (abierta o cerrada) para esta fecha.</p>
                                <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Totales de Ventas por Método de Pago</h6>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Método</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                            // Aseguramos que 'tarjeta' esté presente para el cálculo
                            $metodos_totales = ['efectivo', 'tarjeta']; 
                            foreach ($metodos_totales as $metodo): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo ucfirst($metodo); ?>
                                                    </td>
                                                    <td>$
                                                        <?php echo number_format($totales_ventas[$metodo] ?? 0, 2); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Movimientos de Caja (Ingresos/Egresos)</h6>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Origen</th>
                                                    <th>Monto Neto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (['deposito', 'retiro', 'manual'] as $origen): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo ucfirst($origen); ?>
                                                    </td>
                                                    <td>$
                                                        <?php echo number_format($totales_movimientos[$origen] ?? 0, 2); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <hr>

                                <?php if ($caja_data['estado'] === 'abierta'): ?>
                                <h6 class="mt-4">Proyección de Cierre (Caja Abierta)</h6>
                                <p><strong>Efectivo Inicial:</strong> $
                                    <?php echo number_format($efectivo_inicial, 2); ?>
                                </p>
                                <p class="h5 text-primary"><strong>Efectivo Esperado (Cálculo del Sistema):</strong> $
                                    <?php echo number_format($efectivo_esperado_final, 2); ?>
                                </p>

                                <hr>

                                <h6 class="mt-4">Cierre de Caja</h6>
                                <form id="formCierreCaja" method="POST" action="">
                                    <input type="hidden" name="accion" value="cerrar_caja">
                                    <input type="hidden" name="fecha_caja"
                                        value="<?php echo htmlspecialchars($fecha_seleccionada); ?>">

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Efectivo Contado Físico</label>
                                            <input type="number" name="monto_contado" class="form-control"
                                                placeholder="0.00" step="0.01" required>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-danger w-100">Cerrar Caja</button>
                                        </div>
                                    </div>
                                </form>

                                <?php else: ?>
                                <h6 class="mt-4">Resultados Finales (Caja Cerrada)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Efectivo Inicial:</strong> $
                                            <?php echo number_format($efectivo_inicial, 2); ?>
                                        </p>
                                        <p><strong>Efectivo Esperado (Sistema):</strong> $
                                            <?php echo number_format($efectivo_esperado_final, 2); ?>
                                        </p>
                                        <p><strong>Efectivo Contado (Final):</strong> $
                                            <?php echo number_format($efectivo_final, 2); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p
                                            class="h4 <?php echo $diferencia_cuadre == 0 ? 'text-success' : 'text-danger'; ?>">
                                            <strong>Diferencia Final:</strong> $
                                            <?php echo number_format($diferencia_cuadre, 2); ?>
                                            <?php if ($diferencia_cuadre != 0): ?>
                                            <br><small>¡La caja no cuadró! Revisar movimientos o ventas.</small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="titulo-seccion mb-3">Movimientos (Ingresos/Egresos)</h5>

                                <?php if ($id_caja_abierta): ?>
                                <button class="btn btn-primary mb-3" data-bs-toggle="modal"
                                    data-bs-target="#modalMovimiento">
                                    ➕ Registrar Movimiento
                                </button>
                                <?php else: ?>
                                <div class="alert alert-info">Para registrar movimientos, la caja debe estar abierta.
                                </div>
                                <?php endif; ?>

                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Monto</th>
                                            <th>Descripción</th>
                                            <th>Hora</th>
                                            <th>Origen</th>
                                            <th>ID Caja</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($movimientos_registrados)): ?>
                                        <?php foreach ($movimientos_registrados as $mov): ?>
                                        <tr>
                                            <td>
                                                <?php echo ucfirst($mov['tipo']); ?>
                                            </td>
                                            <td>$
                                                <?php echo number_format($mov['monto'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($mov['descripcion'] ?? 'N/A'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($mov['hora_format']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($mov['origen']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($mov['id_caja']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No hay movimientos
                                                registrados para esta fecha.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                            </div>
                        </div>

                    </div>
                    <?php else: ?>
                    <div class="row justify-content-center mb-5">
                        <div class="col-lg-6">
                            <h4 class="text-center mb-4">Vista de Caja (Solo Lectura)</h4>
                            <p class="text-center">Como vendedor, solo puedes consultar la caja. Contacta a un
                                administrador para modificaciones.</p>
                        </div>
                    </div>
                    <?php endif; ?>


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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="../js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="../assets/demo/chart-area-demo.js"></script>
    <script src="../assets/demo/chart-bar-demo.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"
        crossorigin="anonymous"></script>
    <script src="../js/datatables-simple-demo.js"></script>
    <script>
            // No se requiere JS adicional para el manejo de POST/Cierre/Apertura, todo se maneja en PHP
    </script>
</body>

</html>

<div class="modal fade" id="modalMovimiento" tabindex="-1" aria-labelledby="modalMovimientoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMovimientoLabel">Registrar Movimiento (Caja ID:
                    <?php echo $id_caja_abierta; ?>)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="registrar_movimiento">
                    <input type="hidden" name="id_caja" value="<?php echo $id_caja_abierta; ?>">
                    <div class="mb-3">
                        <label for="tipoMovimiento" class="form-label">Tipo</label>
                        <select class="form-select" name="tipo" id="tipoMovimiento" required>
                            <option value="">Seleccionar...</option>
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="montoMovimiento" class="form-label">Monto</label>
                        <input type="number" class="form-control" name="monto" id="montoMovimiento" placeholder="0.00"
                            step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcionMovimiento" class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcionMovimiento" rows="3"
                            placeholder="Detalles del movimiento"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="origenMovimiento" class="form-label">Origen</label>
                        <select class="form-select" name="origen" id="origenMovimiento" required>
                            <option value="">Seleccionar...</option>
                            <option value="retiro">Retiro</option>
                            <option value="deposito">Depósito</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>