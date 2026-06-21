<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#ventas');
}

$ventaId = (int)($_POST['venta_id'] ?? 0);
if ($ventaId <= 0) {
    redirigir('index.php?venta_error=' . urlencode('No se encontro la venta para anular.') . '#ventas');
}

$pdo = conectarDB();
$pdo->exec("ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa' AFTER metodo");
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id, estado FROM ventas WHERE id = ? FOR UPDATE');
$stmt->execute([$ventaId]);
$venta = $stmt->fetch();

if (!$venta) {
    $pdo->rollBack();
    redirigir('index.php?venta_error=' . urlencode('No se encontro la venta para anular.') . '#ventas');
}

if ($venta['estado'] === 'anulada') {
    $pdo->commit();
    redirigir('index.php#ventas');
}

$stmt = $pdo->prepare('SELECT producto_id, unidades_descontadas FROM venta_detalles WHERE venta_id = ? FOR UPDATE');
$stmt->execute([$ventaId]);
$detalles = $stmt->fetchAll();

$stockDevuelto = [];
foreach ($detalles as $detalle) {
    $productoId = (int)$detalle['producto_id'];
    $stockDevuelto[$productoId] = ($stockDevuelto[$productoId] ?? 0) + (int)$detalle['unidades_descontadas'];
}

$stmtProducto = $pdo->prepare('SELECT id FROM productos WHERE id = ? FOR UPDATE');
$stmtStock = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
foreach ($stockDevuelto as $productoId => $unidades) {
    $stmtProducto->execute([$productoId]);
    if (!$stmtProducto->fetch()) {
        $pdo->rollBack();
        redirigir('index.php?venta_error=' . urlencode('No se pudo devolver stock: producto no encontrado.') . '#ventas');
    }

    $stmtStock->execute([$unidades, $productoId]);
}

$stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada' WHERE id = ?");
$stmt->execute([$ventaId]);

$pdo->commit();

redirigir('index.php#ventas');
