<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#categorias');
}

function volverCategoriaNueva(string $mensaje): void
{
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje]);
        exit;
    }

    redirigir('index.php?producto_error=' . rawurlencode($mensaje) . '#categorias');
}

function responderCategoriaJson(array $categoria): void
{
    if (($_POST['ajax'] ?? '') !== '1') {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'categoria' => $categoria]);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');

if ($nombre === '') {
    volverCategoriaNueva('El nombre de la categoria es obligatorio.');
}

$pdo = conectarDB();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS producto_categorias (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nombre VARCHAR(80) NOT NULL,
      estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_producto_categorias_nombre (nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$stmt = $pdo->prepare('SELECT id, estado FROM producto_categorias WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
$categoria = $stmt->fetch();

if ($categoria) {
    if ($categoria['estado'] === 'inactivo') {
        $stmt = $pdo->prepare("UPDATE producto_categorias SET estado = 'activo' WHERE id = ?");
        $stmt->execute([(int)$categoria['id']]);
        responderCategoriaJson(['id' => (int)$categoria['id'], 'nombre' => $nombre, 'estado' => 'activo']);
        redirigir('index.php#categorias');
    }

    volverCategoriaNueva('Ya existe una categoria con ese nombre.');
}

$stmt = $pdo->prepare("INSERT INTO producto_categorias (nombre, estado) VALUES (?, 'activo')");
$stmt->execute([$nombre]);
$categoriaId = (int)$pdo->lastInsertId();

responderCategoriaJson(['id' => $categoriaId, 'nombre' => $nombre, 'estado' => 'activo']);

redirigir('index.php#categorias');
