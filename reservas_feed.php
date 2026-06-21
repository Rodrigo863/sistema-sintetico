<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = conectarDB();
    $reservas = $pdo->query(
        "SELECT r.*, c.nombre AS cliente, c.telefono, ca.nombre AS cancha, ca.precio_hora,
                COALESCE(pagos.total_pagado, 0) AS pagado,
                COALESCE(pagos_metodo.pagado_efectivo, 0) AS pagado_efectivo,
                COALESCE(pagos_metodo.pagado_transferencia, 0) AS pagado_transferencia,
                COALESCE(consumos.total_consumo, 0) AS consumo_total,
                r.precio_total + COALESCE(consumos.total_consumo, 0) AS total_alcanzado,
                GREATEST((r.precio_total + COALESCE(consumos.total_consumo, 0)) - COALESCE(pagos.total_pagado, 0), 0) AS saldo,
                ultimo_pago.id AS ultimo_pago_id,
                ultimo_pago.metodo AS ultimo_pago_metodo,
                comprobante_pago.comprobante_path AS comprobante_path,
                abono_pendiente.id AS abono_pendiente_id,
                abono_pendiente.monto AS abono_pendiente_monto,
                abono_pendiente.comprobante_path AS abono_pendiente_comprobante_path,
                abono_pendiente.comprobante_confirmado AS abono_pendiente_comprobante_confirmado,
                abono_pendiente.estado AS abono_pendiente_estado
         FROM reservas r
         INNER JOIN clientes c ON c.id = r.cliente_id
         INNER JOIN canchas ca ON ca.id = r.cancha_id
         LEFT JOIN (
           SELECT reserva_id, SUM(monto) AS total_pagado
           FROM pagos
           GROUP BY reserva_id
         ) pagos ON pagos.reserva_id = r.id
         LEFT JOIN (
           SELECT reserva_id,
                  SUM(CASE WHEN metodo = 'efectivo' THEN monto ELSE 0 END) AS pagado_efectivo,
                  SUM(CASE WHEN metodo = 'transferencia' THEN monto ELSE 0 END) AS pagado_transferencia
           FROM pagos
           GROUP BY reserva_id
         ) pagos_metodo ON pagos_metodo.reserva_id = r.id
         LEFT JOIN (
           SELECT reserva_id, SUM(total) AS total_consumo
           FROM ventas
           WHERE reserva_id IS NOT NULL AND estado <> 'anulada'
           GROUP BY reserva_id
         ) consumos ON consumos.reserva_id = r.id
         LEFT JOIN pagos ultimo_pago ON ultimo_pago.id = (
           SELECT p2.id
           FROM pagos p2
           WHERE p2.reserva_id = r.id
           ORDER BY p2.fecha_pago DESC, p2.id DESC
           LIMIT 1
         )
         LEFT JOIN pagos comprobante_pago ON comprobante_pago.id = (
           SELECT p3.id
           FROM pagos p3
           WHERE p3.reserva_id = r.id
             AND p3.comprobante_path IS NOT NULL
             AND p3.comprobante_path <> ''
           ORDER BY p3.fecha_pago DESC, p3.id DESC
           LIMIT 1
         )
         LEFT JOIN caja_abonos_pendientes abono_pendiente ON abono_pendiente.id = (
           SELECT ap.id
           FROM caja_abonos_pendientes ap
           WHERE ap.reserva_id = r.id
             AND ap.estado IN ('revision', 'pendiente')
           ORDER BY ap.creado_en DESC, ap.id DESC
           LIMIT 1
         )
         WHERE r.fecha >= CURDATE()
         ORDER BY r.fecha ASC, r.hora_inicio ASC"
    )->fetchAll();

    echo json_encode([
        'ok' => true,
        'reservas' => $reservas,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudieron actualizar las reservas.',
    ]);
}
