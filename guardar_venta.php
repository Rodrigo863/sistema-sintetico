<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#ventas');
}

function postPrimero(string $clave, mixed $default = ''): mixed
{
    $valor = $_POST[$clave] ?? $default;
    return is_array($valor) ? ($valor[0] ?? $default) : $valor;
}

function volverVenta(string $mensaje, ?int $cantidadSugerida = null): void
{
    $origen = $_POST['origen'] ?? 'ventas';
    $hash = $origen === 'reserva' ? '#reservas' : '#ventas';
    $params = [
        'venta_error' => $mensaje,
        'venta_producto_id' => postPrimero('producto_id'),
        'venta_tipo' => postPrimero('tipo_venta', 'unidad'),
        'venta_cantidad' => $cantidadSugerida ?? postPrimero('cantidad', '1'),
        'venta_metodo' => $_POST['metodo'] ?? 'efectivo',
        'venta_observacion' => $_POST['observacion'] ?? '',
    ];

    redirigir('index.php?' . http_build_query($params) . $hash);
}

$productoIds = $_POST['producto_id'] ?? [];
$tiposVenta = $_POST['tipo_venta'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];

if (!is_array($productoIds)) {
    $productoIds = [$productoIds];
}
if (!is_array($tiposVenta)) {
    $tiposVenta = [$tiposVenta];
}
if (!is_array($cantidades)) {
    $cantidades = [$cantidades];
}

$reservaId = (int)($_POST['reserva_id'] ?? 0);
$clienteId = (int)($_POST['cliente_id'] ?? 0);
$origen = $_POST['origen'] ?? 'ventas';
$metodo = $_POST['metodo'] ?? 'efectivo';
$observacion = trim($_POST['observacion'] ?? '');

if (!in_array($metodo, ['efectivo', 'transferencia'], true)) {
    $metodo = 'efectivo';
}

$lineas = [];
foreach ($productoIds as $index => $productoIdRaw) {
    $productoId = (int)$productoIdRaw;
    $cantidad = (int)($cantidades[$index] ?? 0);
    $tipoVenta = $tiposVenta[$index] ?? 'unidad';

    if ($productoId <= 0 && $cantidad <= 0) {
        continue;
    }

    if ($productoId <= 0 || $cantidad <= 0) {
        volverVenta('Selecciona un producto e ingresa una cantidad valida.');
    }

    if (!in_array($tipoVenta, ['unidad', 'pack'], true)) {
        $tipoVenta = 'unidad';
    }

    $lineas[] = [
        'producto_id' => $productoId,
        'tipo_venta' => $tipoVenta,
        'cantidad' => $cantidad,
    ];
}

if (empty($lineas)) {
    volverVenta('Agrega al menos un producto a la venta.');
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
    volverVenta('Debes abrir la caja antes de registrar ventas o consumos.');
}

$pdo->exec("ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario");

$pdo->beginTransaction();

if ($reservaId > 0) {
    $stmt = $pdo->prepare('SELECT id, cliente_id, estado FROM reservas WHERE id = ?');
    $stmt->execute([$reservaId]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        $pdo->rollBack();
        volverVenta('No se encontro la reserva para vincular el consumo.');
    }

    if ($clienteId <= 0) {
        $clienteId = (int)$reserva['cliente_id'];
    }
}

if ($clienteId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ?');
    $stmt->execute([$clienteId]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        volverVenta('Cliente no encontrado para registrar la venta.');
    }
}

$productosPorId = [];
$stmtProducto = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND estado = ? FOR UPDATE');
foreach (array_unique(array_column($lineas, 'producto_id')) as $productoId) {
    $stmtProducto->execute([$productoId, 'activo']);
    $producto = $stmtProducto->fetch();

    if (!$producto) {
        $pdo->rollBack();
        volverVenta('Producto no encontrado o inactivo.');
    }

    $productosPorId[$productoId] = $producto;
}

$usoStock = [];
$detalles = [];
$total = 0;

foreach ($lineas as $linea) {
    $producto = $productosPorId[$linea['producto_id']];
    $cantidad = $linea['cantidad'];
    $tipoVenta = $linea['tipo_venta'];
    $unidadesDescontadas = $cantidad;
    $precioUnitario = (float)$producto['precio_venta'];
    $costoUnitario = (float)$producto['precio_compra'];

    if ($tipoVenta === 'pack') {
        if ((int)$producto['pack_cantidad'] <= 0 || (float)$producto['precio_pack'] <= 0) {
            $pdo->rollBack();
            volverVenta('Este producto no tiene venta por pack configurada.');
        }

        $unidadesDescontadas = $cantidad * (int)$producto['pack_cantidad'];
        $precioUnitario = (float)$producto['precio_pack'];
    }

    $productoId = (int)$producto['id'];
    $usoStock[$productoId] = ($usoStock[$productoId] ?? 0) + $unidadesDescontadas;
    $subtotal = $precioUnitario * $cantidad;
    $total += $subtotal;

    $detalles[] = [
        'producto_id' => $productoId,
        'tipo_venta' => $tipoVenta,
        'cantidad' => $cantidad,
        'unidades_descontadas' => $unidadesDescontadas,
        'precio_unitario' => $precioUnitario,
        'costo_unitario' => $costoUnitario,
        'subtotal' => $subtotal,
    ];
}

foreach ($usoStock as $productoId => $unidadesNecesarias) {
    $disponible = (int)$productosPorId[$productoId]['stock'];
    if ($disponible < $unidadesNecesarias) {
        $pdo->rollBack();
        $faltan = $unidadesNecesarias - $disponible;
        volverVenta("Stock insuficiente. {$productosPorId[$productoId]['nombre']} tiene {$disponible} unidad(es) disponibles y faltan {$faltan} unidad(es).", $disponible);
    }
}

$stmt = $pdo->prepare('INSERT INTO ventas (reserva_id, cliente_id, total, metodo, observacion) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([
    $reservaId > 0 ? $reservaId : null,
    $clienteId > 0 ? $clienteId : null,
    $total,
    $metodo,
    $observacion ?: null,
]);
$ventaId = (int)$pdo->lastInsertId();

$stmtDetalle = $pdo->prepare(
    'INSERT INTO venta_detalles (venta_id, producto_id, tipo_venta, cantidad, unidades_descontadas, precio_unitario, costo_unitario, subtotal)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($detalles as $detalle) {
    $stmtDetalle->execute([
        $ventaId,
        $detalle['producto_id'],
        $detalle['tipo_venta'],
        $detalle['cantidad'],
        $detalle['unidades_descontadas'],
        $detalle['precio_unitario'],
        $detalle['costo_unitario'],
        $detalle['subtotal'],
    ]);
}

$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?');
foreach ($usoStock as $productoId => $unidadesDescontadas) {
    $stmtStock->execute([$unidadesDescontadas, $productoId]);
}

if ($reservaId > 0 && ($reserva['estado'] ?? '') === 'finalizado') {
    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'confirmado' WHERE id = ? AND estado = 'finalizado'");
    $stmt->execute([$reservaId]);
}

$pdo->commit();

redirigir($origen === 'reserva' && $reservaId > 0 ? 'index.php?reserva_detalle=' . $reservaId . '#reservas' : 'index.php#ventas');
