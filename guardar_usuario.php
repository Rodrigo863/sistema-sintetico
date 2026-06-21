<?php
require 'config.php';

requiereAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#configuracion');
}

$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = (string)($_POST['password'] ?? '');
$rol = $_POST['rol'] ?? 'usuario';

if ($nombre === '' || $usuario === '' || $password === '') {
    redirigir('index.php?usuario_error=' . urlencode('Completa todos los campos del usuario.') . '#configuracion');
}

if (strlen($password) < 6) {
    redirigir('index.php?usuario_error=' . urlencode('La contrasena debe tener al menos 6 caracteres.') . '#configuracion');
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usuario)) {
    redirigir('index.php?usuario_error=' . urlencode('El usuario debe tener 3 a 60 caracteres y solo letras, numeros, punto, guion o guion bajo.') . '#configuracion');
}

if (!in_array($rol, ['administrador', 'usuario'], true)) {
    $rol = 'usuario';
}

try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, usuario, password_hash, rol, estado)
         VALUES (?, ?, ?, ?, 'activo')"
    );
    $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol]);

    redirigir('index.php?usuario_mensaje=' . urlencode('Usuario creado correctamente.') . '#configuracion');
} catch (PDOException $e) {
    if ($e->errorInfo[1] ?? null) {
        redirigir('index.php?usuario_error=' . urlencode('Ya existe un usuario con ese nombre de acceso.') . '#configuracion');
    }

    redirigir('index.php?usuario_error=' . urlencode('No se pudo crear el usuario.') . '#configuracion');
}
