<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#proveedores');
}

function volverProveedor(string $mensaje): void
{
    redirigir('index.php?proveedor_error=' . rawurlencode($mensaje) . '#proveedores');
}

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$ruc = trim($_POST['ruc'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$notas = trim($_POST['notas'] ?? '');

if ($nombre === '') {
    volverProveedor('El nombre del proveedor es obligatorio.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    volverProveedor('El email del proveedor no es valido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM proveedores WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
if ($stmt->fetch()) {
    volverProveedor('Ya existe un proveedor con ese nombre.');
}

$stmt = $pdo->prepare(
    'INSERT INTO proveedores (nombre, telefono, email, ruc, direccion, notas)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $telefono ?: null,
    $email ?: null,
    $ruc ?: null,
    $direccion ?: null,
    $notas ?: null,
]);

redirigir('index.php#proveedores');
