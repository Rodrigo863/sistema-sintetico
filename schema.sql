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
  rol ENUM('administrador', 'secretario') NOT NULL DEFAULT 'secretario',
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  ultimo_acceso DATETIME DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE usuarios MODIFY rol ENUM('administrador', 'usuario', 'secretario') NOT NULL DEFAULT 'secretario';
UPDATE usuarios SET rol = 'secretario' WHERE rol = 'usuario';
ALTER TABLE usuarios MODIFY rol ENUM('administrador', 'secretario') NOT NULL DEFAULT 'secretario';

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
  metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
  concepto ENUM('sena', 'saldo', 'total', 'extra') NOT NULL DEFAULT 'sena',
  fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion TEXT DEFAULT NULL,
  comprobante_path VARCHAR(255) DEFAULT NULL,
  CONSTRAINT fk_pagos_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS caja_jornadas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  monto_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
  monto_cierre_efectivo DECIMAL(10,2) DEFAULT NULL,
  monto_cierre_transferencia DECIMAL(10,2) DEFAULT NULL,
  estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
  abierta_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrada_en DATETIME DEFAULT NULL,
  usuario_apertura_id INT DEFAULT NULL,
  usuario_cierre_id INT DEFAULT NULL,
  observacion_apertura TEXT DEFAULT NULL,
  observacion_cierre TEXT DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_caja_jornadas_usuario_apertura FOREIGN KEY (usuario_apertura_id) REFERENCES usuarios(id),
  CONSTRAINT fk_caja_jornadas_usuario_cierre FOREIGN KEY (usuario_cierre_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS caja_movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  caja_jornada_id INT NOT NULL,
  tipo ENUM('ingreso', 'egreso') NOT NULL,
  concepto VARCHAR(120) NOT NULL,
  detalle TEXT DEFAULT NULL,
  metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
  monto DECIMAL(10,2) NOT NULL,
  fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_caja_movimientos_jornada FOREIGN KEY (caja_jornada_id) REFERENCES caja_jornadas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo_barra VARCHAR(80) DEFAULT NULL,
  categoria VARCHAR(80) DEFAULT NULL,
  proveedor_id INT DEFAULT NULL,
  precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0,
  pack_cantidad INT NOT NULL DEFAULT 0,
  precio_compra_pack DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_pack DECIMAL(10,2) NOT NULL DEFAULT 0,
  promocion_cantidad INT NOT NULL DEFAULT 0,
  precio_promocion DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
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
  metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
  estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa',
  fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion TEXT DEFAULT NULL,
  CONSTRAINT fk_ventas_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS empresa_configuracion (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  razon_social VARCHAR(160) DEFAULT NULL,
  nombre_fantasia VARCHAR(160) DEFAULT NULL,
  ruc VARCHAR(20) DEFAULT NULL,
  actividad_economica VARCHAR(180) DEFAULT NULL,
  direccion VARCHAR(220) DEFAULT NULL,
  telefono VARCHAR(40) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  modalidad_facturacion ENUM('pendiente', 'autoimpresor', 'ekuatia', 'ekuatia_i') NOT NULL DEFAULT 'pendiente',
  ancho_papel_mm ENUM('58', '80') NOT NULL DEFAULT '80',
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comprobante_numeraciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  tipo_documento ENUM('factura') NOT NULL DEFAULT 'factura',
  timbrado VARCHAR(20) DEFAULT NULL,
  establecimiento CHAR(3) DEFAULT NULL,
  punto_expedicion CHAR(3) DEFAULT NULL,
  numero_desde INT NOT NULL DEFAULT 1,
  numero_hasta INT NOT NULL DEFAULT 9999999,
  ultimo_numero INT NOT NULL DEFAULT 0,
  vigencia_desde DATE DEFAULT NULL,
  vigencia_hasta DATE DEFAULT NULL,
  estado ENUM('pendiente', 'activo', 'inactivo') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_numeracion_empresa FOREIGN KEY (empresa_id) REFERENCES empresa_configuracion(id),
  UNIQUE KEY uq_numeracion_fiscal (empresa_id, tipo_documento, timbrado, establecimiento, punto_expedicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO empresa_configuracion (id) VALUES (1);

CREATE TABLE IF NOT EXISTS compras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proveedor_id INT DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
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
  tipo_venta ENUM('unidad', 'pack', 'promocion') NOT NULL DEFAULT 'unidad',
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
ALTER TABLE productos ADD COLUMN IF NOT EXISTS proveedor_id INT DEFAULT NULL AFTER categoria;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS pack_cantidad INT NOT NULL DEFAULT 0 AFTER precio_venta;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pack_cantidad;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio_compra_pack;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS promocion_cantidad INT NOT NULL DEFAULT 0 AFTER precio_pack;
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_promocion DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER promocion_cantidad;
ALTER TABLE ventas ADD COLUMN IF NOT EXISTS cliente_id INT DEFAULT NULL AFTER reserva_id;
ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa' AFTER metodo;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS comprobante_path VARCHAR(255) DEFAULT NULL AFTER observacion;
ALTER TABLE caja_jornadas ADD COLUMN IF NOT EXISTS usuario_apertura_id INT DEFAULT NULL AFTER cerrada_en;
ALTER TABLE caja_jornadas ADD COLUMN IF NOT EXISTS usuario_cierre_id INT DEFAULT NULL AFTER usuario_apertura_id;
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS tipo_venta ENUM('unidad', 'pack') NOT NULL DEFAULT 'unidad' AFTER producto_id;
ALTER TABLE venta_detalles MODIFY tipo_venta ENUM('unidad', 'pack', 'promocion') NOT NULL DEFAULT 'unidad';
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS unidades_descontadas INT NOT NULL DEFAULT 0 AFTER cantidad;
ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario;

INSERT IGNORE INTO producto_categorias (nombre)
SELECT DISTINCT TRIM(categoria)
FROM productos
WHERE categoria IS NOT NULL AND TRIM(categoria) <> '';

INSERT INTO canchas (nombre, tipo, precio_hora)
SELECT 'Cancha 1', 'Futbol 5', 120000
WHERE NOT EXISTS (SELECT 1 FROM canchas WHERE nombre = 'Cancha 1');
