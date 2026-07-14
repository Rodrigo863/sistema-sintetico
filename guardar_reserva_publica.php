<?php
require 'config.php';

function volverReservaPublica(string $mensaje, bool $error = true, int $reservaId = 0): void
{
    $parametro = $error ? 'error' : 'mensaje';
    $url = 'reservas_publicas.php?' . $parametro . '=' . rawurlencode($mensaje);
    if (!$error && $reservaId > 0) {
        $url .= '&reserva_id=' . $reservaId;
    }
    redirigir($url);
}

function normalizarHoraReservaPublica(string $hora, bool $permite24 = false): ?string
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

function guardarComprobantePublico(): ?string
{
    if (empty($_FILES['comprobante_pago']['name'])) {
        return null;
    }

    if ($_FILES['comprobante_pago']['error'] !== UPLOAD_ERR_OK) {
        volverReservaPublica('No se pudo subir el comprobante. Intenta nuevamente.');
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES['comprobante_pago']['tmp_name']);
    if (!isset($permitidos[$mime])) {
        volverReservaPublica('El comprobante debe ser una imagen JPG, PNG o WEBP.');
    }

    if ((int)$_FILES['comprobante_pago']['size'] > 5 * 1024 * 1024) {
        volverReservaPublica('El comprobante no debe superar 5 MB.');
    }

    $directorio = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprobantes';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true)) {
        volverReservaPublica('No se pudo crear la carpeta de comprobantes.');
    }

    $nombreArchivo = 'reserva_publica_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destino = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

    if (!move_uploaded_file($_FILES['comprobante_pago']['tmp_name'], $destino)) {
        volverReservaPublica('No se pudo guardar el comprobante.');
    }

    return 'uploads/comprobantes/' . $nombreArchivo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('reservas_publicas.php');
}

$nombre = trim($_POST['cliente_nombre'] ?? '');
$telefono = trim($_POST['cliente_telefono'] ?? '');
$canchaId = (int)($_POST['cancha_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';
$montoPago = leerMonto($_POST['monto_pago'] ?? 0);
$metodo = 'transferencia';
$observacion = trim($_POST['observacion'] ?? '');
$tieneComprobante = !empty($_FILES['comprobante_pago']['name']);

if ($nombre === '' || $telefono === '') {
    volverReservaPublica('Completa tu nombre y telefono.');
}

if (!preg_match('/^\d{10}$/', $telefono)) {
    volverReservaPublica('El telefono debe tener exactamente 10 numeros.');
}

if ($canchaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
    volverReservaPublica('Selecciona una cancha y un horario valido.');
}

$horaInicio = normalizarHoraReservaPublica($horaInicio);
$horaFin = normalizarHoraReservaPublica($horaFin, true);
if ($horaInicio === null || $horaFin === null) {
    volverReservaPublica('El horario debe ser en bloques de 30 minutos, por ejemplo 18:30 a 19:30.');
}

if ($fecha < date('Y-m-d')) {
    volverReservaPublica('No se pueden crear reservas en fechas anteriores a hoy.');
}

if ($fecha === date('Y-m-d') && $horaInicio <= date('H:i:s')) {
    volverReservaPublica('No se pueden crear reservas en horarios anteriores a la hora actual.');
}

if ($horaFin <= $horaInicio) {
    volverReservaPublica('La hora de fin debe ser mayor a la hora de inicio.');
}

if ($montoPago > 0 && $montoPago < 20000) {
    volverReservaPublica('El abono minimo es 20.000.');
}

if ($montoPago > 0 && !$tieneComprobante) {
    volverReservaPublica('Adjunta el comprobante para pagar por transferencia.');
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
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_abonos_pendientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      reserva_id INT NOT NULL,
      monto DECIMAL(10,2) NOT NULL,
      metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
      concepto ENUM('sena', 'saldo', 'total', 'extra') NOT NULL DEFAULT 'sena',
      comprobante_path VARCHAR(255) DEFAULT NULL,
      comprobante_confirmado TINYINT(1) NOT NULL DEFAULT 0,
      observacion TEXT DEFAULT NULL,
      estado ENUM('revision', 'pendiente', 'confirmado') NOT NULL DEFAULT 'revision',
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      confirmado_en DATETIME DEFAULT NULL,
      CONSTRAINT fk_abonos_pendientes_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec("ALTER TABLE caja_abonos_pendientes MODIFY estado ENUM('revision', 'pendiente', 'confirmado') NOT NULL DEFAULT 'revision'");
$pdo->exec("ALTER TABLE caja_abonos_pendientes ADD COLUMN IF NOT EXISTS comprobante_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER comprobante_path");

$stmt = $pdo->prepare("SELECT id, precio_hora FROM canchas WHERE id = ? AND estado = 'activa'");
$stmt->execute([$canchaId]);
$cancha = $stmt->fetch();
if (!$cancha) {
    volverReservaPublica('La cancha seleccionada no esta disponible.');
}

$inicio = horaReservaAHoras($horaInicio);
$fin = horaReservaAHoras($horaFin);
$duracion = max(0, $fin - $inicio);
if ($duracion < 1) {
    volverReservaPublica('La duracion minima de alquiler es 1 hora.');
}
$precioTotal = (float)$cancha['precio_hora'] * $duracion;

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
    volverReservaPublica('El rango seleccionado se cruza con una reserva existente.');
}

$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'SELECT id
     FROM clientes
     WHERE telefono = ?
     ORDER BY id DESC
     LIMIT 1'
);
$stmt->execute([$telefono]);
$cliente = $stmt->fetch();

if ($cliente) {
    $clienteId = (int)$cliente['id'];
} else {
    $stmt = $pdo->prepare('INSERT INTO clientes (nombre, telefono) VALUES (?, ?)');
    $stmt->execute([$nombre, $telefono]);
    $clienteId = (int)$pdo->lastInsertId();
}

$stmt = $pdo->prepare("SELECT id FROM caja_jornadas WHERE fecha = CURDATE() AND estado = 'abierta' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$cajaAbierta = (bool)$stmt->fetch();

$abonoDirectoEnCaja = $montoPago > 0 && $cajaAbierta && !$tieneComprobante;
$abonoQuedaPendiente = $montoPago > 0 && !$abonoDirectoEnCaja;
$estado = $abonoDirectoEnCaja ? 'confirmado' : 'reservado';
$stmt = $pdo->prepare(
    'INSERT INTO reservas (cliente_id, cancha_id, fecha, hora_inicio, hora_fin, precio_total, estado, observacion)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$clienteId, $canchaId, $fecha, $horaInicio, $horaFin, $precioTotal, $estado, $observacion ?: null]);
$reservaId = (int)$pdo->lastInsertId();

if ($montoPago > 0) {
    $comprobantePath = guardarComprobantePublico();
    if ($comprobantePath !== null) {
        $metodo = 'transferencia';
    }
    if ($abonoDirectoEnCaja) {
        $stmt = $pdo->prepare('INSERT INTO pagos (reserva_id, monto, metodo, concepto, comprobante_path) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$reservaId, $montoPago, $metodo, 'sena', $comprobantePath]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO caja_abonos_pendientes (reserva_id, monto, metodo, concepto, comprobante_path, observacion)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $observacionPendiente = $comprobantePath !== null
            ? 'Abono cargado desde reserva publica con comprobante pendiente de confirmar'
            : 'Abono cargado desde reserva publica sin caja abierta';
        $stmt->execute([$reservaId, $montoPago, $metodo, 'sena', $comprobantePath, $observacionPendiente]);
    }
}

$pdo->commit();

$mensajeFinal = 'Tu reserva #' . $reservaId . ' fue registrada correctamente.';
if ($abonoQuedaPendiente) {
    $mensajeFinal .= ' El abono quedo pendiente de confirmar en caja.';
}

volverReservaPublica($mensajeFinal, false, $reservaId);
