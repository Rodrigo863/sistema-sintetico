<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#productos');
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

function volverProducto(string $mensaje): void
{
    redirigir('index.php?producto_error=' . rawurlencode($mensaje) . '#productos');
}

if ($nombre === '') {
    volverProducto('El nombre del producto es obligatorio.');
}

if ($precioCompra < 0 || $precioVenta < 0 || $packCantidad < 0 || $precioCompraPack < 0 || $precioPack < 0 || $promocionCantidad < 0 || $precioPromocion < 0 || $stock < 0) {
    volverProducto('Precios, pack, promocion y stock deben ser valores validos.');
}

if ($packCantidad > 0 && $precioCompra <= 0 && $precioCompraPack > 0) {
    $precioCompra = ceil($precioCompraPack / $packCantidad);
}

if ($categoria === '') {
    volverProducto('La categoria del producto es obligatoria.');
}

if ($precioCompra <= 0 || $precioVenta <= 0) {
    volverProducto('Ingresa precio de compra unidad y precio de venta unidad mayores a cero.');
}

if ($packCantidad > 0 && ($precioCompraPack <= 0 || $precioPack <= 0)) {
    volverProducto('Si cargas unidades por pack, ingresa precio compra pack y precio de venta pack.');
}

if ($packCantidad <= 0 && ($precioCompraPack > 0 || $precioPack > 0)) {
    volverProducto('Para usar precios por pack, ingresa tambien las unidades por pack.');
}

if (($promocionCantidad > 0) !== ($precioPromocion > 0)) {
    volverProducto('Para usar una promocion, ingresa sus unidades y su precio; o deja ambos en cero.');
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
        volverProducto('Proveedor no encontrado.');
    }
}

$stmt = $pdo->prepare('SELECT id FROM productos WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->execute([$nombre]);

if ($stmt->fetch()) {
    volverProducto('Ya existe un producto con ese nombre.');
}

if ($codigoBarra !== '') {
    $stmt = $pdo->prepare('SELECT id FROM productos WHERE codigo_barra = ? LIMIT 1');
    $stmt->execute([$codigoBarra]);

    if ($stmt->fetch()) {
        volverProducto('Ya existe un producto con ese codigo de barras.');
    }
}

if ($categoria !== '') {
    $stmt = $pdo->prepare("SELECT nombre FROM producto_categorias WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND estado = 'activo' LIMIT 1");
    $stmt->execute([$categoria]);
    $categoriaActiva = $stmt->fetch();

    if (!$categoriaActiva) {
        volverProducto('Primero carga la categoria en el modulo Categorias.');
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

redirigir('index.php#productos');
