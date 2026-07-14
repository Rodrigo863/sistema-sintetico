<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#clientes');
}

$id = (int)($_POST['id'] ?? 0);
$estado = $_POST['estado'] ?? 'activo';

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $estado = 'activo';
}

if ($id > 0) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare('UPDATE clientes SET estado = ? WHERE id = ?');
    $stmt->execute([$estado, $id]);
}

redirigir('index.php#clientes');
