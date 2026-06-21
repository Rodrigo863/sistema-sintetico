<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#caja');
}

function volverCaja(string $mensaje, bool $error = true): void
{
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = date('Y-m-d');
    }

    $clave = $error ? 'caja_error' : 'caja_mensaje';
    redirigir('index.php?caja_fecha=' . rawurlencode($fecha) . '&' . $clave . '=' . rawurlencode($mensaje) . '#caja');
}

$fecha = $_POST['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    volverCaja('Fecha de caja invalida.');
}

$accion = $_POST['accion'] ?? '';
$observacion = trim($_POST['observacion'] ?? '');

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
$cajaIndiceUnico = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'caja_jornadas'
       AND INDEX_NAME = 'uq_caja_jornadas_fecha'"
)->fetchColumn();
if ($cajaIndiceUnico > 0) {
    $pdo->exec("ALTER TABLE caja_jornadas DROP INDEX uq_caja_jornadas_fecha");
}
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_movimientos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      caja_jornada_id INT NOT NULL,
      tipo ENUM('ingreso', 'egreso') NOT NULL,
      concepto VARCHAR(120) NOT NULL,
      detalle TEXT DEFAULT NULL,
      metodo ENUM('efectivo', 'transferencia', 'tarjeta', 'otro') NOT NULL DEFAULT 'efectivo',
      monto DECIMAL(10,2) NOT NULL,
      fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_caja_movimientos_jornada FOREIGN KEY (caja_jornada_id) REFERENCES caja_jornadas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_abonos_pendientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      reserva_id INT NOT NULL,
      monto DECIMAL(10,2) NOT NULL,
      metodo ENUM('efectivo', 'transferencia', 'tarjeta', 'otro') NOT NULL DEFAULT 'transferencia',
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

$pdo->beginTransaction();

if ($accion === 'abrir') {
    $montoInicial = leerMonto($_POST['monto_inicial'] ?? 0);
    if ($montoInicial < 0) {
        $pdo->rollBack();
        volverCaja('El monto inicial no puede ser negativo.');
    }

    $stmt = $pdo->prepare("SELECT id FROM caja_jornadas WHERE fecha = ? AND estado = 'abierta' FOR UPDATE");
    $stmt->execute([$fecha]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        volverCaja('Ya hay una caja abierta para esta fecha. Primero debes cerrarla.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO caja_jornadas (fecha, monto_inicial, observacion_apertura)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$fecha, $montoInicial, $observacion ?: null]);
    $pdo->commit();

    volverCaja('Caja abierta correctamente.', false);
}

if ($accion === 'cerrar') {
    if (trim((string)($_POST['monto_cierre_efectivo'] ?? '')) === '') {
        $pdo->rollBack();
        volverCaja('Ingrese efectivo contado.');
    }

    $montoEfectivo = leerMonto($_POST['monto_cierre_efectivo'] ?? 0);
    $montoTransferencia = leerMonto($_POST['monto_cierre_transferencia'] ?? 0);
    if ($montoEfectivo < 0 || $montoTransferencia < 0) {
        $pdo->rollBack();
        volverCaja('Los montos de cierre no pueden ser negativos.');
    }

    $stmt = $pdo->prepare(
        "SELECT id, estado
         FROM caja_jornadas
         WHERE fecha = ? AND estado = 'abierta'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$fecha]);
    $jornada = $stmt->fetch();

    if (!$jornada) {
        $pdo->rollBack();
        volverCaja('Primero debes abrir la caja de esta fecha.');
    }

    $stmt = $pdo->prepare(
        "UPDATE caja_jornadas
         SET estado = 'cerrada',
             monto_cierre_efectivo = ?,
             monto_cierre_transferencia = ?,
             observacion_cierre = ?,
             cerrada_en = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$montoEfectivo, $montoTransferencia, $observacion ?: null, (int)$jornada['id']]);
    $pdo->commit();

    volverCaja('Caja cerrada correctamente.', false);
}

if ($accion === 'movimiento') {
    $tipoMovimiento = $_POST['tipo_movimiento'] ?? 'egreso';
    $metodo = $_POST['metodo'] ?? 'efectivo';
    $concepto = trim($_POST['concepto'] ?? '');
    $monto = leerMonto($_POST['monto'] ?? 0);

    if (!in_array($tipoMovimiento, ['ingreso', 'egreso'], true)) {
        $tipoMovimiento = 'egreso';
    }

    if (!in_array($metodo, ['efectivo', 'transferencia', 'tarjeta', 'otro'], true)) {
        $metodo = 'efectivo';
    }

    if ($concepto === '') {
        $pdo->rollBack();
        volverCaja('Ingresa un concepto para el movimiento.');
    }

    if ($monto <= 0) {
        $pdo->rollBack();
        volverCaja('Ingresa un monto valido para el movimiento.');
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM caja_jornadas
         WHERE fecha = ? AND estado = 'abierta'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$fecha]);
    $jornada = $stmt->fetch();

    if (!$jornada) {
        $pdo->rollBack();
        volverCaja('Primero debes abrir la caja para registrar movimientos.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO caja_movimientos (caja_jornada_id, tipo, concepto, detalle, metodo, monto)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int)$jornada['id'], $tipoMovimiento, $concepto, $observacion ?: null, $metodo, $monto]);
    $pdo->commit();

    volverCaja('Movimiento de caja registrado correctamente.', false);
}

if ($accion === 'confirmar_abono') {
    $abonoPendienteId = (int)($_POST['abono_pendiente_id'] ?? 0);
    if ($abonoPendienteId <= 0) {
        $pdo->rollBack();
        volverCaja('No se encontro el abono pendiente.');
    }

    $stmt = $pdo->prepare("SELECT id FROM caja_jornadas WHERE fecha = CURDATE() AND estado = 'abierta' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        volverCaja('Debes abrir la caja antes de ingresar abonos pendientes.');
    }

    $stmt = $pdo->prepare("SELECT * FROM caja_abonos_pendientes WHERE id = ? AND estado = 'pendiente' FOR UPDATE");
    $stmt->execute([$abonoPendienteId]);
    $abono = $stmt->fetch();
    if (!$abono) {
        $pdo->rollBack();
        volverCaja('No se encontro el abono pendiente o ya fue confirmado.');
    }

    if (!empty($abono['comprobante_path']) && (int)($abono['comprobante_confirmado'] ?? 0) !== 1) {
        $pdo->rollBack();
        volverCaja('Primero debes confirmar el comprobante antes de ingresar el abono a caja.');
    }

    $stmt = $pdo->prepare('INSERT INTO pagos (reserva_id, monto, metodo, concepto, comprobante_path) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        (int)$abono['reserva_id'],
        (float)$abono['monto'],
        $abono['metodo'],
        $abono['concepto'],
        $abono['comprobante_path'],
    ]);

    $stmt = $pdo->prepare(
        "UPDATE caja_abonos_pendientes
         SET estado = 'confirmado', confirmado_en = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$abonoPendienteId]);

    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'confirmado' WHERE id = ? AND estado <> 'cancelado'");
    $stmt->execute([(int)$abono['reserva_id']]);

    $pdo->commit();
    volverCaja('Abono ingresado a caja correctamente.', false);
}

if ($accion === 'confirmar_comprobante_abono') {
    $abonoPendienteId = (int)($_POST['abono_pendiente_id'] ?? 0);
    $reservaId = (int)($_POST['reserva_id'] ?? 0);
    $volverDetalle = fn(string $mensaje) => redirigir(
        'index.php' . (
            $reservaId > 0
                ? '?reserva_detalle=' . $reservaId . '&caja_mensaje=' . rawurlencode($mensaje) . '#reservas'
                : '?caja_mensaje=' . rawurlencode($mensaje) . '#caja'
        )
    );

    if ($abonoPendienteId <= 0) {
        $pdo->commit();
        $volverDetalle('El comprobante ya fue confirmado.');
    }

    $stmt = $pdo->prepare("SELECT id, estado FROM caja_abonos_pendientes WHERE id = ? FOR UPDATE");
    $stmt->execute([$abonoPendienteId]);
    $abono = $stmt->fetch();
    if (!$abono) {
        $pdo->commit();
        $volverDetalle('El comprobante ya fue confirmado.');
    }

    if ($abono['estado'] !== 'revision') {
        $pdo->commit();
        $volverDetalle('El comprobante ya fue confirmado.');
    }

    $stmt = $pdo->prepare("UPDATE caja_abonos_pendientes SET estado = 'pendiente', comprobante_confirmado = 1 WHERE id = ?");
    $stmt->execute([$abonoPendienteId]);
    $pdo->commit();

    $volverDetalle('Comprobante confirmado. El abono quedo pendiente en caja.');
}

$pdo->rollBack();
volverCaja('Accion de caja no valida.');
