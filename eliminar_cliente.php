<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#clientes');
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo = conectarDB();
    try {
        $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        $_SESSION['cliente_error'] = 'No se pudo eliminar el cliente porque esta asociado a reservas o ventas. Puedes desactivarlo.';
    }
}

redirigir('index.php#clientes');
