<?php
require 'config.php';

function volverConErrorReserva(string $mensaje): void
{
    redirigir('index.php?reserva_error=' . urlencode($mensaje) . '#reservas');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#reservas');
}

$reservaId = (int)($_POST['reserva_id'] ?? 0);
$clienteId = (int)($_POST['cliente_id'] ?? 0);
$canchaId = (int)($_POST['cancha_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';
$precioTotal = leerMonto($_POST['precio_total'] ?? 0);
$estado = $_POST['estado'] ?? 'reservado';
$observacion = trim($_POST['observacion'] ?? '');

if ($reservaId <= 0 || $clienteId <= 0 || $canchaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
    volverConErrorReserva('Completa cliente, cancha, fecha y horario.');
}

if ($fecha < date('Y-m-d')) {
    volverConErrorReserva('No se pueden mover reservas a fechas anteriores a hoy.');
}

if ($horaFin <= $horaInicio) {
    volverConErrorReserva('La hora de fin debe ser mayor a la hora de inicio.');
}

if (!in_array($estado, ['reservado', 'confirmado', 'cancelado', 'finalizado'], true)) {
    $estado = 'reservado';
}

$pdo = conectarDB();
$pdo->beginTransaction();
$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM reservas
     WHERE id <> ?
       AND cancha_id = ?
       AND fecha = ?
       AND estado <> 'cancelado'
       AND hora_inicio < ?
       AND hora_fin > ?"
);
$stmt->execute([$reservaId, $canchaId, $fecha, $horaFin, $horaInicio]);

if ((int)$stmt->fetchColumn() > 0) {
    $pdo->rollBack();
    volverConErrorReserva('Ya existe otra reserva para esa cancha en ese horario.');
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
$totalReserva = $precioTotal + (float)$totales['total_consumo'];
$totalPagado = (float)$totales['total_pagado'];

if ($estado === 'finalizado' && $totalReserva > 0 && $totalPagado < $totalReserva) {
    $estado = $totalPagado > 0 ? 'confirmado' : 'reservado';
}

$stmt = $pdo->prepare(
    'UPDATE reservas
     SET cliente_id = ?, cancha_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, precio_total = ?, estado = ?, observacion = ?
     WHERE id = ?'
);
$stmt->execute([
    $clienteId,
    $canchaId,
    $fecha,
    $horaInicio,
    $horaFin,
    $precioTotal,
    $estado,
    $observacion ?: null,
    $reservaId,
]);

$pdo->commit();

redirigir('index.php?reserva_detalle=' . $reservaId . '#reservas');
