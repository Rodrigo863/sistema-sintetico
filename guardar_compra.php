<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#compras');
}

function volverCompra(string $mensaje): void
{
    redirigir('index.php?compra_error=' . rawurlencode($mensaje) . '#compras');
}

$productoIds = $_POST['producto_id'] ?? [];
$tiposCompra = $_POST['tipo_compra'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];
$precios = $_POST['precio_unitario'] ?? [];
$stockYaCargado = $_POST['stock_ya_cargado'] ?? [];

if (!is_array($productoIds)) {
    $productoIds = [$productoIds];
}
if (!is_array($tiposCompra)) {
    $tiposCompra = [$tiposCompra];
}
if (!is_array($cantidades)) {
    $cantidades = [$cantidades];
}
if (!is_array($precios)) {
    $precios = [$precios];
}
if (!is_array($stockYaCargado)) {
    $stockYaCargado = [$stockYaCargado];
}

$proveedorId = (int)($_POST['proveedor_id'] ?? 0);
$metodo = $_POST['metodo'] ?? 'efectivo';
$observacion = trim($_POST['observacion'] ?? '');

if (!in_array($metodo, ['efectivo', 'transferencia'], true)) {
    $metodo = 'efectivo';
}

$lineas = [];
foreach ($productoIds as $index => $productoIdRaw) {
    $productoId = (int)$productoIdRaw;
    $cantidad = (int)($cantidades[$index] ?? 0);
    $tipoCompra = $tiposCompra[$index] ?? 'unidad';
    $precio = leerMonto($precios[$index] ?? 0);
    $stockCargado = (int)($stockYaCargado[$index] ?? 0) === 1;

    if ($productoId <= 0 && $cantidad <= 0) {
        continue;
    }

    if ($productoId <= 0 || $cantidad <= 0 || $precio <= 0) {
        volverCompra('Selecciona un producto, cantidad y precio de compra validos.');
    }

    if (!in_array($tipoCompra, ['unidad', 'pack'], true)) {
        $tipoCompra = 'unidad';
    }

    $lineas[] = [
        'producto_id' => $productoId,
        'tipo_compra' => $tipoCompra,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'stock_ya_cargado' => $stockCargado,
    ];
}

if (empty($lineas)) {
    volverCompra('Agrega al menos un producto a la compra.');
}

$pdo = conectarDB();
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pack_cantidad");
$pdo->beginTransaction();

if ($proveedorId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ?');
    $stmt->execute([$proveedorId]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        volverCompra('Proveedor no encontrado.');
    }
}

$productosPorId = [];
$stmtProducto = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND estado = ? FOR UPDATE');
foreach (array_unique(array_column($lineas, 'producto_id')) as $productoId) {
    $stmtProducto->execute([$productoId, 'activo']);
    $producto = $stmtProducto->fetch();

    if (!$producto) {
        $pdo->rollBack();
        volverCompra('Producto no encontrado o inactivo.');
    }

    $productosPorId[$productoId] = $producto;
}

$detalles = [];
$stockAgregado = [];
$total = 0;

foreach ($lineas as $linea) {
    $producto = $productosPorId[$linea['producto_id']];
    $cantidad = $linea['cantidad'];
    $tipoCompra = $linea['tipo_compra'];
    $unidadesAgregadas = $cantidad;

    if ($tipoCompra === 'pack') {
        if ((int)$producto['pack_cantidad'] <= 0) {
            $pdo->rollBack();
            volverCompra('Este producto no tiene cantidad por pack configurada.');
        }
        $unidadesAgregadas = $cantidad * (int)$producto['pack_cantidad'];
    }

    $subtotal = $linea['precio_unitario'] * $cantidad;
    $total += $subtotal;
    $productoId = (int)$producto['id'];
    $stockAgregado[$productoId] = ($stockAgregado[$productoId] ?? 0) + $unidadesAgregadas;

    $detalles[] = [
        'producto_id' => $productoId,
        'tipo_compra' => $tipoCompra,
        'cantidad' => $cantidad,
        'unidades_agregadas' => $unidadesAgregadas,
        'precio_unitario' => $linea['precio_unitario'],
        'subtotal' => $subtotal,
        'stock_ya_cargado' => $linea['stock_ya_cargado'],
    ];
}

$stmt = $pdo->prepare('INSERT INTO compras (proveedor_id, total, metodo, observacion) VALUES (?, ?, ?, ?)');
$stmt->execute([
    $proveedorId > 0 ? $proveedorId : null,
    $total,
    $metodo,
    $observacion ?: null,
]);
$compraId = (int)$pdo->lastInsertId();

$stmtDetalle = $pdo->prepare(
    'INSERT INTO compra_detalles (compra_id, producto_id, tipo_compra, cantidad, unidades_agregadas, precio_unitario, subtotal)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
foreach ($detalles as $detalle) {
    $stmtDetalle->execute([
        $compraId,
        $detalle['producto_id'],
        $detalle['tipo_compra'],
        $detalle['cantidad'],
        $detalle['unidades_agregadas'],
        $detalle['precio_unitario'],
        $detalle['subtotal'],
    ]);
}

$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
foreach ($detalles as $detalle) {
    if ($detalle['stock_ya_cargado']) {
        continue;
    }

    $stmtStock->execute([$detalle['unidades_agregadas'], $detalle['producto_id']]);
}

// Consolida los precios por producto. Si hay compra por unidad y por pack en la
// misma operacion, el precio explicito de unidad tiene prioridad como costo
// unitario y el precio del pack se guarda en su propio campo.
$preciosPorProducto = [];
foreach ($detalles as $detalle) {
    $productoId = $detalle['producto_id'];
    $preciosPorProducto[$productoId] ??= ['unidad' => null, 'pack' => null, 'unidad_pack' => null];

    if ($detalle['tipo_compra'] === 'pack') {
        $preciosPorProducto[$productoId]['pack'] = $detalle['precio_unitario'];
        $preciosPorProducto[$productoId]['unidad_pack'] = $detalle['unidades_agregadas'] > 0
            ? $detalle['subtotal'] / $detalle['unidades_agregadas']
            : null;
    } else {
        $preciosPorProducto[$productoId]['unidad'] = $detalle['precio_unitario'];
    }
}

$stmtPrecio = $pdo->prepare(
    'UPDATE productos
     SET precio_compra = ?, precio_compra_pack = COALESCE(?, precio_compra_pack)
     WHERE id = ?'
);
foreach ($preciosPorProducto as $productoId => $preciosProducto) {
    $precioUnidad = $preciosProducto['unidad'] ?? $preciosProducto['unidad_pack'];
    $stmtPrecio->execute([$precioUnidad, $preciosProducto['pack'], $productoId]);
}

$pdo->commit();

redirigir('index.php#compras');
