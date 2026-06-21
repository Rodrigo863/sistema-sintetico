<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#canchas');
}

$nombre = trim($_POST['nombre'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');
$precioHora = leerMonto($_POST['precio_hora'] ?? 0);
$estado = $_POST['estado'] ?? 'activa';

if ($nombre === '') {
    redirigir('index.php?cancha_error=' . rawurlencode('El nombre de la cancha es obligatorio.') . '#canchas');
}

if (!in_array($estado, ['activa', 'mantenimiento', 'inactiva'], true)) {
    $estado = 'activa';
}

$pdo = conectarDB();
$stmt = $pdo->prepare('SELECT id FROM canchas WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
if ($stmt->fetch()) {
    redirigir('index.php?cancha_error=' . rawurlencode('Ya existe una cancha con ese nombre.') . '#canchas');
}

$stmt = $pdo->prepare('INSERT INTO canchas (nombre, tipo, precio_hora, estado) VALUES (?, ?, ?, ?)');
$stmt->execute([$nombre, $tipo ?: null, $precioHora, $estado]);

redirigir('index.php#canchas');
