<?php
require 'config.php';

function volverConErrorReserva(string $mensaje): void
{
    redirigir('index.php?reserva_error=' . urlencode($mensaje) . '#reservas');
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

function guardarComprobanteReserva(): ?string
{
    if (empty($_FILES['comprobante_pago']['name'])) {
        return null;
    }

    if ($_FILES['comprobante_pago']['error'] !== UPLOAD_ERR_OK) {
        volverConErrorReserva('No se pudo subir el comprobante. Intenta nuevamente.');
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES['comprobante_pago']['tmp_name']);
    if (!isset($permitidos[$mime])) {
        volverConErrorReserva('El comprobante debe ser una imagen JPG, PNG o WEBP.');
    }

    if ((int)$_FILES['comprobante_pago']['size'] > 5 * 1024 * 1024) {
        volverConErrorReserva('El comprobante no debe superar 5 MB.');
    }

    $directorio = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprobantes';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true)) {
        volverConErrorReserva('No se pudo crear la carpeta de comprobantes.');
    }

    $nombreArchivo = 'reserva_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destino = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

    if (!move_uploaded_file($_FILES['comprobante_pago']['tmp_name'], $destino)) {
        volverConErrorReserva('No se pudo guardar el comprobante.');
    }

    return 'uploads/comprobantes/' . $nombreArchivo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#reservas');
}

$clienteId = (int)($_POST['cliente_id'] ?? 0);
$canchaId = (int)($_POST['cancha_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';
$precioTotal = leerMonto($_POST['precio_total'] ?? 0);
$montoPago = leerMonto($_POST['monto_pago'] ?? 0);
$estado = $_POST['estado'] ?? 'reservado';
$metodo = $_POST['metodo'] ?? 'efectivo';
$observacion = trim($_POST['observacion'] ?? '');

if ($clienteId <= 0 || $canchaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
    volverConErrorReserva('Completa cliente, cancha, fecha y horario.');
}

$horaInicio = normalizarHoraReserva($horaInicio);
$horaFin = normalizarHoraReserva($horaFin, true);
if ($horaInicio === null || $horaFin === null) {
    volverConErrorReserva('El horario debe ser en bloques de 30 minutos, por ejemplo 18:30 a 19:30.');
}

if ($fecha < date('Y-m-d')) {
    volverConErrorReserva('No se pueden crear reservas en fechas anteriores a hoy.');
}

if ($fecha === date('Y-m-d') && $horaInicio <= date('H:i:s')) {
    volverConErrorReserva('No se pueden crear reservas en horarios anteriores a la hora actual.');
}

if ($horaFin <= $horaInicio) {
    volverConErrorReserva('La hora de fin debe ser mayor a la hora de inicio.');
}

if ((horaReservaAHoras($horaFin) - horaReservaAHoras($horaInicio)) < 1) {
    volverConErrorReserva('La duracion minima de alquiler es 1 hora.');
}

if ($montoPago > 0 && $montoPago < 20000) {
    volverConErrorReserva('El abono minimo es 20.000.');
}

if (!in_array($metodo, ['efectivo', 'transferencia'], true)) {
    $metodo = 'efectivo';
}

if (!in_array($estado, ['reservado', 'confirmado'], true)) {
    $estado = 'reservado';
}

$pdo = conectarDB();
$pdo->exec("ALTER TABLE pagos ADD COLUMN IF NOT EXISTS comprobante_path VARCHAR(255) DEFAULT NULL AFTER observacion");
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
if ($montoPago > 0) {
    $stmt = $pdo->prepare("SELECT id FROM caja_jornadas WHERE fecha = CURDATE() AND estado = 'abierta' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        volverConErrorReserva('Debes abrir la caja antes de registrar abonos.');
    }
}
$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM reservas
     WHERE cancha_id = ?
       AND fecha = ?
       AND estado <> 'cancelado'
       AND hora_inicio < ?
       AND hora_fin > ?"
);
$stmt->execute([$canchaId, $fecha, $horaFin, $horaInicio]);

if ((int)$stmt->fetchColumn() > 0) {
    volverConErrorReserva('Ya existe una reserva para esa cancha en ese horario.');
}

$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'INSERT INTO reservas (cliente_id, cancha_id, fecha, hora_inicio, hora_fin, precio_total, estado, observacion)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$estado = $montoPago > 0 ? 'confirmado' : $estado;
$stmt->execute([$clienteId, $canchaId, $fecha, $horaInicio, $horaFin, $precioTotal, $estado, $observacion ?: null]);
$reservaId = (int)$pdo->lastInsertId();

if ($montoPago > 0) {
    $comprobantePath = guardarComprobanteReserva();
    $stmt = $pdo->prepare('INSERT INTO pagos (reserva_id, monto, metodo, concepto, comprobante_path) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$reservaId, $montoPago, $metodo, 'sena', $comprobantePath]);
}

$pdo->commit();

redirigir('index.php#reservas');
