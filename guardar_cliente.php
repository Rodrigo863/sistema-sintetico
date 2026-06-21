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

if ($nombre === '') {
    redirigir('index.php?cliente_error=' . urlencode('El nombre es obligatorio.') . '#clientes');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigir('index.php?cliente_error=' . urlencode('El email no tiene un formato valido.') . '#clientes');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
$clienteExistente = $stmt->fetch();

if ($clienteExistente) {
    redirigir('index.php?cliente_error=' . urlencode('Ya existe un cliente con ese nombre: ' . $clienteExistente['nombre'] . '.') . '#clientes');
}

if ($telefono !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE telefono = ? LIMIT 1');
    $stmt->execute([$telefono]);
    $clienteExistente = $stmt->fetch();

    if ($clienteExistente) {
        redirigir('index.php?cliente_error=' . urlencode('Ya existe un cliente con ese telefono: ' . $clienteExistente['nombre'] . '.') . '#clientes');
    }
}

if ($documento !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE documento = ? LIMIT 1');
    $stmt->execute([$documento]);
    $clienteExistente = $stmt->fetch();

    if ($clienteExistente) {
        redirigir('index.php?cliente_error=' . urlencode('Ya existe un cliente con ese documento: ' . $clienteExistente['nombre'] . '.') . '#clientes');
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
