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
$documento = trim($_POST['documento'] ?? '');
$notas = trim($_POST['notas'] ?? '');

if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio.']);
    exit;
}

if ($telefono === '') {
    echo json_encode(['ok' => false, 'error' => 'El telefono es obligatorio.']);
    exit;
}

if (!preg_match('/^\d{10}$/', $telefono)) {
    echo json_encode(['ok' => false, 'error' => 'El telefono debe tener exactamente 10 numeros.']);
    exit;
}

if ($documento !== '' && !preg_match('/^[0-9-]+$/', $documento)) {
    echo json_encode(['ok' => false, 'error' => 'El documento o RUC solo puede contener numeros y guion.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'El email no tiene un formato valido.']);
    exit;
}

$pdo = conectarDB();

$stmt = $pdo->prepare(
    'SELECT id, nombre, telefono, email, documento
     FROM clientes
     WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
        OR (? <> "" AND telefono = ?)
     LIMIT 1'
);
$stmt->execute([$nombre, $telefono, $telefono]);
$clienteExistente = $stmt->fetch();

if (!$clienteExistente && $documento !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre, telefono, email, documento FROM clientes WHERE documento = ? LIMIT 1');
    $stmt->execute([$documento]);
    $clienteExistente = $stmt->fetch();
}

if ($clienteExistente) {
    echo json_encode([
        'ok' => true,
        'existe' => true,
        'mensaje' => 'El cliente ya existia y fue seleccionado.',
        'cliente' => [
            'id' => (int)$clienteExistente['id'],
            'nombre' => $clienteExistente['nombre'],
            'telefono' => $clienteExistente['telefono'],
            'email' => $clienteExistente['email'] ?? '',
            'documento' => $clienteExistente['documento'] ?? '',
        ],
    ]);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO clientes (nombre, email, telefono, documento, notas)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $email ?: null,
    $telefono,
    $documento ?: null,
    $notas ?: null,
]);

$id = (int)$pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'cliente' => [
        'id' => $id,
        'nombre' => $nombre,
        'telefono' => $telefono,
        'email' => $email,
        'documento' => $documento,
        'notas' => $notas,
    ],
]);
