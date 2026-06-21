<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#compras');
}

function volverEditarCompra(string $mensaje): void
{
    redirigir('index.php?compra_error=' . rawurlencode($mensaje) . '#compras');
}

$compraId = (int)($_POST['compra_id'] ?? 0);
if ($compraId <= 0) {
    volverEditarCompra('No se encontro la compra para editar.');
}

$productoIds = $_POST['producto_id'] ?? [];
$tiposCompra = $_POST['tipo_compra'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];
$precios = $_POST['precio_unitario'] ?? [];

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

$proveedorId = (int)($_POST['proveedor_id'] ?? 0);
$metodo = $_POST['metodo'] ?? 'efectivo';
$observacion = trim($_POST['observacion'] ?? '');

if (!in_array($metodo, ['efectivo', 'transferencia', 'tarjeta', 'otro'], true)) {
    $metodo = 'efectivo';
}

$lineas = [];
foreach ($productoIds as $index => $productoIdRaw) {
    $productoId = (int)$productoIdRaw;
    $cantidad = (int)($cantidades[$index] ?? 0);
    $tipoCompra = $tiposCompra[$index] ?? 'unidad';
    $precio = leerMonto($precios[$index] ?? 0);

    if ($productoId <= 0 && $cantidad <= 0) {
        continue;
    }

    if ($productoId <= 0 || $cantidad <= 0 || $precio <= 0) {
        volverEditarCompra('Selecciona un producto, cantidad y precio de compra validos.');
    }

    if (!in_array($tipoCompra, ['unidad', 'pack'], true)) {
        $tipoCompra = 'unidad';
    }

    $lineas[] = [
        'producto_id' => $productoId,
        'tipo_compra' => $tipoCompra,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
    ];
}

if (empty($lineas)) {
    volverEditarCompra('Agrega al menos un producto a la compra.');
}

$pdo = conectarDB();
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT * FROM compras WHERE id = ? FOR UPDATE');
$stmt->execute([$compraId]);
$compra = $stmt->fetch();

if (!$compra) {
    $pdo->rollBack();
    volverEditarCompra('No se encontro la compra para editar.');
}

if ($compra['estado'] === 'anulada') {
    $pdo->rollBack();
    volverEditarCompra('Esta compra esta anulada y no se puede editar.');
}

if ($proveedorId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ?');
    $stmt->execute([$proveedorId]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        volverEditarCompra('Proveedor no encontrado.');
    }
}

$stmt = $pdo->prepare('SELECT * FROM compra_detalles WHERE compra_id = ? FOR UPDATE');
$stmt->execute([$compraId]);
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
        volverEditarCompra('Producto no encontrado.');
    }

    $productosPorId[$productoId] = $producto;
}

$stockAnterior = [];
foreach ($detallesAnteriores as $detalle) {
    $productoId = (int)$detalle['producto_id'];
    $stockAnterior[$productoId] = ($stockAnterior[$productoId] ?? 0) + (int)$detalle['unidades_agregadas'];
}

$detalles = [];
$stockNuevo = [];
$total = 0;

foreach ($lineas as $linea) {
    $producto = $productosPorId[$linea['producto_id']] ?? null;
    if (!$producto) {
        $pdo->rollBack();
        volverEditarCompra('Producto no encontrado.');
    }

    if ($producto['estado'] !== 'activo' && !in_array((int)$producto['id'], $productosAnteriores, true)) {
        $pdo->rollBack();
        volverEditarCompra('Producto no encontrado o inactivo.');
    }

    $cantidad = $linea['cantidad'];
    $tipoCompra = $linea['tipo_compra'];
    $unidadesAgregadas = $cantidad;

    if ($tipoCompra === 'pack') {
        if ((int)$producto['pack_cantidad'] <= 0) {
            $pdo->rollBack();
            volverEditarCompra('Este producto no tiene cantidad por pack configurada.');
        }

        $unidadesAgregadas = $cantidad * (int)$producto['pack_cantidad'];
    }

    $subtotal = $linea['precio_unitario'] * $cantidad;
    $total += $subtotal;
    $productoId = (int)$producto['id'];
    $stockNuevo[$productoId] = ($stockNuevo[$productoId] ?? 0) + $unidadesAgregadas;

    $detalles[] = [
        'producto_id' => $productoId,
        'tipo_compra' => $tipoCompra,
        'cantidad' => $cantidad,
        'unidades_agregadas' => $unidadesAgregadas,
        'precio_unitario' => $linea['precio_unitario'],
        'subtotal' => $subtotal,
    ];
}

$productosConCambio = array_values(array_unique(array_merge(array_keys($stockAnterior), array_keys($stockNuevo))));
$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
foreach ($productosConCambio as $productoId) {
    $diferencia = (int)($stockNuevo[$productoId] ?? 0) - (int)($stockAnterior[$productoId] ?? 0);
    if ($diferencia === 0) {
        continue;
    }

    if ($diferencia < 0 && (int)$productosPorId[$productoId]['stock'] < abs($diferencia)) {
        $pdo->rollBack();
        volverEditarCompra('No se puede editar esta compra porque ya se vendio parte del stock de ' . $productosPorId[$productoId]['nombre'] . '.');
    }

    $stmtStock->execute([$diferencia, $productoId]);
}

$stmt = $pdo->prepare('UPDATE compras SET proveedor_id = ?, total = ?, metodo = ?, observacion = ? WHERE id = ?');
$stmt->execute([
    $proveedorId > 0 ? $proveedorId : null,
    $total,
    $metodo,
    $observacion ?: null,
    $compraId,
]);

$stmt = $pdo->prepare('DELETE FROM compra_detalles WHERE compra_id = ?');
$stmt->execute([$compraId]);

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

$stmtPrecio = $pdo->prepare('UPDATE productos SET precio_compra = ? WHERE id = ?');
foreach ($detalles as $detalle) {
    $precioReferencia = $detalle['tipo_compra'] === 'pack' && $detalle['unidades_agregadas'] > 0
        ? $detalle['subtotal'] / $detalle['unidades_agregadas']
        : $detalle['precio_unitario'];
    $stmtPrecio->execute([$precioReferencia, $detalle['producto_id']]);
}

$pdo->commit();

redirigir('index.php#compras');
