<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#productos');
}

function volverCategoria(string $mensaje): void
{
    redirigir('index.php?producto_error=' . rawurlencode($mensaje) . '#categorias');
}

$categoriaId = (int)($_POST['categoria_id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$nombre = trim($_POST['nombre'] ?? '');

if ($categoriaId <= 0) {
    volverCategoria('Categoria invalida.');
}

if (!in_array($accion, ['renombrar', 'desactivar', 'activar'], true)) {
    volverCategoria('Accion de categoria invalida.');
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

$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT * FROM producto_categorias WHERE id = ? FOR UPDATE');
$stmt->execute([$categoriaId]);
$categoria = $stmt->fetch();

if (!$categoria) {
    $pdo->rollBack();
    volverCategoria('No se encontro la categoria.');
}

if ($accion === 'renombrar') {
    if ($nombre === '') {
        $pdo->rollBack();
        volverCategoria('El nombre de la categoria es obligatorio.');
    }

    $stmt = $pdo->prepare('SELECT id FROM producto_categorias WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
    $stmt->execute([$nombre, $categoriaId]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        volverCategoria('Ya existe una categoria con ese nombre.');
    }

    $stmt = $pdo->prepare('UPDATE producto_categorias SET nombre = ?, estado = ? WHERE id = ?');
    $stmt->execute([$nombre, 'activo', $categoriaId]);

    $stmt = $pdo->prepare('UPDATE productos SET categoria = ? WHERE LOWER(TRIM(categoria)) = LOWER(TRIM(?))');
    $stmt->execute([$nombre, $categoria['nombre']]);
} elseif ($accion === 'desactivar') {
    $stmt = $pdo->prepare("UPDATE producto_categorias SET estado = 'inactivo' WHERE id = ?");
    $stmt->execute([$categoriaId]);
} else {
    $stmt = $pdo->prepare("UPDATE producto_categorias SET estado = 'activo' WHERE id = ?");
    $stmt->execute([$categoriaId]);
}

$pdo->commit();

redirigir('index.php#categorias');
