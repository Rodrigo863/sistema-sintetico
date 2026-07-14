<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$documento = trim($_POST['documento'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$notas = trim($_POST['notas'] ?? '');

function redirigirErrorCliente(string $mensaje): void
{
    $_SESSION['cliente_error'] = $mensaje;
    redirigir('index.php#clientes');
}

if ($nombre === '') {
    redirigirErrorCliente('El nombre es obligatorio.');
}

if ($telefono === '') {
    redirigirErrorCliente('El telefono es obligatorio.');
}

if (!preg_match('/^\d{10}$/', $telefono)) {
    redirigirErrorCliente('El telefono debe tener exactamente 10 numeros.');
}

if ($documento !== '' && !preg_match('/^[0-9-]+$/', $documento)) {
    redirigirErrorCliente('El documento o RUC solo puede contener numeros y guion.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigirErrorCliente('El email no tiene un formato valido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
$clienteExistente = $stmt->fetch();

if ($clienteExistente) {
    redirigirErrorCliente('Ya existe un cliente con ese nombre: ' . $clienteExistente['nombre'] . '.');
}

if ($telefono !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE telefono = ? LIMIT 1');
    $stmt->execute([$telefono]);
    $clienteExistente = $stmt->fetch();

    if ($clienteExistente) {
        redirigirErrorCliente('Ya existe un cliente con ese telefono: ' . $clienteExistente['nombre'] . '.');
    }
}

if ($documento !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE documento = ? LIMIT 1');
    $stmt->execute([$documento]);
    $clienteExistente = $stmt->fetch();

    if ($clienteExistente) {
        redirigirErrorCliente('Ya existe un cliente con ese documento: ' . $clienteExistente['nombre'] . '.');
    }
}

$stmt = $pdo->prepare(
    'INSERT INTO clientes (nombre, email, telefono, documento, direccion, notas)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $email ?: null,
    $telefono,
    $documento ?: null,
    $direccion ?: null,
    $notas ?: null,
]);

redirigir('index.php');
