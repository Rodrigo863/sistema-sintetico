<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#categorias');
}

function volverEliminarCategoria(string $mensaje): void
{
    redirigir('index.php?producto_error=' . rawurlencode($mensaje) . '#categorias');
}

$categoriaId = (int)($_POST['categoria_id'] ?? 0);

if ($categoriaId <= 0) {
    volverEliminarCategoria('Categoria invalida.');
}

$pdo = conectarDB();

$stmt = $pdo->prepare('SELECT nombre FROM producto_categorias WHERE id = ?');
$stmt->execute([$categoriaId]);
$categoria = $stmt->fetch();

if (!$categoria) {
    volverEliminarCategoria('No se encontro la categoria.');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE LOWER(TRIM(categoria)) = LOWER(TRIM(?))');
$stmt->execute([$categoria['nombre']]);
if ((int)$stmt->fetchColumn() > 0) {
    volverEliminarCategoria('No se puede eliminar una categoria usada por productos. Puedes desactivarla.');
}

$stmt = $pdo->prepare('DELETE FROM producto_categorias WHERE id = ?');
$stmt->execute([$categoriaId]);

redirigir('index.php#categorias');
