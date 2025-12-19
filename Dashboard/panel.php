<?php
session_start(); 
include_once('../controlador/conexion.php'); 

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// --- CONFIGURACIÓN DE PERÍODO ---
$periodoTendencia = 30; 
if (isset($_GET['periodo_tendencia']) && in_array((int)$_GET['periodo_tendencia'], [7, 15, 30])) {
    $periodoTendencia = (int)$_GET['periodo_tendencia'];
}

// --- VALORES POR DEFECTO PARA EVITAR ADVERTENCIAS ---
$prediccionHoy = 0.00; $prediccionSemanal = 0.00; $prediccionMensual = 0.00;
$prediccionUnidades = [];
$clusterDominante = ['label' => 'Sin Datos', 'count' => 0, 'ventas' => 0.00];
$porcentajeClientes = 0.0; $porcentajeVentas = 0.0;
$productosTendencia = []; $productosTendenciaTemp = [];
$ventasDia = 0.00; $productosDisponibles = 0; $montoCaja = 0.00; $stockCritico = 0;
$ultimas = [];
$python_error = null;

// --- 1. CONSULTAS DIRECTAS A LA BASE DE DATOS (DATOS REALES) ---
try {
    // Ventas del Día
    $queryVentas = $pdo->query("SELECT SUM(total) as total FROM ventas WHERE DATE(fecha) = CURDATE()");
    $ventasDia = $queryVentas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Productos Disponibles
    $queryProd = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND stock > 0");
    $productosDisponibles = $queryProd->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // CONSULTA DE CAJA CORREGIDA (Cambié monto_apertura por una búsqueda más genérica)
    // NOTA: Si tu columna se llama distinto, cámbiala aquí abajo
    $queryCaja = $pdo->query("SELECT * FROM caja WHERE estado = 'abierta' ORDER BY id_caja DESC LIMIT 1");
    $cajaInfo = $queryCaja->fetch(PDO::FETCH_ASSOC);
    
    if ($cajaInfo) {
        // Intentamos detectar el nombre de la columna de monto (monto, saldo, monto_apertura)
        $columnaMonto = isset($cajaInfo['monto_apertura']) ? 'monto_apertura' : (isset($cajaInfo['monto']) ? 'monto' : null);
        $apertura = $columnaMonto ? $cajaInfo[$columnaMonto] : 0;
        $fecha_ap = $cajaInfo['fecha_apertura'] ?? $cajaInfo['fecha'];
        
        $stmtEfec = $pdo->prepare("SELECT SUM(total) as efec FROM ventas WHERE metodo_pago = 'efectivo' AND fecha >= ?");
        $stmtEfec->execute([$fecha_ap]);
        $ventasEfectivo = $stmtEfec->fetch(PDO::FETCH_ASSOC)['efec'] ?? 0;
        $montoCaja = $apertura + $ventasEfectivo;
    }

    // Insumos Críticos
    $queryStock = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND stock <= 5");
    $stockCritico = $queryStock->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (PDOException $e) {
    $python_error = "Error en base de datos: " . $e->getMessage();
}

// --- 2. LÓGICA DE PYTHON (SOLO PARA PREDICCIONES IA) ---
try {
    $python_script = __DIR__ . '/ml_api_service.py'; 
    $python_command = "py " . escapeshellarg($python_script) . " " . escapeshellarg($periodoTendencia) . " 2>&1";
    $output = shell_exec($python_command);
    $metrics = json_decode($output, true);

    if (json_last_error() === JSON_ERROR_NONE && $metrics) {
        $prediccionHoy = $metrics['prediccionHoy'] ?? 0;
        $prediccionSemanal = $metrics['prediccionSemanal'] ?? 0;
        $prediccionMensual = $metrics['prediccionMensual'] ?? 0;
        $prediccionUnidades = $metrics['prediccionUnidades'] ?? [];
        $clusterDominante = $metrics['clusterDominante'] ?? $clusterDominante;
        $porcentajeClientes = $metrics['porcentajeClientes'] ?? 0;
        $porcentajeVentas = $metrics['porcentajeVentas'] ?? 0;
        $productosTendencia = $metrics['productosTendencia'] ?? [];
        $productosTendenciaTemp = $metrics['productosTendenciaTemp'] ?? [];
        $ultimas = $metrics['ultimasVentas'] ?? [];
    }
} catch (\Exception $e) {
    $python_error = "Error IA: " . $e->getMessage();
}

// --- AÑADIR A LAS CONSULTAS DIRECTAS EN PHP ---

// Obtener las últimas 5 ventas con el nombre del usuario (vendedor)
$queryUltimas = $pdo->query("
    SELECT v.id_venta, TIME(v.fecha) as hora, v.total, u.nombre as vendedor 
    FROM ventas v 
    INNER JOIN usuarios u ON v.id_usuario = u.id_usuario 
    ORDER BY v.fecha DESC 
    LIMIT 5
");
$ultimas = $queryUltimas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>SmartCoffee AI</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="../css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-coffee: #6F4E37;
            --secondary-orange: #E67E22;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        #layoutSidenav_nav .sb-sidenav {
            background: linear-gradient(180deg, #212529 0%, #2c3e50 100%) !important;
        }

        .sb-sidenav-menu .nav-link.active {
            background: linear-gradient(45deg, var(--primary-coffee), var(--secondary-orange)) !important;
            color: #fff !important;
        }

        .status-dot {
            height: 10px;
            width: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-left: auto;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .dot-online {
            background-color: #2ecc71;
            box-shadow: 0 0 8px #2ecc71;
        }

        .dot-offline {
            background-color: #e74c3c;
            box-shadow: 0 0 8px #e74c3c;
        }

        .bg-gradient-primary {
            background: linear-gradient(45deg, #4e73df 0%, #224abe 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(45deg, #1cc88a 0%, #13855c 100%);
        }

        .bg-gradient-danger {
            background: linear-gradient(45deg, #e74c3c 0%, #c0392b 100%);
        }

        .bg-gradient-warning {
            background: linear-gradient(45deg, #f6c23e 0%, #dda20a 100%);
        }

        .card-reporte {
            border: none;
            transition: transform 0.2s;
            border-radius: 12px;
        }

        .icon-circle {
            height: 3rem;
            width: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
        }

        .carrito-scroll {
            min-height: 400px;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }

        .total-panel {
            background: #1a1d20;
            color: #fff;
            border-radius: 12px;
            padding: 20px;
        }

        .opacity-closed {
            filter: grayscale(1);
            opacity: 0.5;
            pointer-events: none;
        }

        .venta-item {
            transition: background 0.2s;
            border-bottom: 1px solid #eee;
        }

        .venta-item:hover {
            background-color: #f8f9fa !important;
        }

        .btn-print {
            color: #6c757d;
            transition: color 0.2s;
        }

        .btn-print:hover {
            color: #000;
        }
    </style>
    <style>
        .card-metric {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 0.5rem;
        }

        .card-metric:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .card-body h3 {
            font-weight: 700;
        }

        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            opacity: 0.8;
        }

        .bg-custom-ai {
            background-color: #3867d6 !important;
            /* Azul más vibrante para AI */
        }
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
            padding: 4px;
            /* Espacio entre el borde del círculo y el logo */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
            /* Evita que el círculo se deforme */
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
    <?php if ($python_error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'error',
                title: 'Error de Procesamiento',
                text: 'No se pudo obtener la información del script de Python. Verifique la ruta de ml_api_service.py y la conexión a la base de datos.',
                footer: 'Detalle: <?php echo str_replace("'", "\'", $python_error); ?>'
                });
        });
    </script>
    <?php endif; ?>
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
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                    data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle fa-lg"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item py-2" href="../usuarios/register.php"><i
                                class="fas fa-id-card me-2 opacity-50"></i>Mi Perfil</a></li>
                    <li>
                        <hr class="dropdown-divider" />
                    </li>
                    <li><a class="dropdown-item py-2 text-danger" href="../usuarios/cerrar.php"><i
                                class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">CORE</div>
                        <a class="nav-link active" href="../Dashboard/panel.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            Panel
                        </a>
                        <div class="sb-sidenav-menu-heading">GESTIÓN</div>
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
                            Productos  (Lec.)
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
                            Stock (Lec.)
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
                        <div class="sb-sidenav-menu-heading">REPORTES Y CAJA</div>
                        <a class="nav-link" href="../Reporte/reporte.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                            Reportes
                        </a>

                        <a class="nav-link" href="../caja/caja.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>
                            Caja
                        </a>
                        
                        <?php if ($rol === 'admin'): ?>
                        <a class="nav-link" href="../monitor_qr.php" target="_blank" rel="noopener noreferrer">
                            <div class="sb-nav-link-icon">
                                <i class="fas fa-robot text-primary"></i>
                            </div>
                            Monitor AI
                        </a>
                    <?php endif; ?>


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
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Panel AI</h1>


                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-success text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="card-title">Ventas del Día</div>
                                            <h3>$
                                                <?php echo number_format($ventasDia, 0, ',', '.'); ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <a class="small text-white stretched-link" href="../venta/venta.php">Ver Últimas
                                        Ventas</a>
                                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-primary text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="card-title">Productos Disponibles</div>
                                            <h3>
                                                <?php echo $productosDisponibles; ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-box-open fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <a class="small text-white stretched-link"
                                        href="../productos/GestionProducto.php">Ver Inventario</a>
                                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-warning text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="card-title">Monto en Caja (Hoy)</div>
                                            <h3>$
                                                <?php echo number_format($montoCaja, 0, ',', '.'); ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-cash-register fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <a class="small text-white stretched-link" href="../caja/caja.php">Ver
                                        Movimientos</a>
                                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-danger text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="card-title">Insumos Críticos</div>
                                            <h3>
                                                <?php echo $stockCritico; ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <a class="small text-white stretched-link"
                                        href="../stock/GestionStock.php">Gestionar Alertas</a>
                                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 col-md-6">
                            <div class="card bg-custom-ai text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="card-title">
                                                Predicción Ventas Hoy
                                                <button type="button" class="btn btn-sm btn-link text-white p-0"
                                                    data-bs-toggle="modal" data-bs-target="#modalPrediccion">
                                                    <i class="fas fa-info-circle fa-lg"></i>
                                                </button>
                                            </div>
                                            <h3>$
                                                <?php echo number_format($prediccionHoy, 2, ',', '.'); ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex flex-column align-items-start small">
                                    <span class="text-white-50">Proyección semanal: $
                                        <?php echo number_format($prediccionSemanal, 2, ',', '.'); ?>
                                    </span>
                                    <span class="text-white-50">Proyección mensual: $
                                        <?php echo number_format($prediccionMensual, 2, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6">
                            <div class="card bg-info text-white mb-4 card-metric">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="card-title">
                                                Predicción de Unidades TOP 3
                                                <button type="button" class="btn btn-sm btn-link text-white p-0"
                                                    data-bs-toggle="modal" data-bs-target="#modalPrediccionUnidades">
                                                    <i class="fas fa-info-circle fa-lg"></i>
                                                </button>
                                            </div>
                                            <?php if (!empty($prediccionUnidades)): ?>
                                            <?php $top_prod_unidades = $prediccionUnidades[0]; ?>
                                            <h6 class="mb-1 fw-bold">
                                                <?php echo htmlspecialchars($top_prod_unidades['nombre']); ?> (HOY)
                                            </h6>
                                            <h3 class="mb-0">
                                                <?php echo number_format($top_prod_unidades['prediccion'], 0, ',', '.'); ?>
                                                Unidades
                                            </h3>
                                            <?php else: ?>
                                            <h3 class="mb-0">Sin Datos</h3>
                                            <span class="small">Se necesitan ventas por 90 días.</span>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-cubes fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="card-footer d-flex flex-column align-items-start small">
                                    <?php 
                                        if (count($prediccionUnidades) > 1):
                                            $details = array_slice($prediccionUnidades, 1, 2);
                                            foreach ($details as $prod_detail):
                                        ?>
                                    <span class="text-white-50">
                                        *
                                        <?php echo htmlspecialchars($prod_detail['nombre']); ?>:
                                        <?php echo number_format($prod_detail['prediccion'], 0, ',', '.'); ?> u.
                                    </span>
                                    <?php 
                                            endforeach;
                                        else:
                                        ?>
                                    <span class="text-white-50">No hay más productos en el TOP 3.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($metrics['productosTendencia'])): 
        $nombreTop = key($metrics['productosTendencia']);
        $datosTop = current($metrics['productosTendencia']);
    ?>
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Top:
                                        <?php echo htmlspecialchars($nombreTop); ?>
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $datosTop['tendencia']; ?>%
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i
                                        class="fas fa-<?php echo $datosTop['icono']; ?> fa-2x text-<?php echo $datosTop['color']; ?>"></i>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-muted small">Sin datos de tendencia</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <i class="fas fa-list-alt me-1"></i>
                                    Últimas 5 Ventas Realizadas
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nº Venta</th>
                                                    <th>Hora</th>
                                                    <th>Total</th>
                                                    <th>Vendedor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ultimas as $venta): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo $venta['id_venta']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $venta['hora']; ?>
                                                    </td>
                                                    <td class="fw-bold">$
                                                        <?php echo number_format($venta['total'], 0, ',', '.'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($venta['vendedor']); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if (empty($ultimas)): ?>
                                    <p class="text-center text-muted m-3">No se encontraron ventas hoy.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-chart-bar me-1"></i> Top 5 Productos por Tendencia (
                                            <?php echo $periodoTendencia; ?> Días)
                                        </span>
                                        <div class="btn-group" role="group">
                                            <a href="?periodo_tendencia=7"
                                                class="btn btn-sm <?php echo ($periodoTendencia == 7 ? 'btn-primary' : 'btn-outline-secondary'); ?>">7D</a>
                                            <a href="?periodo_tendencia=15"
                                                class="btn btn-sm <?php echo ($periodoTendencia == 15 ? 'btn-primary' : 'btn-outline-secondary'); ?>">15D</a>
                                            <a href="?periodo_tendencia=30"
                                                class="btn btn-sm <?php echo ($periodoTendencia == 30 ? 'btn-primary' : 'btn-outline-secondary'); ?>">30D</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Vendido</th>
                                                    <th>Tendencia (%)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($productosTendenciaTemp, 0, 5) as $data): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($data['prod']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $data['vendido']; ?>
                                                    </td>
                                                    <td>
                                                        <span class="text-<?php echo $data['color']; ?> fw-bold">
                                                            <?php echo number_format($data['tendencia'], 1, ',', '.'); ?>%
                                                            <i class="fas fa-<?php echo $data['icono']; ?>"></i>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if (empty($productosTendenciaTemp)): ?>
                                    <p class="text-center text-muted m-3">Ejecuta el script de Python para ver
                                        tendencias.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4 border-left-info">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-lightbulb me-1"></i> Sugerencias Estratégicas (Análisis de Canasta)
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($metrics['sugerencias'])): ?>
                                <?php foreach ($metrics['sugerencias'] as $sug): ?>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded bg-light mb-2">
                                        <h6 class="text-primary">
                                            <?php echo $sug['principal']; ?> +
                                            <?php echo $sug['sugerido']; ?>
                                        </h6>
                                        <p class="small mb-1">
                                            Quien compra
                                            <?php echo $sug['principal']; ?> suele llevar
                                            <?php echo $sug['sugerido']; ?>
                                        </p>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-info"
                                                style="width: <?php echo $sug['probabilidad']; ?>%"></div>
                                        </div>
                                        <small class="text-muted">Confianza:
                                            <?php echo $sug['probabilidad']; ?>%
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="col-12 text-center text-muted">
                                    Se necesitan más transacciones con múltiples productos para generar sugerencias.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <footer class="py-4 bg-light mt-auto">
            </footer>
        </div>
    </div>

    <div class="modal fade" id="modalPrediccion" tabindex="-1" aria-labelledby="modalPrediccionLabel"
        aria-hidden="true">
    </div>

    <div class="modal fade" id="modalPrediccionUnidades" tabindex="-1" aria-labelledby="modalPrediccionUnidadesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalPrediccionUnidadesLabel"><i class="fas fa-cubes me-2"></i>
                        Predicción de Unidades (Operacional)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Esta es la métrica más crítica para la **eficiencia de costos** y la operación diaria.</p>

                    <p>Predice la **cantidad exacta de unidades** que se venderán hoy de los tres productos más
                        populares, permitiéndole optimizar la preparación de stock e insumos:</p>

                    <h6>¿Cómo funciona?</h6>
                    <ol>
                        <li>El sistema analiza las ventas de unidades de los últimos **90 días** para los productos TOP
                            3.</li>
                        <li>Aplica un modelo de Regresión Lineal a la serie de tiempo de cada producto para encontrar su
                            tendencia de volumen.</li>
                        <li>Proyecta las unidades esperadas para el día de hoy.</li>
                    </ol>

                    <p class="alert alert-info small mt-3 mb-0">Use esta predicción para preparar con anticipación la
                        pastelería, sándwiches, o asegurar el stock de los ingredientes más sensibles (ej. leche fresca)
                        y **reducir las mermas**.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTendencia" tabindex="-1" aria-labelledby="modalTendenciaLabel" aria-hidden="true">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="../js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"
        crossorigin="anonymous"></script>
    <script src="../js/datatables-simple-demo.js"></script>

</body>

</html>