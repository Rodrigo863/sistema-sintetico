<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$codigoBarra = trim($_POST['codigo_barra'] ?? '');
$proveedorId = (int)($_POST['proveedor_id'] ?? 0);
$categoria = trim($_POST['categoria'] ?? '');
$precioCompra = leerMonto($_POST['precio_compra'] ?? 0);
$precioVenta = leerMonto($_POST['precio_venta'] ?? 0);
$packCantidad = (int)($_POST['pack_cantidad'] ?? 0);
$precioCompraPack = leerMonto($_POST['precio_compra_pack'] ?? 0);
$precioPack = leerMonto($_POST['precio_pack'] ?? 0);
$promocionCantidad = (int)($_POST['promocion_cantidad'] ?? 0);
$precioPromocion = leerMonto($_POST['precio_promocion'] ?? 0);
$stock = (int)($_POST['stock'] ?? 0);
$origen = $_POST['origen'] ?? '';

if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'El nombre del producto es obligatorio.']);
    exit;
}

if ($precioCompra < 0 || $precioVenta < 0 || $packCantidad < 0 || $precioCompraPack < 0 || $precioPack < 0 || $promocionCantidad < 0 || $precioPromocion < 0 || $stock < 0) {
    echo json_encode(['ok' => false, 'error' => 'Precios, pack, promocion y stock deben ser valores validos.']);
    exit;
}

if ($packCantidad > 0 && $precioCompra <= 0 && $precioCompraPack > 0) {
    $precioCompra = ceil($precioCompraPack / $packCantidad);
}

if ($categoria === '') {
    echo json_encode(['ok' => false, 'error' => 'La categoria del producto es obligatoria.']);
    exit;
}

if ($precioCompra <= 0 || $precioVenta <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Ingresa precio de compra unidad y precio de venta unidad mayores a cero.']);
    exit;
}

if ($packCantidad > 0 && ($precioCompraPack <= 0 || $precioPack <= 0)) {
    echo json_encode(['ok' => false, 'error' => 'Si cargas unidades por pack, ingresa precio compra pack y precio de venta pack.']);
    exit;
}

if ($packCantidad <= 0 && ($precioCompraPack > 0 || $precioPack > 0)) {
    echo json_encode(['ok' => false, 'error' => 'Para usar precios por pack, ingresa tambien las unidades por pack.']);
    exit;
}

if (($promocionCantidad > 0) !== ($precioPromocion > 0)) {
    echo json_encode(['ok' => false, 'error' => 'Para usar una promocion, ingresa sus unidades y su precio; o deja ambos en cero.']);
    exit;
}

if ($origen === 'compra' && $stock <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Para cargar desde compras, ingresa stock inicial valido.']);
    exit;
}

$pdo = conectarDB();
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS proveedor_id INT DEFAULT NULL AFTER categoria");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pack_cantidad");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS promocion_cantidad INT NOT NULL DEFAULT 0 AFTER precio_pack");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_promocion DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER promocion_cantidad");
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

if ($proveedorId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ?');
    $stmt->execute([$proveedorId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Proveedor no encontrado.']);
        exit;
    }
}

$stmt = $pdo->prepare(
    'SELECT p.*, pv.nombre AS proveedor
     FROM productos p
     LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
     WHERE LOWER(TRIM(p.nombre)) = LOWER(TRIM(?))
     LIMIT 1'
);
$stmt->execute([$nombre]);
$productoExistente = $stmt->fetch();

if (!$productoExistente && $codigoBarra !== '') {
    $stmt = $pdo->prepare(
        'SELECT p.*, pv.nombre AS proveedor
         FROM productos p
         LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
         WHERE p.codigo_barra = ?
         LIMIT 1'
    );
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
    'INSERT INTO productos (nombre, codigo_barra, proveedor_id, categoria, precio_compra, precio_venta, pack_cantidad, precio_compra_pack, precio_pack, promocion_cantidad, precio_promocion, stock)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $nombre,
    $codigoBarra ?: null,
    $proveedorId > 0 ? $proveedorId : null,
    $categoria ?: null,
    $precioCompra,
    $precioVenta,
    $packCantidad,
    $precioCompraPack,
    $precioPack,
    $promocionCantidad,
    $precioPromocion,
    $stock,
]);

$id = (int)$pdo->lastInsertId();
$stmt = $pdo->prepare(
    'SELECT p.*, pv.nombre AS proveedor
     FROM productos p
     LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
     WHERE p.id = ?'
);
$stmt->execute([$id]);

echo json_encode([
    'ok' => true,
    'producto' => $stmt->fetch(),
]);
