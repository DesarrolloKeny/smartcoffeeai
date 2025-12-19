<?php
include_once('../controlador/conexion.php');  // Ajusta ruta si es '../controlador/conexion.php'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // Validaciones y sanitización
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = (float)$_POST['precio'] ?? 0;
        $categoria = trim($_POST['categoria'] ?? '');
        $stock = (int)$_POST['stock'] ?? 0;
        $destacado = isset($_POST['destacado']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_creacion = date('Y-m-d H:i:s');

        if (empty($nombre) || $precio <= 0 || $stock < 0) {
            throw new Exception("Nombre, precio y stock son obligatorios.");
        }

        // Preparar INSERT
        $sql = "INSERT INTO productos (nombre, descripcion, precio, categoria, stock, destacado, activo, fecha_creacion) 
                VALUES (:nombre, :descripcion, :precio, :categoria, :stock, :destacado, :activo, :fecha_creacion)";
        $stmt = $pdo->prepare($sql);

        // Bind
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':precio', $precio);
        $stmt->bindParam(':categoria', $categoria);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':destacado', $destacado);
        $stmt->bindParam(':activo', $activo);
        $stmt->bindParam(':fecha_creacion', $fecha_creacion);

        // Ejecutar
        $stmt->execute();
        $lastInsertId = $pdo->lastInsertId();

        if ($lastInsertId > 0) {
            header("Location: ../productos/GestionProducto.php?success=Producto registrado correctamente.");
            exit;
        } else {
            header("Location: ../productos/GestionProducto.php?error=Error al registrar producto.");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: ../productos/GestionProducto.php?error=Error de DB: " . $e->getMessage());
        exit;
    } catch (Exception $e) {
        header("Location: ../productos/GestionProducto.php?error=" . $e->getMessage());
        exit;
    }
}
?>
