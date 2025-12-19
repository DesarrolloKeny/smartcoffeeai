<?php
session_start();
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.php');
    exit;
}

// Solo administradores pueden ver el historial completo
$rol = $_SESSION['rol'] ?? 'vendedor';
if ($rol !== 'admin') {
    header('Location: ../Dashboard/panel.php?error=acceso_denegado_historial');
    exit;
}

// Incluir conexión a la DB (debe definir $pdo)
include_once('../controlador/conexion.php');

// Función para ejecutar consultas seguras con PDO
if (!function_exists('ejecutarConsulta')) {
    function ejecutarConsulta($pdo, $query, $params = []) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }
}

// --- Lógica de Filtrado por Fechas ---
// Por defecto, muestra los últimos 30 días
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));

// Consulta SQL para obtener el historial completo
$query_historial = "
    SELECT
        c.id_caja,
        c.fecha,
        c.apertura,
        c.monto_contado,
        c.total_esperado,
        c.diferencia,
        c.estado,
        u_ap.nombre AS usuario_apertura,
        u_ci.nombre AS usuario_cierre,
        DATE_FORMAT(c.fecha_cierre, '%Y-%m-%d %H:%i') AS fecha_cierre_format
    FROM caja c
    LEFT JOIN usuarios u_ap ON c.id_usuario_apertura = u_ap.id_usuario
    LEFT JOIN usuarios u_ci ON c.id_usuario_cierre = u_ci.id_usuario
    WHERE c.fecha BETWEEN ? AND ?  /* CRÍTICO: Filtro de fechas */
    ORDER BY c.fecha DESC, c.id_caja DESC
";

try {
    $stmt_historial = ejecutarConsulta($pdo, $query_historial, [$fecha_inicio, $fecha_fin]);
    $cajas_historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_mensaje = "Error al cargar historial: " . $e->getMessage();
    $cajas_historial = [];
}

// --- Preparar Datos para Gráfico (Chart.js) ---
$chart_labels = [];
$chart_data_esperado = [];
$chart_data_diferencia = [];

// Recorrer los datos, pero en orden ascendente para el gráfico
$cajas_para_grafico = array_reverse($cajas_historial);

foreach ($cajas_para_grafico as $caja) {
    if ($caja['estado'] === 'cerrada') {
        $chart_labels[] = $caja['fecha'];
        $chart_data_esperado[] = floatval($caja['total_esperado']);
        $chart_data_diferencia[] = floatval($caja['diferencia']);
    }
}

// Codificar datos a JSON para JavaScript
$json_labels = json_encode($chart_labels);
$json_esperado = json_encode($chart_data_esperado);
$json_diferencia = json_encode($chart_data_diferencia);

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>SmartCoffee AI</title>
        <link rel="icon" type="image/png" href="../assets/img/logo.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
                        <?php echo htmlspecialchars($nombre_usuario); ?> <br><?php echo ucfirst($rol); ?>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 py-4">
                    <h1 class="mt-4"><i class="fas fa-history me-2"></i> Historial de Cajas</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="caja.php">Caja</a></li>
                        <li class="breadcrumb-item active">Historial</li>
                    </ol>

                    <?php if (isset($error_mensaje)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_mensaje); ?></div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-filter me-1"></i> Filtrar Historial y Gráficos</div>
                        <div class="card-body">
                            <form method="GET" action="historial_caja.php">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Aplicar Filtro</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-chart-line me-1"></i> Tendencia de Cuadre de Caja (Fechas Cerradas)</div>
                        <div class="card-body">
                            <?php if (empty($chart_labels)): ?>
                                <div class="alert alert-warning">No hay datos de cajas cerradas en el periodo seleccionado para generar el gráfico.</div>
                            <?php else: ?>
                                <canvas id="cajaTrendChart" height="150"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>


                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Reporte Detallado de Aperturas y Cierres
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID Caja</th>
                                        <th>Fecha (Día)</th>
                                        <th>Estado</th>
                                        <th>Apertura ($)</th>
                                        <th>Contado ($)</th>
                                        <th>Esperado ($)</th>
                                        <th>Diferencia ($)</th>
                                        <th>Usuario Apertura</th>
                                        <th>Usuario Cierre</th>
                                        <th>Fecha Cierre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($cajas_historial)): ?>
                                        <?php foreach ($cajas_historial as $caja): 
                                            // Clases de color para el estado y la diferencia
                                            $estado_color = ($caja['estado'] === 'abierta') ? 'bg-warning text-dark' : 'bg-success text-white';
                                            $diferencia_cuadre = floatval($caja['diferencia']);
                                            $diferencia_color = ($diferencia_cuadre == 0.00) ? 'text-success fw-bold' : 'text-danger fw-bold';
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($caja['id_caja']); ?></td>
                                                <td><?php echo htmlspecialchars($caja['fecha']); ?></td>
                                                <td><span class="badge <?php echo $estado_color; ?>"><?php echo ucfirst($caja['estado']); ?></span></td>
                                                <td><?php echo number_format($caja['apertura'], 2); ?></td>
                                                <td><?php echo number_format($caja['monto_contado'], 2); ?></td>
                                                <td><?php echo number_format($caja['total_esperado'], 2); ?></td>
                                                <td class="<?php echo $diferencia_color; ?>">
                                                    <?php echo number_format($diferencia_cuadre, 2); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($caja['usuario_apertura'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($caja['usuario_cierre'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($caja['fecha_cierre_format'] ?? 'Pendiente'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">No hay registros de cajas disponibles.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    
    <script>
        // CRÍTICO: Inicialización de Simple-Datatables con exportación y etiquetas en español.
        window.addEventListener('DOMContentLoaded', event => {
            const datatablesSimple = document.getElementById('datatablesSimple');
            if (datatablesSimple) {
                new simpleDatatables.DataTable(datatablesSimple, {
                    sortable: true,
                    searchable: true,
                    exportable: true, // HABILITA EXPORTACIÓN
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Buscar en el historial...",
                        perPage: " Mostrar {select} entradas",
                        noRows: "No se encontraron resultados",
                        info: "Mostrando de {start} a {end} de {rows} entradas",
                        export: "Exportar",
                    }
                });
            }
        });

        // CRÍTICO: Inicialización de Chart.js
        const labels = <?php echo $json_labels; ?>;
        const dataEsperado = <?php echo $json_esperado; ?>;
        const dataDiferencia = <?php echo $json_diferencia; ?>;

        if (labels.length > 0) {
            const ctx = document.getElementById('cajaTrendChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Efectivo Esperado ($)',
                            data: dataEsperado,
                            borderColor: 'rgba(0, 123, 255, 1)',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Diferencia ($)',
                            data: dataDiferencia,
                            borderColor: 'rgba(220, 53, 69, 1)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            yAxisID: 'y1' // Usa el segundo eje Y para la diferencia
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Fecha'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Monto ($)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false, // Solo dibuja la cuadrícula del primer eje
                            },
                            title: {
                                display: true,
                                text: 'Diferencia ($)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Tendencia de Cuadre de Caja (Esperado vs. Diferencia)'
                        }
                    }
                }
            });
        }
    </script>
    </body>
</html>