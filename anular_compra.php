<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#compras');
}

$compraId = (int)($_POST['compra_id'] ?? 0);
if ($compraId <= 0) {
    redirigir('index.php?compra_error=' . urlencode('No se encontro la compra para anular.') . '#compras');
}

$pdo = conectarDB();
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id, estado FROM compras WHERE id = ? FOR UPDATE');
$stmt->execute([$compraId]);
$compra = $stmt->fetch();

if (!$compra) {
    $pdo->rollBack();
    redirigir('index.php?compra_error=' . urlencode('No se encontro la compra para anular.') . '#compras');
}

if ($compra['estado'] === 'anulada') {
    $pdo->commit();
    redirigir('index.php#compras');
}

$stmt = $pdo->prepare('SELECT producto_id, unidades_agregadas FROM compra_detalles WHERE compra_id = ? FOR UPDATE');
$stmt->execute([$compraId]);
$detalles = $stmt->fetchAll();

$stockADescontar = [];
foreach ($detalles as $detalle) {
    $productoId = (int)$detalle['producto_id'];
    $stockADescontar[$productoId] = ($stockADescontar[$productoId] ?? 0) + (int)$detalle['unidades_agregadas'];
}

$stmtProducto = $pdo->prepare('SELECT id, nombre, stock FROM productos WHERE id = ? FOR UPDATE');
$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?');
foreach ($stockADescontar as $productoId => $unidades) {
    $stmtProducto->execute([$productoId]);
    $producto = $stmtProducto->fetch();
    if (!$producto) {
        $pdo->rollBack();
        redirigir('index.php?compra_error=' . urlencode('No se pudo descontar stock: producto no encontrado.') . '#compras');
    }

    if ((int)$producto['stock'] < $unidades) {
        $pdo->rollBack();
        redirigir('index.php?compra_error=' . urlencode('No se puede anular esta compra porque ya se vendio parte del stock de ' . $producto['nombre'] . '.') . '#compras');
    }

    $stmtStock->execute([$unidades, $productoId]);
}

$stmt = $pdo->prepare("UPDATE compras SET estado = 'anulada' WHERE id = ?");
$stmt->execute([$compraId]);

$pdo->commit();

redirigir('index.php#compras');
