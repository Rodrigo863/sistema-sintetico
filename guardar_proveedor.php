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

if ($telefono !== '' && !preg_match('/^\d{10}$/', $telefono)) {
    volverProveedor('El telefono del proveedor debe tener exactamente 10 numeros.');
}

if ($ruc !== '' && !preg_match('/^[0-9-]+$/', $ruc)) {
    volverProveedor('El RUC / Documento solo puede contener numeros y guion.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM proveedores WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
if ($stmt->fetch()) {
    volverProveedor('Ya existe un proveedor con ese nombre.');
}

if ($ruc !== '') {
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE ruc = ? LIMIT 1');
    $stmt->execute([$ruc]);
    if ($stmt->fetch()) {
        volverProveedor('Ya existe un proveedor con ese RUC / Documento.');
    }
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
