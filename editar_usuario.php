<?php
require 'config.php';

requiereAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#configuracion');
}

$usuarioId = (int)($_POST['usuario_id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = (string)($_POST['password'] ?? '');
$rol = $_POST['rol'] ?? 'secretario';
$estado = $_POST['estado'] ?? 'activo';
$usuarioActualId = (int)(usuarioActual()['id'] ?? 0);

if ($usuarioId <= 0 || $nombre === '' || $usuario === '') {
    redirigir('index.php?usuario_error=' . urlencode('Completa los datos del usuario.') . '#configuracion');
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usuario)) {
    redirigir('index.php?usuario_error=' . urlencode('El usuario debe tener 3 a 60 caracteres y solo letras, numeros, punto, guion o guion bajo.') . '#configuracion');
}

if ($password !== '' && strlen($password) < 6) {
    redirigir('index.php?usuario_error=' . urlencode('La nueva contrasena debe tener al menos 6 caracteres.') . '#configuracion');
}

if (!in_array($rol, ['administrador', 'secretario'], true)) {
    $rol = 'secretario';
}

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $estado = 'activo';
}

if ($usuarioId === $usuarioActualId) {
    $rol = 'administrador';
    $estado = 'activo';
}

try {
    $pdo = conectarDB();

    if ($password !== '') {
        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET nombre = ?, usuario = ?, password_hash = ?, rol = ?, estado = ?
             WHERE id = ?"
        );
        $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol, $estado, $usuarioId]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET nombre = ?, usuario = ?, rol = ?, estado = ?
             WHERE id = ?"
        );
        $stmt->execute([$nombre, $usuario, $rol, $estado, $usuarioId]);
    }

    if ($usuarioId === $usuarioActualId) {
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['usuario'] = $usuario;
        $_SESSION['usuario']['rol'] = $rol;
    }

    redirigir('index.php?usuario_mensaje=' . urlencode('Usuario actualizado correctamente.') . '#configuracion');
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        redirigir('index.php?usuario_error=' . urlencode('Ya existe un usuario con ese nombre de acceso.') . '#configuracion');
    }

    redirigir('index.php?usuario_error=' . urlencode('No se pudo actualizar el usuario.') . '#configuracion');
}
