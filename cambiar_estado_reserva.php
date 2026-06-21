<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#reservas');
}

$reservaId = (int)($_POST['reserva_id'] ?? 0);
$estado = $_POST['estado'] ?? 'reservado';

if ($reservaId <= 0) {
    redirigir('index.php#reservas');
}

if (!in_array($estado, ['reservado', 'confirmado', 'cancelado', 'finalizado'], true)) {
    $estado = 'reservado';
}

$pdo = conectarDB();
$stmt = $pdo->prepare('UPDATE reservas SET estado = ? WHERE id = ?');
$stmt->execute([$estado, $reservaId]);

redirigir('index.php#reservas');
