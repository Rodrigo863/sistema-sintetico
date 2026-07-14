<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#proveedores');
}

function volverEditarProveedor(string $mensaje): void
{
    redirigir('index.php?proveedor_error=' . rawurlencode($mensaje) . '#proveedores');
}

$proveedorId = (int)($_POST['proveedor_id'] ?? 0);
$accion = $_POST['accion'] ?? 'editar';

if ($proveedorId <= 0) {
    volverEditarProveedor('Proveedor invalido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ?');
$stmt->execute([$proveedorId]);
if (!$stmt->fetch()) {
    volverEditarProveedor('No se encontro el proveedor.');
}

if ($accion === 'estado') {
    $estado = $_POST['estado'] ?? 'activo';
    if (!in_array($estado, ['activo', 'inactivo'], true)) {
        volverEditarProveedor('Estado de proveedor invalido.');
    }

    $stmt = $pdo->prepare('UPDATE proveedores SET estado = ? WHERE id = ?');
    $stmt->execute([$estado, $proveedorId]);

    redirigir('index.php#proveedores');
}

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$ruc = trim($_POST['ruc'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$estado = $_POST['estado'] ?? 'activo';
$notas = trim($_POST['notas'] ?? '');

if ($nombre === '') {
    volverEditarProveedor('El nombre del proveedor es obligatorio.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    volverEditarProveedor('El email del proveedor no es valido.');
}

if ($telefono !== '' && !preg_match('/^\d{10}$/', $telefono)) {
    volverEditarProveedor('El telefono del proveedor debe tener exactamente 10 numeros.');
}

if ($ruc !== '' && !preg_match('/^[0-9-]+$/', $ruc)) {
    volverEditarProveedor('El RUC / Documento solo puede contener numeros y guion.');
}

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    volverEditarProveedor('Estado de proveedor invalido.');
}

$stmt = $pdo->prepare('SELECT id FROM proveedores WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
$stmt->execute([$nombre, $proveedorId]);
if ($stmt->fetch()) {
    volverEditarProveedor('Ya existe otro proveedor con ese nombre.');
}

if ($ruc !== '') {
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE ruc = ? AND id <> ? LIMIT 1');
    $stmt->execute([$ruc, $proveedorId]);
    if ($stmt->fetch()) {
        volverEditarProveedor('Ya existe otro proveedor con ese RUC / Documento.');
    }
}

$stmt = $pdo->prepare(
    'UPDATE proveedores
     SET nombre = ?, telefono = ?, email = ?, ruc = ?, direccion = ?, estado = ?, notas = ?
     WHERE id = ?'
);
$stmt->execute([
    $nombre,
    $telefono ?: null,
    $email ?: null,
    $ruc ?: null,
    $direccion ?: null,
    $estado,
    $notas ?: null,
    $proveedorId,
]);

redirigir('index.php#proveedores');
