<?php
// actualizar_insumo.php

// 1. Incluir la conexión a la base de datos
// Asegúrate de que esta ruta sea correcta para tu estructura de archivos.
include_once('conexion.php'); 

// 2. Verificar que la petición sea POST (envío de formulario)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, redirigir o mostrar un error
    header("Location: ../stock/insumos.php?error=Acceso no permitido.");
    exit;
}

try {
    // 3. Obtener y sanitizar datos del formulario
    $id_insumo = (int)$_POST['id_insumo'] ?? 0;
    $nombre = trim($_POST['nombre'] ?? '');
    $unidad = trim($_POST['unidad'] ?? '');
    $stock = (float)$_POST['stock'] ?? 0;
    $alerta_stock = (float)$_POST['alerta_stock'] ?? 0;
    
    // Un checkbox envía '1' si está marcado, o no se envía si no lo está.
    $activo = isset($_POST['activo']) ? 1 : 0; 
    
    // Obtener la fecha y hora actual para el campo de actualización
    $fecha_actualizacion = date('Y-m-d H:i:s');

    // 4. Validaciones
    if ($id_insumo <= 0) {
        throw new Exception("ID de insumo inválido para la actualización.");
    }
    if (empty($nombre) || empty($unidad)) {
        throw new Exception("El nombre y la unidad del insumo son campos obligatorios.");
    }

    // 5. Preparar la consulta UPDATE
    $sql = "UPDATE insumos SET 
                nombre = :nombre, 
                unidad = :unidad, 
                stock = :stock, 
                alerta_stock = :alerta_stock, 
                activo = :activo, 
                fecha_actualizacion = :fecha_actualizacion 
            WHERE id_insumo = :id_insumo";
            
    $stmt = $pdo->prepare($sql);

    // 6. Bindear los parámetros
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':unidad', $unidad);
    $stmt->bindParam(':stock', $stock);
    $stmt->bindParam(':alerta_stock', $alerta_stock);
    $stmt->bindParam(':activo', $activo);
    $stmt->bindParam(':fecha_actualizacion', $fecha_actualizacion);
    $stmt->bindParam(':id_insumo', $id_insumo);

    // 7. Ejecutar la consulta
    $stmt->execute();

    // 8. Verificar resultado y redirigir
    // Si el contador de filas afectadas es 1 (actualizado) o 0 (no hubo cambios), se considera éxito
    if ($stmt->rowCount() >= 0) {
        $message = "Insumo '" . htmlspecialchars($nombre) . "' actualizado correctamente.";
        header("Location: ../stock/insumos.php?success=" . urlencode($message));
        exit;
    } else {
        throw new Exception("Error desconocido al actualizar el insumo.");
    }

} catch (PDOException $e) {
    // Error de Base de Datos
    $error_message = "Error de DB al actualizar insumo: " . $e->getMessage();
    header("Location: ../stock/insumos.php?error=" . urlencode($error_message));
    exit;
} catch (Exception $e) {
    // Error de Validación o Lógica
    header("Location: ../stock/insumos.php?error=" . urlencode($e->getMessage()));
    exit;
}

// Se recomienda omitir la etiqueta de cierre `?>`