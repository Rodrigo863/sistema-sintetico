<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$ruc = trim($_POST['ruc'] ?? '');

if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'El email no tiene un formato valido.']);
    exit;
}

if ($telefono !== '' && !preg_match('/^\d{10}$/', $telefono)) {
    echo json_encode(['ok' => false, 'error' => 'El telefono debe tener exactamente 10 numeros.']);
    exit;
}

if ($ruc !== '' && !preg_match('/^[0-9-]+$/', $ruc)) {
    echo json_encode(['ok' => false, 'error' => 'El RUC / Documento solo puede contener numeros y guion.']);
    exit;
}

$pdo = conectarDB();

$stmt = $pdo->prepare(
    'SELECT id, nombre, telefono, email, ruc, direccion, estado, notas
     FROM proveedores
     WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
        OR (? <> "" AND ruc = ?)
     LIMIT 1'
);
$stmt->execute([$nombre, $ruc, $ruc]);
$proveedor = $stmt->fetch();

if ($proveedor) {
    echo json_encode([
        'ok' => true,
        'existe' => true,
        'mensaje' => 'El proveedor ya existia y fue seleccionado.',
        'proveedor' => $proveedor,
    ]);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO proveedores (nombre, telefono, email, ruc)
     VALUES (?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $telefono ?: null,
    $email ?: null,
    $ruc ?: null,
]);

echo json_encode([
    'ok' => true,
    'proveedor' => [
        'id' => (int)$pdo->lastInsertId(),
        'nombre' => $nombre,
        'telefono' => $telefono,
        'email' => $email,
        'ruc' => $ruc,
        'direccion' => '',
        'estado' => 'activo',
        'notas' => '',
    ],
]);
