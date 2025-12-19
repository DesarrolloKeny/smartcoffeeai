<?php
// Asegúrate de que no haya NADA (ni espacios, ni saltos de línea) antes de <?php
include_once('../controlador/conexion.php');

// *******************************************************************
// 1. LÓGICA PARA CARGAR DATOS DE INSUMO (AJAX GET)
// Esta lógica se ejecuta cuando insumos.php llama a fetch('registroStock.php?id=X')
// *******************************************************************
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    
    // **CRUCIAL 1: Encabezado JSON**
    // Esto le indica al navegador que la respuesta es JSON, no HTML.
    header('Content-Type: application/json');
    
    $id = (int)$_GET['id'];
    
    try {
        // Consulta para obtener los datos del insumo a editar
        $stmt = $pdo->prepare("SELECT id_insumo, nombre, unidad, stock, alerta_stock, activo 
                               FROM insumos 
                               WHERE id_insumo = ?");
        $stmt->execute([$id]);
        $insumo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($insumo) {
            // Éxito: Devuelve los datos
            echo json_encode($insumo);
        } else {
            // No encontrado
            http_response_code(404);
            echo json_encode(['error' => 'Insumo no encontrado.']);
        }

    } catch (PDOException $e) {
        // Error de DB
        http_response_code(500);
        echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
    }
    
    // **CRUCIAL 2: Detener la ejecución**
    // Impide que se ejecute la lógica POST o que se imprima cualquier contenido accidental.
    exit; 
}


// *******************************************************************
// 2. LÓGICA PARA REGISTRO/MOVIMIENTO DE STOCK (POST)
// Esta es la lógica original que manejas cuando se envía el formulario
// *******************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // Validaciones y sanitización
        $id_insumo = (int)$_POST['id_insumo'] ?? 0;
        $cantidad = (float)$_POST['cantidad'] ?? 0;
        $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? '');
        $origen = trim($_POST['origen'] ?? '');
        $fecha = date('Y-m-d H:i:s');  // Fecha actual

        if ($id_insumo <= 0 || $cantidad == 0 || !in_array($tipo_movimiento, ['entrada', 'salida'])) {
            throw new Exception("ID de insumo, cantidad y tipo de movimiento son obligatorios. Tipo debe ser 'entrada' o 'salida'.");
        }

        // Verificar que el insumo existe en la tabla insumos
        $stmt_check = $pdo->prepare("SELECT id_insumo FROM insumos WHERE id_insumo = :id_insumo AND activo = 1");
        $stmt_check->bindParam(':id_insumo', $id_insumo);
        $stmt_check->execute();
        if ($stmt_check->rowCount() == 0) {
            throw new Exception("El insumo seleccionado no existe o no está activo.");
        }

        // Preparar INSERT
        $sql = "INSERT INTO stock_insumos (id_insumo, cantidad, tipo_movimiento, origen, fecha) 
                 VALUES (:id_insumo, :cantidad, :tipo_movimiento, :origen, :fecha)";
        $stmt = $pdo->prepare($sql);

        // Bind
        $stmt->bindParam(':id_insumo', $id_insumo);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':tipo_movimiento', $tipo_movimiento);
        $stmt->bindParam(':origen', $origen);
        $stmt->bindParam(':fecha', $fecha);

        // Ejecutar
        $stmt->execute();
        $lastInsertId = $pdo->lastInsertId();

        if ($lastInsertId > 0) {
            header("Location: ../stock/GestionStock.php?success=Movimiento de stock registrado correctamente.");
            exit;
        } else {
            header("Location: ../stock/GestionStock.php?error=Error al registrar movimiento de stock.");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: ../stock/GestionStock.php?error=Error de DB: " . $e->getMessage());
        exit;
    } catch (Exception $e) {
        header("Location: ../stock/GestionStock.php?error=" . $e->getMessage());
        exit;
    }
}
// Se recomienda omitir la etiqueta de cierre `?>` 