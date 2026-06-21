<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#reservas');
}

function volverPagoReserva(string $mensaje, int $reservaId = 0): void
{
    $detalle = $reservaId > 0 ? '&reserva_detalle=' . $reservaId : '';
    redirigir('index.php?reserva_error=' . rawurlencode($mensaje) . $detalle . '#reservas');
}

$reservaId = (int)($_POST['reserva_id'] ?? 0);
$monto = leerMonto($_POST['monto'] ?? 0);
$metodo = $_POST['metodo'] ?? 'efectivo';
$concepto = $_POST['concepto'] ?? 'saldo';
$actualizarMetodoPago = ($_POST['actualizar_metodo_pago'] ?? '0') === '1';
$pagoId = (int)($_POST['pago_id'] ?? 0);

if ($reservaId <= 0) {
    volverPagoReserva('Selecciona una reserva valida.', $reservaId);
}

if (!in_array($metodo, ['efectivo', 'transferencia', 'tarjeta', 'otro'], true)) {
    $metodo = 'efectivo';
}

if ($actualizarMetodoPago) {
    if ($pagoId <= 0) {
        volverPagoReserva('No se encontro el pago para corregir el metodo.', $reservaId);
    }

    $pdo = conectarDB();
    $stmt = $pdo->prepare('SELECT id FROM pagos WHERE id = ? AND reserva_id = ?');
    $stmt->execute([$pagoId, $reservaId]);

    if (!$stmt->fetch()) {
        volverPagoReserva('No se encontro el pago para corregir el metodo.', $reservaId);
    }

    $stmt = $pdo->prepare('UPDATE pagos SET metodo = ? WHERE id = ? AND reserva_id = ?');
    $stmt->execute([$metodo, $pagoId, $reservaId]);

    redirigir('index.php?reserva_detalle=' . $reservaId . '#reservas');
}

if ($monto <= 0) {
    volverPagoReserva('Ingresa un monto valido.', $reservaId);
}

if (!in_array($concepto, ['sena', 'saldo', 'total', 'extra'], true)) {
    $concepto = 'saldo';
}

$pdo = conectarDB();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_jornadas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      fecha DATE NOT NULL,
      monto_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
      monto_cierre_efectivo DECIMAL(10,2) DEFAULT NULL,
      monto_cierre_transferencia DECIMAL(10,2) DEFAULT NULL,
      estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
      abierta_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      cerrada_en DATETIME DEFAULT NULL,
      observacion_apertura TEXT DEFAULT NULL,
      observacion_cierre TEXT DEFAULT NULL,
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$stmt = $pdo->prepare("SELECT id FROM caja_jornadas WHERE fecha = CURDATE() AND estado = 'abierta' ORDER BY id DESC LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    volverPagoReserva('Debes abrir la caja antes de registrar pagos o abonos.', $reservaId);
}

$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id, estado, precio_total FROM reservas WHERE id = ? FOR UPDATE');
$stmt->execute([$reservaId]);
$reserva = $stmt->fetch();

if (!$reserva) {
    $pdo->rollBack();
    volverPagoReserva('No se encontro la reserva para registrar el pago.', $reservaId);
}

$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(p.monto), 0) AS total_pagado,
        (
            SELECT COALESCE(SUM(v.total), 0)
            FROM ventas v
            WHERE v.reserva_id = ?
              AND v.estado <> 'anulada'
        ) AS total_consumo
     FROM pagos p
     WHERE p.reserva_id = ?"
);
$stmt->execute([$reservaId, $reservaId]);
$totales = $stmt->fetch();

$totalReserva = (float)$reserva['precio_total'] + (float)$totales['total_consumo'];
$totalPagadoAntes = (float)$totales['total_pagado'];
$saldoAntes = max(0, $totalReserva - $totalPagadoAntes);

if ($saldoAntes <= 0) {
    $pdo->rollBack();
    volverPagoReserva('Esta reserva ya esta pagada completamente. No se puede registrar otro pago.', $reservaId);
}

if ($monto > $saldoAntes) {
    $pdo->rollBack();
    volverPagoReserva('El monto supera el saldo pendiente de la reserva.', $reservaId);
}

$stmt = $pdo->prepare('INSERT INTO pagos (reserva_id, monto, metodo, concepto) VALUES (?, ?, ?, ?)');
$stmt->execute([$reservaId, $monto, $metodo, $concepto]);
$pagoId = (int)$pdo->lastInsertId();

$totalPagado = $totalPagadoAntes + $monto;

if ($reserva['estado'] !== 'cancelado' && $totalReserva > 0 && $totalPagado >= $totalReserva) {
    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'finalizado' WHERE id = ?");
    $stmt->execute([$reservaId]);
}

$pdo->commit();

$_SESSION['pago_ticket_id'] = $pagoId;
redirigir('index.php#reservas');
