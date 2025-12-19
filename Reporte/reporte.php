<?php
session_start(); // Iniciar sesión
// Obtener rol y nombre del usuario
$rol = $_SESSION['rol'] ?? 'vendedor'; 
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// --- CONFIGURACIÓN DE LA CONEXIÓN FLASK ---
$FLASK_URL = 'http://127.0.0.1:5000';
$REPORTE_ENDPOINT = $FLASK_URL . '/reportes';
$KPI_ENDPOINT = $REPORTE_ENDPOINT . '?kpis=true'; // Nuevo: Añadimos el parámetro para obtener JSON de KPIs

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.php'); // Redirigir al login si no está logueado
    exit;
}

// Obtener rol del usuario
$rol = $_SESSION['rol'] ?? 'vendedor';
if (!in_array($rol, ['vendedor', 'admin'])) {
    header('Location: ../login.php?error=4'); // Rol inválido
    exit;
}

// --- OBTENER DATOS INICIALES (KPIs) DEL SERVIDOR FLASK (GET request) ---
$total_ventas_dia = 'Cargando...';
$proyeccion_manana = 'Cargando...';
$kpi_error = false;

try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5, // 5 segundos de timeout
            // Opcional: puede ayudar en ciertos entornos
            // 'header' => "X-Requested-With: XMLHttpRequest\r\n" 
        ]
    ]);
    
    // Solicitud GET que espera JSON gracias al parámetro ?kpis=true
    $response = @file_get_contents($KPI_ENDPOINT, false, $context);
    
    if ($response === FALSE) {
        // Esto indica que el servidor no respondió (conexión rechazada, timeout, etc.)
        throw new Exception("El servidor de reportes (Flask) no responde o no está accesible en $KPI_ENDPOINT.");
    }
    
    // Decodificar la respuesta JSON
    $kpis_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Esto indica que la respuesta no fue un JSON válido
        throw new Exception("Respuesta inválida del servidor Flask. Posiblemente un error interno en Python.");
    }
    
    // Asignar los valores del JSON
    $total_ventas_dia = $kpis_data['total_ventas_dia'] ?? 'N/A';
    $proyeccion_manana = $kpis_data['proyeccion_manana'] ?? 'N/A';
    $kpi_error = $kpis_data['kpi_error'] ?? true;
    
} catch (Exception $e) {
    $kpi_error = true;
    $total_ventas_dia = 'Error de conexión';
    $proyeccion_manana = 'Ver Consola';
    // Para depuración en el servidor PHP:
    error_log("Error al cargar KPIs de Flask: " . $e->getMessage()); 
}

// Si $kpi_error es False, se mostrará "Activo", si es True (o si hubo error de PHP) se mostrará "Fuera de Línea"
$estado_motor_clase = $kpi_error ? 'text-warning' : 'text-success';
$estado_motor_texto = $kpi_error ? 'Fuera de Línea' : 'Activo';

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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
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
                            <a class="nav-link" href="../Dashboard/panel.php"><div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>Panel</a>
                            <div class="sb-sidenav-menu-heading">Gestión</div>
                            <a class="nav-link" href="../venta/venta.php"><div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>Ventas</a>
                            <a class="nav-link" href="../productos/GestionProducto.php" <?php if ($rol === 'vendedor') echo 'title="Solo lectura"'; ?>><div class="sb-nav-link-icon"><i class="fas fa-coffee"></i></div>Productos</a>
                            <a class="nav-link" href="../recetas/GestionResetas.php"><div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>Recetas</a>
                            <a class="nav-link" href="../stock/GestionStock.php" <?php if ($rol === 'vendedor') echo 'title="Solo lectura"'; ?>><div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>Stock <?php if ($rol === 'vendedor') echo '(Lectura)'; ?></a>
                            <a class="nav-link" href="../cliente/GestionCliente.php"><div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>Clientes</a>
                            <?php if ($rol === 'admin'): ?>
                                <a class="nav-link" href="../usuarios/GestionUsuario.php"><div class="sb-nav-link-icon"><i class="fas fa-user-cog"></i></div>Usuarios</a>
                            <?php endif; ?>
                            <div class="sb-sidenav-menu-heading">Addons</div>
                            <a class="nav-link active" href="../Reporte/reporte.php"><div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>Reportes</a>
                            <a class="nav-link" href="../caja/caja.php"><div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>Caja</a>
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
                        <h1 class="mt-4"><i class="fas fa-chart-line me-2"></i> Reportes y Análisis Predictivo</h1>
                        <p class="mb-4 text-muted">Datos procesados en tiempo real por el motor de análisis Python (Flask).</p>

                        <div class="row mb-4">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-primary text-white h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="fs-5 fw-bold mb-1">Ventas Hoy</div>
                                        <div class="h3 mb-0" id="kpi-ventas-hoy"><?php echo $total_ventas_dia; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-success text-white h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="fs-5 fw-bold mb-1">Predicción ML Mañana</div>
                                        <div class="h3 mb-0" id="kpi-prediccion-ml"><?php echo $proyeccion_manana; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-info text-white h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="fs-5 fw-bold mb-1">Estado del Motor ML</div>
                                        <div class="h3 mb-0">
                                            <span class="<?php echo $estado_motor_clase; ?>"><?php echo $estado_motor_texto; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-lg">
                            <div class="card-header bg-light">
                                <i class="fas fa-sliders-h me-1"></i>
                                Opciones de Reporte
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="tipoReporte" class="form-label">Tipo de Reporte:</label>
                                        <select class="form-select" id="tipoReporte">
                                            <option value="ventas_rango">Ventas por Rango de Fecha</option>
                                            <option value="top_productos">Top 5 Productos más Vendidos</option>
                                            <option value="menos_vendidos">Top 5 Productos Menos Vendidos</option>
                                            <option value="inventario_general">Inventario General</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3" id="date-range-container">
                                        <label for="fechaDesde" class="form-label">Desde:</label>
                                        <input type="date" class="form-control" id="fechaDesde" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                                    </div>
                                    <div class="col-md-3" id="date-hasta-container">
                                        <label for="fechaHasta" class="form-label">Hasta:</label>
                                        <input type="date" class="form-control" id="fechaHasta" value="<?php echo date('Y-m-d'); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <button class="btn btn-primary w-100" id="btnGenerarReporte" onclick="generarReporte()">
                                            <i class="fas fa-play me-1"></i> Generar Reporte
                                        </button>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-12 text-end">
                                        <button class="btn btn-danger me-2" id="btnExportarPDF" disabled onclick="exportar('pdf')">
                                            <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                                        </button>
                                        <button class="btn btn-success" id="btnExportarExcel" disabled onclick="exportar('excel')">
                                            <i class="fas fa-file-excel me-1"></i> Exportar Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-lg">
                            <div class="card-header bg-secondary text-white">
                                <i class="fas fa-table me-1"></i>
                                Datos del Reporte <span id="reporte-title-display"></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="reporteTabla" width="100%" cellspacing="0">
                                        <thead>
                                            <tr></tr>
                                        </thead>
                                        <tbody>
                                            </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="loading-overlay" class="d-none text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2 text-muted">Cargando datos del servidor...</p>
                            </div>
                        </div>

                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; SmartCoffee AI</div>
                            <div></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        
        <script>
            // --- VARIABLES GLOBALES ---
            const FLASK_URL = '<?php echo $FLASK_URL; ?>';
            let currentReportData = []; // Almacena los datos para la exportación

            // --- MANEJO DE VISTA (FECHAS) ---
            document.getElementById('tipoReporte').addEventListener('change', function() {
                const isVentas = this.value === 'ventas_rango';
                document.getElementById('date-range-container').style.display = isVentas ? 'block' : 'none';
                document.getElementById('date-hasta-container').style.display = isVentas ? 'block' : 'none';
            });

            // --- FUNCION PRINCIPAL AJAX (GET KPIS DE NUEVO SI FALLARON) ---
            // Intenta cargar los KPIs de nuevo si el PHP falló inicialmente (aunque PHP ya los cargó)
            // Esta función es redundante si PHP funcionó, pero útil si el GET de PHP falló
            async function loadKpis() {
                // Si el PHP falló, cargamos el estado de error para evitar intentar otra vez
                if (document.getElementById('kpi-ventas-hoy').textContent.includes('Error')) {
                     // Solo intentamos de nuevo si la conexión no está activa.
                    try {
                        const response = await fetch(`${FLASK_URL}/reportes?kpis=true`, {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });
                        
                        if (response.ok) {
                            const kpis_data = await response.json();
                            document.getElementById('kpi-ventas-hoy').textContent = kpis_data.total_ventas_dia;
                            document.getElementById('kpi-prediccion-ml').textContent = kpis_data.proyeccion_manana;
                            // Actualizar estado del motor
                            const motorStatus = document.querySelector('.card.bg-info .h3 mb-0 span');
                            motorStatus.textContent = kpis_data.kpi_error ? 'Fuera de Línea' : 'Activo';
                            motorStatus.classList.remove('text-warning', 'text-success');
                            motorStatus.classList.add(kpis_data.kpi_error ? 'text-warning' : 'text-success');

                        }
                    } catch (error) {
                        console.error("Error al reintentar la carga de KPIs:", error);
                    }
                }
            }
            
            // Carga inicial (aunque ya cargó PHP, este es el lado JS)
            document.addEventListener('DOMContentLoaded', function() {
                loadKpis(); // Carga de KPIs en JS si el PHP falló
                
                // Dispara el reporte inicial (Ventas por Rango) al cargar la página
                generarReporte();
            });

            // --- FUNCION PRINCIPAL AJAX (POST DE REPORTES) ---
            async function generarReporte() {
                const tipoReporte = document.getElementById('tipoReporte').value;
                const fechaDesde = document.getElementById('fechaDesde').value;
                const fechaHasta = document.getElementById('fechaHasta').value;
                const tablaBody = document.querySelector('#reporteTabla tbody');
                const tablaHead = document.querySelector('#reporteTabla thead tr');
                const btnGenerar = document.getElementById('btnGenerarReporte');
                const loadingOverlay = document.getElementById('loading-overlay');
                
                // Limpiar la tabla y deshabilitar exportación
                tablaBody.innerHTML = '';
                tablaHead.innerHTML = '';
                document.getElementById('btnExportarPDF').disabled = true;
                document.getElementById('btnExportarExcel').disabled = true;

                // Mostrar carga
                btnGenerar.disabled = true;
                loadingOverlay.classList.remove('d-none');
                tablaBody.innerHTML = '<tr><td colspan="100" class="text-center text-muted">Solicitando datos...</td></tr>';
                
                let requestBody = {
                    action: tipoReporte,
                    fecha_desde: fechaDesde,
                    fecha_hasta: fechaHasta,
                    limit: 5 // Default limit para Top/Menos Vendidos
                };

                try {
                    const response = await fetch(`${FLASK_URL}/reportes`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestBody)
                    });

                    // Si la respuesta HTTP no es 200 (ej: 404 o 500)
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Error HTTP: ${response.status} - ${errorText.substring(0, 100)}...`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        currentReportData = result.data;
                        if (currentReportData.length > 0) {
                            renderTable(currentReportData, tipoReporte);
                            document.getElementById('btnExportarPDF').disabled = false;
                            document.getElementById('btnExportarExcel').disabled = false;
                        } else {
                            tablaBody.innerHTML = '<tr><td colspan="100" class="text-center text-warning">No se encontraron datos para los criterios seleccionados.</td></tr>';
                        }
                    } else {
                        // Error lógico devuelto por Flask
                        throw new Error(result.message || 'Error desconocido en el procesamiento del reporte.');
                    }

                } catch (error) {
                    console.error('Error en AJAX:', error);
                    tablaBody.innerHTML = `<tr><td colspan="100" class="text-center text-danger">Error: ${error.message}. Verifique que el servidor Flask esté corriendo.</td></tr>`;
                    // Alerta de SweetAlert para el usuario final
                    Swal.fire('Error de Conexión', `No se pudo conectar con el servidor de análisis (${FLASK_URL}). Por favor, verifique que Flask esté activo. Detalle: ${error.message.substring(0, 80)}...`, 'error');

                } finally {
                    btnGenerar.disabled = false;
                    loadingOverlay.classList.add('d-none');
                }
            }

            // --- RENDERIZADO DE TABLA ---
            function renderTable(data, reportType) {
                const tablaBody = document.querySelector('#reporteTabla tbody');
                const tablaHead = document.querySelector('#reporteTabla thead tr');
                
                let headers = [];
                let displayTitle = '';

                if (reportType === 'ventas_rango') {
                    headers = ['Fecha', 'Total'];
                    displayTitle = 'Ventas por Día';
                } else if (reportType === 'top_productos' || reportType === 'menos_vendidos') {
                    headers = ['Nombre', 'Cantidad Vendida'];
                    displayTitle = (reportType === 'top_productos' ? 'Top ' : 'Menos ') + data.length + ' Productos';
                } else if (reportType === 'inventario_general') {
                    headers = ['Nombre', 'Precio de Venta', 'Stock Actual'];
                    displayTitle = 'Inventario General';
                }
                
                // Limpiar y crear encabezados
                tablaBody.innerHTML = '';
                tablaHead.innerHTML = '';
                headers.forEach(h => {
                    const th = document.createElement('th');
                    th.textContent = h;
                    tablaHead.appendChild(th);
                });
                
                document.getElementById('reporte-title-display').textContent = `(${displayTitle})`;

                // Llenar filas
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    
                    // Claves en el orden esperado por los encabezados (asumimos que Flask devuelve las claves en el orden 'fecha', 'total' etc.)
                    const rowKeys = Object.keys(row); 
                    
                    rowKeys.forEach(key => {
                        const value = row[key];
                        const td = document.createElement('td');
                        
                        // Formato de moneda para totales y precios (si la clave es 'total' o 'precio_venta')
                        if (typeof value === 'number' && (key === 'total' || key === 'precio_venta')) {
                            td.textContent = '$' + value.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        } else {
                            td.textContent = value;
                        }
                        tr.appendChild(td);
                    });
                    tablaBody.appendChild(tr);
                });
            }

            // --- FUNCIÓN DE EXPORTACIÓN ---
            function exportar(tipo) {
                const tipoReporte = document.getElementById('tipoReporte').value;
                const fechaDesde = document.getElementById('fechaDesde').value;
                const fechaHasta = document.getElementById('fechaHasta').value;
                
                let url = `${FLASK_URL}/exportar/${tipoReporte}/${tipo}?`;
                
                if (tipoReporte === 'ventas_rango') {
                    url += `fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`;
                } else if (tipoReporte === 'top_productos' || tipoReporte === 'menos_vendidos') {
                    url += `limit=5`; 
                }
                
                // Iniciar la descarga
                window.open(url, '_blank');
            }
        </script>
    </body>
</html>