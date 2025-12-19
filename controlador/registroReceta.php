<?php
     include_once('../controlador/conexion.php');  // Ajusta ruta si es necesario

     if (isset($_POST['gt'])) {
         
         try {
             // Validaciones y sanitización
             $nombreR = trim($_POST['nombreR'] ?? '');
             $producto = trim($_POST['id_producto'] ?? '');  // Corregido: era $_POST['fecha']
             $bebestible = trim($_POST['bebestible'] ?? '');
             $capacidad_ml = trim($_POST['capacidad_ml'] ?? '');
             $RecetaActivo = trim($_POST['activo'] ?? '');

             
               // Preparar INSERT
               $sql = "INSERT INTO recetas (nombre, id_producto, bebestible, capacidad_ml, activo)
               VALUES (:nombre, :id_producto, :bebestible, :capacidad_ml, :activo)";
   
       
             $stmt = $pdo->prepare($sql);  // Cambiado de $conn a $pdo

             // Bind
             $stmt->bindParam(':nombre', $nombreR);
             $stmt->bindParam(':id_producto', $producto);
             $stmt->bindParam(':bebestible', $bebestible);
             $stmt->bindParam(':capacidad_ml', $capacidad_ml);
             $stmt->bindParam(':activo', $RecetaActivo);


             // Ejecutar
             $stmt->execute();
             $lastInsertId = $pdo->lastInsertId();  // Cambiado de $conn a $pdo

             if ($lastInsertId > 0) {
                 header("Location: ../recetas/GestionResetas.php");
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
     