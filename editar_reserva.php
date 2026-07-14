<?php
require 'config.php';

function volverConErrorReserva(string $mensaje, int $reservaId = 0): void
{
    $detalle = $reservaId > 0 ? '&reserva_detalle=' . $reservaId : '';
    redirigir('index.php?reserva_error=' . urlencode($mensaje) . $detalle . '#reservas');
}

function normalizarHoraReserva(string $hora, bool $permite24 = false): ?string
{
    if (!preg_match('/^(\d{1,2}):(00|30)(?::00)?$/', trim($hora), $coincidencias)) {
        return null;
    }

    $horas = (int)$coincidencias[1];
    $minutos = $coincidencias[2];
    if ($horas === 24 && $minutos === '00' && $permite24) {
        return '24:00:00';
    }

    if ($horas < 0 || $horas > 23) {
        return null;
    }

    return sprintf('%02d:%s:00', $horas, $minutos);
}

function horaReservaAHoras(string $hora): float
{
    [$horas, $minutos] = array_map('intval', explode(':', $hora));
    return $horas + ($minutos / 60);
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
$estado = $_POST['estado'] ?? 'reservado';
if ($reservaId <= 0 || $clienteId <= 0 || $canchaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
    volverConErrorReserva('Completa cliente, cancha, fecha y horario.');
}

$horaInicio = normalizarHoraReserva($horaInicio);
$horaFin = normalizarHoraReserva($horaFin, true);
if ($horaInicio === null || $horaFin === null) {
    volverConErrorReserva('El horario debe ser en bloques de 30 minutos, por ejemplo 18:30 a 19:30.', $reservaId);
}

if ($fecha < date('Y-m-d')) {
    volverConErrorReserva('No se pueden mover reservas a fechas anteriores a hoy.');
}

if ($horaFin <= $horaInicio) {
    volverConErrorReserva('La hora de fin debe ser mayor a la hora de inicio.');
}

if ((horaReservaAHoras($horaFin) - horaReservaAHoras($horaInicio)) < 1) {
    volverConErrorReserva('La duracion minima de alquiler es 1 hora.', $reservaId);
}

if (!in_array($estado, ['reservado', 'confirmado', 'cancelado', 'finalizado'], true)) {
    $estado = 'reservado';
}

$pdo = conectarDB();
$stmt = $pdo->prepare('SELECT precio_hora FROM canchas WHERE id = ?');
$stmt->execute([$canchaId]);
$precioHora = $stmt->fetchColumn();
if ($precioHora === false) {
    volverConErrorReserva('La cancha seleccionada no existe.', $reservaId);
}
$duracion = horaReservaAHoras($horaFin) - horaReservaAHoras($horaInicio);
$precioTotal = (float)$precioHora * $duracion;

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

if ($estado === 'cancelado' && $totalPagado > 0) {
    $pdo->rollBack();
    volverConErrorReserva('No se puede cancelar una reserva que ya tiene pagos registrados.', $reservaId);
}

$stmt = $pdo->prepare(
    'UPDATE reservas
     SET cliente_id = ?, cancha_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, precio_total = ?, estado = ?
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
    $reservaId,
]);

$pdo->commit();

redirigir('index.php?reserva_detalle=' . $reservaId . '#reservas');
