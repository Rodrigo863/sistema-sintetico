<?php
require 'config.php';

requiereAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#configuracion');
}

$usuarioId = (int)($_POST['usuario_id'] ?? 0);
$usuarioActualId = (int)(usuarioActual()['id'] ?? 0);

if ($usuarioId <= 0) {
    redirigir('index.php?usuario_error=' . urlencode('Solicitud invalida.') . '#configuracion');
}

if ($usuarioId === $usuarioActualId) {
    redirigir('index.php?usuario_error=' . urlencode('No puedes eliminar tu propio usuario.') . '#configuracion');
}

$pdo = conectarDB();
$stmt = $pdo->prepare("SELECT rol, estado FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    redirigir('index.php?usuario_error=' . urlencode('El usuario no existe.') . '#configuracion');
}

if ($usuario['rol'] === 'administrador' && $usuario['estado'] === 'activo') {
    $adminsActivos = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador' AND estado = 'activo'")->fetchColumn();
    if ($adminsActivos <= 1) {
        redirigir('index.php?usuario_error=' . urlencode('Debe quedar al menos un administrador activo.') . '#configuracion');
    }
}

$stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);

redirigir('index.php?usuario_mensaje=' . urlencode('Usuario eliminado correctamente.') . '#configuracion');
