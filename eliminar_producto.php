<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#productos-list');
}

function volverEliminarProducto(string $mensaje): void
{
    redirigir('index.php?producto_error=' . rawurlencode($mensaje) . '#productos-list');
}

$productoId = (int)($_POST['producto_id'] ?? 0);

if ($productoId <= 0) {
    volverEliminarProducto('Producto invalido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM productos WHERE id = ?');
$stmt->execute([$productoId]);
if (!$stmt->fetch()) {
    volverEliminarProducto('No se encontro el producto.');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM compra_detalles WHERE producto_id = ?');
$stmt->execute([$productoId]);
$compras = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM venta_detalles WHERE producto_id = ?');
$stmt->execute([$productoId]);
$ventas = (int)$stmt->fetchColumn();

if ($compras > 0 || $ventas > 0) {
    volverEliminarProducto('No se puede eliminar un producto usado en compras, ventas o consumos. Puedes desactivarlo.');
}

$stmt = $pdo->prepare('DELETE FROM productos WHERE id = ?');
$stmt->execute([$productoId]);

redirigir('index.php#productos-list');
