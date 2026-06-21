CREATE DATABASE IF NOT EXISTS sistema_clientes;
USE sistema_clientes;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(100) DEFAULT NULL,
  telefono VARCHAR(30) NOT NULL,
  documento VARCHAR(40) DEFAULT NULL,
  direccion VARCHAR(180) DEFAULT NULL,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  notas TEXT DEFAULT NULL,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  ruc VARCHAR(40) DEFAULT NULL,
  direccion VARCHAR(180) DEFAULT NULL,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  notas TEXT DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS canchas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  tipo VARCHAR(80) DEFAULT NULL,
  precio_hora DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado ENUM('activa', 'mantenimiento', 'inactiva') NOT NULL DEFAULT 'activa',
  notas TEXT DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  usuario VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('administrador', 'usuario') NOT NULL DEFAULT 'usuario',
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  ultimo_acceso DATETIME DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  cancha_id INT NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  precio_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado ENUM('reservado', 'confirmado', 'cancelado', 'finalizado') NOT NULL DEFAULT 'reservado',
  observacion TEXT DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_reservas_cancha FOREIGN KEY (cancha_id) REFERENCES canchas(id),
  INDEX idx_reserva_fecha_cancha (fecha, cancha_id, hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  metodo ENUM('efectivo', 'transferencia', 'tarjeta', 'otro') NOT NULL DEFAULT 'efectivo',
  concepto ENUM('sena', 'saldo', 'total', 'extra') NOT NULL DEFAULT 'sena',
  fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion TEXT DEFAULT NULL,
  comprobante_path VARCHAR(255) DEFAULT NULL,
  CONSTRAINT fk_pagos_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo_barra VARCHAR(80) DEFAULT NULL,
  categoria VARCHAR(80) DEFAULT NULL,
  precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0,
  pack_cantidad INT NOT NULL DEFAULT 0,
  precio_pack DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS producto_categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_producto_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT DEFAULT NULL,
  cliente_id INT DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  metodo ENUM('efectivo', 'transferencia', 'tarjeta', 'otro') NOT NULL DEFAULT 'efectivo',
  estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa',
  fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion TEXT DEFAULT NULL,
  CONSTRAINT fk_ventas_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proveedor_id INT DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  metodo ENUM('efectivo', 'transferencia', 'tarjeta', 'otro') NOT NULL DEFAULT 'efectivo',
  estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa',
  fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion TEXT DEFAULT NULL,
  CONSTRAINT fk_compras_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compra_detalles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  compra_id INT NOT NULL,
  producto_id INT NOT NULL,
  tipo_compra ENUM('unidad', 'pack') NOT NULL DEFAULT 'unidad',
  cantidad INT NOT NULL,
  unidades_agregadas INT NOT NULL DEFAULT 0,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detalles_compra FOREIGN KEY (compra_id) REFERENCES compras(id),
  CONSTRAINT fk_detalles_compra_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venta_detalles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venta_id INT NOT NULL,
  producto_id INT NOT NULL,
  tipo_venta ENUM('unidad', 'pack') NOT NULL DEFAULT 'unidad',
  cantidad INT NOT NULL,
  unidades_descontadas INT NOT NULL DEFAULT 0,
  precio_unitario DECIMAL(10,2) NOT NULL,
  costo_unitario DECIMAL(10,2) DEFAULT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detalles_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT fk_detalles_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS documento VARCHAR(40) DEFAULT NULL AFTER telefono;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS direccion VARCHAR(180) DEFAULT NULL AFTER documento;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo' AFTER direccion;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS notas TEXT DEFAULT NULL AFTER estado;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER notas;
ALTER TABLE clientes MODIFY email VARCHAR(100) DEFAULT NULL;
ALTER TABLE clientes MODIFY telefono VARCHAR(30) NOT NULL;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER categoria;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS codigo_barra VARCHAR(80) DEFAULT NULL AFTER nombre;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS pack_cantidad INT NOT NULL DEFAULT 0 AFTER precio_venta;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pack_cantidad;
ALTER TABLE ventas ADD COLUMN IF NOT EXISTS cliente_id INT DEFAULT NULL AFTER reserva_id;
ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa' AFTER metodo;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS comprobante_path VARCHAR(255) DEFAULT NULL AFTER observacion;
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS tipo_venta ENUM('unidad', 'pack') NOT NULL DEFAULT 'unidad' AFTER producto_id;
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS unidades_descontadas INT NOT NULL DEFAULT 0 AFTER cantidad;
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario;

INSERT IGNORE INTO producto_categorias (nombre)
SELECT DISTINCT TRIM(categoria)
FROM productos
WHERE categoria IS NOT NULL AND TRIM(categoria) <> '';

INSERT INTO canchas (nombre, tipo, precio_hora)
SELECT 'Cancha 1', 'Futbol 5', 120000
WHERE NOT EXISTS (SELECT 1 FROM canchas WHERE nombre = 'Cancha 1');
