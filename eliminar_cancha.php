<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#canchas');
}

function volverEliminarCancha(string $mensaje): void
{
    redirigir('index.php?cancha_error=' . rawurlencode($mensaje) . '#canchas');
}

$canchaId = (int)($_POST['cancha_id'] ?? 0);

if ($canchaId <= 0) {
    volverEliminarCancha('Cancha invalida.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reservas WHERE cancha_id = ?');
$stmt->execute([$canchaId]);
if ((int)$stmt->fetchColumn() > 0) {
    volverEliminarCancha('No se puede eliminar una cancha con reservas registradas. Puedes editarla y marcarla como inactiva.');
}

$stmt = $pdo->prepare('DELETE FROM canchas WHERE id = ?');
$stmt->execute([$canchaId]);

redirigir('index.php#canchas');
