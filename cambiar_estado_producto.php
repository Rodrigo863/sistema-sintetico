<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#productos-list');
}

$productoId = (int)($_POST['producto_id'] ?? 0);
$estado = $_POST['estado'] ?? 'activo';

if ($productoId <= 0) {
    redirigir('index.php#productos-list');
}

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $estado = 'activo';
}

$pdo = conectarDB();
$stmt = $pdo->prepare('UPDATE productos SET estado = ? WHERE id = ?');
$stmt->execute([$estado, $productoId]);

redirigir('index.php#productos-list');
