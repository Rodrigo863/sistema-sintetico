<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#proveedores');
}

function volverEliminarProveedor(string $mensaje): void
{
    redirigir('index.php?proveedor_error=' . rawurlencode($mensaje) . '#proveedores');
}

$proveedorId = (int)($_POST['proveedor_id'] ?? 0);

if ($proveedorId <= 0) {
    volverEliminarProveedor('Proveedor invalido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ?');
$stmt->execute([$proveedorId]);
if (!$stmt->fetch()) {
    volverEliminarProveedor('No se encontro el proveedor.');
}

$stmt = $pdo->prepare(
    "SELECT TABLE_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND COLUMN_NAME = 'proveedor_id'
       AND TABLE_NAME <> 'proveedores'"
);
$stmt->execute();
$tablasRelacionadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasRelacionadas as $tabla) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
        continue;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tabla` WHERE proveedor_id = ?");
    $stmt->execute([$proveedorId]);
    if ((int)$stmt->fetchColumn() > 0) {
        volverEliminarProveedor('No se puede eliminar este proveedor porque ya esta asociado a otros registros. Puedes desactivarlo.');
    }
}

$stmt = $pdo->prepare('DELETE FROM proveedores WHERE id = ?');
$stmt->execute([$proveedorId]);

redirigir('index.php#proveedores');
