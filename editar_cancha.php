<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#canchas');
}

function volverCancha(string $mensaje): void
{
    redirigir('index.php?cancha_error=' . rawurlencode($mensaje) . '#canchas');
}

$canchaId = (int)($_POST['cancha_id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');
$precioHora = leerMonto($_POST['precio_hora'] ?? 0);
$estado = $_POST['estado'] ?? 'activa';

if ($canchaId <= 0) {
    volverCancha('Cancha invalida.');
}

if ($nombre === '') {
    volverCancha('El nombre de la cancha es obligatorio.');
}

if ($precioHora < 0) {
    volverCancha('El precio por hora debe ser valido.');
}

if (!in_array($estado, ['activa', 'mantenimiento', 'inactiva'], true)) {
    volverCancha('Estado de cancha invalido.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT id FROM canchas WHERE id = ?');
$stmt->execute([$canchaId]);
if (!$stmt->fetch()) {
    volverCancha('No se encontro la cancha para editar.');
}

$stmt = $pdo->prepare('SELECT id FROM canchas WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
$stmt->execute([$nombre, $canchaId]);
if ($stmt->fetch()) {
    volverCancha('Ya existe otra cancha con ese nombre.');
}

$stmt = $pdo->prepare('UPDATE canchas SET nombre = ?, tipo = ?, precio_hora = ?, estado = ? WHERE id = ?');
$stmt->execute([$nombre, $tipo ?: null, $precioHora, $estado, $canchaId]);

redirigir('index.php#canchas');
