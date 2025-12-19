<?php
     include_once('../controlador/conexion.php');  // Ajusta ruta si es necesario

     if (isset($_POST['gt'])) {
         
         try {
             // Validaciones y sanitización
             $nombre = trim($_POST['nombre'] ?? '');
             $telefono = trim($_POST['telefono'] ?? '');  // Corregido: era $_POST['fecha']
             $rut = trim($_POST['rut'] ?? '');
             $correo = trim($_POST['correo'] ?? '');
             $direccion = trim($_POST['direccion'] ?? '');

             if (empty($nombre) || empty($rut)) {
                 throw new Exception("Nombre y RUT son obligatorios.");
             }
             if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                 throw new Exception("Correo no válido.");
             }

             // Preparar INSERT
             $sql = "INSERT INTO clientes (nombre, telefono, rut, correo, direccion) 
                     VALUES (:nombre, :telefono, :rut, :correo, :direccion)";
             $stmt = $pdo->prepare($sql);  // Cambiado de $conn a $pdo

             // Bind
             $stmt->bindParam(':nombre', $nombre);
             $stmt->bindParam(':telefono', $telefono);
             $stmt->bindParam(':rut', $rut);
             $stmt->bindParam(':correo', $correo);
             $stmt->bindParam(':direccion', $direccion);

             // Ejecutar
             $stmt->execute();
             $lastInsertId = $pdo->lastInsertId();  // Cambiado de $conn a $pdo

             if ($lastInsertId > 0) {
                 header("Location: ../cliente/GestionCliente.php");
                 exit;
             } else {
                 echo "Error: No se pudo insertar.";
             }

         } catch (PDOException $e) {
             echo "Error de DB: " . $e->getMessage();
         } catch (Exception $e) {
             echo "Error: " . $e->getMessage();
         }
     }
     ?>
     