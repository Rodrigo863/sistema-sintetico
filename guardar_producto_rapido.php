<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$codigoBarra = trim($_POST['codigo_barra'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');
$precioCompra = leerMonto($_POST['precio_compra'] ?? 0);
$precioVenta = leerMonto($_POST['precio_venta'] ?? 0);
$packCantidad = (int)($_POST['pack_cantidad'] ?? 0);
$precioPack = leerMonto($_POST['precio_pack'] ?? 0);
$stock = (int)($_POST['stock'] ?? 0);

if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'El nombre del producto es obligatorio.']);
    exit;
}

if ($precioCompra < 0 || $precioVenta < 0 || $packCantidad < 0 || $precioPack < 0 || $stock < 0) {
    echo json_encode(['ok' => false, 'error' => 'Precios, pack y stock deben ser valores validos.']);
    exit;
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

$stmt = $pdo->prepare('SELECT * FROM productos WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);
$productoExistente = $stmt->fetch();

if (!$productoExistente && $codigoBarra !== '') {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE codigo_barra = ? LIMIT 1');
    $stmt->execute([$codigoBarra]);
    $productoExistente = $stmt->fetch();
}

if ($productoExistente) {
    if (($productoExistente['estado'] ?? 'activo') !== 'activo') {
        echo json_encode(['ok' => false, 'error' => 'El producto ya existe, pero esta inactivo. Activalo desde Productos.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'existe' => true,
        'mensaje' => 'El producto ya existia y fue seleccionado.',
        'producto' => $productoExistente,
    ]);
    exit;
}

if ($categoria !== '') {
    $stmt = $pdo->prepare("SELECT nombre FROM producto_categorias WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND estado = 'activo' LIMIT 1");
    $stmt->execute([$categoria]);
    $categoriaActiva = $stmt->fetch();

    if (!$categoriaActiva) {
        echo json_encode(['ok' => false, 'error' => 'Primero carga la categoria con el boton + del modal.']);
        exit;
    }

    $categoria = $categoriaActiva['nombre'];
}

$stmt = $pdo->prepare(
    'INSERT INTO productos (nombre, codigo_barra, categoria, precio_compra, precio_venta, pack_cantidad, precio_pack, stock)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $codigoBarra ?: null,
    $categoria ?: null,
    $precioCompra,
    $precioVenta,
    $packCantidad,
    $precioPack,
    $stock,
]);

$id = (int)$pdo->lastInsertId();
$stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
$stmt->execute([$id]);

echo json_encode([
    'ok' => true,
    'producto' => $stmt->fetch(),
]);
