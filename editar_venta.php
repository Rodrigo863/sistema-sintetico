<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#ventas');
}

function postPrimeroEditarVenta(string $clave, mixed $default = ''): mixed
{
    $valor = $_POST[$clave] ?? $default;
    return is_array($valor) ? ($valor[0] ?? $default) : $valor;
}

function volverEditarVenta(string $mensaje, ?int $cantidadSugerida = null): void
{
    $params = [
        'venta_error' => $mensaje,
        'venta_producto_id' => postPrimeroEditarVenta('producto_id'),
        'venta_tipo' => postPrimeroEditarVenta('tipo_venta', 'unidad'),
        'venta_cantidad' => $cantidadSugerida ?? postPrimeroEditarVenta('cantidad', '1'),
        'venta_metodo' => $_POST['metodo'] ?? 'efectivo',
        'venta_observacion' => $_POST['observacion'] ?? '',
    ];

    redirigir('index.php?' . http_build_query($params) . '#ventas');
}

$ventaId = (int)($_POST['venta_id'] ?? 0);
if ($ventaId <= 0) {
    volverEditarVenta('No se encontro la venta para editar.');
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

$clienteId = (int)($_POST['cliente_id'] ?? 0);
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
        volverEditarVenta('Selecciona un producto e ingresa una cantidad valida.');
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
    volverEditarVenta('Agrega al menos un producto a la venta.');
}

$pdo = conectarDB();
$pdo->exec("ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa' AFTER metodo");
$pdo->exec("ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario");
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT * FROM ventas WHERE id = ? FOR UPDATE');
$stmt->execute([$ventaId]);
$venta = $stmt->fetch();

if (!$venta) {
    $pdo->rollBack();
    volverEditarVenta('No se encontro la venta para editar.');
}

if ($venta['estado'] === 'anulada') {
    $pdo->rollBack();
    volverEditarVenta('Esta venta esta anulada y no se puede editar.');
}

$reservaId = (int)($venta['reserva_id'] ?? 0);
if ($reservaId > 0) {
    $stmt = $pdo->prepare('SELECT id, cliente_id FROM reservas WHERE id = ?');
    $stmt->execute([$reservaId]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        $pdo->rollBack();
        volverEditarVenta('No se encontro la reserva vinculada a esta venta.');
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
        volverEditarVenta('Cliente no encontrado para actualizar la venta.');
    }
}

$stmt = $pdo->prepare('SELECT * FROM venta_detalles WHERE venta_id = ? FOR UPDATE');
$stmt->execute([$ventaId]);
$detallesAnteriores = $stmt->fetchAll();
$productosAnteriores = array_map('intval', array_column($detallesAnteriores, 'producto_id'));
$productosSolicitados = array_map('intval', array_column($lineas, 'producto_id'));
$productosIds = array_values(array_unique(array_merge($productosAnteriores, $productosSolicitados)));

$productosPorId = [];
$stmtProducto = $pdo->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
foreach ($productosIds as $productoId) {
    $stmtProducto->execute([$productoId]);
    $producto = $stmtProducto->fetch();

    if (!$producto) {
        $pdo->rollBack();
        volverEditarVenta('Producto no encontrado.');
    }

    $productosPorId[$productoId] = $producto;
}

$stockDevuelto = [];
foreach ($detallesAnteriores as $detalle) {
    $productoId = (int)$detalle['producto_id'];
    $stockDevuelto[$productoId] = ($stockDevuelto[$productoId] ?? 0) + (int)$detalle['unidades_descontadas'];
}

$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
foreach ($stockDevuelto as $productoId => $unidades) {
    $stmtStock->execute([$unidades, $productoId]);
    $productosPorId[$productoId]['stock'] = (int)$productosPorId[$productoId]['stock'] + $unidades;
}

$usoStock = [];
$detalles = [];
$total = 0;

foreach ($lineas as $linea) {
    $producto = $productosPorId[$linea['producto_id']] ?? null;
    if (!$producto) {
        $pdo->rollBack();
        volverEditarVenta('Producto no encontrado.');
    }

    if ($producto['estado'] !== 'activo' && !in_array((int)$producto['id'], $productosAnteriores, true)) {
        $pdo->rollBack();
        volverEditarVenta('Producto no encontrado o inactivo.');
    }

    $cantidad = $linea['cantidad'];
    $tipoVenta = $linea['tipo_venta'];
    $unidadesDescontadas = $cantidad;
    $precioUnitario = (float)$producto['precio_venta'];
    $costoUnitario = (float)$producto['precio_compra'];

    if ($tipoVenta === 'pack') {
        if ((int)$producto['pack_cantidad'] <= 0 || (float)$producto['precio_pack'] <= 0) {
            $pdo->rollBack();
            volverEditarVenta('Este producto no tiene venta por pack configurada.');
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
        volverEditarVenta("Stock insuficiente. {$productosPorId[$productoId]['nombre']} tiene {$disponible} unidad(es) disponibles y faltan {$faltan} unidad(es).", $disponible);
    }
}

$stmt = $pdo->prepare('UPDATE ventas SET cliente_id = ?, total = ?, metodo = ?, observacion = ? WHERE id = ?');
$stmt->execute([
    $clienteId > 0 ? $clienteId : null,
    $total,
    $metodo,
    $observacion ?: null,
    $ventaId,
]);

$stmt = $pdo->prepare('DELETE FROM venta_detalles WHERE venta_id = ?');
$stmt->execute([$ventaId]);

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

$pdo->commit();

redirigir('index.php#ventas');
