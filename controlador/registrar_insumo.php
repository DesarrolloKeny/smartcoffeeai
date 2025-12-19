<?php
include_once('../controlador/conexion.php');  // Ajusta ruta si es '../controlador/conexion.php'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // Validaciones y sanitización
        $nombre = trim($_POST['nombre'] ?? '');
        $unidad = trim($_POST['unidad'] ?? '');
        $stock = (float)$_POST['stock'] ?? 0;
        $alerta_stock = (float)$_POST['alerta_stock'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_actualizacion = date('Y-m-d H:i:s');

        if (empty($nombre) || empty($unidad) || $stock < 0) {
            throw new Exception("Nombre, unidad y stock son obligatorios.");
        }

        // Preparar INSERT
        $sql = "INSERT INTO insumos (nombre, unidad, stock, alerta_stock, activo, fecha_actualizacion) 
                VALUES (:nombre, :unidad, :stock, :alerta_stock, :activo, :fecha_actualizacion)";
        $stmt = $pdo->prepare($sql);

        // Bind
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':unidad', $unidad);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':alerta_stock', $alerta_stock);
        $stmt->bindParam(':activo', $activo);
        $stmt->bindParam(':fecha_actualizacion', $fecha_actualizacion);

        // Ejecutar
        $stmt->execute();
        $lastInsertId = $pdo->lastInsertId();

        if ($lastInsertId > 0) {
            header("Location: ../stock/insumos.php?success=Insumo registrado correctamente.");
            exit;
        } else {
            header("Location: ../stock/insumos.php?error=Error al registrar insumo.");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: ../stock/insumos.php?error=Error de DB: " . $e->getMessage());
        exit;
    } catch (Exception $e) {
        header("Location: ../stock/insumos.php?error=" . $e->getMessage());
        exit;
    }
}
?>
