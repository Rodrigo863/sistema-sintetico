<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = ?');
    $stmt->execute([$id]);
}

redirigir('index.php');
