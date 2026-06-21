<?php
require 'config.php';

requiereAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#configuracion');
}

$usuarioId = (int)($_POST['usuario_id'] ?? 0);
$estado = $_POST['estado'] ?? '';
$usuarioActualId = (int)(usuarioActual()['id'] ?? 0);

if ($usuarioId <= 0 || !in_array($estado, ['activo', 'inactivo'], true)) {
    redirigir('index.php?usuario_error=' . urlencode('Solicitud invalida.') . '#configuracion');
}

if ($usuarioId === $usuarioActualId) {
    redirigir('index.php?usuario_error=' . urlencode('No puedes desactivar tu propio usuario.') . '#configuracion');
}

$pdo = conectarDB();
$stmt = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
$stmt->execute([$estado, $usuarioId]);

$mensaje = $estado === 'activo' ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.';
redirigir('index.php?usuario_mensaje=' . urlencode($mensaje) . '#configuracion');
