<?php
// CRÍTICO: Todas las funciones ahora usan PDO ($pdo) y son consistentes con caja.php

function obtenerCajaAbierta($pdo) {
    // Busca la caja abierta.
    $stmt = $pdo->query("SELECT id_caja, fecha, apertura FROM caja WHERE estado = 'abierta' ORDER BY fecha DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerMovimientos($pdo, $id_caja) {
    $stmt = $pdo->prepare("SELECT * FROM movimientos_caja WHERE id_caja = ?");
    $stmt->execute([$id_caja]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerVentasPorFecha($pdo, $fecha) {
    $sql = "SELECT metodo_pago, SUM(total) AS total FROM ventas WHERE DATE(fecha) = ? GROUP BY metodo_pago";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calcularTotales($ventas) {
    $resultados = [
        "efectivo" => 0,
        "debito" => 0,
        "credito" => 0,
        "transferencia" => 0,
        "otros" => 0,
        "total_vendido" => 0
    ];

    foreach ($ventas as $v) {
        $metodo = strtolower($v['metodo_pago']);
        $monto = floatval($v['total']);

        if (isset($resultados[$metodo])) {
            $resultados[$metodo] += $monto;
        } else {
            $resultados["otros"] += $monto;
        }

        $resultados["total_vendido"] += $monto;
    }

    return $resultados;
}

/**
 * Guarda el cierre de caja en la base de datos.
 * CRÍTICO: Se eliminó 'efectivo_final' y se usa 'monto_contado' y 'total_esperado'.
 */
 // En caja/funciones_caja.php, la función guardarCierreCaja debe verse así:

function guardarCierreCaja($pdo, $data) {
    // CRÍTICO: Se eliminó 'efectivo_final' y se usa 'monto_contado' y 'total_esperado'.
    $sql = "UPDATE caja SET 
                cierre=:cierre, 
                diferencia=:diferencia, 
                monto_contado=:monto_contado, 
                total_esperado=:total_esperado, 
                estado='cerrada',
                id_usuario_cierre=:id_usuario_cierre, 
                fecha_cierre=NOW()
            WHERE id_caja=:id_caja";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':cierre' => $data["cierre"],
        ':diferencia' => $data["diferencia"],
        ':monto_contado' => $data["monto_contado"],
        ':total_esperado' => $data["total_esperado"],
        ':id_usuario_cierre' => $data["id_usuario"],
        ':id_caja' => $data["id_caja"]
    ];

    return $stmt->execute($params);
}
// ... (y el resto de funciones deben usar $pdo, como se mostró en la respuesta anterior)
?>