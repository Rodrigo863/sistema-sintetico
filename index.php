<?php
require 'config.php';

$pdo = conectarDB();
$esAdministrador = esAdministrador();
$pdo->exec("ALTER TABLE ventas ADD COLUMN IF NOT EXISTS estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa' AFTER metodo");
$pdo->exec("ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario");
$pdo->exec("ALTER TABLE pagos ADD COLUMN IF NOT EXISTS comprobante_path VARCHAR(255) DEFAULT NULL AFTER observacion");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra_pack DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pack_cantidad");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS promocion_cantidad INT NOT NULL DEFAULT 0 AFTER precio_pack");
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_promocion DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER promocion_cantidad");
$pdo->exec("ALTER TABLE venta_detalles MODIFY tipo_venta ENUM('unidad', 'pack', 'promocion') NOT NULL DEFAULT 'unidad'");
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS empresa_configuracion (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec("INSERT IGNORE INTO empresa_configuracion (id) VALUES (1)");
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS comprobante_numeraciones (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
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
$pdo->exec(
    "INSERT IGNORE INTO producto_categorias (nombre)
     SELECT DISTINCT TRIM(categoria)
     FROM productos
     WHERE categoria IS NOT NULL AND TRIM(categoria) <> ''"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS proveedores (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS proveedor_id INT DEFAULT NULL AFTER categoria");
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS compras (
      id INT AUTO_INCREMENT PRIMARY KEY,
      proveedor_id INT DEFAULT NULL,
      total DECIMAL(10,2) NOT NULL DEFAULT 0,
      metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
      estado ENUM('activa', 'anulada') NOT NULL DEFAULT 'activa',
      fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      observacion TEXT DEFAULT NULL,
      CONSTRAINT fk_compras_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS compra_detalles (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_jornadas (
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
      actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec("ALTER TABLE caja_jornadas ADD COLUMN IF NOT EXISTS usuario_apertura_id INT DEFAULT NULL AFTER cerrada_en");
$pdo->exec("ALTER TABLE caja_jornadas ADD COLUMN IF NOT EXISTS usuario_cierre_id INT DEFAULT NULL AFTER usuario_apertura_id");
$cajaIndiceUnico = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'caja_jornadas'
       AND INDEX_NAME = 'uq_caja_jornadas_fecha'"
)->fetchColumn();
if ($cajaIndiceUnico > 0) {
    $pdo->exec("ALTER TABLE caja_jornadas DROP INDEX uq_caja_jornadas_fecha");
}
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_movimientos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS caja_abonos_pendientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      reserva_id INT NOT NULL,
      monto DECIMAL(10,2) NOT NULL,
      metodo ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
      concepto ENUM('sena', 'saldo', 'total', 'extra') NOT NULL DEFAULT 'sena',
      comprobante_path VARCHAR(255) DEFAULT NULL,
      comprobante_confirmado TINYINT(1) NOT NULL DEFAULT 0,
      observacion TEXT DEFAULT NULL,
      estado ENUM('revision', 'pendiente', 'confirmado') NOT NULL DEFAULT 'revision',
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      confirmado_en DATETIME DEFAULT NULL,
      CONSTRAINT fk_abonos_pendientes_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec("ALTER TABLE caja_abonos_pendientes MODIFY estado ENUM('revision', 'pendiente', 'confirmado') NOT NULL DEFAULT 'revision'");
$pdo->exec("ALTER TABLE caja_abonos_pendientes ADD COLUMN IF NOT EXISTS comprobante_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER comprobante_path");
$hoy = date('Y-m-d');
$proximaReservaId = (int)$pdo->query(
    "SELECT AUTO_INCREMENT
     FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'reservas'"
)->fetchColumn();
if ($proximaReservaId <= 0) {
    $proximaReservaId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM reservas")->fetchColumn();
}

$clientes = $pdo->query("SELECT * FROM clientes WHERE estado = 'activo' ORDER BY nombre")->fetchAll();
$todosClientes = $pdo->query("SELECT * FROM clientes ORDER BY nombre")->fetchAll();
$proveedores = $pdo->query("SELECT * FROM proveedores ORDER BY nombre")->fetchAll();
$canchas = $pdo->query("SELECT * FROM canchas ORDER BY nombre")->fetchAll();
$productos = $pdo->query(
    "SELECT p.*, pv.nombre AS proveedor
     FROM productos p
     LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
     ORDER BY p.nombre"
)->fetchAll();
$categorias = $pdo->query("SELECT * FROM producto_categorias ORDER BY estado ASC, nombre ASC")->fetchAll();
$categoriasActivas = array_values(array_filter($categorias, fn($categoria) => $categoria['estado'] === 'activo'));
$usuariosSistema = $esAdministrador
    ? $pdo->query("SELECT id, nombre, usuario, rol, estado, ultimo_acceso, creado_en FROM usuarios ORDER BY estado ASC, nombre ASC")->fetchAll()
    : [];
$empresaConfiguracion = $pdo->query("SELECT * FROM empresa_configuracion WHERE id = 1")->fetch() ?: [];
$numeracionFactura = $pdo->query(
    "SELECT * FROM comprobante_numeraciones
     WHERE empresa_id = 1 AND tipo_documento = 'factura'
     ORDER BY (estado = 'activo') DESC, id DESC
     LIMIT 1"
)->fetch() ?: [];
$facturacionLista = ($empresaConfiguracion['razon_social'] ?? '') !== ''
    && ($empresaConfiguracion['ruc'] ?? '') !== ''
    && ($empresaConfiguracion['direccion'] ?? '') !== ''
    && ($empresaConfiguracion['modalidad_facturacion'] ?? 'pendiente') !== 'pendiente'
    && ($numeracionFactura['timbrado'] ?? '') !== ''
    && ($numeracionFactura['establecimiento'] ?? '') !== ''
    && ($numeracionFactura['punto_expedicion'] ?? '') !== ''
    && !empty($numeracionFactura['vigencia_desde'])
    && !empty($numeracionFactura['vigencia_hasta'])
    && ($numeracionFactura['estado'] ?? 'pendiente') === 'activo'
    && (int)($numeracionFactura['ultimo_numero'] ?? 0) < (int)($numeracionFactura['numero_hasta'] ?? 0);
$facturacionMensaje = trim($_GET['facturacion_mensaje'] ?? '');
$facturacionError = trim($_GET['facturacion_error'] ?? '');
$usuarioMensaje = trim($_GET['usuario_mensaje'] ?? '');
$usuarioError = trim($_GET['usuario_error'] ?? '');

$productosPorPagina = 10;
$productoBuscar = trim($_GET['producto_buscar'] ?? '');
$productoPagina = max(1, (int)($_GET['producto_pagina'] ?? 1));
$productoWhere = '';
$productoParams = [];

if ($productoBuscar !== '') {
    $productoWhere = "WHERE (
        p.nombre LIKE ?
        OR p.codigo_barra LIKE ?
        OR p.categoria LIKE ?
        OR pv.nombre LIKE ?
        OR p.estado LIKE ?
    )";
    $productoLike = '%' . $productoBuscar . '%';
    $productoParams = [$productoLike, $productoLike, $productoLike, $productoLike, $productoLike];
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM productos p
     LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
     $productoWhere"
);
$stmt->execute($productoParams);
$productosTotal = (int)$stmt->fetchColumn();
$productosPaginas = max(1, (int)ceil($productosTotal / $productosPorPagina));
$productoPagina = min($productoPagina, $productosPaginas);
$productosOffset = ($productoPagina - 1) * $productosPorPagina;

$stmt = $pdo->prepare(
    "SELECT p.*, pv.nombre AS proveedor
     FROM productos p
     LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
     $productoWhere
     ORDER BY p.nombre
     LIMIT $productosPorPagina OFFSET $productosOffset"
);
$stmt->execute($productoParams);
$productosInventario = $stmt->fetchAll();

$reservas = $pdo->query(
    "SELECT r.*, c.nombre AS cliente, c.telefono, ca.nombre AS cancha, ca.precio_hora,
            COALESCE(pagos.total_pagado, 0) AS pagado,
            COALESCE(pagos_metodo.pagado_efectivo, 0) AS pagado_efectivo,
            COALESCE(pagos_metodo.pagado_transferencia, 0) AS pagado_transferencia,
            COALESCE(consumos.total_consumo, 0) AS consumo_total,
            r.precio_total + COALESCE(consumos.total_consumo, 0) AS total_alcanzado,
            GREATEST((r.precio_total + COALESCE(consumos.total_consumo, 0)) - COALESCE(pagos.total_pagado, 0), 0) AS saldo,
            ultimo_pago.id AS ultimo_pago_id,
            ultimo_pago.metodo AS ultimo_pago_metodo,
            comprobante_pago.comprobante_path AS comprobante_path,
            abono_pendiente.id AS abono_pendiente_id,
            abono_pendiente.monto AS abono_pendiente_monto,
            abono_pendiente.comprobante_path AS abono_pendiente_comprobante_path,
            abono_pendiente.comprobante_confirmado AS abono_pendiente_comprobante_confirmado,
            abono_pendiente.estado AS abono_pendiente_estado
     FROM reservas r
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     LEFT JOIN (
       SELECT reserva_id, SUM(monto) AS total_pagado
       FROM pagos
       GROUP BY reserva_id
     ) pagos ON pagos.reserva_id = r.id
     LEFT JOIN (
       SELECT reserva_id,
              SUM(CASE WHEN metodo = 'efectivo' THEN monto ELSE 0 END) AS pagado_efectivo,
              SUM(CASE WHEN metodo = 'transferencia' THEN monto ELSE 0 END) AS pagado_transferencia
       FROM pagos
       GROUP BY reserva_id
     ) pagos_metodo ON pagos_metodo.reserva_id = r.id
     LEFT JOIN (
       SELECT reserva_id, SUM(total) AS total_consumo
       FROM ventas
       WHERE reserva_id IS NOT NULL AND estado <> 'anulada'
       GROUP BY reserva_id
     ) consumos ON consumos.reserva_id = r.id
     LEFT JOIN pagos ultimo_pago ON ultimo_pago.id = (
       SELECT p2.id
       FROM pagos p2
       WHERE p2.reserva_id = r.id
       ORDER BY p2.fecha_pago DESC, p2.id DESC
       LIMIT 1
     )
     LEFT JOIN pagos comprobante_pago ON comprobante_pago.id = (
       SELECT p3.id
       FROM pagos p3
       WHERE p3.reserva_id = r.id
         AND p3.comprobante_path IS NOT NULL
         AND p3.comprobante_path <> ''
       ORDER BY p3.fecha_pago DESC, p3.id DESC
       LIMIT 1
     )
     LEFT JOIN caja_abonos_pendientes abono_pendiente ON abono_pendiente.id = (
       SELECT ap.id
       FROM caja_abonos_pendientes ap
       WHERE ap.reserva_id = r.id
         AND ap.estado IN ('revision', 'pendiente')
       ORDER BY ap.creado_en DESC, ap.id DESC
       LIMIT 1
     )
     WHERE r.fecha >= CURDATE()
     ORDER BY r.fecha ASC, r.hora_inicio ASC"
)->fetchAll();

$consumosReservas = [];
$reservaIds = array_map('intval', array_column($reservas, 'id'));
if (!empty($reservaIds)) {
    $reservaPlaceholders = implode(',', array_fill(0, count($reservaIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT v.reserva_id, v.id AS venta_id, v.fecha_venta, v.metodo, v.observacion,
                p.nombre AS producto, vd.tipo_venta, vd.cantidad, vd.precio_unitario, vd.subtotal
         FROM ventas v
         INNER JOIN venta_detalles vd ON vd.venta_id = v.id
         INNER JOIN productos p ON p.id = vd.producto_id
         WHERE v.reserva_id IN ($reservaPlaceholders)
           AND v.estado <> 'anulada'
         ORDER BY v.fecha_venta ASC, v.id ASC, vd.id ASC"
    );
    $stmt->execute($reservaIds);
    foreach ($stmt->fetchAll() as $consumoReserva) {
        $consumosReservas[(int)$consumoReserva['reserva_id']][] = $consumoReserva;
    }
}

$pagos = $pdo->query(
    "SELECT p.*, r.fecha, r.hora_inicio, c.nombre AS cliente, ca.nombre AS cancha
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     ORDER BY p.fecha_pago DESC
     LIMIT 20"
)->fetchAll();

$ventasPorPagina = 10;
$ventaBuscar = trim($_GET['venta_buscar'] ?? '');
$ventaPagina = max(1, (int)($_GET['venta_pagina'] ?? 1));
$ventaWhere = '';
$ventaParams = [];

if ($ventaBuscar !== '') {
    $ventaWhere = "WHERE (
        v.id = ?
        OR v.metodo LIKE ?
        OR v.estado LIKE ?
        OR DATE_FORMAT(v.fecha_venta, '%d/%m/%Y %H:%i') LIKE ?
        OR DATE_FORMAT(v.fecha_venta, '%Y-%m-%d') LIKE ?
        OR COALESCE(cl.nombre, 'Sin cliente') LIKE ?
        OR ca.nombre LIKE ?
        OR EXISTS (
            SELECT 1
            FROM venta_detalles vds
            INNER JOIN productos ps ON ps.id = vds.producto_id
            WHERE vds.venta_id = v.id
              AND (ps.nombre LIKE ? OR ps.codigo_barra LIKE ? OR ps.categoria LIKE ?)
        )
    )";
    $ventaLike = '%' . $ventaBuscar . '%';
    $ventaParams = [
        ctype_digit($ventaBuscar) ? (int)$ventaBuscar : 0,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
        $ventaLike,
    ];
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM ventas v
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     LEFT JOIN reservas r ON r.id = v.reserva_id
     LEFT JOIN canchas ca ON ca.id = r.cancha_id
     $ventaWhere"
);
$stmt->execute($ventaParams);
$ventasTotal = (int)$stmt->fetchColumn();
$ventasPaginas = max(1, (int)ceil($ventasTotal / $ventasPorPagina));
$ventaPagina = min($ventaPagina, $ventasPaginas);
$ventasOffset = ($ventaPagina - 1) * $ventasPorPagina;

$stmt = $pdo->prepare(
    "SELECT v.*, cl.nombre AS cliente, r.fecha AS reserva_fecha, ca.nombre AS reserva_cancha,
            COUNT(vd.id) AS items,
            COALESCE(SUM(vd.unidades_descontadas), 0) AS unidades
     FROM ventas v
     LEFT JOIN venta_detalles vd ON vd.venta_id = v.id
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     LEFT JOIN reservas r ON r.id = v.reserva_id
     LEFT JOIN canchas ca ON ca.id = r.cancha_id
     $ventaWhere
     GROUP BY v.id
     ORDER BY v.fecha_venta DESC
     LIMIT $ventasPorPagina OFFSET $ventasOffset"
);
$stmt->execute($ventaParams);
$ventas = $stmt->fetchAll();

$todasVentas = $pdo->query(
    "SELECT v.*, cl.nombre AS cliente, r.fecha AS reserva_fecha, ca.nombre AS reserva_cancha,
            COUNT(vd.id) AS items,
            COALESCE(SUM(vd.unidades_descontadas), 0) AS unidades
     FROM ventas v
     LEFT JOIN venta_detalles vd ON vd.venta_id = v.id
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     LEFT JOIN reservas r ON r.id = v.reserva_id
     LEFT JOIN canchas ca ON ca.id = r.cancha_id
     GROUP BY v.id
     ORDER BY v.fecha_venta DESC"
)->fetchAll();

$ventaDetalles = [];
$ventasIds = array_map('intval', array_column($todasVentas, 'id'));
if (!empty($ventasIds)) {
    $ventasPlaceholders = implode(',', array_fill(0, count($ventasIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT vd.*, p.nombre AS producto, v.fecha_venta, v.total, v.metodo, v.observacion,
                cl.nombre AS cliente, r.fecha AS reserva_fecha, ca.nombre AS reserva_cancha
         FROM venta_detalles vd
         INNER JOIN ventas v ON v.id = vd.venta_id
         INNER JOIN productos p ON p.id = vd.producto_id
         LEFT JOIN clientes cl ON cl.id = v.cliente_id
         LEFT JOIN reservas r ON r.id = v.reserva_id
         LEFT JOIN canchas ca ON ca.id = r.cancha_id
         WHERE vd.venta_id IN ($ventasPlaceholders)
         ORDER BY v.fecha_venta DESC, vd.id ASC"
    );
    $stmt->execute($ventasIds);
    $ventaDetalles = $stmt->fetchAll();
}

$comprasPorPagina = 10;
$compraBuscar = trim($_GET['compra_buscar'] ?? '');
$compraPagina = max(1, (int)($_GET['compra_pagina'] ?? 1));
$compraWhere = '';
$compraParams = [];

if ($compraBuscar !== '') {
    $compraWhere = "WHERE (
        c.id = ?
        OR c.metodo LIKE ?
        OR c.estado LIKE ?
        OR DATE_FORMAT(c.fecha_compra, '%d/%m/%Y %H:%i') LIKE ?
        OR DATE_FORMAT(c.fecha_compra, '%Y-%m-%d') LIKE ?
        OR COALESCE(pv.nombre, 'Sin proveedor') LIKE ?
        OR EXISTS (
            SELECT 1
            FROM compra_detalles cds
            INNER JOIN productos ps ON ps.id = cds.producto_id
            WHERE cds.compra_id = c.id
              AND (ps.nombre LIKE ? OR ps.codigo_barra LIKE ? OR ps.categoria LIKE ?)
        )
    )";
    $compraLike = '%' . $compraBuscar . '%';
    $compraParams = [
        ctype_digit($compraBuscar) ? (int)$compraBuscar : 0,
        $compraLike,
        $compraLike,
        $compraLike,
        $compraLike,
        $compraLike,
        $compraLike,
        $compraLike,
        $compraLike,
    ];
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM compras c
     LEFT JOIN proveedores pv ON pv.id = c.proveedor_id
     $compraWhere"
);
$stmt->execute($compraParams);
$comprasTotal = (int)$stmt->fetchColumn();
$comprasPaginas = max(1, (int)ceil($comprasTotal / $comprasPorPagina));
$compraPagina = min($compraPagina, $comprasPaginas);
$comprasOffset = ($compraPagina - 1) * $comprasPorPagina;

$stmt = $pdo->prepare(
    "SELECT c.*, pv.nombre AS proveedor,
            COUNT(cd.id) AS items,
            COALESCE(SUM(cd.unidades_agregadas), 0) AS unidades
     FROM compras c
     LEFT JOIN compra_detalles cd ON cd.compra_id = c.id
     LEFT JOIN proveedores pv ON pv.id = c.proveedor_id
     $compraWhere
     GROUP BY c.id
     ORDER BY c.fecha_compra DESC
     LIMIT $comprasPorPagina OFFSET $comprasOffset"
);
$stmt->execute($compraParams);
$compras = $stmt->fetchAll();

$todasCompras = $pdo->query(
    "SELECT c.*, pv.nombre AS proveedor,
            COUNT(cd.id) AS items,
            COALESCE(SUM(cd.unidades_agregadas), 0) AS unidades
     FROM compras c
     LEFT JOIN compra_detalles cd ON cd.compra_id = c.id
     LEFT JOIN proveedores pv ON pv.id = c.proveedor_id
     GROUP BY c.id
     ORDER BY c.fecha_compra DESC"
)->fetchAll();

$compraDetalles = [];
$comprasIds = array_map('intval', array_column($todasCompras, 'id'));
if (!empty($comprasIds)) {
    $comprasPlaceholders = implode(',', array_fill(0, count($comprasIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT cd.*, p.nombre AS producto, p.estado AS producto_estado, p.stock AS producto_stock,
                p.pack_cantidad AS producto_pack_cantidad, p.precio_compra AS producto_precio_compra,
                p.precio_compra_pack AS producto_precio_compra_pack, p.precio_venta AS producto_precio_venta,
                p.precio_pack AS producto_precio_pack,
                c.fecha_compra, c.total, c.metodo, c.estado, c.observacion,
                pv.nombre AS proveedor
         FROM compra_detalles cd
         INNER JOIN compras c ON c.id = cd.compra_id
         INNER JOIN productos p ON p.id = cd.producto_id
         LEFT JOIN proveedores pv ON pv.id = c.proveedor_id
         WHERE cd.compra_id IN ($comprasPlaceholders)
         ORDER BY c.fecha_compra DESC, cd.id ASC"
    );
    $stmt->execute($comprasIds);
    $compraDetalles = $stmt->fetchAll();
}

$totalReservasHoy = (int)$pdo->query("SELECT COUNT(*) FROM reservas WHERE fecha = CURDATE() AND estado <> 'cancelado'")->fetchColumn();
$ingresosHoy = (float)$pdo->query("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE DATE(fecha_pago) = CURDATE()")->fetchColumn();
$ventasHoy = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND estado <> 'anulada'")->fetchColumn();
$saldoPendiente = (float)$pdo->query(
    "SELECT COALESCE(SUM(deuda.saldo), 0)
     FROM (
       SELECT GREATEST((r.precio_total + COALESCE(consumos.total_consumo, 0)) - COALESCE(SUM(p.monto), 0), 0) AS saldo
       FROM reservas r
       LEFT JOIN pagos p ON p.reserva_id = r.id
       LEFT JOIN (
         SELECT reserva_id, SUM(total) AS total_consumo
         FROM ventas
         WHERE estado <> 'anulada'
         GROUP BY reserva_id
       ) consumos ON consumos.reserva_id = r.id
       WHERE r.estado <> 'cancelado'
       GROUP BY r.id
     ) deuda
     WHERE deuda.saldo > 0"
)->fetchColumn();
// La caja siempre corresponde al dia actual. No se permite consultar ni operar
// otra fecha mediante parametros manipulados en la URL.
$cajaFecha = $hoy;

$stmt = $pdo->prepare(
    "SELECT cj.*,
            ua.nombre AS usuario_apertura_nombre,
            ua.usuario AS usuario_apertura_usuario,
            uc.nombre AS usuario_cierre_nombre,
            uc.usuario AS usuario_cierre_usuario
     FROM caja_jornadas cj
     LEFT JOIN usuarios ua ON ua.id = cj.usuario_apertura_id
     LEFT JOIN usuarios uc ON uc.id = cj.usuario_cierre_id
     WHERE cj.fecha = ?
     ORDER BY CASE WHEN cj.estado = 'abierta' THEN 0 ELSE 1 END, cj.id DESC
     LIMIT 1"
);
$stmt->execute([$cajaFecha]);
$cajaJornada = $stmt->fetch();
$cajaMensaje = trim($_GET['caja_mensaje'] ?? '');
$cajaError = trim($_GET['caja_error'] ?? '');
$cajaJornadaId = (int)($cajaJornada['id'] ?? 0);
$stmt = $pdo->query("SELECT id FROM caja_jornadas WHERE fecha = CURDATE() AND estado = 'abierta' ORDER BY id DESC LIMIT 1");
$cajaAbiertaHoy = (bool)$stmt->fetch();
$cajaInicio = $cajaJornada ? $cajaJornada['abierta_en'] : $cajaFecha . ' 00:00:00';
$cajaFin = $cajaJornada && $cajaJornada['estado'] === 'cerrada'
    ? $cajaJornada['cerrada_en']
    : $cajaFecha . ' 23:59:59';

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN ? AND ?");
$stmt->execute([$cajaInicio, $cajaFin]);
$cajaPagos = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND estado <> 'anulada' AND reserva_id IS NULL");
$stmt->execute([$cajaInicio, $cajaFin]);
$cajaVentasDirectas = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) FROM caja_movimientos WHERE caja_jornada_id = ?");
$stmt->execute([$cajaJornadaId]);
$cajaMovimientosManualesTotal = (float)$stmt->fetchColumn();

$cajaMontoInicial = (float)($cajaJornada['monto_inicial'] ?? 0);
$cajaIngresos = $cajaPagos + $cajaVentasDirectas;
$cajaSaldo = $cajaMontoInicial + $cajaIngresos + $cajaMovimientosManualesTotal;

$stmt = $pdo->prepare(
    "SELECT metodo, SUM(monto) AS total
     FROM (
       SELECT p.metodo, p.monto
       FROM pagos p
       WHERE p.fecha_pago BETWEEN ? AND ?
       UNION ALL
       SELECT v.metodo, v.total AS monto
       FROM ventas v
       WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado <> 'anulada' AND v.reserva_id IS NULL
       UNION ALL
       SELECT cm.metodo, CASE WHEN cm.tipo = 'ingreso' THEN cm.monto ELSE -cm.monto END AS monto
       FROM caja_movimientos cm
       WHERE cm.caja_jornada_id = ?
     ) caja_metodos
     GROUP BY metodo"
);
$stmt->execute([$cajaInicio, $cajaFin, $cajaInicio, $cajaFin, $cajaJornadaId]);
$cajaPorMetodo = array_fill_keys(['efectivo', 'transferencia', 'tarjeta', 'otro'], 0.0);
foreach ($stmt->fetchAll() as $metodoCaja) {
    $cajaPorMetodo[$metodoCaja['metodo']] = (float)$metodoCaja['total'];
}
$cajaSaldoEfectivo = $cajaMontoInicial + $cajaPorMetodo['efectivo'];
$cajaSaldoTransferencia = $cajaPorMetodo['transferencia'];
$cajaCierreEfectivo = isset($cajaJornada['monto_cierre_efectivo']) ? (float)$cajaJornada['monto_cierre_efectivo'] : null;
$cajaCierreTransferencia = isset($cajaJornada['monto_cierre_transferencia']) ? (float)$cajaJornada['monto_cierre_transferencia'] : null;
$cajaTotalCierre = ($cajaCierreEfectivo ?? 0) + ($cajaCierreTransferencia ?? 0);
$cajaDiferenciaEfectivo = $cajaCierreEfectivo === null ? null : $cajaCierreEfectivo - $cajaSaldoEfectivo;
$cajaDiferenciaTransferencia = $cajaCierreTransferencia === null ? null : $cajaCierreTransferencia - $cajaSaldoTransferencia;
$cajaUsuarioApertura = $cajaJornada['usuario_apertura_nombre'] ?? $cajaJornada['usuario_apertura_usuario'] ?? '';
$cajaUsuarioCierre = $cajaJornada['usuario_cierre_nombre'] ?? $cajaJornada['usuario_cierre_usuario'] ?? '';

$stmt = $pdo->prepare(
    "SELECT 'apertura' AS tipo, 'Apertura de caja' AS concepto, cj.abierta_en AS fecha, cj.monto_inicial AS monto,
            'efectivo' AS metodo,
            CONCAT(COALESCE(cj.observacion_apertura, 'Monto inicial'), ' - Usuario: ', COALESCE(ua.nombre, ua.usuario, 'Sin usuario registrado')) AS detalle
     FROM caja_jornadas cj
     LEFT JOIN usuarios ua ON ua.id = cj.usuario_apertura_id
     WHERE cj.id = ?
     UNION ALL
     SELECT 'ingreso' AS tipo, 'Pago reserva' AS concepto, MAX(p.fecha_pago) AS fecha, SUM(p.monto) AS monto,
            p.metodo, CONCAT('Reserva #', r.id, ' - ', c.nombre, ' - ', ca.nombre) AS detalle
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     WHERE p.fecha_pago BETWEEN ? AND ?
     GROUP BY r.id, c.nombre, ca.nombre, p.metodo
     UNION ALL
     SELECT 'ingreso' AS tipo, CONCAT('Venta directa #', v.id) AS concepto, v.fecha_venta AS fecha, v.total AS monto,
            v.metodo, COALESCE(cl.nombre, 'Sin cliente') AS detalle
     FROM ventas v
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado <> 'anulada' AND v.reserva_id IS NULL
     UNION ALL
     SELECT cm.tipo, cm.concepto, cm.fecha_movimiento AS fecha, cm.monto,
            cm.metodo, COALESCE(cm.detalle, 'Movimiento manual') AS detalle
     FROM caja_movimientos cm
     WHERE cm.caja_jornada_id = ?
     UNION ALL
     SELECT 'cierre' AS tipo, 'Cierre de caja' AS concepto, cj.cerrada_en AS fecha,
            COALESCE(cj.monto_cierre_efectivo, 0) + COALESCE(cj.monto_cierre_transferencia, 0) AS monto,
            'control' AS metodo,
            CONCAT(COALESCE(cj.observacion_cierre, 'Caja cerrada'), ' - Usuario: ', COALESCE(uc.nombre, uc.usuario, 'Sin usuario registrado')) AS detalle
     FROM caja_jornadas cj
     LEFT JOIN usuarios uc ON uc.id = cj.usuario_cierre_id
     WHERE cj.id = ? AND cj.estado = 'cerrada'
     ORDER BY fecha DESC"
);
$stmt->execute([$cajaJornadaId, $cajaInicio, $cajaFin, $cajaInicio, $cajaFin, $cajaJornadaId, $cajaJornadaId]);
$cajaMovimientos = $stmt->fetchAll();
$cajaMovimientosDetalle = array_values(array_filter($cajaMovimientos, fn($movimiento) => in_array($movimiento['tipo'], ['ingreso', 'egreso'], true)));

$stmt = $pdo->prepare(
    "SELECT cj.*,
            ua.nombre AS usuario_apertura_nombre,
            ua.usuario AS usuario_apertura_usuario,
            uc.nombre AS usuario_cierre_nombre,
            uc.usuario AS usuario_cierre_usuario
     FROM caja_jornadas cj
     LEFT JOIN usuarios ua ON ua.id = cj.usuario_apertura_id
     LEFT JOIN usuarios uc ON uc.id = cj.usuario_cierre_id
     WHERE cj.fecha = ? AND cj.estado = 'cerrada'
     ORDER BY cj.cerrada_en DESC, cj.id DESC
     LIMIT 5"
);
$stmt->execute([$cajaFecha]);
$cajaCierresRecientes = $stmt->fetchAll();
$cajaCierresDetalle = [];

$stmtTotalesCierre = $pdo->prepare(
    "SELECT
        COALESCE((SELECT SUM(monto) FROM pagos WHERE fecha_pago BETWEEN ? AND ?), 0) AS pagos,
        COALESCE((SELECT SUM(total) FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND estado <> 'anulada' AND reserva_id IS NULL), 0) AS ventas,
        COALESCE((SELECT SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) FROM caja_movimientos WHERE caja_jornada_id = ?), 0) AS manuales"
);
$stmtMetodosCierre = $pdo->prepare(
    "SELECT metodo, SUM(monto) AS total
     FROM (
       SELECT p.metodo, p.monto
       FROM pagos p
       WHERE p.fecha_pago BETWEEN ? AND ?
       UNION ALL
       SELECT v.metodo, v.total AS monto
       FROM ventas v
       WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado <> 'anulada' AND v.reserva_id IS NULL
       UNION ALL
       SELECT cm.metodo, CASE WHEN cm.tipo = 'ingreso' THEN cm.monto ELSE -cm.monto END AS monto
       FROM caja_movimientos cm
       WHERE cm.caja_jornada_id = ?
     ) caja_metodos
     GROUP BY metodo"
);
$stmtMovimientosCierre = $pdo->prepare(
    "SELECT 'ingreso' AS tipo, 'Pago reserva' AS concepto, p.fecha_pago AS fecha, p.monto AS monto,
            p.metodo, CONCAT('Reserva #', r.id, ' - ', c.nombre, ' - ', ca.nombre) AS detalle
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     WHERE p.fecha_pago BETWEEN ? AND ?
     UNION ALL
     SELECT 'ingreso' AS tipo, CONCAT('Venta directa #', v.id) AS concepto, v.fecha_venta AS fecha, v.total AS monto,
            v.metodo, COALESCE(cl.nombre, 'Sin cliente') AS detalle
     FROM ventas v
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado <> 'anulada' AND v.reserva_id IS NULL
     UNION ALL
     SELECT cm.tipo, cm.concepto, cm.fecha_movimiento AS fecha, cm.monto,
            cm.metodo, COALESCE(cm.detalle, 'Movimiento manual') AS detalle
     FROM caja_movimientos cm
     WHERE cm.caja_jornada_id = ?
     ORDER BY fecha DESC"
);

foreach ($cajaCierresRecientes as $cierre) {
    $inicioCierre = $cierre['abierta_en'];
    $finCierre = $cierre['cerrada_en'];
    $stmtTotalesCierre->execute([$inicioCierre, $finCierre, $inicioCierre, $finCierre, (int)$cierre['id']]);
    $totalesCierre = $stmtTotalesCierre->fetch();

    $porMetodoCierre = array_fill_keys(['efectivo', 'transferencia', 'tarjeta', 'otro'], 0.0);
    $stmtMetodosCierre->execute([$inicioCierre, $finCierre, $inicioCierre, $finCierre, (int)$cierre['id']]);
    foreach ($stmtMetodosCierre->fetchAll() as $metodoCierre) {
        $porMetodoCierre[$metodoCierre['metodo']] = (float)$metodoCierre['total'];
    }

    $stmtMovimientosCierre->execute([$inicioCierre, $finCierre, $inicioCierre, $finCierre, (int)$cierre['id']]);
    $esperadoEfectivo = (float)$cierre['monto_inicial'] + $porMetodoCierre['efectivo'];
    $esperadoTransferencia = $porMetodoCierre['transferencia'];
    $esperadoTotal = (float)$cierre['monto_inicial'] + (float)$totalesCierre['pagos'] + (float)$totalesCierre['ventas'] + (float)$totalesCierre['manuales'];
    $contadoEfectivo = (float)$cierre['monto_cierre_efectivo'];
    $contadoTransferencia = (float)$cierre['monto_cierre_transferencia'];

    $cajaCierresDetalle[(int)$cierre['id']] = [
        'esperado_total' => $esperadoTotal,
        'esperado_efectivo' => $esperadoEfectivo,
        'esperado_transferencia' => $esperadoTransferencia,
        'total_contado' => $contadoEfectivo + $contadoTransferencia,
        'diferencia_efectivo' => $contadoEfectivo - $esperadoEfectivo,
        'diferencia_transferencia' => $contadoTransferencia - $esperadoTransferencia,
        'movimientos' => $stmtMovimientosCierre->fetchAll(),
    ];
}

$abonosPendientesCaja = $pdo->query(
    "SELECT ap.*, r.fecha, r.hora_inicio, c.nombre AS cliente, ca.nombre AS cancha
     FROM caja_abonos_pendientes ap
     INNER JOIN reservas r ON r.id = ap.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     WHERE ap.estado IN ('revision', 'pendiente')
     ORDER BY ap.creado_en ASC"
)->fetchAll();
$reservaError = trim($_GET['reserva_error'] ?? '');
$clienteError = trim($_SESSION['cliente_error'] ?? '');
unset($_SESSION['cliente_error']);
$canchaError = trim($_GET['cancha_error'] ?? '');
$proveedorError = trim($_GET['proveedor_error'] ?? '');
$productoError = trim($_GET['producto_error'] ?? '');
$compraError = trim($_GET['compra_error'] ?? '');
$ventaError = trim($_GET['venta_error'] ?? '');
$accesoError = trim($_SESSION['acceso_error'] ?? '');
unset($_SESSION['acceso_error']);

$reporteDesde = trim($_GET['reporte_desde'] ?? $hoy);
$reporteHasta = trim($_GET['reporte_hasta'] ?? $hoy);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporteDesde)) {
    $reporteDesde = $hoy;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporteHasta)) {
    $reporteHasta = $hoy;
}
if ($reporteDesde > $reporteHasta) {
    [$reporteDesde, $reporteHasta] = [$reporteHasta, $reporteDesde];
}

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT v.id) AS cantidad_ventas,
            COALESCE(SUM(v.total), 0) AS total_ventas,
            COALESCE(SUM(COALESCE(detalles.ganancia, 0)), 0) AS ganancia_ventas
     FROM ventas v
     LEFT JOIN (
       SELECT vd.venta_id,
              SUM(vd.subtotal - (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0))) AS ganancia
       FROM venta_detalles vd
       INNER JOIN productos p ON p.id = vd.producto_id
       GROUP BY vd.venta_id
     ) detalles ON detalles.venta_id = v.id
     WHERE v.estado <> 'anulada'
       AND DATE(v.fecha_venta) BETWEEN ? AND ?"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reporteVentas = $stmt->fetch() ?: [];

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT p.reserva_id) AS reservas_pagadas,
            COUNT(*) AS pagos_registrados,
            COALESCE(SUM(p.monto), 0) AS total_cobrado_reservas
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     WHERE DATE(p.fecha_pago) BETWEEN ? AND ?"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reporteReservas = $stmt->fetch() ?: [];

$stmt = $pdo->prepare(
    "SELECT DATE(p.fecha_pago) AS fecha,
            COUNT(DISTINCT p.reserva_id) AS reservas_pagadas,
            COUNT(*) AS pagos_registrados,
            COALESCE(SUM(p.monto), 0) AS total_cobrado_reservas
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     WHERE DATE(p.fecha_pago) BETWEEN ? AND ?
     GROUP BY DATE(p.fecha_pago)
     ORDER BY fecha ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reporteReservasPorDia = [];
foreach ($stmt->fetchAll() as $fila) {
    $reporteReservasPorDia[$fila['fecha']] = $fila;
}

$stmt = $pdo->prepare(
    "SELECT DATE(v.fecha_venta) AS fecha,
            COUNT(DISTINCT v.id) AS cantidad_ventas,
            COALESCE(SUM(v.total), 0) AS total_ventas,
            COALESCE(SUM(COALESCE(detalles.ganancia, 0)), 0) AS ganancia_ventas
     FROM ventas v
     LEFT JOIN (
       SELECT vd.venta_id,
              SUM(vd.subtotal - (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0))) AS ganancia
       FROM venta_detalles vd
       INNER JOIN productos p ON p.id = vd.producto_id
       GROUP BY vd.venta_id
     ) detalles ON detalles.venta_id = v.id
     WHERE v.estado <> 'anulada'
       AND DATE(v.fecha_venta) BETWEEN ? AND ?
     GROUP BY DATE(v.fecha_venta)
     ORDER BY fecha ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reporteVentasPorDia = [];
foreach ($stmt->fetchAll() as $fila) {
    $reporteVentasPorDia[$fila['fecha']] = $fila;
}

$reporteFechas = array_unique(array_merge(array_keys($reporteReservasPorDia), array_keys($reporteVentasPorDia)));
sort($reporteFechas);
$ventaTipo = $_GET['venta_tipo'] ?? 'unidad';
$ventaMetodo = $_GET['venta_metodo'] ?? 'efectivo';
$ventaForm = [
    'producto_id' => (int)($_GET['venta_producto_id'] ?? 0),
    'tipo_venta' => in_array($ventaTipo, ['unidad', 'pack', 'promocion'], true) ? $ventaTipo : 'unidad',
    'cantidad' => max(0, (int)($_GET['venta_cantidad'] ?? 1)),
    'metodo' => in_array($ventaMetodo, ['efectivo', 'transferencia'], true) ? $ventaMetodo : 'efectivo',
    'observacion' => trim($_GET['venta_observacion'] ?? ''),
];
$pagoTicket = null;
$pagoTicketId = (int)($_GET['pago_ticket'] ?? ($_SESSION['pago_ticket_id'] ?? 0));
unset($_SESSION['pago_ticket_id']);
$reservaDetalleId = (int)($_GET['reserva_detalle'] ?? 0);
$reservasConComprobantePendiente = array_filter($reservas, static function (array $reserva): bool {
    return !empty($reserva['abono_pendiente_comprobante_path'])
        && ($reserva['abono_pendiente_estado'] ?? '') === 'revision';
});

if ($pagoTicketId > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, r.fecha, r.hora_inicio, r.hora_fin, r.precio_total,
                c.nombre AS cliente, c.telefono, ca.nombre AS cancha,
                TIME_TO_SEC(TIMEDIFF(r.hora_fin, r.hora_inicio)) / 3600 AS horas,
                COALESCE(consumos.total_consumo, 0) AS consumo_total,
                r.precio_total + COALESCE(consumos.total_consumo, 0) AS total_reserva,
                COALESCE(pagos.total_pagado, 0) AS total_pagado,
                COALESCE(pagos_metodo.pagado_efectivo, 0) AS pagado_efectivo,
                COALESCE(pagos_metodo.pagado_transferencia, 0) AS pagado_transferencia,
                (r.precio_total + COALESCE(consumos.total_consumo, 0)) - COALESCE(pagos.total_pagado, 0) AS saldo_actual
         FROM pagos p
         INNER JOIN reservas r ON r.id = p.reserva_id
         INNER JOIN clientes c ON c.id = r.cliente_id
         INNER JOIN canchas ca ON ca.id = r.cancha_id
         LEFT JOIN (
           SELECT reserva_id, SUM(total) AS total_consumo
           FROM ventas
           WHERE estado <> 'anulada'
           GROUP BY reserva_id
         ) consumos ON consumos.reserva_id = r.id
         LEFT JOIN (
           SELECT reserva_id, SUM(monto) AS total_pagado
           FROM pagos
           GROUP BY reserva_id
         ) pagos ON pagos.reserva_id = r.id
         LEFT JOIN (
           SELECT reserva_id,
                  SUM(CASE WHEN metodo = 'efectivo' THEN monto ELSE 0 END) AS pagado_efectivo,
                  SUM(CASE WHEN metodo = 'transferencia' THEN monto ELSE 0 END) AS pagado_transferencia
           FROM pagos
           GROUP BY reserva_id
         ) pagos_metodo ON pagos_metodo.reserva_id = r.id
         WHERE p.id = ?"
    );
    $stmt->execute([$pagoTicketId]);
    $pagoTicket = $stmt->fetch();
}

$useAppLoading = true;
include 'partials/header.php';
?>

<main class="container">
  <nav class="module-menu" aria-label="Modulos del sistema">
    <button type="button" class="active" data-target="caja"><span class="menu-icon" aria-hidden="true">$</span><span>Caja</span></button>
    <button type="button" data-target="clientes"><span class="menu-icon" aria-hidden="true">◉</span><span>Clientes</span></button>
    <?php if ($esAdministrador): ?>
    <button type="button" data-target="proveedores"><span class="menu-icon" aria-hidden="true">▤</span><span>Proveedores</span></button>
    <button type="button" data-target="canchas"><span class="menu-icon" aria-hidden="true">▣</span><span>Canchas</span></button>
    <?php endif; ?>
    <button type="button" data-target="reservas" class="<?= !empty($reservasConComprobantePendiente) ? 'has-notification' : '' ?>"><span class="menu-icon" aria-hidden="true">◷</span><span>Reservas</span></button>
    <?php if ($esAdministrador): ?>
    <button type="button" data-target="categorias"><span class="menu-icon" aria-hidden="true">◇</span><span>Categor&iacute;as</span></button>
    <?php endif; ?>
    <button type="button" data-target="productos"><span class="menu-icon" aria-hidden="true">□</span><span>Productos</span></button>
    <?php if ($esAdministrador): ?>
    <button type="button" data-target="compras"><span class="menu-icon" aria-hidden="true">↓</span><span>Compras</span></button>
    <?php endif; ?>
    <button type="button" data-target="ventas"><span class="menu-icon" aria-hidden="true">↑</span><span>Ventas</span></button>
    <?php if ($esAdministrador): ?>
    <button type="button" data-target="reportes"><span class="menu-icon" aria-hidden="true">▥</span><span>Reportes</span></button>
    <button type="button" data-target="configuracion"><span class="menu-icon" aria-hidden="true">⚙</span><span>Configuraci&oacute;n</span></button>
    <?php endif; ?>
    <?php if (!$esAdministrador): ?>
      <a class="btn secondary secretary-logout" href="logout.php">Cerrar sesi&oacute;n</a>
    <?php endif; ?>
  </nav>

  <?php if ($accesoError !== ''): ?>
    <div class="notice error"><?= e($accesoError) ?></div>
  <?php endif; ?>

  <?php if ($esAdministrador): ?>
  <section class="module" id="reportes">
    <article class="panel">
      <div class="section-header">
        <div>
          <h2>Reportes</h2>
          <span class="muted">Resumen general por rango de fechas.</span>
        </div>
        <form action="index.php#reportes" method="get" class="search-form">
          <input type="date" name="reporte_desde" value="<?= e($reporteDesde) ?>">
          <input type="date" name="reporte_hasta" value="<?= e($reporteHasta) ?>">
          <button type="submit" class="small">Ver resumen</button>
          <a class="btn small secondary" href="reportes_descargar_pdf.php?reporte_desde=<?= e($reporteDesde) ?>&reporte_hasta=<?= e($reporteHasta) ?>">Descargar PDF</a>
        </form>
      </div>

      <div class="stats">
        <article class="stat">
          <span>Total de ventas</span>
          <strong><?= formatearGuaranies($reporteVentas['total_ventas'] ?? 0) ?></strong>
          <small><?= (int)($reporteVentas['cantidad_ventas'] ?? 0) ?> venta(s)</small>
        </article>
        <article class="stat">
          <span>Ganancia ventas</span>
          <strong><?= formatearGuaranies($reporteVentas['ganancia_ventas'] ?? 0) ?></strong>
          <small>Venta menos costo de productos</small>
        </article>
        <article class="stat">
          <span>Reservas cobradas</span>
          <strong><?= (int)($reporteReservas['reservas_pagadas'] ?? 0) ?></strong>
          <small><?= (int)($reporteReservas['pagos_registrados'] ?? 0) ?> pago(s)</small>
        </article>
        <article class="stat">
          <span>Ingresos reservas</span>
          <strong><?= formatearGuaranies($reporteReservas['total_cobrado_reservas'] ?? 0) ?></strong>
          <small>Segun fecha de pago</small>
        </article>
      </div>

      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Ventas</th>
            <th>Total ventas</th>
            <th>Ganancia ventas</th>
            <th>Reservas cobradas</th>
            <th>Ingresos reservas</th>
            <th>Pagos</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reporteFechas)): ?>
            <tr><td colspan="7">No hay movimientos en el rango seleccionado.</td></tr>
          <?php else: ?>
            <?php foreach ($reporteFechas as $fechaReporte): ?>
              <?php
                $ventasDia = $reporteVentasPorDia[$fechaReporte] ?? [];
                $reservasDia = $reporteReservasPorDia[$fechaReporte] ?? [];
              ?>
              <tr>
                <td><?= e(date('d/m/Y', strtotime($fechaReporte))) ?></td>
                <td><?= (int)($ventasDia['cantidad_ventas'] ?? 0) ?></td>
                <td><?= formatearGuaranies($ventasDia['total_ventas'] ?? 0) ?></td>
                <td><?= formatearGuaranies($ventasDia['ganancia_ventas'] ?? 0) ?></td>
                <td><?= (int)($reservasDia['reservas_pagadas'] ?? 0) ?></td>
                <td><?= formatearGuaranies($reservasDia['total_cobrado_reservas'] ?? 0) ?></td>
                <td><?= (int)($reservasDia['pagos_registrados'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </article>
  </section>
  <?php endif; ?>

  <section class="module active" id="caja">
    <article class="panel">
      <div class="section-header">
        <div>
          <h2>Caja</h2>
          <span class="muted">Resumen de movimientos del d&iacute;a.</span>
        </div>
        <div class="search-form">
          <input type="date" value="<?= e($cajaFecha) ?>" readonly aria-label="Fecha actual de caja" title="La fecha de caja se establece automaticamente">
        </div>
      </div>

      <?php if ($cajaError !== ''): ?>
        <div class="notice error"><?= e($cajaError) ?></div>
      <?php endif; ?>

      <div class="cash-register">
        <div class="cash-status">
          <span class="muted">Estado</span>
          <?php if (!$cajaJornada): ?>
            <strong><span class="badge inactiva">Sin abrir</span></strong>
            <small>Abre la caja antes de controlar los movimientos del d&iacute;a.</small>
          <?php elseif ($cajaJornada['estado'] === 'cerrada'): ?>
            <strong><span class="badge anulada">Cerrada</span></strong>
            <small>Cerrada el <?= e(date('d/m/Y H:i', strtotime($cajaJornada['cerrada_en']))) ?></small>
            <small>Abierta por <?= e($cajaUsuarioApertura ?: 'Sin usuario registrado') ?></small>
            <small>Cerrada por <?= e($cajaUsuarioCierre ?: 'Sin usuario registrado') ?></small>
            <div class="cash-status-amount">
              <span>Monto inicial</span>
              <strong><?= formatearGuaranies($cajaMontoInicial) ?></strong>
            </div>
          <?php else: ?>
            <strong><span class="badge activa">Abierta</span></strong>
            <small>Abierta el <?= e(date('d/m/Y H:i', strtotime($cajaJornada['abierta_en']))) ?></small>
            <small>Abierta por <?= e($cajaUsuarioApertura ?: 'Sin usuario registrado') ?></small>
            <div class="cash-status-amount">
              <span>Monto inicial</span>
              <strong><?= formatearGuaranies($cajaMontoInicial) ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$cajaJornada): ?>
          <form action="guardar_caja.php" method="post" class="grid compact cash-form">
            <input type="hidden" name="accion" value="abrir">
            <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
            <label>Monto inicial efectivo
              <input type="text" name="monto_inicial" class="money-input" inputmode="numeric" placeholder="0">
            </label>
            <label class="wide">Observaci&oacute;n
              <textarea name="observacion" rows="2" placeholder="Ej.: Fondo inicial del turno"></textarea>
            </label>
            <button type="submit">Abrir caja</button>
          </form>
        <?php elseif ($cajaJornada['estado'] === 'abierta'): ?>
          <div class="cash-open-tools">
            <div class="cash-close-layout">
              <form action="guardar_caja.php" method="post" class="grid compact cash-form cash-close-form" id="cashCloseForm">
                <input type="hidden" name="accion" value="cerrar">
                <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
                <label>Efectivo contado
                  <input type="text" name="monto_cierre_efectivo" class="money-input" inputmode="numeric" placeholder="0" data-allow-empty-money="1">
                </label>
                <label>Transferencia contada
                  <input type="text" name="monto_cierre_transferencia" class="money-input" inputmode="numeric" value="<?= e(formatearGuaranies($cajaSaldoTransferencia)) ?>">
                </label>
                <label class="wide">Observaci&oacute;n de cierre
                  <input type="text" name="observacion" placeholder="Ej.: Sin diferencias">
                </label>
                <button type="submit" class="danger">Cerrar caja</button>
              </form>
              <div class="cash-current-summary">
                <div><span>Saldo caja total</span><strong><?= formatearGuaranies($cajaSaldo) ?></strong></div>
                <div><span>Total efectivo</span><strong><?= formatearGuaranies($cajaSaldoEfectivo) ?></strong></div>
                <div><span>Total transferencia</span><strong><?= formatearGuaranies($cajaSaldoTransferencia) ?></strong></div>
              </div>
            </div>
            <form action="guardar_caja.php" method="post" class="cash-movement-form">
              <input type="hidden" name="accion" value="movimiento">
              <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
              <label>Movimiento
                <select name="tipo_movimiento">
                  <option value="egreso">Egreso</option>
                  <option value="ingreso">Ingreso</option>
                </select>
              </label>
              <label>Concepto
                <input type="text" name="concepto" placeholder="Ej.: Pago de limpieza" required>
              </label>
              <label>Monto
                <input type="text" name="monto" class="money-input" inputmode="numeric" placeholder="0" required>
              </label>
              <button type="submit" class="secondary">Agregar movimiento</button>
            </form>
          </div>
        <?php else: ?>
          <div class="closed-cash-actions">
            <div class="cash-close-summary">
              <div><span>Efectivo contado</span><strong><?= formatearGuaranies($cajaCierreEfectivo) ?></strong></div>
              <div><span>Diferencia efectivo</span><strong class="<?= ($cajaDiferenciaEfectivo ?? 0) < 0 ? 'negative' : 'positive' ?>"><?= formatearGuaranies($cajaDiferenciaEfectivo) ?></strong></div>
              <div><span>Transferencia contada</span><strong><?= formatearGuaranies($cajaCierreTransferencia) ?></strong></div>
              <div><span>Diferencia transferencia</span><strong class="<?= ($cajaDiferenciaTransferencia ?? 0) < 0 ? 'negative' : 'positive' ?>"><?= formatearGuaranies($cajaDiferenciaTransferencia) ?></strong></div>
            </div>
            <form action="guardar_caja.php" method="post" class="grid compact cash-form">
              <input type="hidden" name="accion" value="abrir">
              <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
              <label>Nueva caja - monto inicial
                <input type="text" name="monto_inicial" class="money-input" inputmode="numeric" placeholder="0">
              </label>
              <label>Observaci&oacute;n
                <input type="text" name="observacion" placeholder="Ej.: Segundo turno">
              </label>
              <button type="submit">Abrir nueva caja</button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($abonosPendientesCaja)): ?>
        <div class="cash-pending-payments">
          <div class="section-header">
            <h2>Abonos pendientes en caja</h2>
            <span class="muted"><?= count($abonosPendientesCaja) ?> pendiente(s)</span>
          </div>
          <table>
            <thead><tr><th>Reserva</th><th>Cliente</th><th>Fecha</th><th>M&eacute;todo</th><th>Monto</th><th>Acci&oacute;n</th></tr></thead>
            <tbody>
              <?php foreach ($abonosPendientesCaja as $abonoPendiente): ?>
                <tr>
                  <td>#<?= (int)$abonoPendiente['reserva_id'] ?> - <?= e($abonoPendiente['cancha']) ?></td>
                  <td><?= e($abonoPendiente['cliente']) ?></td>
                  <td><?= e(date('d/m/Y H:i', strtotime($abonoPendiente['fecha'] . ' ' . $abonoPendiente['hora_inicio']))) ?></td>
                  <td><?= e($abonoPendiente['metodo']) ?></td>
                  <td><?= formatearGuaranies($abonoPendiente['monto']) ?></td>
                  <td>
                    <?php
                      $comprobanteDebeConfirmarse = !empty($abonoPendiente['comprobante_path']) && (int)($abonoPendiente['comprobante_confirmado'] ?? 0) !== 1;
                      $abonoPuedeIngresar = $cajaAbiertaHoy && $abonoPendiente['estado'] === 'pendiente' && !$comprobanteDebeConfirmarse;
                    ?>
                    <?php if ($abonoPuedeIngresar): ?>
                      <form action="guardar_caja.php" method="post" class="inline-form">
                        <input type="hidden" name="accion" value="confirmar_abono">
                        <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
                        <input type="hidden" name="abono_pendiente_id" value="<?= (int)$abonoPendiente['id'] ?>">
                        <button type="submit" class="small">Ingresar a caja</button>
                      </form>
                    <?php elseif ($comprobanteDebeConfirmarse): ?>
                      <button
                        type="button"
                        class="small danger"
                        data-view-pending-cash-receipt
                        data-receipt-url="<?= e($abonoPendiente['comprobante_path']) ?>"
                        data-pending-id="<?= (int)$abonoPendiente['id'] ?>"
                        data-reserva-id="<?= (int)$abonoPendiente['reserva_id'] ?>"
                      >Ver y confirmar</button>
                    <?php elseif ($abonoPendiente['estado'] !== 'pendiente'): ?>
                      <span class="muted">Pendiente de revisi&oacute;n</span>
                    <?php else: ?>
                      <span class="muted">Abre caja para ingresar</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if (!$cajaJornada || $cajaJornada['estado'] !== 'cerrada'): ?>
        <table class="cash-movements-table">
          <thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th>Detalle</th><th>M&eacute;todo</th><th>Monto</th></tr></thead>
          <tbody>
            <?php if (empty($cajaMovimientos)): ?>
              <tr><td colspan="6">Sin movimientos de caja para esta fecha.</td></tr>
            <?php else: ?>
              <?php foreach ($cajaMovimientos as $movimiento): ?>
                <?php $movimientoClase = $movimiento['tipo'] === 'egreso' ? 'cash-out' : ($movimiento['tipo'] === 'ingreso' ? 'cash-in' : 'cash-neutral'); ?>
                <tr class="<?= e($movimientoClase) ?>">
                  <td><?= e(date('H:i', strtotime($movimiento['fecha']))) ?></td>
                  <td><span class="badge <?= $movimiento['tipo'] === 'egreso' ? 'anulada' : ($movimiento['tipo'] === 'ingreso' ? 'activa' : 'reservado') ?>"><?= e($movimiento['tipo']) ?></span></td>
                  <td><?= e($movimiento['concepto']) ?></td>
                  <td><?= e($movimiento['detalle']) ?></td>
                  <td><?= e($movimiento['metodo']) ?></td>
                  <td><?= $movimiento['tipo'] === 'egreso' ? '-' : ($movimiento['tipo'] === 'ingreso' ? '+' : '') ?><?= formatearGuaranies($movimiento['monto']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!empty($cajaCierresRecientes)): ?>
        <div class="cash-history">
          <div class="section-header">
            <h2>&Uacute;ltimos cierres de caja</h2>
            <span class="muted">Se muestran los 5 m&aacute;s recientes de esta fecha.</span>
          </div>
          <table>
            <thead><tr><th>Apertura</th><th>Cierre</th><th>Usuario apertura</th><th>Usuario cierre</th><th>Inicial</th><th>Total contado</th><th>Movimientos</th></tr></thead>
            <tbody>
              <?php foreach ($cajaCierresRecientes as $cierre): ?>
                <?php $detalleCierre = $cajaCierresDetalle[(int)$cierre['id']] ?? null; ?>
                <tr class="clickable-row" data-cash-detail="<?= (int)$cierre['id'] ?>">
                  <td><?= e(date('H:i', strtotime($cierre['abierta_en']))) ?></td>
                  <td><?= e(date('H:i', strtotime($cierre['cerrada_en']))) ?></td>
                  <td><?= e(($cierre['usuario_apertura_nombre'] ?? $cierre['usuario_apertura_usuario'] ?? '') ?: 'Sin registro') ?></td>
                  <td><?= e(($cierre['usuario_cierre_nombre'] ?? $cierre['usuario_cierre_usuario'] ?? '') ?: 'Sin registro') ?></td>
                  <td><?= formatearGuaranies($cierre['monto_inicial']) ?></td>
                  <td><?= formatearGuaranies($detalleCierre['total_contado'] ?? 0) ?></td>
                  <td><?= count($detalleCierre['movimientos'] ?? []) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="module" id="clientes">
    <article class="panel clients-panel">
      <div class="section-header clients-list-header">
        <div class="title-actions">
          <h2>Listado de clientes</h2>
          <button type="button" class="small" id="openClientListQuickClient">Nuevo cliente</button>
        </div>
        <div class="search-form">
          <input type="search" id="clientListSearch" placeholder="Buscar cliente, telefono, email...">
          <span class="muted" id="clientListCount"><?= count($todosClientes) ?> registrado(s)</span>
        </div>
      </div>
      <table>
        <thead><tr><th>Nombre</th><th>Tel&eacute;fono</th><th>Email</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if (empty($todosClientes)): ?>
            <tr><td colspan="5">Todav&iacute;a no hay clientes.</td></tr>
          <?php else: ?>
            <?php foreach ($todosClientes as $cliente): ?>
              <tr data-client-row data-client-search="<?= e(strtolower(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['telefono'] ?? '') . ' ' . ($cliente['email'] ?? '') . ' ' . ($cliente['documento'] ?? '') . ' ' . ($cliente['estado'] ?? '')))) ?>">
                <td><?= e($cliente['nombre']) ?></td>
                <td><?= e($cliente['telefono']) ?></td>
                <td><?= e($cliente['email'] ?: '-') ?></td>
                <td><span class="badge <?= e($cliente['estado']) ?>"><?= e($cliente['estado']) ?></span></td>
                <td>
                  <div class="client-row-actions">
                  <button
                    type="button"
                    class="small secondary"
                    data-edit-client
                    data-id="<?= (int)$cliente['id'] ?>"
                    data-nombre="<?= e($cliente['nombre']) ?>"
                    data-telefono="<?= e($cliente['telefono']) ?>"
                    data-email="<?= e($cliente['email'] ?? '') ?>"
                    data-documento="<?= e($cliente['documento'] ?? '') ?>"
                    data-direccion="<?= e($cliente['direccion'] ?? '') ?>"
                    data-estado="<?= e($cliente['estado'] ?? 'activo') ?>"
                    data-notas="<?= e($cliente['notas'] ?? '') ?>"
                  >Editar</button>
                  <button
                    type="button"
                    class="small <?= ($cliente['estado'] ?? '') === 'activo' ? 'danger' : '' ?>"
                    data-toggle-client
                    data-id="<?= (int)$cliente['id'] ?>"
                    data-nombre="<?= e($cliente['nombre']) ?>"
                    data-estado="<?= e($cliente['estado'] ?? 'activo') ?>"
                  ><?= ($cliente['estado'] ?? '') === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                  <button
                    type="button"
                    class="small danger"
                    data-delete-client
                    data-id="<?= (int)$cliente['id'] ?>"
                    data-nombre="<?= e($cliente['nombre']) ?>"
                  >Eliminar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr data-client-empty hidden><td colspan="5">No se encontraron clientes.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <nav class="pagination" id="clientListPagination" aria-label="Paginacion de clientes"></nav>
    </article>
  </section>

  <?php if ($esAdministrador): ?>
  <section class="module" id="proveedores">
    <article class="panel providers-panel">
      <div class="section-header providers-list-header">
        <div class="title-actions">
          <h2>Listado de proveedores</h2>
          <button type="button" id="openProviderListQuickProvider">Nuevo proveedor</button>
        </div>
        <div class="search-form">
          <input type="search" id="providerSearch" placeholder="Buscar proveedor, telefono, RUC...">
          <span class="muted" id="providerCount"><?= count($proveedores) ?> registrado(s)</span>
        </div>
      </div>
      <table>
        <thead><tr><th>Nombre</th><th>Tel&eacute;fono</th><th>Email</th><th>RUC</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if (empty($proveedores)): ?>
            <tr><td colspan="6">Todav&iacute;a no hay proveedores.</td></tr>
          <?php else: ?>
            <?php foreach ($proveedores as $proveedor): ?>
              <tr data-provider-row data-provider-search="<?= e(strtolower(trim(($proveedor['nombre'] ?? '') . ' ' . ($proveedor['telefono'] ?? '') . ' ' . ($proveedor['email'] ?? '') . ' ' . ($proveedor['ruc'] ?? '') . ' ' . ($proveedor['estado'] ?? '')))) ?>">
                <td><?= e($proveedor['nombre']) ?></td>
                <td><?= e($proveedor['telefono'] ?: '-') ?></td>
                <td><?= e($proveedor['email'] ?: '-') ?></td>
                <td><?= e($proveedor['ruc'] ?: '-') ?></td>
                <td><span class="badge <?= e($proveedor['estado']) ?>"><?= e($proveedor['estado']) ?></span></td>
                <td>
                  <div class="provider-row-actions">
                    <button type="button" class="small secondary" data-edit-provider="<?= (int)$proveedor['id'] ?>">Editar</button>
                    <button
                      type="button"
                      class="small <?= $proveedor['estado'] === 'activo' ? 'danger' : '' ?>"
                      data-toggle-provider
                      data-id="<?= (int)$proveedor['id'] ?>"
                      data-nombre="<?= e($proveedor['nombre']) ?>"
                      data-estado="<?= e($proveedor['estado']) ?>"
                    ><?= $proveedor['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                    <button type="button" class="small danger" data-delete-provider="<?= (int)$proveedor['id'] ?>">Eliminar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr data-provider-empty hidden><td colspan="6">No se encontraron proveedores.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <nav class="pagination" id="providerListPagination" aria-label="Paginacion de proveedores"></nav>
    </article>
  </section>

  <section class="module" id="canchas">
    <article class="panel courts-panel">
      <div class="section-header courts-list-header">
        <div class="title-actions">
          <h2>Listado de canchas</h2>
          <button type="button" id="openCourtCreateModal">Nueva cancha</button>
        </div>
      </div>
      <table>
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Precio hora</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($canchas as $cancha): ?>
            <tr data-court-row>
              <td><?= e($cancha['nombre']) ?></td>
              <td><?= e($cancha['tipo'] ?: '-') ?></td>
              <td><?= formatearGuaranies($cancha['precio_hora']) ?></td>
              <td><span class="badge <?= e($cancha['estado']) ?>"><?= e($cancha['estado']) ?></span></td>
              <td>
                <div class="court-row-actions">
                  <button type="button" class="small secondary" data-edit-court="<?= (int)$cancha['id'] ?>">Editar</button>
                  <button
                    type="button"
                    class="small danger"
                    data-delete-court
                    data-id="<?= (int)$cancha['id'] ?>"
                    data-nombre="<?= e($cancha['nombre']) ?>"
                  >Eliminar</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>
  </section>

  <?php endif; ?>
  <section class="module" id="reservas">
    <article class="panel calendar-panel">
      <div class="court-tabs reservation-topbar">
        <div class="court-tabs-list" id="courtTabs"></div>
        <div class="reservation-topbar-controls">
          <span class="court-tabs-date" id="calendarDateInTabs"></span>
          <button type="button" class="secondary" id="prevWeek">Anterior</button>
          <button type="button" class="secondary" id="todayWeek">Hoy</button>
          <button type="button" class="secondary" id="nextWeek">Siguiente</button>
        </div>
      </div>

      <div class="calendar-toolbar">
        <div>
          <h2 id="calendarTitle">Reservas</h2>
          <span class="muted">Selecciona un horario libre para crear una reserva.</span>
        </div>
      </div>

      <?php if (empty($canchas)): ?>
        <p class="empty-state">Primero carga una cancha en el men&uacute; Canchas.</p>
      <?php else: ?>
        <div class="calendar-frame">
          <div class="week-calendar-header" id="weekCalendarHeader"></div>
          <div class="calendar-scroll">
            <div class="week-calendar" id="weekCalendar"></div>
          </div>
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="module" id="pagos">
    <article class="panel">
      <h2>&Uacute;ltimos pagos</h2>
      <table>
        <thead><tr><th>Fecha</th><th>Cliente</th><th>Reserva</th><th>Concepto</th><th>Monto</th></tr></thead>
        <tbody>
          <?php if (empty($pagos)): ?>
            <tr><td colspan="5">Sin pagos registrados.</td></tr>
          <?php else: ?>
            <?php foreach ($pagos as $pago): ?>
              <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($pago['fecha_pago']))) ?></td>
                <td><?= e($pago['cliente']) ?></td>
                <td><?= e($pago['cancha']) ?><span><?= e(date('d/m/Y', strtotime($pago['fecha']))) ?> - <?= e(substr($pago['hora_inicio'], 0, 5)) ?></span></td>
                <td><?= e($pago['concepto']) ?></td>
                <td><?= formatearGuaranies($pago['monto']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </article>
  </section>

  <?php if ($esAdministrador): ?>
  <section class="module" id="categorias">
    <article class="panel categories-panel">
      <div class="section-header categories-list-header">
        <div class="title-actions">
          <h2>Listado de categor&iacute;as</h2>
          <button type="button" id="openCategoryListModal">Nueva categor&iacute;a</button>
        </div>
        <div class="search-form">
          <input type="search" id="categoryListSearch" placeholder="Buscar categor&iacute;a, estado...">
          <span class="muted" id="categoryListCount"><?= count($categorias) ?> categor&iacute;a(s)</span>
        </div>
      </div>
      <table>
        <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if (empty($categorias)): ?>
            <tr><td colspan="3">Todav&iacute;a no hay categor&iacute;as cargadas.</td></tr>
          <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
              <tr data-category-row data-category-search="<?= e(strtolower(trim(($categoria['nombre'] ?? '') . ' ' . ($categoria['estado'] ?? '')))) ?>">
                <td><?= e($categoria['nombre']) ?></td>
                <td><span class="badge <?= e($categoria['estado']) ?>"><?= e($categoria['estado']) ?></span></td>
                <td>
                  <div class="category-row-actions">
                    <button
                      type="button"
                      class="small secondary"
                      data-edit-category="<?= (int)$categoria['id'] ?>"
                      data-category-name="<?= e($categoria['nombre']) ?>"
                    >Editar</button>
                    <button
                      type="button"
                      class="small <?= $categoria['estado'] === 'activo' ? 'danger' : '' ?>"
                      data-toggle-category
                      data-id="<?= (int)$categoria['id'] ?>"
                      data-nombre="<?= e($categoria['nombre']) ?>"
                      data-estado="<?= e($categoria['estado']) ?>"
                    ><?= $categoria['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                    <button
                      type="button"
                      class="small danger"
                      data-delete-category
                      data-id="<?= (int)$categoria['id'] ?>"
                      data-nombre="<?= e($categoria['nombre']) ?>"
                    >Eliminar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr data-category-empty hidden><td colspan="3">No se encontraron categor&iacute;as.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <nav class="pagination" id="categoryListPagination" aria-label="Paginacion de categorias"></nav>
    </article>
  </section>
  <?php endif; ?>

  <section class="module" id="productos">
    <article class="panel products-panel" id="productosList">
      <div class="section-header products-list-header">
        <div class="title-actions">
          <h2>Lista de productos</h2>
          <?php if ($esAdministrador): ?>
            <button type="button" id="openProductCreateModal">Nuevo producto</button>
          <?php endif; ?>
        </div>
        <div class="search-form" id="productSearchForm">
          <input type="search" name="producto_buscar" id="productInventorySearch" value="<?= e($productoBuscar) ?>" placeholder="Buscar producto, proveedor, c&oacute;digo...">
          <span class="muted" id="productInventoryCount"><?= $productosTotal ?> producto(s)<?= $productoBuscar !== '' ? ' encontrados' : '' ?></span>
        </div>
      </div>
      <table class="<?= $esAdministrador ? 'products-admin-table' : 'products-readonly-table' ?>">
        <?php if ($esAdministrador): ?>
          <thead><tr><th>Producto</th><th>C&oacute;digo</th><th>Proveedor</th><th>Categor&iacute;a</th><th>Compra unidad</th><th>Venta unidad</th><th>Compra pack</th><th>Venta pack</th><th>Promoci&oacute;n</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
        <?php else: ?>
          <thead><tr><th>Producto</th><th>C&oacute;digo</th><th>Proveedor</th><th>Categor&iacute;a</th><th>Venta unidad</th><th>Venta pack</th><th>Promoci&oacute;n</th><th>Stock</th><th>Estado</th></tr></thead>
        <?php endif; ?>
        <tbody id="productInventoryRows">
          <?php if (empty($productosInventario)): ?>
            <tr><td colspan="<?= $esAdministrador ? 12 : 9 ?>">Todav&iacute;a no hay productos cargados.</td></tr>
          <?php else: ?>
            <?php foreach ($productosInventario as $producto): ?>
              <tr data-product-row>
                <td><?= e($producto['nombre']) ?></td>
                <td><?= e($producto['codigo_barra'] ?: '-') ?></td>
                <td><?= e($producto['proveedor'] ?: '-') ?></td>
                <td><?= e($producto['categoria'] ?: '-') ?></td>
                <?php if ($esAdministrador): ?>
                <td><?= formatearGuaranies($producto['precio_compra']) ?></td>
                <?php endif; ?>
                <td><?= formatearGuaranies($producto['precio_venta']) ?></td>
                <?php if ($esAdministrador): ?>
                <td>
                  <?php if ((int)$producto['pack_cantidad'] > 0): ?>
                    <?= formatearGuaranies($producto['precio_compra_pack']) ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                  <?php if ((int)$producto['pack_cantidad'] > 0): ?>
                    <?= (int)$producto['pack_cantidad'] ?> un. / <?= formatearGuaranies($producto['precio_pack']) ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$producto['promocion_cantidad'] > 0): ?>
                    <?= (int)$producto['promocion_cantidad'] ?> por <?= formatearGuaranies($producto['precio_promocion']) ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td><?= (int)$producto['stock'] ?></td>
                <td><span class="badge <?= e($producto['estado']) ?>"><?= e($producto['estado']) ?></span></td>
                <?php if ($esAdministrador): ?>
                <td>
                  <div class="product-row-actions">
                    <button type="button" class="small secondary" data-edit-product="<?= (int)$producto['id'] ?>">Editar</button>
                    <button
                      type="button"
                      class="small <?= $producto['estado'] === 'activo' ? 'danger' : '' ?>"
                      data-toggle-product
                      data-id="<?= (int)$producto['id'] ?>"
                      data-nombre="<?= e($producto['nombre']) ?>"
                      data-estado="<?= e($producto['estado']) ?>"
                    ><?= $producto['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                    <button
                      type="button"
                      class="small danger"
                      data-delete-product
                      data-id="<?= (int)$producto['id'] ?>"
                      data-nombre="<?= e($producto['nombre']) ?>"
                    >Eliminar</button>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($productosPaginas > 1): ?>
        <nav class="pagination" id="productInventoryPagination" aria-label="Paginacion de productos">
          <?php
            $productoQueryBase = $productoBuscar !== '' ? ['producto_buscar' => $productoBuscar] : [];
            $productoAnterior = max(1, $productoPagina - 1);
            $productoSiguiente = min($productosPaginas, $productoPagina + 1);
          ?>
          <a class="btn secondary <?= $productoPagina <= 1 ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($productoQueryBase + ['producto_pagina' => $productoAnterior])) ?>#productos-list">Anterior</a>
          <span>P&aacute;gina <?= $productoPagina ?> de <?= $productosPaginas ?></span>
          <a class="btn secondary <?= $productoPagina >= $productosPaginas ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($productoQueryBase + ['producto_pagina' => $productoSiguiente])) ?>#productos-list">Siguiente</a>
        </nav>
      <?php endif; ?>
    </article>
  </section>

  <?php if ($esAdministrador): ?>
  <section class="module" id="compras">
    <article class="panel">
      <div class="section-header">
        <h2 id="purchaseFormTitle">Nueva compra</h2>
      </div>
      <form action="guardar_compra.php" method="post" class="grid compact purchase-grid" id="purchaseForm">
        <input type="hidden" name="compra_id" id="purchaseCompraId">
        <div class="client-picker">
          <label>Proveedor
            <input type="hidden" name="proveedor_id" id="purchaseProviderId">
            <input type="search" id="purchaseProviderSearch" autocomplete="off" placeholder="Buscar proveedor">
          </label>
          <button type="button" class="add-client-button" id="openQuickProvider" title="Agregar proveedor">+</button>
          <div class="autocomplete-list" id="purchaseProviderSuggestions"></div>
        </div>
        <div class="product-picker with-add-button">
          <label>Producto
            <input type="hidden" id="purchaseProduct">
            <input type="search" id="purchaseProductSearch" autocomplete="off" placeholder="Buscar producto o codigo de barras">
          </label>
          <button type="button" class="add-client-button" id="openQuickProduct" title="Agregar producto">+</button>
          <div class="autocomplete-list" id="purchaseProductSuggestions"></div>
        </div>
        <label class="purchase-field purchase-type">Tipo de compra
          <select id="purchaseType">
            <option value="unidad">Unidad</option>
            <option value="pack" id="purchaseTypePack">Pack</option>
          </select>
        </label>
        <div class="purchase-field purchase-quantity quantity-field">
          <span>Cantidad</span>
          <div class="quantity-stepper">
            <button type="button" class="quantity-step" data-purchase-quantity-step="-1" aria-label="Restar cantidad">-</button>
            <input type="number" id="purchaseQuantity" min="1" step="1" value="1">
            <button type="button" class="quantity-step" data-purchase-quantity-step="1" aria-label="Sumar cantidad">+</button>
          </div>
        </div>
        <label class="purchase-field purchase-price">Precio compra <input type="text" id="purchasePrice" class="money-input" inputmode="numeric" value="0"></label>
        <label class="purchase-field purchase-method">M&eacute;todo
          <select name="metodo" id="purchaseMethod">
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
          </select>
        </label>
        <input type="hidden" id="purchaseTotal" value="0">
        <button type="button" class="secondary purchase-add-button" id="addPurchaseItem" disabled>Agregar producto</button>
        <button type="button" class="secondary" id="cancelPurchaseEdit" hidden>Cancelar edici&oacute;n</button>
        <div class="cart-list wide" id="purchaseCartList"></div>
        <div id="purchaseCartInputs"></div>
        <input type="hidden" name="observacion" id="purchaseObservation" value="">
        <button type="submit" id="purchaseSubmitButton">Registrar compra</button>
      </form>
    </article>

    <article class="panel" id="comprasList">
      <div class="section-header">
        <div>
          <h2>&Uacute;ltimas compras</h2>
          <span class="muted" id="purchaseSearchCount"><?= $comprasTotal ?> compra(s)<?= $compraBuscar !== '' ? ' encontradas' : '' ?></span>
        </div>
        <div class="search-form" id="purchaseSearchForm">
          <input type="search" name="compra_buscar" id="purchaseListSearch" value="<?= e($compraBuscar) ?>" placeholder="Buscar proveedor, producto, estado...">
        </div>
      </div>
      <table>
        <thead><tr><th>ID</th><th>Fecha</th><th>Proveedor</th><th>Resumen</th><th>M&eacute;todo</th><th>Estado</th><th>Total</th></tr></thead>
        <tbody id="purchaseListRows">
          <?php if (empty($compras)): ?>
            <tr><td colspan="7">Sin compras registradas.</td></tr>
          <?php else: ?>
            <?php foreach ($compras as $compra): ?>
              <tr class="clickable-row <?= $compra['estado'] === 'anulada' ? 'sale-canceled' : '' ?>" data-purchase-id="<?= (int)$compra['id'] ?>">
                <td>#<?= (int)$compra['id'] ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($compra['fecha_compra']))) ?></td>
                <td><?= e($compra['proveedor'] ?: 'Sin proveedor') ?></td>
                <td>
                  <?= (int)$compra['items'] ?> producto(s)
                  <span><?= (int)$compra['unidades'] ?> unidad(es)</span>
                </td>
                <td><?= e($compra['metodo']) ?></td>
                <td><span class="badge <?= e($compra['estado']) ?>"><?= e($compra['estado']) ?></span></td>
                <td><?= formatearGuaranies($compra['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($comprasPaginas > 1): ?>
        <nav class="pagination" id="purchaseListPagination" aria-label="Paginacion de compras">
          <?php
            $compraQueryBase = $compraBuscar !== '' ? ['compra_buscar' => $compraBuscar] : [];
            $compraAnterior = max(1, $compraPagina - 1);
            $compraSiguiente = min($comprasPaginas, $compraPagina + 1);
          ?>
          <a class="btn secondary <?= $compraPagina <= 1 ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($compraQueryBase + ['compra_pagina' => $compraAnterior])) ?>#compras-list">Anterior</a>
          <span>P&aacute;gina <?= $compraPagina ?> de <?= $comprasPaginas ?></span>
          <a class="btn secondary <?= $compraPagina >= $comprasPaginas ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($compraQueryBase + ['compra_pagina' => $compraSiguiente])) ?>#compras-list">Siguiente</a>
        </nav>
      <?php endif; ?>
    </article>
  </section>

  <?php endif; ?>
  <section class="module" id="ventas">
    <article class="panel">
      <div class="section-header">
        <h2 id="saleFormTitle">Nueva venta</h2>
      </div>
      <?php if (!$cajaAbiertaHoy): ?>
        <div class="notice error">Debes abrir la caja antes de registrar ventas.</div>
      <?php endif; ?>
      <form action="guardar_venta.php" method="post" class="grid compact sale-grid" id="saleForm">
        <input type="hidden" name="venta_id" id="saleVentaId">
        <div class="client-picker">
          <label>Cliente
            <input type="hidden" name="cliente_id" id="saleClienteId">
            <input type="search" id="saleClientSearch" autocomplete="off" placeholder="Sin cliente">
          </label>
          <button type="button" class="add-client-button" id="openSaleQuickClient" title="Agregar cliente">+</button>
          <div class="autocomplete-list" id="saleClientSuggestions"></div>
        </div>
        <div class="product-picker with-search-button">
          <label>Producto
            <input type="hidden" id="ventaProducto" value="<?= (int)$ventaForm['producto_id'] ?>">
            <input type="search" id="ventaProductoSearch" autocomplete="off" placeholder="Buscar producto o codigo de barras">
          </label>
          <button type="button" class="add-client-button search-product-button" id="openSaleProductSearch" title="Buscar producto">⌕</button>
          <div class="autocomplete-list" id="productSuggestions"></div>
        </div>
        <label>Tipo de venta
          <select id="ventaTipo">
            <option value="unidad" <?= $ventaForm['tipo_venta'] === 'unidad' ? 'selected' : '' ?>>Unidad</option>
            <option value="pack" id="ventaTipoPack" <?= $ventaForm['tipo_venta'] === 'pack' ? 'selected' : '' ?>>Pack</option>
            <option value="promocion" id="ventaTipoPromocion" <?= $ventaForm['tipo_venta'] === 'promocion' ? 'selected' : '' ?>>Promoci&oacute;n</option>
          </select>
        </label>
        <div class="quantity-field">
          <span>Cantidad</span>
          <div class="quantity-stepper">
            <button type="button" class="quantity-step" data-sale-quantity-step="-1" aria-label="Restar cantidad">-</button>
            <input type="number" id="ventaCantidad" min="1" step="1" value="<?= max(1, (int)$ventaForm['cantidad']) ?>">
            <button type="button" class="quantity-step" data-sale-quantity-step="1" aria-label="Sumar cantidad">+</button>
          </div>
        </div>
        <label>M&eacute;todo
          <select name="metodo" id="saleMethod">
            <option value="efectivo" <?= $ventaForm['metodo'] === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
            <option value="transferencia" <?= $ventaForm['metodo'] === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
          </select>
        </label>
        <label>Total estimado <input type="text" id="ventaTotal" value="0" readonly></label>
        <button type="button" class="secondary" id="addSaleItem" disabled>Agregar producto</button>
        <button type="button" class="secondary" id="cancelSaleEdit" hidden>Cancelar edici&oacute;n</button>
        <div class="cart-list wide" id="saleCartList"></div>
        <div id="saleCartInputs"></div>
        <input type="hidden" name="observacion" id="saleObservation" value="<?= e($ventaForm['observacion']) ?>">
        <button type="submit" id="saleSubmitButton" <?= !$cajaAbiertaHoy ? 'disabled' : '' ?>>Registrar venta</button>
      </form>
    </article>

    <article class="panel" id="ventasList">
      <div class="section-header">
        <div>
          <h2>&Uacute;ltimas ventas</h2>
          <span class="muted" id="saleSearchCount"><?= $ventasTotal ?> venta(s)<?= $ventaBuscar !== '' ? ' encontradas' : '' ?></span>
        </div>
        <div class="search-form" id="saleSearchForm">
          <input type="search" name="venta_buscar" id="saleListSearch" value="<?= e($ventaBuscar) ?>" placeholder="Buscar cliente, producto, estado...">
        </div>
      </div>
      <table>
        <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Resumen</th><th>M&eacute;todo</th><th>Estado</th><th>Total</th></tr></thead>
        <tbody id="saleListRows">
          <?php if (empty($ventas)): ?>
            <tr><td colspan="7">Sin ventas registradas.</td></tr>
          <?php else: ?>
            <?php foreach ($ventas as $venta): ?>
              <tr class="clickable-row <?= $venta['estado'] === 'anulada' ? 'sale-canceled' : '' ?>" data-sale-id="<?= (int)$venta['id'] ?>">
                <td>#<?= (int)$venta['id'] ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($venta['fecha_venta']))) ?></td>
                <td><?= e($venta['cliente'] ?: 'Sin cliente') ?></td>
                <td>
                  <?= (int)$venta['items'] ?> producto(s)
                  <span><?= (int)$venta['unidades'] ?> unidad(es)<?= $venta['reserva_cancha'] ? ' - ' . e($venta['reserva_cancha']) . ' - R#' . (int)$venta['reserva_id'] : '' ?></span>
                </td>
                <td><?= e($venta['metodo']) ?></td>
                <td><span class="badge <?= e($venta['estado']) ?>"><?= e($venta['estado']) ?></span></td>
                <td><?= formatearGuaranies($venta['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($ventasPaginas > 1): ?>
        <nav class="pagination" id="saleListPagination" aria-label="Paginacion de ventas">
          <?php
            $ventaQueryBase = $ventaBuscar !== '' ? ['venta_buscar' => $ventaBuscar] : [];
            $ventaAnterior = max(1, $ventaPagina - 1);
            $ventaSiguiente = min($ventasPaginas, $ventaPagina + 1);
          ?>
          <a class="btn secondary <?= $ventaPagina <= 1 ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($ventaQueryBase + ['venta_pagina' => $ventaAnterior])) ?>#ventas-list">Anterior</a>
          <span>P&aacute;gina <?= $ventaPagina ?> de <?= $ventasPaginas ?></span>
          <a class="btn secondary <?= $ventaPagina >= $ventasPaginas ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($ventaQueryBase + ['venta_pagina' => $ventaSiguiente])) ?>#ventas-list">Siguiente</a>
        </nav>
      <?php endif; ?>
    </article>
  </section>

  <?php if ($esAdministrador): ?>
  <section class="module" id="configuracion" data-settings-initial="<?= ($facturacionMensaje !== '' || $facturacionError !== '') ? 'facturacion' : 'usuarios' ?>">
    <nav class="settings-menu" aria-label="Secciones de configuraci&oacute;n">
      <button type="button" class="active" data-settings-target="usuarios" aria-selected="true">
        <span aria-hidden="true">&#128101;</span> Usuarios
      </button>
      <button type="button" data-settings-target="facturacion" aria-selected="false">
        <span aria-hidden="true">&#129534;</span> Facturaci&oacute;n
      </button>
    </nav>

    <div class="settings-panel" data-settings-panel="facturacion" hidden>
    <article class="panel billing-settings-panel">
      <div class="section-header">
        <div>
          <h2>Facturaci&oacute;n</h2>
          <span class="muted">Datos preparados para una futura impresi&oacute;n fiscal en ticketera.</span>
        </div>
        <span class="badge <?= $facturacionLista ? 'activa' : 'reservado' ?>"><?= $facturacionLista ? 'Configuraci&oacute;n lista' : 'Configuraci&oacute;n pendiente' ?></span>
      </div>

      <?php if ($facturacionMensaje !== ''): ?>
        <div class="notice success"><?= e($facturacionMensaje) ?></div>
      <?php endif; ?>
      <?php if ($facturacionError !== ''): ?>
        <div class="notice error"><?= e($facturacionError) ?></div>
      <?php endif; ?>

      <div class="notice info">
        Guardar estos datos no habilita todav&iacute;a la emisi&oacute;n fiscal. La opci&oacute;n Factura permanecer&aacute; desactivada hasta implementar y validar el formato autorizado por la DNIT.
      </div>

      <form action="guardar_configuracion_facturacion.php" method="post" class="grid compact billing-settings-form">
        <label>Modalidad
          <select name="modalidad_facturacion">
            <?php $modalidadActual = $empresaConfiguracion['modalidad_facturacion'] ?? 'pendiente'; ?>
            <option value="pendiente" <?= $modalidadActual === 'pendiente' ? 'selected' : '' ?>>Por definir</option>
            <option value="autoimpresor" <?= $modalidadActual === 'autoimpresor' ? 'selected' : '' ?>>Autoimpresor</option>
            <option value="ekuatia_i" <?= $modalidadActual === 'ekuatia_i' ? 'selected' : '' ?>>e-Kuatia'i</option>
            <option value="ekuatia" <?= $modalidadActual === 'ekuatia' ? 'selected' : '' ?>>e-Kuatia</option>
          </select>
        </label>
        <label>Ancho de papel
          <select name="ancho_papel_mm">
            <option value="80" <?= ($empresaConfiguracion['ancho_papel_mm'] ?? '80') === '80' ? 'selected' : '' ?>>80 mm</option>
            <option value="58" <?= ($empresaConfiguracion['ancho_papel_mm'] ?? '80') === '58' ? 'selected' : '' ?>>58 mm</option>
          </select>
        </label>
        <label>Raz&oacute;n social <input type="text" name="razon_social" maxlength="160" value="<?= e($empresaConfiguracion['razon_social'] ?? '') ?>"></label>
        <label>Nombre de fantas&iacute;a <input type="text" name="nombre_fantasia" maxlength="160" value="<?= e($empresaConfiguracion['nombre_fantasia'] ?? '') ?>"></label>
        <label>RUC <input type="text" name="ruc" maxlength="20" inputmode="numeric" pattern="[0-9-]*" value="<?= e($empresaConfiguracion['ruc'] ?? '') ?>"></label>
        <label>Actividad econ&oacute;mica <input type="text" name="actividad_economica" maxlength="180" value="<?= e($empresaConfiguracion['actividad_economica'] ?? '') ?>"></label>
        <label>Direcci&oacute;n <input type="text" name="direccion" maxlength="220" value="<?= e($empresaConfiguracion['direccion'] ?? '') ?>"></label>
        <label>Tel&eacute;fono <input type="text" name="telefono" maxlength="40" value="<?= e($empresaConfiguracion['telefono'] ?? '') ?>"></label>
        <label>Email <input type="email" name="email" maxlength="120" value="<?= e($empresaConfiguracion['email'] ?? '') ?>"></label>

        <div class="billing-settings-divider"><strong>Timbrado y numeraci&oacute;n</strong><span>Completar cuando la DNIT entregue la autorizaci&oacute;n.</span></div>
        <input type="hidden" name="numeracion_id" value="<?= (int)($numeracionFactura['id'] ?? 0) ?>">
        <label>N&uacute;mero de timbrado <input type="text" name="timbrado" maxlength="20" inputmode="numeric" pattern="[0-9]*" value="<?= e($numeracionFactura['timbrado'] ?? '') ?>"></label>
        <label>Establecimiento <input type="text" name="establecimiento" maxlength="3" inputmode="numeric" pattern="[0-9]{3}" placeholder="001" value="<?= e($numeracionFactura['establecimiento'] ?? '') ?>"></label>
        <label>Punto de expedici&oacute;n <input type="text" name="punto_expedicion" maxlength="3" inputmode="numeric" pattern="[0-9]{3}" placeholder="001" value="<?= e($numeracionFactura['punto_expedicion'] ?? '') ?>"></label>
        <label>N&uacute;mero desde <input type="number" name="numero_desde" min="1" max="9999999" value="<?= max(1, (int)($numeracionFactura['numero_desde'] ?? 1)) ?>"></label>
        <label>N&uacute;mero hasta <input type="number" name="numero_hasta" min="1" max="9999999" value="<?= max(1, (int)($numeracionFactura['numero_hasta'] ?? 9999999)) ?>"></label>
        <label>Pr&oacute;ximo n&uacute;mero <input type="number" name="proximo_numero" min="1" max="9999999" value="<?= max(1, (int)($numeracionFactura['ultimo_numero'] ?? 0) + 1) ?>"></label>
        <label>Vigencia desde <input type="date" name="vigencia_desde" value="<?= e($numeracionFactura['vigencia_desde'] ?? '') ?>"></label>
        <label>Vigencia hasta <input type="date" name="vigencia_hasta" value="<?= e($numeracionFactura['vigencia_hasta'] ?? '') ?>"></label>
        <label>Estado de numeraci&oacute;n
          <select name="estado_numeracion">
            <?php $estadoNumeracion = $numeracionFactura['estado'] ?? 'pendiente'; ?>
            <option value="pendiente" <?= $estadoNumeracion === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="activo" <?= $estadoNumeracion === 'activo' ? 'selected' : '' ?>>Activo</option>
            <option value="inactivo" <?= $estadoNumeracion === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </label>
        <button type="submit">Guardar facturaci&oacute;n</button>
      </form>
    </article>
    </div>

    <div class="settings-panel active" data-settings-panel="usuarios">
    <article class="panel" hidden>
      <div class="section-header">
        <div>
          <h2>Configuraci&oacute;n</h2>
          <span class="muted">Sistema de reservas · <?= e(usuarioActual()['nombre'] ?? 'Usuario') ?></span>
          <span class="muted">Administra los usuarios que pueden acceder al sistema.</span>
        </div>
        <a class="btn small secondary" href="logout.php">Cerrar sesion</a>
      </div>

      <?php if ($usuarioMensaje !== ''): ?>
        <div class="notice success"><?= e($usuarioMensaje) ?></div>
      <?php endif; ?>
      <?php if ($usuarioError !== ''): ?>
        <div class="notice error"><?= e($usuarioError) ?></div>
      <?php endif; ?>

      <form action="guardar_usuario.php" method="post" class="grid compact user-form">
        <label>Nombre
          <input type="text" name="nombre" required maxlength="120" placeholder="Nombre completo">
        </label>
        <label>Usuario
          <input type="text" name="usuario" required maxlength="60" placeholder="usuario">
        </label>
        <label>Contrase&ntilde;a
          <input type="password" name="password" required minlength="6" placeholder="Minimo 6 caracteres">
        </label>
        <label>Rol
          <select name="rol">
            <option value="secretario">Secretario</option>
            <option value="administrador">Administrador</option>
          </select>
        </label>
        <button type="submit">Crear usuario</button>
      </form>
    </article>

    <article class="panel users-panel" id="usuariosList">
      <div class="section-header users-list-header">
        <div class="title-actions">
          <h2>Lista de usuarios</h2>
          <div class="users-header-actions">
            <a class="btn small secondary" href="logout.php">Cerrar sesi&oacute;n</a>
            <button type="button" id="openUserCreateModal">Nuevo usuario</button>
          </div>
        </div>
        <div class="search-form">
          <input type="search" id="userInventorySearch" placeholder="Buscar nombre, usuario, rol o estado...">
          <span class="muted" id="userInventoryCount"><?= count($usuariosSistema) ?> usuario(s)</span>
        </div>
      </div>

      <?php if ($usuarioMensaje !== ''): ?>
        <div class="notice success"><?= e($usuarioMensaje) ?></div>
      <?php endif; ?>
      <?php if ($usuarioError !== ''): ?>
        <div class="notice error"><?= e($usuarioError) ?></div>
      <?php endif; ?>

      <table>
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>&Uacute;ltimo acceso</th><th>Acciones</th></tr></thead>
        <tbody id="userInventoryRows">
          <?php foreach ($usuariosSistema as $usuarioSistema): ?>
            <?php $esUsuarioActual = (int)$usuarioSistema['id'] === (int)(usuarioActual()['id'] ?? 0); ?>
            <tr data-user-row data-user-search="<?= e(strtolower(trim(($usuarioSistema['nombre'] ?? '') . ' ' . ($usuarioSistema['usuario'] ?? '') . ' ' . ($usuarioSistema['rol'] ?? '') . ' ' . ($usuarioSistema['estado'] ?? '')))) ?>">
              <td><?= e($usuarioSistema['nombre']) ?></td>
              <td><?= e($usuarioSistema['usuario']) ?></td>
              <td><?= e($usuarioSistema['rol']) ?></td>
              <td><span class="badge <?= $usuarioSistema['estado'] === 'activo' ? 'activa' : 'inactiva' ?>"><?= e($usuarioSistema['estado']) ?></span></td>
              <td><?= $usuarioSistema['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($usuarioSistema['ultimo_acceso']))) : 'Sin acceso' ?></td>
              <td>
                <div class="actions">
                  <button
                    type="button"
                    class="small secondary"
                    data-edit-user
                    data-user-id="<?= (int)$usuarioSistema['id'] ?>"
                    data-user-name="<?= e($usuarioSistema['nombre']) ?>"
                    data-user-username="<?= e($usuarioSistema['usuario']) ?>"
                    data-user-role="<?= e($usuarioSistema['rol']) ?>"
                    data-user-status="<?= e($usuarioSistema['estado']) ?>"
                  >Editar</button>
                  <?php if ($esUsuarioActual): ?>
                    <span class="muted">Usuario actual</span>
                  <?php else: ?>
                    <form action="cambiar_estado_usuario.php" method="post" class="inline-form">
                      <input type="hidden" name="usuario_id" value="<?= (int)$usuarioSistema['id'] ?>">
                      <input type="hidden" name="estado" value="<?= $usuarioSistema['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                      <button type="submit" class="small <?= $usuarioSistema['estado'] === 'activo' ? 'danger' : '' ?>">
                        <?= $usuarioSistema['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                      </button>
                    </form>
                    <form action="eliminar_usuario.php" method="post" class="inline-form" onsubmit="return confirm('Seguro que deseas eliminar este usuario?');">
                      <input type="hidden" name="usuario_id" value="<?= (int)$usuarioSistema['id'] ?>">
                      <button type="submit" class="small danger">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr id="userInventoryEmpty" hidden><td colspan="6">No se encontraron usuarios.</td></tr>
        </tbody>
      </table>
    </article>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php if ($esAdministrador): ?>
<div class="modal-backdrop" id="userCreateModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2>Nuevo usuario</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="guardar_usuario.php" method="post" class="modal-body" id="userCreateForm">
      <label>Nombre
        <input type="text" name="nombre" id="createUserName" required maxlength="120" placeholder="Nombre completo">
      </label>
      <label>Usuario
        <input type="text" name="usuario" required maxlength="60" placeholder="usuario">
      </label>
      <label>Contrase&ntilde;a
        <input type="password" name="password" required minlength="6" placeholder="M&iacute;nimo 6 caracteres">
      </label>
      <label>Rol
        <select name="rol">
          <option value="secretario">Secretario</option>
          <option value="administrador">Administrador</option>
        </select>
      </label>
      <div class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit">Guardar usuario</button>
      </div>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="userEditModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2>Editar usuario</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_usuario.php" method="post" class="modal-body">
      <input type="hidden" name="usuario_id" id="editUserId">
      <label>Nombre
        <input type="text" name="nombre" id="editUserName" required maxlength="120">
      </label>
      <label>Usuario
        <input type="text" name="usuario" id="editUserUsername" required maxlength="60">
      </label>
      <label>Nueva contrase&ntilde;a
        <div class="password-edit-row">
          <input type="password" name="password" id="editUserPassword" minlength="6" placeholder="Dejar vacio para conservar la actual">
          <button type="button" class="secondary" id="toggleEditPassword">Ver</button>
        </div>
      </label>
      <p class="muted">La contrase&ntilde;a actual no se puede mostrar porque se guarda cifrada. Escribe una nueva solo si deseas cambiarla.</p>
      <label>Rol
        <select name="rol" id="editUserRole">
          <option value="secretario">Secretario</option>
          <option value="administrador">Administrador</option>
        </select>
      </label>
      <label>Estado
        <select name="estado" id="editUserStatus">
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
        </select>
      </label>
      <div class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit">Guardar cambios</button>
      </div>
    </form>
  </section>
</div>
<?php endif; ?>

<?php foreach ($cajaCierresRecientes as $cierre): ?>
  <?php $detalleCierre = $cajaCierresDetalle[(int)$cierre['id']] ?? ['movimientos' => []]; ?>
  <div class="modal-backdrop" id="cashDetailModal-<?= (int)$cierre['id'] ?>" aria-hidden="true">
    <section class="modal">
      <header class="modal-header">
        <h2>Detalle de caja</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <div class="reservation-detail">
          <dl>
            <div><dt>Fecha</dt><dd><?= e(date('d/m/Y', strtotime($cierre['fecha']))) ?></dd></div>
            <div><dt>Apertura</dt><dd><?= e(date('H:i', strtotime($cierre['abierta_en']))) ?></dd></div>
            <div><dt>Usuario apertura</dt><dd><?= e(($cierre['usuario_apertura_nombre'] ?? $cierre['usuario_apertura_usuario'] ?? '') ?: 'Sin usuario registrado') ?></dd></div>
            <div><dt>Cierre</dt><dd><?= e(date('H:i', strtotime($cierre['cerrada_en']))) ?></dd></div>
            <div><dt>Usuario cierre</dt><dd><?= e(($cierre['usuario_cierre_nombre'] ?? $cierre['usuario_cierre_usuario'] ?? '') ?: 'Sin usuario registrado') ?></dd></div>
            <div><dt>Monto inicial</dt><dd><?= formatearGuaranies($cierre['monto_inicial']) ?></dd></div>
            <div><dt>Saldo esperado</dt><dd><?= formatearGuaranies($detalleCierre['esperado_total'] ?? 0) ?></dd></div>
            <div><dt>Total contado</dt><dd><?= formatearGuaranies($detalleCierre['total_contado'] ?? 0) ?></dd></div>
            <div><dt>Efectivo contado</dt><dd><?= formatearGuaranies($cierre['monto_cierre_efectivo']) ?></dd></div>
            <div><dt>Dif. efectivo</dt><dd><?= formatearGuaranies($detalleCierre['diferencia_efectivo'] ?? 0) ?></dd></div>
            <div><dt>Transferencia contada</dt><dd><?= formatearGuaranies($cierre['monto_cierre_transferencia']) ?></dd></div>
            <div><dt>Dif. transferencia</dt><dd><?= formatearGuaranies($detalleCierre['diferencia_transferencia'] ?? 0) ?></dd></div>
          </dl>
        </div>

        <div class="cart-list">
          <table>
            <thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th>Detalle</th><th>M&eacute;todo</th><th>Monto</th></tr></thead>
            <tbody>
              <?php if (empty($detalleCierre['movimientos'])): ?>
                <tr><td colspan="6">Sin movimientos entre apertura y cierre.</td></tr>
              <?php else: ?>
                <?php foreach ($detalleCierre['movimientos'] as $movimiento): ?>
                  <tr class="<?= $movimiento['tipo'] === 'egreso' ? 'cash-out' : 'cash-in' ?>">
                    <td><?= e(date('H:i', strtotime($movimiento['fecha']))) ?></td>
                    <td><span class="badge <?= $movimiento['tipo'] === 'egreso' ? 'anulada' : 'activa' ?>"><?= e($movimiento['tipo']) ?></span></td>
                    <td><?= e($movimiento['concepto']) ?></td>
                    <td><?= e($movimiento['detalle']) ?></td>
                    <td><?= e($movimiento['metodo']) ?></td>
                    <td><?= $movimiento['tipo'] === 'egreso' ? '-' : '+' ?><?= formatearGuaranies($movimiento['monto']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <footer class="modal-footer">
          <button type="button" class="secondary" data-reprint-cash-ticket="<?= (int)$cierre['id'] ?>">Reimprimir ticket</button>
          <button type="button" class="secondary" data-close-modal>Cerrar</button>
        </footer>
      </div>
    </section>
  </div>
<?php endforeach; ?>

<div class="modal-backdrop" id="reservationModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <div class="modal-title-row">
        <h2>Adicionar reserva</h2>
        <span class="modal-title-badge" id="modalReservationNumber">#<?= (int)$proximaReservaId ?></span>
      </div>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="guardar_reserva.php" method="post" class="modal-body" id="reservationForm" enctype="multipart/form-data">
      <input type="hidden" name="cancha_id" id="modalCanchaId">
      <input type="hidden" name="fecha" id="modalFecha">
      <input type="hidden" name="hora_inicio" id="modalHoraInicio">
      <input type="hidden" name="hora_fin" id="modalHoraFin">

      <div class="modal-summary" id="modalSummary">
        <span id="modalSummaryText"></span>
        <label>Inicio
          <select id="modalHoraInicioVisible" aria-label="Hora de inicio"></select>
        </label>
        <label>Fin
          <select id="modalHoraFinVisible" aria-label="Hora de fin"></select>
        </label>
        <span class="duration-display" id="modalReservationDuration">1 hora</span>
      </div>

      <div class="client-picker">
        <label>Cliente
          <input type="hidden" name="cliente_id" id="modalClienteId" required>
          <input type="search" id="clientSearch" autocomplete="off" placeholder="Buscar por nombre o telefono" required>
        </label>
        <button type="button" class="add-client-button" id="openQuickClient" title="Agregar cliente">+</button>
        <div class="autocomplete-list" id="clientSuggestions"></div>
      </div>
      <label>Precio total <input type="text" name="precio_total" id="modalPrecioTotal" class="money-input" inputmode="numeric" required readonly></label>
      <?php if (!$cajaAbiertaHoy): ?>
        <div class="notice error wide">Caja cerrada: puedes crear la reserva, pero no registrar abonos.</div>
      <?php endif; ?>
      <div class="grid compact">
        <label>Abono <input type="text" name="monto_pago" id="modalMontoPago" class="money-input" inputmode="numeric" value="0" <?= !$cajaAbiertaHoy ? 'readonly' : '' ?>></label>
        <label>Estado
          <select name="estado" id="modalReservaEstado">
            <option value="reservado">Reservado</option>
            <option value="confirmado">Confirmado</option>
          </select>
        </label>
        <label>Comprobante
          <input type="file" name="comprobante_pago" id="modalComprobantePago" accept="image/*" <?= !$cajaAbiertaHoy ? 'disabled' : '' ?>>
        </label>
        <label>M&eacute;todo
          <select name="metodo" id="modalReservaMetodo" <?= !$cajaAbiertaHoy ? 'disabled' : '' ?>>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
          </select>
        </label>
      </div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit">Guardar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="quickClientModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Nuevo cliente</h2>
      <button type="button" class="icon-button" data-close-quick-client>&times;</button>
    </header>
    <form class="modal-body" id="quickClientForm">
      <label>Nombre <input type="text" name="nombre" id="quickClientName" required></label>
      <div class="duplicate-warning" id="quickClientDuplicateName"></div>
      <label>Telefono <input type="text" name="telefono" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-digits-only data-max-digits="10" required></label>
      <label>Email <input type="email" name="email"></label>
      <label>Documento o RUC <input type="text" name="documento" inputmode="numeric" pattern="[0-9-]*" data-ruc-input></label>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-quick-client>Cerrar</button>
        <button type="submit">Guardar cliente</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="editClientModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Editar cliente</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_cliente.php" method="post" class="modal-body" id="editClientForm">
      <label>Nombre <input type="text" name="nombre" id="editClientName" required></label>
      <label>Telefono <input type="text" name="telefono" id="editClientPhone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-digits-only data-max-digits="10" required></label>
      <label>Email <input type="email" name="email" id="editClientEmail"></label>
      <label>Documento o RUC <input type="text" name="documento" id="editClientDocument" inputmode="numeric" pattern="[0-9-]*" data-ruc-input></label>
      <label>Direccion <input type="text" name="direccion" id="editClientAddress"></label>
      <label>Estado
        <select name="estado" id="editClientStatus">
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
        </select>
      </label>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit">Actualizar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="toggleClientModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="toggleClientTitle">Cambiar estado</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="cambiar_estado_cliente.php" method="post" class="modal-body">
      <input type="hidden" name="id" id="toggleClientId">
      <input type="hidden" name="estado" id="toggleClientStatus">
      <p class="modal-message" id="toggleClientText">Cambiar estado del cliente?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" id="toggleClientSubmit">Confirmar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="deleteClientModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Eliminar cliente</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="eliminar_cliente.php" method="post" class="modal-body">
      <input type="hidden" name="id" id="deleteClientId">
      <p class="modal-message" id="deleteClientText">Eliminar este cliente?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" class="danger">Eliminar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="quickProviderModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Nuevo proveedor</h2>
      <button type="button" class="icon-button" data-close-quick-provider>&times;</button>
    </header>
    <form class="modal-body" id="quickProviderForm">
      <label>Nombre <input type="text" name="nombre" id="quickProviderName" required></label>
      <label>Tel&eacute;fono <input type="text" name="telefono" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-digits-only data-max-digits="10"></label>
      <label>Email <input type="email" name="email"></label>
      <label>RUC / Documento <input type="text" name="ruc" inputmode="numeric" pattern="[0-9-]*" data-ruc-input></label>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-quick-provider>Cerrar</button>
        <button type="submit">Guardar proveedor</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="quickProductModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2>Nuevo producto</h2>
      <button type="button" class="icon-button" data-close-quick-product>&times;</button>
    </header>
    <form class="modal-body" id="quickProductForm">
      <div class="grid compact">
        <label>Nombre <input type="text" name="nombre" id="quickProductName" required></label>
        <div class="duplicate-warning product-duplicate-warning" id="quickProductDuplicateName"></div>
        <label>C&oacute;digo de barras <input type="text" name="codigo_barra" placeholder="Escanear o escribir"></label>
        <div class="client-picker">
          <label>Proveedor
            <input type="hidden" name="proveedor_id" id="quickProductProviderId">
            <input type="search" id="quickProductProviderSearch" autocomplete="off" placeholder="Sin proveedor">
          </label>
          <button type="button" class="add-client-button" data-open-quick-provider="quickProduct" title="Agregar proveedor">+</button>
          <div class="autocomplete-list" id="quickProductProviderSuggestions"></div>
        </div>
        <div class="category-picker">
          <label>Categor&iacute;a <input type="text" name="categoria" id="quickProductCategoria" autocomplete="off" required></label>
          <button type="button" class="add-client-button" data-add-category="quickProductCategoria" title="Agregar categor&iacute;a">+</button>
          <div class="autocomplete-list" data-category-suggestions-for="quickProductCategoria"></div>
        </div>
        <label>Unidades por pack <input type="number" name="pack_cantidad" id="quickProductPackQuantity" min="0" step="1" value="0"></label>
        <label>Precio compra pack <input type="text" name="precio_compra_pack" id="quickProductCompraPack" class="money-input" inputmode="numeric" value="0"></label>
        <label>Precio de venta pack <input type="text" name="precio_pack" id="quickProductPackPrecio" class="money-input" inputmode="numeric" value="0"><small class="price-below-cost-warning" data-price-warning="pack" hidden></small></label>
        <label>Unidades por promoci&oacute;n <input type="number" name="promocion_cantidad" id="quickProductPromoQuantity" min="0" step="1" value="0"></label>
        <label>Precio promoci&oacute;n <input type="text" name="precio_promocion" id="quickProductPromoPrice" class="money-input" inputmode="numeric" value="0"><small class="price-below-cost-warning" data-price-warning="promotion" hidden></small></label>
        <label>Precio compra unidad <input type="text" name="precio_compra" id="quickProductCompra" class="money-input" inputmode="numeric" value="0" data-positive-money></label>
        <label>Precio de venta unidad <input type="text" name="precio_venta" id="quickProductVenta" class="money-input" inputmode="numeric" value="0" data-positive-money><small class="price-below-cost-warning" data-price-warning="unit" hidden></small></label>
        <label>Stock inicial <input type="number" name="stock" id="quickProductStock" min="0" step="1" value="0"></label>
      </div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-quick-product>Cerrar</button>
        <button type="submit">Guardar producto</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="saleProductSearchModal" aria-hidden="true">
  <section class="modal compact-product-search-modal">
    <header class="modal-header">
      <h2>Buscar producto</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <label>Buscar
        <input type="search" id="saleProductSearchModalInput" autocomplete="off" placeholder="Nombre, codigo o categoria">
      </label>
      <div class="product-search-results" id="saleProductSearchResults"></div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="courtModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="courtModalTitle">Editar cancha</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_cancha.php" method="post" class="modal-body" id="courtForm">
      <input type="hidden" name="cancha_id" id="editCanchaModalId">
      <label>Nombre <input type="text" name="nombre" id="editCanchaNombre" required></label>
      <label>Tipo <input type="text" name="tipo" id="editCanchaTipo"></label>
      <label>Precio por hora <input type="text" name="precio_hora" id="editCanchaPrecio" class="money-input" inputmode="numeric" required></label>
      <label>Estado
        <select name="estado" id="editCanchaEstado" required>
          <option value="activa">Activa</option>
          <option value="mantenimiento">Mantenimiento</option>
          <option value="inactiva">Inactiva</option>
        </select>
      </label>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit" id="courtSubmitButton">Guardar cambios</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="deleteCourtModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Eliminar cancha</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="eliminar_cancha.php" method="post" class="modal-body">
      <input type="hidden" name="cancha_id" id="deleteCanchaId">
      <p class="modal-message" id="deleteCanchaText">Eliminar esta cancha?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" class="danger">Eliminar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="providerModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Editar proveedor</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_proveedor.php" method="post" class="modal-body">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="proveedor_id" id="editProveedorId">
      <div class="grid compact">
        <label>Nombre <input type="text" name="nombre" id="editProveedorNombre" required></label>
        <label>Tel&eacute;fono <input type="text" name="telefono" id="editProveedorTelefono" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-digits-only data-max-digits="10"></label>
        <label>Email <input type="email" name="email" id="editProveedorEmail"></label>
        <label>RUC / Documento <input type="text" name="ruc" id="editProveedorRuc" inputmode="numeric" pattern="[0-9-]*" data-ruc-input></label>
        <label>Estado
          <select name="estado" id="editProveedorEstado" required>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
        </label>
        <label class="wide">Direcci&oacute;n <input type="text" name="direccion" id="editProveedorDireccion"></label>
      </div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit">Guardar cambios</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="toggleProviderModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="toggleProviderTitle">Cambiar estado</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_proveedor.php" method="post" class="modal-body">
      <input type="hidden" name="accion" value="estado">
      <input type="hidden" name="proveedor_id" id="toggleProviderId">
      <input type="hidden" name="estado" id="toggleProviderStatus">
      <p class="modal-message" id="toggleProviderText">Cambiar estado del proveedor?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" id="toggleProviderSubmit">Confirmar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="deleteProviderModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Eliminar proveedor</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="eliminar_proveedor.php" method="post" class="modal-body">
      <input type="hidden" name="proveedor_id" id="deleteProveedorId">
      <p class="modal-message" id="deleteProveedorText">Eliminar este proveedor?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" class="danger">Eliminar</button>
      </footer>
    </form>
  </section>
</div>

  <div class="modal-backdrop" id="productModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2 id="productModalTitle">Editar producto</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_producto.php" method="post" class="modal-body" id="productForm">
      <input type="hidden" name="producto_id" id="editProductoId">
      <div class="grid compact">
        <label>Nombre <input type="text" name="nombre" id="editProductoNombre" required></label>
        <div class="duplicate-warning product-duplicate-warning" id="editProductDuplicateName"></div>
        <label>C&oacute;digo de barras <input type="text" name="codigo_barra" id="editProductoCodigo"></label>
        <div class="client-picker">
          <label>Proveedor
            <input type="hidden" name="proveedor_id" id="editProductoProveedorId">
            <input type="search" id="editProductoProveedorSearch" autocomplete="off" placeholder="Sin proveedor">
          </label>
          <button type="button" class="add-client-button" data-open-quick-provider="editProduct" title="Agregar proveedor">+</button>
          <div class="autocomplete-list" id="editProductoProveedorSuggestions"></div>
        </div>
        <div class="category-picker">
          <label>Categor&iacute;a <input type="text" name="categoria" id="editProductoCategoria" autocomplete="off" required></label>
          <button type="button" class="add-client-button" data-add-category="editProductoCategoria" title="Agregar categor&iacute;a">+</button>
          <div class="autocomplete-list" data-category-suggestions-for="editProductoCategoria"></div>
        </div>
        <label>Unidades por pack <input type="number" name="pack_cantidad" id="editProductoPackCantidad" min="0" step="1" required></label>
        <label>Precio compra pack <input type="text" name="precio_compra_pack" id="editProductoCompraPack" class="money-input" inputmode="numeric" required></label>
        <label>Precio de venta pack <input type="text" name="precio_pack" id="editProductoPackPrecio" class="money-input" inputmode="numeric" required><small class="price-below-cost-warning" data-price-warning="pack" hidden></small></label>
        <label>Unidades por promoci&oacute;n <input type="number" name="promocion_cantidad" id="editProductoPromoCantidad" min="0" step="1" required></label>
        <label>Precio promoci&oacute;n <input type="text" name="precio_promocion" id="editProductoPromoPrecio" class="money-input" inputmode="numeric" required><small class="price-below-cost-warning" data-price-warning="promotion" hidden></small></label>
        <label>Precio compra unidad <input type="text" name="precio_compra" id="editProductoCompra" class="money-input" inputmode="numeric" required data-positive-money></label>
        <label>Precio de venta unidad <input type="text" name="precio_venta" id="editProductoVenta" class="money-input" inputmode="numeric" required data-positive-money><small class="price-below-cost-warning" data-price-warning="unit" hidden></small></label>
        <label>Stock <input type="number" name="stock" id="editProductoStock" min="0" step="1" required></label>
        <label>Estado
          <select name="estado" id="editProductoEstado" required>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
        </label>
      </div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit" id="productSubmitButton">Guardar cambios</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="toggleProductModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="toggleProductTitle">Cambiar estado</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="cambiar_estado_producto.php" method="post" class="modal-body">
      <input type="hidden" name="producto_id" id="toggleProductId">
      <input type="hidden" name="estado" id="toggleProductStatus">
      <p class="modal-message" id="toggleProductText">Cambiar estado del producto?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" id="toggleProductSubmit">Confirmar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="deleteProductModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Eliminar producto</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="eliminar_producto.php" method="post" class="modal-body">
      <input type="hidden" name="producto_id" id="deleteProductId">
      <p class="modal-message" id="deleteProductText">Eliminar este producto?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" class="danger">Eliminar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop <?= $clienteError !== '' ? 'open' : '' ?>" id="clientMessageModal" aria-hidden="<?= $clienteError !== '' ? 'false' : 'true' ?>">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Aviso</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message" id="clientMessageText"><?= e($clienteError) ?></p>
      <footer class="modal-footer">
        <button type="button" data-close-modal>Entendido</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="receiptImageModal" aria-hidden="true">
  <section class="modal receipt-modal">
    <header class="modal-header">
      <h2>Comprobante</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <img src="" alt="Comprobante de pago" id="receiptImagePreview" class="receipt-preview">
      <form action="guardar_caja.php" method="post" id="confirmPendingReceiptForm" hidden>
        <input type="hidden" name="accion" value="confirmar_comprobante_abono">
        <input type="hidden" name="fecha" value="<?= e($cajaFecha) ?>">
        <input type="hidden" name="abono_pendiente_id" id="confirmPendingReceiptId">
        <input type="hidden" name="reserva_id" id="confirmPendingReceiptReservaId">
        <input type="hidden" name="volver" id="confirmPendingReceiptVolver" value="reservas">
      </form>
      <footer class="modal-footer">
        <button type="submit" form="confirmPendingReceiptForm" id="confirmPendingReceiptButton" hidden>Confirmado</button>
        <button type="button" data-close-modal>Cerrar</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="categoryModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="categoryModalTitle">Nueva categor&iacute;a</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="guardar_categoria.php" method="post" class="modal-body" id="categoryForm">
      <input type="hidden" name="categoria_id" id="editCategoriaId">
      <input type="hidden" name="accion" id="editCategoriaAccion" value="crear">
      <input type="hidden" id="editCategoriaOriginalNombre">
      <input type="hidden" id="categoryTargetInput">
      <label>Nombre <input type="text" name="nombre" id="editCategoriaNombre" required></label>
      <div class="duplicate-warning" id="categoryDuplicateWarning"></div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit" id="categorySubmitButton">Guardar categor&iacute;a</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="toggleCategoryModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2 id="toggleCategoryTitle">Cambiar estado</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="editar_categoria.php" method="post" class="modal-body">
      <input type="hidden" name="categoria_id" id="toggleCategoryId">
      <input type="hidden" name="accion" id="toggleCategoryAction">
      <p class="modal-message" id="toggleCategoryText">Cambiar estado de la categor&iacute;a?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" id="toggleCategorySubmit">Confirmar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="deleteCategoryModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Eliminar categor&iacute;a</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="eliminar_categoria.php" method="post" class="modal-body">
      <input type="hidden" name="categoria_id" id="deleteCategoryId">
      <p class="modal-message" id="deleteCategoryText">Eliminar esta categor&iacute;a?</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="submit" class="danger">Eliminar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="saleCheckoutModal" aria-hidden="true">
  <section class="modal small-modal checkout-modal">
    <header class="modal-header">
      <h2>Registrar venta</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <div class="checkout-total">
        <span>Total a cobrar</span>
        <strong id="checkoutTotal">0</strong>
      </div>
      <div class="checkout-method">
        <span>M&eacute;todo de pago</span>
        <select id="checkoutMethod">
          <option value="efectivo">Efectivo</option>
          <option value="transferencia">Transferencia</option>
        </select>
      </div>
      <label>Recibido <input type="text" id="checkoutReceived" class="money-input" inputmode="numeric" value="0"></label>
      <div class="checkout-change">
        <span>Vuelto</span>
        <strong id="checkoutChange">0</strong>
      </div>
      <label class="check-line">
        <input type="checkbox" id="checkoutPrintTicket">
        Imprimir ticket
      </label>
      <label class="check-line muted" title="La generacion de facturas se preparara en una proxima etapa">
        <input type="checkbox" id="checkoutPrintInvoice" disabled>
        Imprimir factura (pr&oacute;ximamente)
      </label>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="button" id="confirmSaleSubmit">Confirmar venta</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="saleTicketPreviewModal" aria-hidden="true">
  <section class="modal ticket-preview-modal">
    <header class="modal-header">
      <h2>Vista previa del ticket</h2>
      <button type="button" class="icon-button" id="closeSaleTicketPreview">&times;</button>
    </header>
    <div class="modal-body">
      <div class="ticket-preview-frame-wrap">
        <iframe id="saleTicketPreviewFrame" title="Vista previa del ticket de venta"></iframe>
      </div>
      <footer class="modal-footer ticket-preview-actions">
        <button type="button" class="secondary" id="cancelSaleTicketPrint">Cancelar</button>
        <button type="button" id="printRegisteredSaleTicket">Imprimir</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="cashCloseConfirmModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Cerrar caja</h2>
      <button type="button" class="icon-button" data-close-cash-close>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message">Se cerrar&aacute; la caja actual. Revisa los montos antes de confirmar.</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-cash-close>Cancelar</button>
        <button type="button" class="secondary" id="printCashCloseTicket">Imprimir ticket</button>
        <button type="button" class="danger" id="confirmCashClose">Aceptar</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="saleDetailModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2>Detalle de venta</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <div class="reservation-detail" id="saleDetailSummary"></div>
      <div class="cart-list" id="saleDetailItems"></div>
      <form action="anular_venta.php" method="post" id="cancelSaleForm">
        <input type="hidden" name="venta_id" id="cancelSaleId">
      </form>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="button" class="secondary" id="editSaleFromDetail">Editar venta</button>
        <button type="button" class="danger" id="cancelSaleButton">Anular venta</button>
        <button type="button" id="reprintSaleTicket">Reimprimir ticket</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="purchaseDetailModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2>Detalle de compra</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <div class="reservation-detail" id="purchaseDetailSummary"></div>
      <div class="cart-list" id="purchaseDetailItems"></div>
      <form action="anular_compra.php" method="post" id="cancelPurchaseForm">
        <input type="hidden" name="compra_id" id="cancelPurchaseId">
      </form>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="button" class="secondary" id="editPurchaseFromDetail">Editar compra</button>
        <button type="button" class="danger" id="cancelPurchaseButton">Anular compra</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="cancelPurchaseConfirmModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Anular compra</h2>
      <button type="button" class="icon-button" data-close-cancel-purchase>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message">Anular esta compra? Se descontara del stock lo que ingreso con esta compra.</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-cancel-purchase>Cancelar</button>
        <button type="button" class="danger" id="confirmCancelPurchase">Anular compra</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop" id="cancelSaleConfirmModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Anular venta</h2>
      <button type="button" class="icon-button" data-close-cancel-sale>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message">Anular esta venta? Se devolvera el stock de sus productos.</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-cancel-sale>Cancelar</button>
        <button type="button" class="danger" id="confirmCancelSale">Anular venta</button>
      </footer>
    </div>
  </section>
</div>

<?php if ($reservaError !== ''): ?>
  <div class="modal-backdrop open" id="serverMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($reservaError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($canchaError !== ''): ?>
  <div class="modal-backdrop open" id="courtMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($canchaError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($proveedorError !== ''): ?>
  <div class="modal-backdrop open" id="providerMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($proveedorError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($productoError !== ''): ?>
  <div class="modal-backdrop open" id="productMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($productoError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($compraError !== ''): ?>
  <div class="modal-backdrop open" id="purchaseMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($compraError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($ventaError !== ''): ?>
  <div class="modal-backdrop open" id="saleMessageModal" aria-hidden="false">
    <section class="modal small-modal">
      <header class="modal-header">
        <h2>Aviso</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <p class="modal-message"><?= e($ventaError) ?></p>
        <footer class="modal-footer">
          <button type="button" data-close-modal>Entendido</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($pagoTicket): ?>
  <div class="modal-backdrop open" id="paymentTicketModal" aria-hidden="false">
    <section class="modal ticket-modal">
      <header class="modal-header">
        <h2>Ticket de pago</h2>
        <button type="button" class="icon-button" data-close-modal>&times;</button>
      </header>
      <div class="modal-body">
        <div class="ticket">
          <div class="ticket-title">
            <strong>Cancha Sint&eacute;tica</strong>
            <span>Pago #<?= (int)$pagoTicket['id'] ?></span>
          </div>
          <dl>
            <div><dt>Cliente</dt><dd><?= e($pagoTicket['cliente']) ?><?= $pagoTicket['telefono'] ? ' - ' . e($pagoTicket['telefono']) : '' ?></dd></div>
            <div><dt>Cancha</dt><dd><?= e($pagoTicket['cancha']) ?></dd></div>
            <div><dt>Reserva</dt><dd>#<?= (int)$pagoTicket['reserva_id'] ?> - <?= e(date('d/m/Y', strtotime($pagoTicket['fecha']))) ?> de <?= e(substr($pagoTicket['hora_inicio'], 0, 5)) ?> a <?= e(substr($pagoTicket['hora_fin'], 0, 5) === '24:00' ? '00:00' : substr($pagoTicket['hora_fin'], 0, 5)) ?></dd></div>
            <div><dt>Alquiler</dt><dd><?= formatearGuaranies($pagoTicket['precio_total']) ?></dd></div>
            <div><dt>Consumos</dt><dd><?= formatearGuaranies($pagoTicket['consumo_total']) ?></dd></div>
            <div><dt>Total reserva</dt><dd><?= formatearGuaranies($pagoTicket['total_reserva']) ?></dd></div>
            <?php if (!empty($pagoTicket['comprobante_path'])): ?>
              <div><dt>Comprobante</dt><dd><a href="<?= e($pagoTicket['comprobante_path']) ?>" target="_blank" rel="noopener">Ver imagen</a></dd></div>
            <?php endif; ?>
            <div class="ticket-total"><dt>Total efectivo</dt><dd><?= formatearGuaranies($pagoTicket['pagado_efectivo']) ?></dd></div>
            <div class="ticket-total-line"><dt>Total transferencia</dt><dd><?= formatearGuaranies($pagoTicket['pagado_transferencia']) ?></dd></div>
            <div class="ticket-grand-total"><dt>Total pagado</dt><dd><?= formatearGuaranies($pagoTicket['total_pagado']) ?></dd></div>
          </dl>
        </div>
        <footer class="modal-footer">
          <button type="button" class="secondary" onclick="window.print()">Imprimir</button>
          <button type="button" data-close-modal>Cerrar</button>
        </footer>
      </div>
    </section>
  </div>
<?php endif; ?>

<div class="modal-backdrop" id="detailModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <h2 id="reservationDetailTitle">Detalle de reserva</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <div class="reservation-detail" id="reservationDetail"></div>
      <div class="reservation-section-tabs" role="tablist" aria-label="Acciones de la reserva">
        <button type="button" class="reservation-section-tab" data-reservation-section="reservationEditSection" aria-controls="reservationEditSection" aria-selected="false">Editar reserva</button>
        <button type="button" class="reservation-section-tab" data-reservation-section="reservationPaymentSection" aria-controls="reservationPaymentSection" aria-selected="false">Registrar pago</button>
        <button type="button" class="reservation-section-tab" data-reservation-section="reservationConsumptionSection" aria-controls="reservationConsumptionSection" aria-selected="false">Registrar consumo</button>
      </div>
      <details class="modal-section" id="reservationEditSection">
        <summary>Editar reserva</summary>
        <form action="editar_reserva.php" method="post" class="grid compact" id="reservationEditForm">
          <input type="hidden" name="reserva_id" id="editReservaId">
          <div class="client-picker">
            <label>Cliente
              <input type="hidden" name="cliente_id" id="editClienteId" required>
              <input type="search" id="editClientSearch" autocomplete="off" placeholder="Buscar por nombre o telefono" required>
            </label>
            <button type="button" class="add-client-button" id="openEditQuickClient" title="Agregar cliente">+</button>
            <div class="autocomplete-list" id="editClientSuggestions"></div>
          </div>
          <label>Cancha
            <select name="cancha_id" id="editCanchaId" required>
              <?php foreach ($canchas as $cancha): ?>
                <option value="<?= (int)$cancha['id'] ?>"><?= e($cancha['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Fecha <input type="date" name="fecha" id="editFecha" min="<?= e($hoy) ?>" required></label>
          <label>Precio total <input type="text" name="precio_total" id="editPrecioTotal" class="money-input" inputmode="numeric" required readonly></label>
          <label>Hora inicio
            <select id="editHoraInicioHour" required></select>
            <input type="hidden" name="hora_inicio" id="editHoraInicio">
          </label>
          <label>Hora fin
            <select id="editHoraFinHour" required></select>
            <input type="hidden" name="hora_fin" id="editHoraFin">
          </label>
          <label>Estado
            <select name="estado" id="editEstado" required>
              <option value="reservado">Reservado</option>
              <option value="confirmado">Confirmado</option>
              <option value="finalizado">Finalizado</option>
              <option value="cancelado" id="editEstadoCancelado">Cancelado</option>
            </select>
          </label>
          <button type="submit">Guardar cambios</button>
        </form>
      </details>

      <details class="modal-section" id="reservationPaymentSection">
        <summary>Registrar pago</summary>
      <form action="guardar_pago.php" method="post" class="grid compact" id="reservationPaymentForm">
        <input type="hidden" name="reserva_id" id="detailReservaIdPago">
        <input type="hidden" name="pago_id" id="detailPagoIdMetodo">
        <input type="hidden" name="actualizar_metodo_pago" id="updatePaymentMethodFlag" value="0">
        <div class="payment-summary wide" id="paymentReservationSummary"></div>
        <p class="empty-state wide" id="reservationPaidNotice" hidden>Esta reserva ya esta pagada completamente. No se pueden registrar pagos duplicados, pero podes corregir el metodo del ultimo pago.</p>
        <?php if (!$cajaAbiertaHoy): ?>
          <div class="notice error wide" id="reservationPaymentCashNotice">Caja cerrada: no se pueden registrar pagos ni abonos.</div>
        <?php endif; ?>
        <label>Monto <input type="text" name="monto" class="money-input" inputmode="numeric" required></label>
        <label>M&eacute;todo
          <select name="metodo" id="reservationPaymentMethod">
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
          </select>
        </label>
        <button type="submit" id="reservationPaymentSubmit">Registrar pago</button>
      </form>
      </details>

      <details class="modal-section" id="reservationConsumptionSection">
        <summary>Registrar consumo</summary>
        <?php if (!$cajaAbiertaHoy): ?>
          <div class="notice error">Debes abrir la caja antes de registrar consumos.</div>
        <?php endif; ?>
        <form action="guardar_venta.php" method="post" class="grid compact">
          <input type="hidden" name="reserva_id" id="detailReservaIdVenta">
          <input type="hidden" name="origen" value="reserva">
          <div class="product-picker">
            <label>Producto
              <input type="hidden" id="reservaVentaProducto">
              <input type="search" id="reservaVentaProductoSearch" autocomplete="off" placeholder="Buscar producto o codigo de barras">
            </label>
            <div class="autocomplete-list" id="reservationProductSuggestions"></div>
          </div>
          <label>Tipo
            <select id="reservaVentaTipo">
              <option value="unidad">Unidad</option>
              <option value="pack" id="reservaVentaTipoPack">Pack</option>
              <option value="promocion" id="reservaVentaTipoPromocion">Promoci&oacute;n</option>
            </select>
          </label>
          <label>Cantidad <input type="number" id="reservaVentaCantidad" min="1" step="1" value="1"></label>
          <label>M&eacute;todo
            <select name="metodo">
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia</option>
            </select>
          </label>
          <label>Total estimado <input type="text" id="reservaVentaTotal" value="0" readonly></label>
          <button type="button" class="secondary" id="addReservationSaleItem" <?= !$cajaAbiertaHoy ? 'disabled' : '' ?>>Agregar producto</button>
          <div class="cart-list wide" id="reservationSaleCartList"></div>
          <div id="reservationSaleCartInputs"></div>
          <button type="submit" <?= !$cajaAbiertaHoy ? 'disabled' : '' ?>>Registrar consumo</button>
        </form>
      </details>
    </div>
  </section>
</div>

<script>
  const menuButtons = document.querySelectorAll('.module-menu button');
  const modules = document.querySelectorAll('.module');
  const settingsModule = document.getElementById('configuracion');
  const settingsButtons = document.querySelectorAll('[data-settings-target]');
  const settingsPanels = document.querySelectorAll('[data-settings-panel]');
  const courts = <?= json_encode($canchas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const providers = <?= json_encode($proveedores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  let clients = <?= json_encode($clientes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const allClients = <?= json_encode($todosClientes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  let reservations = <?= json_encode($reservas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const reservationConsumptions = <?= json_encode($consumosReservas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const nextReservationId = <?= (int)$proximaReservaId ?>;
  const initialReservationDetailId = <?= (int)$reservaDetalleId ?>;
  const products = <?= json_encode($productos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  let productCategories = <?= json_encode(array_column($categoriasActivas, 'nombre'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const sales = <?= json_encode($todasVentas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const saleDetails = <?= json_encode($ventaDetalles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const purchases = <?= json_encode($todasCompras, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const purchaseDetails = <?= json_encode($compraDetalles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const cashOpenToday = <?= $cajaAbiertaHoy ? 'true' : 'false' ?>;
  const cashTicketData = <?= json_encode([
    'fecha' => $cajaFecha,
    'estado' => $cajaJornada['estado'] ?? 'sin_abrir',
    'abierta_en' => $cajaJornada['abierta_en'] ?? null,
    'usuario_apertura' => $cajaUsuarioApertura,
    'usuario_cierre' => $cajaUsuarioCierre ?: (usuarioActual()['nombre'] ?? usuarioActual()['usuario'] ?? ''),
    'monto_inicial' => $cajaMontoInicial,
    'saldo_total' => $cajaSaldo,
    'saldo_efectivo' => $cajaSaldoEfectivo,
    'saldo_transferencia' => $cajaSaldoTransferencia,
    'movimientos' => $cajaMovimientos,
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const cashClosedTickets = <?= json_encode(array_reduce($cajaCierresRecientes, function (array $carry, array $cierre) use ($cajaCierresDetalle): array {
    $detalle = $cajaCierresDetalle[(int)$cierre['id']] ?? ['movimientos' => []];
    $carry[(int)$cierre['id']] = [
      'fecha' => $cierre['fecha'],
      'estado' => $cierre['estado'],
      'abierta_en' => $cierre['abierta_en'],
      'cerrada_en' => $cierre['cerrada_en'],
      'usuario_apertura' => ($cierre['usuario_apertura_nombre'] ?? $cierre['usuario_apertura_usuario'] ?? '') ?: 'Sin usuario registrado',
      'usuario_cierre' => ($cierre['usuario_cierre_nombre'] ?? $cierre['usuario_cierre_usuario'] ?? '') ?: 'Sin usuario registrado',
      'monto_inicial' => (float)$cierre['monto_inicial'],
      'saldo_total' => (float)($detalle['esperado_total'] ?? 0),
      'saldo_efectivo' => (float)($detalle['esperado_efectivo'] ?? 0),
      'saldo_transferencia' => (float)($detalle['esperado_transferencia'] ?? 0),
      'monto_cierre_efectivo' => (float)$cierre['monto_cierre_efectivo'],
      'monto_cierre_transferencia' => (float)$cierre['monto_cierre_transferencia'],
      'movimientos' => $detalle['movimientos'] ?? [],
    ];
    return $carry;
  }, []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const weekCalendar = document.getElementById('weekCalendar');
  const weekCalendarHeader = document.getElementById('weekCalendarHeader');
  const courtTabs = document.getElementById('courtTabs');
  const calendarTitle = document.getElementById('calendarTitle');
  const reservationModal = document.getElementById('reservationModal');
  const quickClientModal = document.getElementById('quickClientModal');
  const quickProviderModal = document.getElementById('quickProviderModal');
  const quickProductModal = document.getElementById('quickProductModal');
  const quickProductName = document.getElementById('quickProductName');
  const quickProductCompra = document.getElementById('quickProductCompra');
  const productProviderId = document.getElementById('productProviderId');
  const productProviderSearch = document.getElementById('productProviderSearch');
  const productProviderSuggestions = document.getElementById('productProviderSuggestions');
  const quickProductProviderId = document.getElementById('quickProductProviderId');
  const quickProductProviderSearch = document.getElementById('quickProductProviderSearch');
  const quickProductProviderSuggestions = document.getElementById('quickProductProviderSuggestions');
  const quickProductPackQuantity = document.getElementById('quickProductPackQuantity');
  const quickProductStock = document.getElementById('quickProductStock');
  const detailModal = document.getElementById('detailModal');
  const courtModal = document.getElementById('courtModal');
  const providerModal = document.getElementById('providerModal');
  const toggleProviderModal = document.getElementById('toggleProviderModal');
  const deleteProviderModal = document.getElementById('deleteProviderModal');
  const productModal = document.getElementById('productModal');
  const categoryModal = document.getElementById('categoryModal');
  const clientMessageModal = document.getElementById('clientMessageModal');
  const clientMessageText = document.getElementById('clientMessageText');
  const modalClienteId = document.getElementById('modalClienteId');
  const clientSearch = document.getElementById('clientSearch');
  const clientSuggestions = document.getElementById('clientSuggestions');
  const saleClienteId = document.getElementById('saleClienteId');
  const saleClientSearch = document.getElementById('saleClientSearch');
  const saleClientSuggestions = document.getElementById('saleClientSuggestions');
  const ventaProductoId = document.getElementById('ventaProducto');
  const ventaProductoSearch = document.getElementById('ventaProductoSearch');
  const productSuggestions = document.getElementById('productSuggestions');
  const saleProductSearchModal = document.getElementById('saleProductSearchModal');
  const saleProductSearchModalInput = document.getElementById('saleProductSearchModalInput');
  const saleProductSearchResults = document.getElementById('saleProductSearchResults');
  const reservaVentaProductoId = document.getElementById('reservaVentaProducto');
  const reservaVentaProductoSearch = document.getElementById('reservaVentaProductoSearch');
  const reservationProductSuggestions = document.getElementById('reservationProductSuggestions');
  const saleForm = document.getElementById('saleForm');
  const saleVentaId = document.getElementById('saleVentaId');
  const saleFormTitle = document.getElementById('saleFormTitle');
  const saleSubmitButton = document.getElementById('saleSubmitButton');
  const addSaleItemButton = document.getElementById('addSaleItem');
  const cancelSaleEdit = document.getElementById('cancelSaleEdit');
  const saleCheckoutModal = document.getElementById('saleCheckoutModal');
  const checkoutTotal = document.getElementById('checkoutTotal');
  const checkoutMethod = document.getElementById('checkoutMethod');
  const checkoutReceived = document.getElementById('checkoutReceived');
  const checkoutChange = document.getElementById('checkoutChange');
  const checkoutPrintTicket = document.getElementById('checkoutPrintTicket');
  const saleTicketPreviewModal = document.getElementById('saleTicketPreviewModal');
  const saleTicketPreviewFrame = document.getElementById('saleTicketPreviewFrame');
  const cashCloseForm = document.getElementById('cashCloseForm');
  const cashCloseConfirmModal = document.getElementById('cashCloseConfirmModal');
  const confirmCashClose = document.getElementById('confirmCashClose');
  const printCashCloseTicketButton = document.getElementById('printCashCloseTicket');
  const saleDetailModal = document.getElementById('saleDetailModal');
  const saleDetailSummary = document.getElementById('saleDetailSummary');
  const saleDetailItems = document.getElementById('saleDetailItems');
  const cancelSaleId = document.getElementById('cancelSaleId');
  const cancelSaleButton = document.getElementById('cancelSaleButton');
  const cancelSaleConfirmModal = document.getElementById('cancelSaleConfirmModal');
  const confirmCancelSale = document.getElementById('confirmCancelSale');
  const editSaleFromDetail = document.getElementById('editSaleFromDetail');
  const clientListSearch = document.getElementById('clientListSearch');
  const clientListCount = document.getElementById('clientListCount');
  const clientListPagination = document.getElementById('clientListPagination');
  const categoryListSearch = document.getElementById('categoryListSearch');
  const categoryListCount = document.getElementById('categoryListCount');
  const categoryListPagination = document.getElementById('categoryListPagination');
  const providerSearch = document.getElementById('providerSearch');
  const providerCount = document.getElementById('providerCount');
  const providerListPagination = document.getElementById('providerListPagination');
  const purchaseProviderId = document.getElementById('purchaseProviderId');
  const purchaseProviderSearch = document.getElementById('purchaseProviderSearch');
  const purchaseProviderSuggestions = document.getElementById('purchaseProviderSuggestions');
  const editProductoProveedorId = document.getElementById('editProductoProveedorId');
  const editProductoProveedorSearch = document.getElementById('editProductoProveedorSearch');
  const editProductoProveedorSuggestions = document.getElementById('editProductoProveedorSuggestions');
  const purchaseForm = document.getElementById('purchaseForm');
  const purchaseCompraId = document.getElementById('purchaseCompraId');
  const purchaseFormTitle = document.getElementById('purchaseFormTitle');
  const purchaseSubmitButton = document.getElementById('purchaseSubmitButton');
  const addPurchaseItemButton = document.getElementById('addPurchaseItem');
  const cancelPurchaseEdit = document.getElementById('cancelPurchaseEdit');
  const purchaseProductId = document.getElementById('purchaseProduct');
  const purchaseProductSearch = document.getElementById('purchaseProductSearch');
  const purchaseProductSuggestions = document.getElementById('purchaseProductSuggestions');
  const purchaseDetailModal = document.getElementById('purchaseDetailModal');
  const purchaseDetailSummary = document.getElementById('purchaseDetailSummary');
  const purchaseDetailItems = document.getElementById('purchaseDetailItems');
  const receiptImageModal = document.getElementById('receiptImageModal');
  const receiptImagePreview = document.getElementById('receiptImagePreview');
  const confirmPendingReceiptForm = document.getElementById('confirmPendingReceiptForm');
  const confirmPendingReceiptId = document.getElementById('confirmPendingReceiptId');
  const confirmPendingReceiptReservaId = document.getElementById('confirmPendingReceiptReservaId');
  const confirmPendingReceiptVolver = document.getElementById('confirmPendingReceiptVolver');
  const confirmPendingReceiptButton = document.getElementById('confirmPendingReceiptButton');
  const cancelPurchaseId = document.getElementById('cancelPurchaseId');
  const cancelPurchaseButton = document.getElementById('cancelPurchaseButton');
  const cancelPurchaseConfirmModal = document.getElementById('cancelPurchaseConfirmModal');
  const confirmCancelPurchase = document.getElementById('confirmCancelPurchase');
  const editPurchaseFromDetail = document.getElementById('editPurchaseFromDetail');
  let selectedSaleDetailId = null;
  let selectedPurchaseDetailId = null;
  let purchaseEditInProgress = false;
  const confirmedPendingReceiptIds = new Set();
  const hourStart = 13;
  const hourEnd = 24;
  const slotStep = 0.5;
  const minReservationDuration = 1;
  const dayNames = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const todayKey = toDateKey(today);
  let selectedCourtId = courts[0] ? Number(courts[0].id) : null;
  let weekStart = new Date(today);
  let dragSelection = null;
  let reservationModalState = null;
  let selectedSaleProduct = null;
  let lastSaleItemAddedAt = 0;
  let selectedReservationSaleProduct = null;
  let selectedPurchaseProduct = null;
  let lastPurchaseItemAddedAt = 0;
  let lastPurchaseEditStartedAt = 0;
  let saleCart = [];
  let reservationSaleCart = [];
  let purchaseCart = [];
  let quickClientTarget = 'reservation';
  let quickProviderTarget = 'purchase';
  let quickProductOpenedFromPurchase = false;
  let saleSubmitConfirmed = false;
  let cashCloseConfirmed = false;
  let editingSaleId = null;
  let editingSaleOriginalUnits = {};
  let reservationFeedSignature = JSON.stringify(reservations);
  let reservationFeedLoading = false;

  function shouldProtectSubmit(form) {
    const action = form.getAttribute('action') || '';
    return [
      'guardar_pago.php',
      'guardar_caja.php',
      'guardar_venta.php',
      'guardar_compra.php',
      'editar_compra.php',
      'guardar_reserva.php',
      'guardar_reserva_publica.php',
      'guardar_producto.php',
      'editar_producto.php',
      'anular_venta.php',
      'anular_compra.php'
    ].some((path) => action.includes(path));
  }

  function lockSubmitForm(form, submitter = null) {
    if (!shouldProtectSubmit(form)) {
      return;
    }

    form.dataset.submitting = '1';
    const buttons = new Set(Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')));
    if (form.id) {
      document.querySelectorAll(`button[form="${form.id}"], input[form="${form.id}"]`).forEach((button) => buttons.add(button));
    }
    if (submitter) {
      buttons.add(submitter);
    }

    buttons.forEach((button) => {
      button.disabled = true;
      if (button.tagName === 'BUTTON') {
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = 'Procesando...';
      }
    });
  }

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !shouldProtectSubmit(form)) {
      return;
    }

    if (form.dataset.submitting === '1') {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  function showModule(moduleId) {
    modules.forEach((module) => module.classList.toggle('active', module.id === moduleId));
    menuButtons.forEach((button) => button.classList.toggle('active', button.dataset.target === moduleId));
    document.body.classList.toggle('reservas-view', moduleId === 'reservas');
    const url = new URL(window.location.href);
    history.replaceState(null, '', `${url.pathname}${url.search}#${moduleId}`);
  }

  function showSettingsPanel(panelId) {
    const validPanel = document.querySelector(`[data-settings-panel="${panelId}"]`)
      ? panelId
      : 'usuarios';
    settingsPanels.forEach((panel) => {
      const isActive = panel.dataset.settingsPanel === validPanel;
      panel.classList.toggle('active', isActive);
      panel.hidden = !isActive;
    });
    settingsButtons.forEach((button) => {
      const isActive = button.dataset.settingsTarget === validPanel;
      button.classList.toggle('active', isActive);
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  function canAutoRefreshCashModule() {
    if (document.hidden || location.hash !== '#caja') {
      return false;
    }

    if (document.querySelector('.modal-backdrop.open')) {
      return false;
    }

    const active = document.activeElement;
    if (active && ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(active.tagName)) {
      return false;
    }

    return !document.querySelector('form[data-submitting="1"]');
  }

  function refreshCashModuleIfIdle() {
    if (!canAutoRefreshCashModule()) {
      return;
    }

    window.location.reload();
  }

  function clearTransientUrlParams() {
    const url = new URL(window.location.href);
    [
      'pago_ticket',
      'reserva_detalle',
      'cliente_error',
      'reserva_error',
      'cancha_error',
      'proveedor_error',
      'producto_error',
      'compra_error',
      'venta_error',
      'caja_error',
      'caja_mensaje',
      'usuario_error',
      'usuario_mensaje',
      'facturacion_error',
      'facturacion_mensaje'
    ].forEach((param) => url.searchParams.delete(param));

    const cleanSearch = url.searchParams.toString();
    const cleanUrl = `${url.pathname}${cleanSearch ? `?${cleanSearch}` : ''}${url.hash || window.location.hash}`;
    history.replaceState(null, '', cleanUrl);
  }

  function hasPendingReceipt(item) {
    return Boolean(item.abono_pendiente_comprobante_path) && item.abono_pendiente_estado === 'revision';
  }

  function updateReservationMenuNotification() {
    const reservationButton = document.querySelector('.module-menu button[data-target="reservas"]');
    reservationButton?.classList.toggle('has-notification', reservations.some(hasPendingReceipt));
  }

  menuButtons.forEach((button) => {
    button.addEventListener('click', () => showModule(button.dataset.target));
  });
  settingsButtons.forEach((button) => {
    button.addEventListener('click', () => showSettingsPanel(button.dataset.settingsTarget));
  });
  showSettingsPanel(settingsModule?.dataset.settingsInitial || 'usuarios');

  function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  function calendarDaysToShow() {
    return window.matchMedia('(max-width: 700px)').matches ? 2 : 7;
  }

  function calendarStepDays() {
    return calendarDaysToShow();
  }

  function toDateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function reservationDateLabel(item) {
    const [year, month, day] = String(item.fecha).split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const weekday = date.toLocaleDateString('es-PY', { weekday: 'long' });
    return `${weekday} ${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')} de ${displayReservationTime(item.hora_inicio)} a ${displayReservationTime(item.hora_fin)}`;
  }

  function money(value) {
    return Number(value || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 });
  }

  function moneyDigits(value) {
    return String(value ?? '').replace(/\D/g, '');
  }

  function formatMoneyInput(input) {
    const digits = moneyDigits(input.value);
    input.value = digits === '' ? '' : money(Number(digits));
  }

  function prepareMoneyInputs(form) {
    form.querySelectorAll('.money-input').forEach((input) => {
      input.value = moneyDigits(input.value) || '0';
    });
  }

  function syncProductUnitPricesFromPack(form) {
    if (!form) return;

    const packQuantity = Number(form.querySelector('[name="pack_cantidad"]')?.value || 0);
    if (packQuantity <= 0) {
      return;
    }

    const unitPurchaseInput = form.querySelector('[name="precio_compra"]');
    const packPurchase = Number(moneyDigits(form.querySelector('[name="precio_compra_pack"]')?.value || '0') || 0);

    if (unitPurchaseInput && packPurchase > 0) {
      unitPurchaseInput.value = money(Math.ceil(packPurchase / packQuantity));
      unitPurchaseInput.setCustomValidity('');
    }
  }

  function updateProductPriceWarnings(form) {
    if (!form) return;

    const comparisons = [
      { type: 'pack', purchase: 'precio_compra_pack', sale: 'precio_pack' },
      { type: 'unit', purchase: 'precio_compra', sale: 'precio_venta' }
    ];

    comparisons.forEach(({ type, purchase, sale }) => {
      const purchaseValue = Number(moneyDigits(form.querySelector(`[name="${purchase}"]`)?.value || '0') || 0);
      const saleValue = Number(moneyDigits(form.querySelector(`[name="${sale}"]`)?.value || '0') || 0);
      const warning = form.querySelector(`[data-price-warning="${type}"]`);
      if (!warning) return;

      const isBelowCost = purchaseValue > 0 && saleValue > 0 && saleValue < purchaseValue;
      warning.hidden = !isBelowCost;
      warning.textContent = isBelowCost
        ? `Aviso: venta menor al costo por ${money(purchaseValue - saleValue)}.`
        : '';
    });

    const promotionQuantity = Number(form.querySelector('[name="promocion_cantidad"]')?.value || 0);
    const promotionPrice = Number(moneyDigits(form.querySelector('[name="precio_promocion"]')?.value || '0') || 0);
    const unitCost = Number(moneyDigits(form.querySelector('[name="precio_compra"]')?.value || '0') || 0);
    const promotionWarning = form.querySelector('[data-price-warning="promotion"]');
    if (promotionWarning) {
      const promotionCost = promotionQuantity * unitCost;
      const promotionLoss = promotionCost - promotionPrice;
      const hasSignificantLoss = promotionQuantity > 0 && promotionPrice > 0 && promotionCost > 0 && promotionLoss >= 1000;
      promotionWarning.hidden = !hasSignificantLoss;
      promotionWarning.textContent = hasSignificantLoss
        ? `Aviso: la promoción vende por debajo del costo en ${money(promotionCost - promotionPrice)}.`
        : '';
    }
  }

  function validateProductRequiredFields(form) {
    if (!form) return true;

    let isValid = true;
    const categoryInput = form.querySelector('[name="categoria"]');
    const unitPurchaseInput = form.querySelector('[name="precio_compra"]');
    const unitSaleInput = form.querySelector('[name="precio_venta"]');
    const packQuantityInput = form.querySelector('[name="pack_cantidad"]');
    const packPurchaseInput = form.querySelector('[name="precio_compra_pack"]');
    const packSaleInput = form.querySelector('[name="precio_pack"]');
    const promotionQuantityInput = form.querySelector('[name="promocion_cantidad"]');
    const promotionPriceInput = form.querySelector('[name="precio_promocion"]');

    [categoryInput, unitPurchaseInput, unitSaleInput, packQuantityInput, packPurchaseInput, packSaleInput, promotionQuantityInput, promotionPriceInput].forEach((input) => {
      input?.setCustomValidity('');
    });

    if (categoryInput && categoryInput.value.trim() === '') {
      categoryInput.setCustomValidity('La categoria del producto es obligatoria.');
      isValid = false;
    }

    if (unitPurchaseInput && Number(moneyDigits(unitPurchaseInput.value) || 0) <= 0) {
      unitPurchaseInput.setCustomValidity('Ingresa un precio de compra unidad mayor a cero.');
      isValid = false;
    }

    if (unitSaleInput && Number(moneyDigits(unitSaleInput.value) || 0) <= 0) {
      unitSaleInput.setCustomValidity('Ingresa un precio de venta unidad mayor a cero.');
      isValid = false;
    }

    const packQuantity = Number(packQuantityInput?.value || 0);
    const packPurchase = Number(moneyDigits(packPurchaseInput?.value || '0') || 0);
    const packSale = Number(moneyDigits(packSaleInput?.value || '0') || 0);

    if (packQuantity > 0 && packPurchase <= 0) {
      packPurchaseInput?.setCustomValidity('Ingresa precio compra pack.');
      isValid = false;
    }

    if (packQuantity > 0 && packSale <= 0) {
      packSaleInput?.setCustomValidity('Ingresa precio de venta pack.');
      isValid = false;
    }

    if (packQuantity <= 0 && (packPurchase > 0 || packSale > 0)) {
      packQuantityInput?.setCustomValidity('Ingresa unidades por pack para usar precios por pack.');
      isValid = false;
    }

    const promotionQuantity = Number(promotionQuantityInput?.value || 0);
    const promotionPrice = Number(moneyDigits(promotionPriceInput?.value || '0') || 0);
    if (promotionQuantity > 0 && promotionPrice <= 0) {
      promotionPriceInput?.setCustomValidity('Ingresa el precio de la promocion.');
      isValid = false;
    }
    if (promotionQuantity <= 0 && promotionPrice > 0) {
      promotionQuantityInput?.setCustomValidity('Ingresa las unidades incluidas en la promocion.');
      isValid = false;
    }

    return isValid;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function shouldAutoCapitalize(element) {
    if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
      return false;
    }

    const type = String(element.type || '').toLowerCase();
    if (['email', 'hidden', 'number', 'date', 'time', 'password', 'search'].includes(type)) {
      return false;
    }

    if (element.readOnly || element.classList.contains('money-input')) {
      return false;
    }

    const fieldInfo = `${element.name || ''} ${element.id || ''} ${element.placeholder || ''}`.toLowerCase();
    return !fieldInfo.includes('email')
      && !fieldInfo.includes('correo')
      && !fieldInfo.includes('codigo')
      && !fieldInfo.includes('barra');
  }

  function capitalizeFirstLetter(value) {
    return String(value ?? '').replace(/^(\s*)([a-záéíóúñü])/i, (match, spaces, letter) => {
      return spaces + letter.toLocaleUpperCase('es-PY');
    });
  }

  function clientLabel(client) {
    return `${client.nombre} - ${client.telefono || 'sin telefono'}`;
  }

  function normalizeName(value) {
    return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
  }

  function normalizeCategory(value) {
    return normalizeName(value);
  }

  function categoryInput(id) {
    return document.getElementById(id);
  }

  function categorySuggestions(id) {
    return document.querySelector(`[data-category-suggestions-for="${id}"]`);
  }

  function categoryExists(value) {
    const normalized = normalizeCategory(value);
    return normalized !== '' && productCategories.some((category) => normalizeCategory(category) === normalized);
  }

  function selectCategory(id, category) {
    const input = categoryInput(id);
    const suggestions = categorySuggestions(id);
    if (!input) return;

    input.value = category;
    suggestions?.classList.remove('open');
    if (suggestions) {
      suggestions.innerHTML = '';
    }
  }

  function renderCategorySuggestions(id) {
    const input = categoryInput(id);
    const suggestions = categorySuggestions(id);
    if (!input || !suggestions) return;

    const term = normalizeCategory(input.value);
    suggestions.innerHTML = '';

    if (term === '') {
      suggestions.classList.remove('open');
      return;
    }

    const matches = productCategories
      .filter((category) => normalizeCategory(category).includes(term))
      .slice(0, 8);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'autocomplete-empty';
      empty.textContent = 'Sin categorias encontradas. Usa + para agregar.';
      suggestions.appendChild(empty);
      suggestions.classList.add('open');
      return;
    }

    matches.forEach((category) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'autocomplete-option';
      option.innerHTML = `<strong>${escapeHtml(category)}</strong><span>Categoria existente</span>`;
      option.addEventListener('click', () => selectCategory(id, category));
      suggestions.appendChild(option);
    });

    suggestions.classList.add('open');
  }

  function addCategoryFromInput(id) {
    const input = categoryInput(id);
    openCategoryModal('', capitalizeFirstLetter(input?.value.trim().replace(/\s+/g, ' ') || ''), 'create', id);
  }

  function selectClient(client) {
    modalClienteId.value = client.id;
    clientSearch.value = clientLabel(client);
    clientSearch.setCustomValidity('');
    clientSuggestions.innerHTML = '';
    clientSuggestions.classList.remove('open');
  }

  function selectSaleClient(client) {
    saleClienteId.value = client.id;
    saleClientSearch.value = clientLabel(client);
    saleClientSuggestions.innerHTML = '';
    saleClientSuggestions.classList.remove('open');
  }

  function selectEditReservationClient(client) {
    const clientIdInput = document.getElementById('editClienteId');
    const searchInput = document.getElementById('editClientSearch');
    const suggestions = document.getElementById('editClientSuggestions');
    if (!clientIdInput || !searchInput || !suggestions) return;

    clientIdInput.value = client.id;
    searchInput.value = clientLabel(client);
    searchInput.setCustomValidity('');
    suggestions.innerHTML = '';
    suggestions.classList.remove('open');
  }

  function providerLabel(provider) {
    const phone = provider.telefono ? ` - ${provider.telefono}` : '';
    return `${provider.nombre}${phone}`;
  }

  function selectProviderForField(provider, idInput, searchInput, suggestionsElement) {
    if (!idInput || !searchInput || !suggestionsElement) {
      return;
    }

    idInput.value = provider.id;
    searchInput.value = providerLabel(provider);
    suggestionsElement.innerHTML = '';
    suggestionsElement.classList.remove('open');
  }

  function selectPurchaseProvider(provider) {
    selectProviderForField(provider, purchaseProviderId, purchaseProviderSearch, purchaseProviderSuggestions);
  }

  function selectProductProvider(provider) {
    selectProviderForField(provider, productProviderId, productProviderSearch, productProviderSuggestions);
  }

  function selectQuickProductProvider(provider) {
    selectProviderForField(provider, quickProductProviderId, quickProductProviderSearch, quickProductProviderSuggestions);
  }

  function selectEditProductProvider(provider) {
    selectProviderForField(provider, editProductoProveedorId, editProductoProveedorSearch, editProductoProveedorSuggestions);
  }

  function renderProviderSuggestions(query, suggestionsElement = purchaseProviderSuggestions, onSelect = selectPurchaseProvider) {
    const term = String(query || '').trim().toLowerCase();
    suggestionsElement.innerHTML = '';

    if (term.length < 1) {
      suggestionsElement.classList.remove('open');
      return;
    }

    const matches = providers
      .filter((provider) => provider.estado === 'activo')
      .filter((provider) => `${provider.nombre} ${provider.telefono || ''} ${provider.email || ''} ${provider.ruc || ''}`.toLowerCase().includes(term))
      .slice(0, 8);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'autocomplete-empty';
      empty.textContent = 'Sin proveedores. Usa + para agregar.';
      suggestionsElement.appendChild(empty);
      suggestionsElement.classList.add('open');
      return;
    }

    matches.forEach((provider) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'autocomplete-option';
      option.innerHTML = `<strong>${escapeHtml(provider.nombre)}</strong><span>${escapeHtml(provider.telefono || provider.ruc || '')}</span>`;
      option.addEventListener('click', () => onSelect(provider));
      suggestionsElement.appendChild(option);
    });

    suggestionsElement.classList.add('open');
  }

  function renderClientSuggestions(query, suggestionsElement = clientSuggestions, onSelect = selectClient, allowEmpty = false) {
    const term = query.trim().toLowerCase();
    suggestionsElement.innerHTML = '';

    if (term.length < 1) {
      if (allowEmpty) {
        const emptyClient = document.createElement('button');
        emptyClient.type = 'button';
        emptyClient.className = 'autocomplete-option';
        emptyClient.innerHTML = '<strong>Sin cliente</strong><span>Registrar la venta sin cliente</span>';
        emptyClient.addEventListener('click', () => {
          saleClienteId.value = '';
          saleClientSearch.value = '';
          suggestionsElement.innerHTML = '';
          suggestionsElement.classList.remove('open');
        });
        suggestionsElement.appendChild(emptyClient);
        suggestionsElement.classList.add('open');
        return;
      }
      suggestionsElement.classList.remove('open');
      return;
    }

    const matches = clients
      .filter((client) => `${client.nombre} ${client.telefono} ${client.email || ''}`.toLowerCase().includes(term))
      .slice(0, 8);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'autocomplete-empty';
      empty.textContent = 'Sin resultados. Usa + para agregar.';
      suggestionsElement.appendChild(empty);
      suggestionsElement.classList.add('open');
      return;
    }

    matches.forEach((client) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'autocomplete-option';
      option.innerHTML = `<strong>${escapeHtml(client.nombre)}</strong><span>${escapeHtml(client.telefono || '')}</span>`;
      option.addEventListener('click', () => onSelect(client));
      suggestionsElement.appendChild(option);
    });

    suggestionsElement.classList.add('open');
  }

  function reservedUnits(cart, productId) {
    return cart
      .filter((item) => Number(item.product.id) === Number(productId))
      .reduce((sum, item) => sum + Number(item.units || 0), 0);
  }

  function availableStock(product, cart) {
    const originalUnits = cart === saleCart && Number(editingSaleId) > 0
      ? Number(editingSaleOriginalUnits[Number(product.id)] || 0)
      : 0;
    return Math.max(0, Number(product.stock || 0) + originalUnits - reservedUnits(cart, product.id));
  }

  function productLabel(product, cart = saleCart) {
    const code = product.codigo_barra ? ` | ${product.codigo_barra}` : '';
    return `${product.nombre}${code} - Stock ${availableStock(product, cart)}`;
  }

  function showClientMessage(message) {
    clientMessageText.textContent = message;
    clientMessageModal.classList.add('open');
    clientMessageModal.setAttribute('aria-hidden', 'false');
    clientMessageModal.querySelector('button')?.focus();
  }

  function openReceiptImageModal(url, pendingId = '', reservaId = '', volver = 'reservas') {
    if (!receiptImageModal || !receiptImagePreview || !url) {
      return;
    }
    receiptImagePreview.src = url;
    const pendingKey = String(pendingId || '');
    const hasPendingConfirmation = pendingKey !== '' && !confirmedPendingReceiptIds.has(pendingKey);
    if (confirmPendingReceiptForm && confirmPendingReceiptId && confirmPendingReceiptReservaId && confirmPendingReceiptButton) {
      confirmPendingReceiptForm.dataset.submitting = '';
      confirmPendingReceiptForm.dataset.receiptConfirmed = hasPendingConfirmation ? '' : '1';
      confirmPendingReceiptId.value = pendingId;
      confirmPendingReceiptReservaId.value = reservaId;
      if (confirmPendingReceiptVolver) {
        confirmPendingReceiptVolver.value = volver;
      }
      confirmPendingReceiptForm.hidden = !hasPendingConfirmation;
      confirmPendingReceiptButton.hidden = !hasPendingConfirmation;
      confirmPendingReceiptButton.disabled = false;
      confirmPendingReceiptButton.textContent = 'Confirmado';
    }
    receiptImageModal.classList.add('open');
    receiptImageModal.setAttribute('aria-hidden', 'false');
  }

  document.querySelectorAll('[data-view-pending-cash-receipt]').forEach((button) => {
    button.addEventListener('click', () => {
      openReceiptImageModal(
        button.dataset.receiptUrl || '',
        button.dataset.pendingId || '',
        button.dataset.reservaId || '',
        'caja'
      );
    });
  });

  confirmPendingReceiptForm?.addEventListener('submit', (event) => {
    if (confirmPendingReceiptForm.dataset.receiptConfirmed === '1') {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }
    confirmPendingReceiptForm.dataset.receiptConfirmed = '1';
    if (confirmPendingReceiptId?.value) {
      confirmedPendingReceiptIds.add(String(confirmPendingReceiptId.value));
    }
    if (confirmPendingReceiptButton) {
      confirmPendingReceiptButton.disabled = true;
      confirmPendingReceiptButton.textContent = 'Procesando...';
    }
  });

  function purchaseProductLabel(product) {
    const code = product.codigo_barra ? ` | ${product.codigo_barra}` : '';
    return `${product.nombre}${code} - Stock ${Number(product.stock || 0)}`;
  }

  function selectPurchaseProduct(product) {
    selectedPurchaseProduct = product;
    if (addPurchaseItemButton) addPurchaseItemButton.disabled = false;
    purchaseProductId.value = product.id;
    purchaseProductSearch.value = purchaseProductLabel(product);
    purchaseProductSuggestions.innerHTML = '';
    purchaseProductSuggestions.classList.remove('open');
    updatePurchasePriceForType();
    updatePurchaseTotal();
  }

  function renderPurchaseProductSuggestions(query) {
    const term = String(query || '').trim().toLowerCase();
    purchaseProductSuggestions.innerHTML = '';

    if (term.length < 1) {
      purchaseProductSuggestions.classList.remove('open');
      return;
    }

    const exactBarcode = findProductByBarcode(query);
    if (exactBarcode) {
      selectPurchaseProduct(exactBarcode);
      return;
    }

    const matches = activeProducts()
      .filter((product) => `${product.nombre} ${product.codigo_barra || ''} ${product.categoria || ''}`.toLowerCase().includes(term))
      .slice(0, 10);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'autocomplete-empty';
      empty.textContent = 'Sin productos encontrados.';
      purchaseProductSuggestions.appendChild(empty);
      purchaseProductSuggestions.classList.add('open');
      return;
    }

    matches.forEach((product) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'autocomplete-option';
      option.innerHTML = `<strong>${escapeHtml(product.nombre)}</strong><span>${escapeHtml(product.codigo_barra || product.categoria || '')} | Stock ${Number(product.stock || 0)} | ${money(product.precio_compra)}</span>`;
      option.addEventListener('click', () => selectPurchaseProduct(product));
      purchaseProductSuggestions.appendChild(option);
    });

    purchaseProductSuggestions.classList.add('open');
  }

  function selectSaleProduct(product, target = 'sale') {
    const isReservation = target === 'reservation';
    const cart = isReservation ? reservationSaleCart : saleCart;
    const productIdInput = isReservation ? reservaVentaProductoId : ventaProductoId;
    const productSearchInput = isReservation ? reservaVentaProductoSearch : ventaProductoSearch;
    const suggestions = isReservation ? reservationProductSuggestions : productSuggestions;
    const typeInput = document.getElementById(isReservation ? 'reservaVentaTipo' : 'ventaTipo');
    const disponible = availableStock(product, cart);
    if (disponible <= 0) {
      if (isReservation) {
        selectedReservationSaleProduct = null;
      } else {
        selectedSaleProduct = null;
        if (addSaleItemButton) addSaleItemButton.disabled = true;
      }
      productIdInput.value = '';
      productSearchInput.value = '';
      suggestions.innerHTML = '';
      suggestions.classList.remove('open');
      syncSaleTypeOptions(target);
      isReservation ? updateReservationSaleTotal() : updateSaleTotal();
      showClientMessage(`${product.nombre} no tiene stock disponible. Carga stock en Productos antes de vender.`);
      return;
    }

    if (isReservation) {
      selectedReservationSaleProduct = product;
    } else {
      selectedSaleProduct = product;
      if (addSaleItemButton) addSaleItemButton.disabled = Boolean(saleSubmitButton?.disabled);
    }
    productIdInput.value = product.id;
    if (typeInput) {
      typeInput.value = 'unidad';
    }
    productSearchInput.value = productLabel(product, cart);
    suggestions.innerHTML = '';
    suggestions.classList.remove('open');
    syncSaleTypeOptions(target);
    isReservation ? updateReservationSaleTotal() : updateSaleTotal();
  }

  function cartTotal(cart) {
    return cart.reduce((sum, item) => sum + item.subtotal, 0);
  }

  function cartItemsCount(cart) {
    return cart.reduce((sum, item) => sum + Number(item.units || item.quantity || 0), 0);
  }

  function cartItemUnitPrice(item) {
    if (item.type === 'pack') return Number(item.product.precio_pack || 0);
    if (item.type === 'promocion') return Number(item.product.precio_promocion || 0);
    return Number(item.product.precio_venta || 0);
  }

  function cartItemUnits(item, quantity) {
    if (item.type === 'pack') return quantity * Number(item.product.pack_cantidad || 0);
    if (item.type === 'promocion') return quantity * Number(item.product.promocion_cantidad || 0);
    return quantity;
  }

  function renderCart(cart, listId, inputsId) {
    const list = document.getElementById(listId);
    const inputs = document.getElementById(inputsId);
    if (!list || !inputs) return;

    inputs.innerHTML = '';
    if (cart.length === 0) {
      list.innerHTML = '<p class="muted">Todavia no agregaste productos.</p>';
      return;
    }

    const rows = cart.map((item, index) => `
      <tr>
        <td>${escapeHtml(item.product.nombre)}</td>
        <td>${escapeHtml(item.product.codigo_barra || '-')}</td>
        <td>${escapeHtml(item.type)}</td>
        <td>
          <input
            type="number"
            class="cart-quantity"
            min="1"
            step="1"
            value="${Number(item.quantity)}"
            data-cart-quantity="${index}"
            data-cart-list="${listId}"
            aria-label="Cantidad de ${escapeHtml(item.product.nombre)}"
          >
        </td>
        <td data-cart-subtotal="${index}">${money(item.subtotal)}</td>
        <td><button type="button" class="small danger" data-remove-cart-item="${index}" data-cart-list="${listId}">Quitar</button></td>
      </tr>
    `).join('');

    if (listId === 'saleCartList') {
      list.innerHTML = `
        <div class="sale-cart-scroll">
          <table>
            <thead><tr><th>Producto</th><th>C&oacute;digo</th><th>Tipo</th><th>Cant.</th><th>Subtotal</th><th></th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="sale-cart-total">
          <span><strong>Total productos</strong> <span class="cart-total-items">${cartItemsCount(cart)} item(s) cargado(s)</span></span>
          <strong data-cart-total>${money(cartTotal(cart))}</strong>
        </div>
      `;
    } else {
      list.innerHTML = `
        <table>
          <thead><tr><th>Producto</th><th>C&oacute;digo</th><th>Tipo</th><th>Cant.</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            ${rows}
            <tr class="cart-total-row"><th colspan="4">Total productos <span class="cart-total-items">${cartItemsCount(cart)} item(s) cargado(s)</span></th><th colspan="2" data-cart-total>${money(cartTotal(cart))}</th></tr>
          </tbody>
        </table>
      `;
    }

    cart.forEach((item) => {
      inputs.insertAdjacentHTML('beforeend', `
        <input type="hidden" name="producto_id[]" value="${Number(item.product.id)}">
        <input type="hidden" name="tipo_venta[]" value="${escapeHtml(item.type)}">
        <input type="hidden" name="cantidad[]" value="${Number(item.quantity)}">
      `);
    });
  }

  function purchaseItemUnits(item, quantity) {
    return item.type === 'pack'
      ? quantity * Number(item.product.pack_cantidad || 0)
      : quantity;
  }

  function renderPurchaseCart() {
    const list = document.getElementById('purchaseCartList');
    const inputs = document.getElementById('purchaseCartInputs');
    if (!list || !inputs) return;

    inputs.innerHTML = '';
    if (purchaseCart.length === 0) {
      list.innerHTML = '<p class="muted">Todavia no agregaste productos.</p>';
      return;
    }

    list.innerHTML = `
      <div class="purchase-cart-scroll">
        <table>
          <thead><tr><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            ${purchaseCart.map((item, index) => `
              <tr>
                <td>${escapeHtml(item.product.nombre)}</td>
                <td>${escapeHtml(item.type)}</td>
                <td>
                  <input type="number" class="cart-quantity" min="1" step="1" value="${Number(item.quantity)}" data-purchase-quantity="${index}" aria-label="Cantidad de ${escapeHtml(item.product.nombre)}">
                </td>
                <td>
                  <input type="text" class="money-input cart-purchase-price" inputmode="numeric" value="${money(item.price)}" data-purchase-price="${index}" aria-label="Precio de compra de ${escapeHtml(item.product.nombre)}">
                </td>
                <td data-purchase-subtotal="${index}">${money(item.subtotal)}</td>
                <td><button type="button" class="small danger" data-remove-purchase-item="${index}">Quitar</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
      <div class="purchase-cart-total">
        <span><strong>Total productos</strong> <span class="cart-total-items">${cartItemsCount(purchaseCart)} item(s) cargado(s)</span></span>
        <strong data-purchase-cart-total>${money(cartTotal(purchaseCart))}</strong>
      </div>
    `;

    purchaseCart.forEach((item, index) => {
      inputs.insertAdjacentHTML('beforeend', `
        <input type="hidden" name="producto_id[]" value="${Number(item.product.id)}">
        <input type="hidden" name="tipo_compra[]" value="${escapeHtml(item.type)}">
        <input type="hidden" name="cantidad[]" value="${Number(item.quantity)}">
        <input type="hidden" name="precio_unitario[]" value="${Number(item.price)}" data-purchase-hidden-price="${Number(item.product.id)}-${index}">
        <input type="hidden" name="stock_ya_cargado[]" value="${item.stockAlreadyLoaded ? '1' : '0'}">
      `);
    });
  }

  function updatePurchaseCartQuantity(index, quantity) {
    const item = purchaseCart[index];
    if (!item) return;

    const nextQuantity = Math.max(1, Number(quantity || 1));
    item.quantity = nextQuantity;
    item.units = purchaseItemUnits(item, nextQuantity);
    item.subtotal = item.price * nextQuantity;
    renderPurchaseCart();
    updatePurchaseTotal();
  }

  function updatePurchaseCartPrice(index, input) {
    const item = purchaseCart[index];
    if (!item || !input) return;

    formatMoneyInput(input);
    const nextPrice = Number(moneyDigits(input.value) || 0);
    item.price = nextPrice;
    item.subtotal = nextPrice * Number(item.quantity || 0);

    const subtotal = document.querySelector(`[data-purchase-subtotal="${index}"]`);
    const hiddenPrice = document.querySelector(`[data-purchase-hidden-price="${Number(item.product.id)}-${index}"]`);
    const cartTotalElement = document.querySelector('[data-purchase-cart-total]');
    if (subtotal) subtotal.textContent = money(item.subtotal);
    if (hiddenPrice) hiddenPrice.value = String(nextPrice);
    if (cartTotalElement) cartTotalElement.textContent = money(cartTotal(purchaseCart));
  }

  function maxSaleCartQuantity(product, cart, type) {
    const available = availableStock(product, cart);
    const unitsPerItem = type === 'pack'
      ? Number(product?.pack_cantidad || 0)
      : (type === 'promocion' ? Number(product?.promocion_cantidad || 0) : 1);
    if (type !== 'unidad') return unitsPerItem > 0 ? Math.floor(available / unitsPerItem) : 0;

    return available;
  }

  function productAllowsPack(product) {
    return Number(product?.pack_cantidad || 0) > 0 && Number(product?.precio_pack || 0) > 0;
  }

  function productAllowsPromotion(product) {
    return Number(product?.promocion_cantidad || 0) > 0 && Number(product?.precio_promocion || 0) > 0;
  }

  function syncSaleTypeOptions(target = 'sale') {
    const isReservation = target === 'reservation';
    const product = isReservation ? selectedReservationSaleProduct : selectedSaleProduct;
    const typeInput = document.getElementById(isReservation ? 'reservaVentaTipo' : 'ventaTipo');
    const packOption = document.getElementById(isReservation ? 'reservaVentaTipoPack' : 'ventaTipoPack');
    const promotionOption = document.getElementById(isReservation ? 'reservaVentaTipoPromocion' : 'ventaTipoPromocion');
    if (!typeInput || !packOption || !promotionOption) {
      return;
    }

    const packAllowed = productAllowsPack(product);
    packOption.disabled = !packAllowed;
    packOption.textContent = packAllowed ? 'Pack' : 'Pack (no disponible)';
    if (!packAllowed && typeInput.value === 'pack') {
      typeInput.value = 'unidad';
    }
    const promotionAllowed = productAllowsPromotion(product);
    promotionOption.disabled = !promotionAllowed;
    promotionOption.textContent = promotionAllowed ? 'Promoción' : 'Promoción (no disponible)';
    if (!promotionAllowed && typeInput.value === 'promocion') {
      typeInput.value = 'unidad';
    }
  }

  function clampSaleQuantity(target = 'sale') {
    const isReservation = target === 'reservation';
    const product = isReservation ? selectedReservationSaleProduct : selectedSaleProduct;
    const cart = isReservation ? reservationSaleCart : saleCart;
    const typeInput = document.getElementById(isReservation ? 'reservaVentaTipo' : 'ventaTipo');
    const quantityInput = document.getElementById(isReservation ? 'reservaVentaCantidad' : 'ventaCantidad');
    if (!quantityInput) {
      return 0;
    }

    quantityInput.min = '1';
    syncSaleTypeOptions(target);
    if (!product) {
      quantityInput.removeAttribute('max');
      return Number(quantityInput.value || 0);
    }

    const maxQuantity = maxSaleCartQuantity(product, cart, typeInput?.value || 'unidad');
    quantityInput.max = String(maxQuantity);

    const currentQuantity = Number(quantityInput.value || 0);
    if (currentQuantity > maxQuantity) {
      quantityInput.value = String(maxQuantity);
    } else if (currentQuantity < 1 && maxQuantity > 0) {
      quantityInput.value = '1';
    } else if (currentQuantity < 0) {
      quantityInput.value = '0';
    }

    return Number(quantityInput.value || 0);
  }

  function updateCartQuantity(cartName, index, quantity) {
    const isReservation = cartName === 'reservation';
    const cart = isReservation ? reservationSaleCart : saleCart;
    const item = cart[index];
    if (!item) return;

    let nextQuantity = Number(quantity || 0);
    if (nextQuantity <= 0) {
      nextQuantity = 1;
    }

    const maxUnits = availableStock(item.product, cart) + Number(item.units || 0);
    const unitsPerItem = item.type === 'pack'
      ? Number(item.product.pack_cantidad || 0)
      : (item.type === 'promocion' ? Number(item.product.promocion_cantidad || 0) : 1);
    const maxQuantity = Math.floor(maxUnits / Math.max(1, unitsPerItem));

    if (maxQuantity <= 0) {
      cart.splice(index, 1);
      showClientMessage(`${item.product.nombre} ya no tiene stock disponible y se quito del carrito.`);
    } else if (nextQuantity > maxQuantity) {
      nextQuantity = maxQuantity;
      showClientMessage(`Stock insuficiente. Solo podes dejar ${maxQuantity} en esta linea.`);
    }

    if (cart[index]) {
      item.quantity = nextQuantity;
      item.units = cartItemUnits(item, nextQuantity);
      item.subtotal = cartItemUnitPrice(item) * nextQuantity;
    }

    if (isReservation) {
      renderCart(reservationSaleCart, 'reservationSaleCartList', 'reservationSaleCartInputs');
      refreshReservationProductOptions();
      updateReservationSaleTotal();
    } else {
      renderCart(saleCart, 'saleCartList', 'saleCartInputs');
      if (ventaProductoSearch.value.trim() !== '') {
        renderProductSuggestions(ventaProductoSearch.value);
      }
      updateSaleTotal();
    }
  }

  function updateCartQuantityLive(input) {
    const index = Number(input.dataset.cartQuantity);
    const isReservation = input.dataset.cartList === 'reservationSaleCartList';
    const cart = isReservation ? reservationSaleCart : saleCart;
    const item = cart[index];
    const typedQuantity = Number(input.value);
    if (!item || !Number.isFinite(typedQuantity) || typedQuantity < 1) return;

    const maxUnits = availableStock(item.product, cart) + Number(item.units || 0);
    const unitsPerItem = item.type === 'pack'
      ? Number(item.product.pack_cantidad || 0)
      : (item.type === 'promocion' ? Number(item.product.promocion_cantidad || 0) : 1);
    const nextQuantity = Math.min(typedQuantity, Math.floor(maxUnits / Math.max(1, unitsPerItem)));
    if (nextQuantity < 1) return;

    if (nextQuantity !== typedQuantity) input.value = String(nextQuantity);
    item.quantity = nextQuantity;
    item.units = cartItemUnits(item, nextQuantity);
    item.subtotal = cartItemUnitPrice(item) * nextQuantity;

    const list = document.getElementById(input.dataset.cartList);
    const inputsId = isReservation ? 'reservationSaleCartInputs' : 'saleCartInputs';
    const hiddenQuantity = document.getElementById(inputsId)?.querySelectorAll('input[name="cantidad[]"]')[index];
    if (hiddenQuantity) hiddenQuantity.value = String(nextQuantity);
    const subtotal = list?.querySelector(`[data-cart-subtotal="${index}"]`);
    const total = list?.querySelector('[data-cart-total]');
    const count = list?.querySelector('.cart-total-items');
    if (subtotal) subtotal.textContent = money(item.subtotal);
    if (total) total.textContent = money(cartTotal(cart));
    if (count) count.textContent = `${cartItemsCount(cart)} item(s) cargado(s)`;
  }

  function refreshReservationProductOptions() {
    if (selectedReservationSaleProduct && reservaVentaProductoSearch) {
      reservaVentaProductoSearch.value = productLabel(selectedReservationSaleProduct, reservationSaleCart);
    }
  }

  function addCartItem(cartName) {
    const isReservation = cartName === 'reservation';
    const now = performance.now();
    if (!isReservation && (addSaleItemButton?.disabled || now - lastSaleItemAddedAt < 700)) return;

    const cart = isReservation ? reservationSaleCart : saleCart;
    const typeInput = document.getElementById(isReservation ? 'reservaVentaTipo' : 'ventaTipo');
    const quantityInput = document.getElementById(isReservation ? 'reservaVentaCantidad' : 'ventaCantidad');
    const productInput = document.getElementById(isReservation ? 'reservaVentaProducto' : 'ventaProducto');
    const product = isReservation
      ? selectedReservationSaleProduct
      : selectedSaleProduct;
    const quantity = Number(quantityInput?.value || 0);

    if (!product || quantity <= 0) {
      showClientMessage('Selecciona un producto e ingresa una cantidad valida.');
      return;
    }

    const available = availableStock(product, cart);
    if (available <= 0) {
      showClientMessage(`${product.nombre} no tiene stock disponible. Carga stock en Productos antes de vender.`);
      return;
    }

    const type = typeInput.value;
    const isPack = type === 'pack';
    const packQuantity = Number(product.pack_cantidad || 0);
    const unitPrice = Number(product.precio_venta || 0);
    const packPrice = Number(product.precio_pack || 0);
    const promotionQuantity = Number(product.promocion_cantidad || 0);
    const promotionPrice = Number(product.precio_promocion || 0);
    if (isPack && (packQuantity <= 0 || packPrice <= 0)) {
      showClientMessage('Este producto no tiene pack configurado.');
      return;
    }

    if (type === 'promocion' && (promotionQuantity <= 0 || promotionPrice <= 0)) {
      showClientMessage('Este producto no tiene promocion configurada.');
      return;
    }

    const units = isPack
      ? quantity * packQuantity
      : (type === 'promocion' ? quantity * promotionQuantity : quantity);
    if (units > available) {
      const unitsPerItem = isPack ? packQuantity : (type === 'promocion' ? promotionQuantity : 1);
      quantityInput.value = Math.floor(available / unitsPerItem);
      if (isReservation) {
        updateReservationSaleTotal();
      } else {
        updateSaleTotal();
      }
      showClientMessage(`Stock insuficiente. Quedan ${available} unidad(es) disponibles para agregar.`);
      return;
    }

    const price = isPack ? packPrice : (type === 'promocion' ? promotionPrice : unitPrice);
    const existing = cart.find((item) => Number(item.product.id) === Number(product.id) && item.type === type);

    if (existing) {
      existing.quantity += quantity;
      existing.units += units;
      existing.subtotal += price * quantity;
    } else {
      cart.push({
        product,
        type,
        quantity,
        units,
        subtotal: price * quantity
      });
    }

    if (isReservation) {
      renderCart(reservationSaleCart, 'reservationSaleCartList', 'reservationSaleCartInputs');
      selectedReservationSaleProduct = null;
      productInput.value = '';
      reservaVentaProductoSearch.value = '';
      typeInput.value = 'unidad';
      quantityInput.value = 1;
      updateReservationSaleTotal();
    } else {
      lastSaleItemAddedAt = performance.now();
      if (addSaleItemButton) addSaleItemButton.disabled = true;
      renderCart(saleCart, 'saleCartList', 'saleCartInputs');
      selectedSaleProduct = null;
      productInput.value = '';
      ventaProductoSearch.value = '';
      typeInput.value = 'unidad';
      quantityInput.value = 1;
      updateSaleTotal();
    }
  }

  function activeProducts() {
    return products.filter((product) => product.estado === 'activo');
  }

  function findProductByBarcode(value) {
    const code = String(value || '').trim();
    if (code === '') {
      return null;
    }

    return activeProducts().find((product) => String(product.codigo_barra || '').trim() === code) || null;
  }

  function renderProductSuggestions(query, target = 'sale') {
    const isReservation = target === 'reservation';
    const suggestions = isReservation ? reservationProductSuggestions : productSuggestions;
    const cart = isReservation ? reservationSaleCart : saleCart;
    if (!suggestions) return;

    const term = String(query || '').trim().toLowerCase();
    suggestions.innerHTML = '';

    if (term.length < 1) {
      suggestions.classList.remove('open');
      return;
    }

    const exactBarcode = findProductByBarcode(query);
    if (exactBarcode) {
      selectSaleProduct(exactBarcode, target);
      return;
    }

    const matches = activeProducts()
      .filter((product) => `${product.nombre} ${product.codigo_barra || ''} ${product.categoria || ''}`.toLowerCase().includes(term))
      .slice(0, 10);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'autocomplete-empty';
      empty.textContent = 'Sin productos encontrados.';
      suggestions.appendChild(empty);
      suggestions.classList.add('open');
      return;
    }

    matches.forEach((product) => {
      const option = document.createElement('button');
      option.type = 'button';
      const stock = availableStock(product, cart);
      option.className = stock <= 0 ? 'autocomplete-option is-disabled' : 'autocomplete-option';
      option.innerHTML = `<strong>${escapeHtml(product.nombre)}</strong><span>${escapeHtml(product.codigo_barra || product.categoria || '')} | ${stock <= 0 ? 'Sin stock' : 'Stock ' + stock} | ${money(product.precio_venta)}</span>`;
      option.addEventListener('click', () => selectSaleProduct(product, target));
      suggestions.appendChild(option);
    });

    suggestions.classList.add('open');
  }

  function renderSaleProductSearchModal() {
    if (!saleProductSearchResults) {
      return;
    }

    const term = String(saleProductSearchModalInput?.value || '').trim().toLowerCase();
    const matches = activeProducts()
      .filter((product) => `${product.nombre} ${product.codigo_barra || ''} ${product.categoria || ''}`.toLowerCase().includes(term))
      .slice(0, 80);

    if (matches.length === 0) {
      saleProductSearchResults.innerHTML = '<p class="muted">No se encontraron productos.</p>';
      return;
    }

    saleProductSearchResults.innerHTML = '';
    matches.forEach((product) => {
      const stock = availableStock(product, saleCart);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'product-search-row';
      button.disabled = stock <= 0;
      button.innerHTML = `
        <span>
          <strong>${escapeHtml(product.nombre)}</strong>
          <span>${escapeHtml(product.codigo_barra || product.categoria || '-')} | ${stock <= 0 ? 'Sin stock' : 'Stock ' + stock}</span>
        </span>
        <span class="product-search-price">${money(product.precio_venta)}</span>
      `;
      button.addEventListener('click', () => {
        selectSaleProduct(product);
        saleProductSearchModal?.classList.remove('open');
        saleProductSearchModal?.setAttribute('aria-hidden', 'true');
        document.getElementById('ventaCantidad')?.focus();
      });
      saleProductSearchResults.appendChild(button);
    });
  }

  function timeLabel(hour) {
    const totalMinutes = Math.round(Number(hour || 0) * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
  }

  function displayTimeLabel(hour) {
    const label = timeLabel(hour);
    return label === '24:00' ? '00:00' : label;
  }

  function displayReservationTime(value) {
    const label = String(value || '').slice(0, 5);
    return label === '24:00' ? '00:00' : label;
  }

  function reservationHour(value) {
    return reservationTimeToHours(value) ?? Number(String(value).slice(0, 2));
  }

  function isPastSlot(date, hour) {
    if (date !== todayKey) {
      return false;
    }

    const now = new Date();
    return hour < (now.getHours() + (now.getMinutes() / 60));
  }

  function reservationTimeToHours(value) {
    const [hours, minutes] = String(value || '').split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
      return null;
    }
    return hours + (minutes / 60);
  }

  function durationLabel(duration) {
    const totalMinutes = Math.round(Number(duration || 0) * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
  }

  function normalizeSlotHour(value) {
    if (value === null) {
      return null;
    }
    return Math.round(Number(value) / slotStep) * slotStep;
  }

  function fillTimeSelect(select, startHour, endHour, selectedHour, isAllowed = null) {
    if (!select) {
      return;
    }

    select.innerHTML = '';
    for (let hour = startHour; hour <= endHour; hour += slotStep) {
      if (isAllowed && !isAllowed(hour)) {
        continue;
      }
      const option = document.createElement('option');
      option.value = timeLabel(hour);
      option.textContent = displayTimeLabel(hour);
      select.appendChild(option);
    }
    if (select.options.length === 0) {
      return;
    }

    const preferredValue = timeLabel(Math.min(endHour, Math.max(startHour, selectedHour)));
    select.value = Array.from(select.options).some((option) => option.value === preferredValue)
      ? preferredValue
      : select.options[0].value;
  }

  function populateReservationTimeSelects(startHour = hourStart, endHour = startHour + minReservationDuration) {
    const startSelect = document.getElementById('modalHoraInicioVisible');
    const endSelect = document.getElementById('modalHoraFinVisible');
    if (!startSelect || !endSelect) {
      return;
    }

    const selectedStart = Math.min(hourEnd - minReservationDuration, Math.max(hourStart, startHour));
    const selectedEnd = Math.min(hourEnd, Math.max(selectedStart + minReservationDuration, endHour));
    fillTimeSelect(
      startSelect,
      hourStart,
      hourEnd - minReservationDuration,
      selectedStart,
      (hour) => !isPastSlot(reservationModalState?.date || todayKey, hour)
        && !hasConflict(reservationModalState?.date || todayKey, hour, hour + minReservationDuration)
    );
    const currentStart = reservationTimeToHours(startSelect.value) ?? selectedStart;
    fillTimeSelect(
      endSelect,
      currentStart + minReservationDuration,
      hourEnd,
      selectedEnd,
      (hour) => !hasConflict(reservationModalState?.date || todayKey, currentStart, hour)
    );
    if (startSelect.options.length === 0 || endSelect.options.length === 0) {
      showClientMessage('No hay horarios disponibles para completar al menos 1 hora.');
    }
  }

  function normalizeSlotTime(value, min, max) {
    const match = String(value || '').match(/(\d{1,2})(?::([0-5]\d))?/);
    let time = match ? Number(match[1]) + (Number(match[2] || 0) / 60) : min;
    time = Math.round(time / slotStep) * slotStep;
    return Math.min(max, Math.max(min, time));
  }

  function syncEditHourInput(sourceId, targetId) {
    const source = document.getElementById(sourceId);
    const target = document.getElementById(targetId);
    if (!source || !target) {
      return;
    }

    const hour = normalizeSlotHour(reservationTimeToHours(source.value));
    target.value = timeLabel(hour ?? 0);
  }

  function syncEditHours() {
    syncEditHourInput('editHoraInicioHour', 'editHoraInicio');
    syncEditHourInput('editHoraFinHour', 'editHoraFin');
  }

  function updateEditReservationPrice() {
    const courtId = Number(document.getElementById('editCanchaId')?.value || 0);
    const startSelect = document.getElementById('editHoraInicioHour');
    const endSelect = document.getElementById('editHoraFinHour');
    const startBefore = reservationTimeToHours(startSelect?.value) ?? hourStart;
    const endBefore = reservationTimeToHours(endSelect?.value) ?? (startBefore + minReservationDuration);
    populateEditReservationTimeSelects(startBefore, endBefore);
    syncEditHours();
    const start = reservationTimeToHours(document.getElementById('editHoraInicio')?.value);
    const end = reservationTimeToHours(document.getElementById('editHoraFin')?.value);
    const court = courts.find((item) => Number(item.id) === courtId);
    if (!court || start === null || end === null || end <= start) {
      return;
    }
    document.getElementById('editPrecioTotal').value = money(Number(court.precio_hora || 0) * (end - start));
  }

  function validateEditReservationSchedule(showMessage = false) {
    const date = document.getElementById('editFecha')?.value || '';
    const courtId = Number(document.getElementById('editCanchaId')?.value || 0);
    const reservaId = Number(document.getElementById('editReservaId')?.value || 0);
    const startSelect = document.getElementById('editHoraInicioHour');
    const endSelect = document.getElementById('editHoraFinHour');
    const startHour = reservationTimeToHours(startSelect?.value);
    const endHour = reservationTimeToHours(endSelect?.value);
    const message = 'Ya existe otra reserva para esa cancha en la fecha y hora seleccionadas.';
    const hasInvalidRange = startHour === null || endHour === null || endHour <= startHour;
    const hasScheduleConflict = !hasInvalidRange
      && hasReservationConflict(date, courtId, startHour, endHour, reservaId);

    startSelect?.setCustomValidity(hasScheduleConflict ? message : '');
    endSelect?.setCustomValidity(hasInvalidRange ? 'La hora fin debe ser posterior a la hora inicio.' : (hasScheduleConflict ? message : ''));

    if (showMessage && (hasInvalidRange || hasScheduleConflict)) {
      showClientMessage(hasInvalidRange ? 'La hora fin debe ser posterior a la hora inicio.' : message);
    }

    return !hasInvalidRange && !hasScheduleConflict;
  }

  function renderCourtTabs() {
    if (!courtTabs) return;
    courtTabs.innerHTML = '';
    courts.forEach((court) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = court.nombre;
      button.className = Number(court.id) === selectedCourtId ? 'active' : '';
      button.addEventListener('click', () => {
        selectedCourtId = Number(court.id);
        renderCourtTabs();
        renderCalendar();
      });
      courtTabs.appendChild(button);
    });
  }

  function renderCalendar() {
    if (!weekCalendar || !weekCalendarHeader || !selectedCourtId) return;

    const daysToShow = calendarDaysToShow();
    const weekDays = Array.from({ length: daysToShow }, (_, index) => addDays(weekStart, index));
    const end = addDays(weekStart, daysToShow - 1);
    const calendarDateLabel = daysToShow === 7
      ? `${weekDays[0].toLocaleDateString('es-PY', { day: '2-digit', month: 'short' })} - ${end.toLocaleDateString('es-PY', { day: '2-digit', month: 'short', year: 'numeric' })}`
      : `${weekDays[0].toLocaleDateString('es-PY', { weekday: 'short', day: '2-digit', month: 'short' })} - ${end.toLocaleDateString('es-PY', { weekday: 'short', day: '2-digit', month: 'short' })}`;
    calendarTitle.textContent = calendarDateLabel;
    const calendarDateInTabs = document.getElementById('calendarDateInTabs');
    if (calendarDateInTabs) {
      calendarDateInTabs.textContent = calendarDateLabel;
    }
    document.getElementById('prevWeek').disabled = weekStart <= today;
    weekCalendar.innerHTML = '';
    weekCalendarHeader.innerHTML = '';
    const calendarRows = Math.round((hourEnd - hourStart) / slotStep);
    weekCalendar.style.setProperty('--calendar-rows', String(calendarRows));
    weekCalendar.style.setProperty('--calendar-days', String(daysToShow));
    weekCalendarHeader.style.setProperty('--calendar-days', String(daysToShow));

    const corner = document.createElement('div');
    corner.className = 'calendar-corner';
    weekCalendarHeader.appendChild(corner);

    weekDays.forEach((date, index) => {
      const header = document.createElement('div');
      header.className = 'calendar-day';
      header.textContent = `${dayNames[date.getDay()]} ${date.getDate()}/${date.getMonth() + 1}`;
      weekCalendarHeader.appendChild(header);
    });

    for (let slotIndex = 0; slotIndex < calendarRows; slotIndex++) {
      const hour = hourStart + (slotIndex * slotStep);
      const row = slotIndex + 1;
      if (Number.isInteger(hour)) {
        const label = document.createElement('div');
        label.className = 'calendar-hour';
        label.textContent = timeLabel(hour);
        label.style.gridColumn = '1';
        label.style.gridRow = `${row} / span ${Math.round(1 / slotStep)}`;
        weekCalendar.appendChild(label);
      }

      if (hour > hourEnd - minReservationDuration) {
        continue;
      }

      weekDays.forEach((date, index) => {
        const slot = document.createElement('button');
        slot.type = 'button';
        slot.className = 'calendar-slot';
        slot.dataset.date = toDateKey(date);
        slot.dataset.hour = String(hour);
        slot.dataset.watermark = `${dayNames[date.getDay()]} ${date.getDate()}/${date.getMonth() + 1} · ${displayTimeLabel(hour)}-${displayTimeLabel(hour + minReservationDuration)}`;
        const slotBlocked = hasConflict(slot.dataset.date, hour, hour + minReservationDuration);
        if (hour > hourEnd - minReservationDuration || isPastSlot(slot.dataset.date, hour) || slotBlocked) {
          slot.classList.add('slot-disabled');
          slot.classList.toggle('slot-occupied', slotBlocked);
          slot.disabled = true;
        }
        if (isSlotInSelection(slot.dataset.date, hour)) {
          slot.classList.add('slot-selected');
        }
        slot.style.gridColumn = String(index + 2);
        slot.style.gridRow = hour === hourEnd - minReservationDuration
          ? `${row} / span ${Math.round(minReservationDuration / slotStep)}`
          : String(row);
        slot.addEventListener('pointerdown', (event) => startSlotSelection(event, slot.dataset.date, Number(slot.dataset.hour)));
        slot.addEventListener('pointerenter', () => updateSlotSelection(slot.dataset.date, Number(slot.dataset.hour)));
        weekCalendar.appendChild(slot);
      });
    }

    reservations
      .filter((item) => Number(item.cancha_id) === selectedCourtId && item.estado !== 'cancelado')
      .filter((item) => weekDays.some((date) => toDateKey(date) === item.fecha))
      .forEach((item) => {
        const dayIndex = weekDays.findIndex((date) => toDateKey(date) === item.fecha);
        const start = reservationHour(item.hora_inicio);
        const endHour = reservationHour(item.hora_fin);
        if (endHour <= hourStart || start >= hourEnd) {
          return;
        }
        const visibleStart = Math.max(start, hourStart);
        const visibleEnd = Math.min(endHour, hourEnd);
        const duration = Math.max(slotStep, visibleEnd - visibleStart);
        const rowStart = Math.round((visibleStart - hourStart) / slotStep) + 1;
        const rowSpan = Math.max(1, Math.round(duration / slotStep));
        const block = document.createElement('button');
        block.type = 'button';
        block.className = `reservation-block ${item.estado}`;
        block.classList.toggle('has-pending-receipt', hasPendingReceipt(item));
        block.style.gridColumn = String(dayIndex + 2);
        block.style.gridRow = `${rowStart} / span ${rowSpan}`;
        block.innerHTML = `<strong><span class="reservation-id">#${item.id}</span> ${displayReservationTime(item.hora_inicio)} - ${displayReservationTime(item.hora_fin)}</strong><span>${escapeHtml(item.cliente)}</span><small>Saldo ${money(item.saldo)}</small>`;
        block.addEventListener('pointerdown', (event) => {
          event.preventDefault();
          event.stopPropagation();
          dragSelection = null;
          paintSlotSelection();
        });
        block.addEventListener('pointerenter', () => {
          if (dragSelection) {
            dragSelection = null;
            paintSlotSelection();
          }
        });
        block.addEventListener('click', (event) => {
          event.stopPropagation();
          openDetailModal(item);
        });
        weekCalendar.appendChild(block);
      });
  }

  async function refreshReservationsFeed() {
    if (reservationFeedLoading || !weekCalendar) {
      return;
    }

    reservationFeedLoading = true;
    try {
      const response = await fetch('reservas_feed.php?t=' + Date.now(), {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store',
      });
      if (!response.ok) {
        return;
      }

      const data = await response.json();
      if (!data.ok || !Array.isArray(data.reservas)) {
        return;
      }

      const nextSignature = JSON.stringify(data.reservas);
      if (nextSignature !== reservationFeedSignature) {
        reservations = data.reservas;
        reservationFeedSignature = nextSignature;
        updateReservationMenuNotification();
        renderCalendar();
      }
    } catch (error) {
      // La proxima consulta vuelve a intentar sin molestar al usuario.
    } finally {
      reservationFeedLoading = false;
    }
  }

  function isSlotInSelection(date, hour) {
    const range = getDragSelectionRange(date, dragSelection?.endHour);
    if (!range) {
      return false;
    }

    return hour >= range.startHour && hour < range.endHour;
  }

  function getDragSelectionRange(date, endSlotHour) {
    if (!dragSelection || dragSelection.date !== date) {
      return null;
    }

    const startHour = Math.min(dragSelection.startHour, endSlotHour);
    const endHour = Math.max(dragSelection.startHour + minReservationDuration, endSlotHour + slotStep);
    return { startHour, endHour };
  }

  function paintSlotSelection() {
    document.querySelectorAll('.calendar-slot').forEach((slot) => {
      slot.classList.toggle('slot-selected', isSlotInSelection(slot.dataset.date, Number(slot.dataset.hour)));
    });
  }

  function hasConflict(date, startHour, endHour) {
    return reservations.some((item) => {
      if (Number(item.cancha_id) !== selectedCourtId || item.fecha !== date || item.estado === 'cancelado') {
        return false;
      }

      const start = reservationHour(item.hora_inicio);
      const end = reservationHour(item.hora_fin);
      return start < endHour && end > startHour;
    });
  }

  function hasReservationConflict(date, courtId, startHour, endHour, ignoreReservationId = 0) {
    return reservations.some((item) => {
      if (
        Number(item.id) === Number(ignoreReservationId)
        || Number(item.cancha_id) !== Number(courtId)
        || item.fecha !== date
        || item.estado === 'cancelado'
      ) {
        return false;
      }

      const start = reservationHour(item.hora_inicio);
      const end = reservationHour(item.hora_fin);
      return start < endHour && end > startHour;
    });
  }

  function populateEditReservationTimeSelects(startHour, endHour) {
    const startSelect = document.getElementById('editHoraInicioHour');
    const endSelect = document.getElementById('editHoraFinHour');
    const date = document.getElementById('editFecha')?.value || '';
    const courtId = Number(document.getElementById('editCanchaId')?.value || 0);
    const reservaId = Number(document.getElementById('editReservaId')?.value || 0);
    if (!startSelect || !endSelect || !date || !courtId) {
      return;
    }

    const selectedStart = Math.min(hourEnd - minReservationDuration, Math.max(hourStart, startHour));
    const selectedEnd = Math.min(hourEnd, Math.max(selectedStart + minReservationDuration, endHour));
    fillTimeSelect(
      startSelect,
      hourStart,
      hourEnd - minReservationDuration,
      selectedStart,
      (hour) => !hasReservationConflict(date, courtId, hour, hour + minReservationDuration, reservaId)
    );

    const currentStart = reservationTimeToHours(startSelect.value) ?? selectedStart;
    fillTimeSelect(
      endSelect,
      currentStart + minReservationDuration,
      hourEnd,
      selectedEnd,
      (hour) => !hasReservationConflict(date, courtId, currentStart, hour, reservaId)
    );
    document.getElementById('editHoraInicio').value = startSelect.value || timeLabel(selectedStart);
    document.getElementById('editHoraFin').value = endSelect.value || timeLabel(selectedEnd);
  }

  function reservationModalDateLabel(date) {
    const [year, month, day] = String(date).split('-').map(Number);
    const selectedDate = new Date(year, month - 1, day);
    const weekday = selectedDate.toLocaleDateString('es-PY', { weekday: 'long' });
    return `${weekday} ${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}`;
  }

  function syncReservationDuration(nextDuration) {
    if (!reservationModalState) return;

    const duration = Math.max(minReservationDuration, Math.round(Number(nextDuration || 1) / slotStep) * slotStep);
    const endHour = reservationModalState.startHour + duration;
    if (endHour > hourEnd) {
      showClientMessage('La reserva no puede pasar de las 24:00.');
      document.getElementById('modalReservationDuration').textContent = durationLabel(reservationModalState.duration);
      return;
    }

    if (hasConflict(reservationModalState.date, reservationModalState.startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      document.getElementById('modalReservationDuration').textContent = durationLabel(reservationModalState.duration);
      return;
    }

    const court = courts.find((item) => Number(item.id) === Number(reservationModalState.courtId));
    reservationModalState.duration = duration;
    document.getElementById('modalReservationDuration').textContent = durationLabel(duration);
    document.getElementById('modalHoraFin').value = timeLabel(endHour);
    populateReservationTimeSelects(reservationModalState.startHour, endHour);
    document.getElementById('modalPrecioTotal').value = money(court ? Number(court.precio_hora || 0) * duration : 0);
    document.getElementById('modalReservationNumber').textContent = `#${nextReservationId}`;
    document.getElementById('modalSummaryText').textContent = `${court ? court.nombre : 'Cancha'} | ${reservationModalDateLabel(reservationModalState.date)} |`;
  }

  function syncReservationTimesFromInputs() {
    if (!reservationModalState) return;
    const startInput = document.getElementById('modalHoraInicioVisible');
    const endInput = document.getElementById('modalHoraFinVisible');
    const previousStart = reservationModalState.startHour;
    const previousDuration = reservationModalState.duration;
    let startHour = normalizeSlotHour(reservationTimeToHours(startInput?.value));
    let endHour = normalizeSlotHour(reservationTimeToHours(endInput?.value));

    if (startHour === null) startHour = previousStart;
    if (endHour === null) endHour = previousStart + previousDuration;

    startHour = Math.min(hourEnd - minReservationDuration, Math.max(hourStart, startHour));
    endHour = Math.min(hourEnd, Math.max(startHour + minReservationDuration, endHour));

    if (isPastSlot(reservationModalState.date, startHour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      populateReservationTimeSelects(previousStart, previousStart + previousDuration);
      return;
    }

    if (hasConflict(reservationModalState.date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      populateReservationTimeSelects(previousStart, previousStart + previousDuration);
      return;
    }

    reservationModalState.startHour = startHour;
    reservationModalState.duration = endHour - startHour;
    document.getElementById('modalHoraInicio').value = timeLabel(startHour);
    syncReservationDuration(reservationModalState.duration);
  }

  function startSlotSelection(event, date, hour) {
    if (event.target.closest('.reservation-block')) {
      dragSelection = null;
      paintSlotSelection();
      return;
    }
    event.preventDefault();
    if (hour > hourEnd - minReservationDuration) {
      showClientMessage('La duracion minima de alquiler es 1 hora.');
      return;
    }
    if (isPastSlot(date, hour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      return;
    }
    if (hasConflict(date, hour, hour + minReservationDuration)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      return;
    }
    dragSelection = { date, startHour: hour, endHour: hour };
    paintSlotSelection();
  }

  function updateSlotSelection(date, hour) {
    if (!dragSelection || dragSelection.date !== date) {
      return;
    }

    const range = getDragSelectionRange(date, hour);
    if (!range || hasConflict(date, range.startHour, range.endHour) || isPastSlot(date, range.startHour)) {
      return;
    }

    dragSelection.endHour = hour;
    paintSlotSelection();
  }

  function finishSlotSelection() {
    if (!dragSelection) {
      return;
    }

    const date = dragSelection.date;
    const startHour = Math.min(dragSelection.startHour, dragSelection.endHour);
    const endHour = Math.max(dragSelection.startHour + minReservationDuration, dragSelection.endHour + slotStep);
    dragSelection = null;

    if (isPastSlot(date, startHour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      paintSlotSelection();
      return;
    }

    if (hasConflict(date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      paintSlotSelection();
      return;
    }

    paintSlotSelection();
    openReservationModal(date, startHour, endHour);
  }

  function openReservationModal(date, startHour, endHour) {
    if (isPastSlot(date, startHour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      return;
    }

    if (hasConflict(date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      return;
    }

    const court = courts.find((item) => Number(item.id) === selectedCourtId);
    document.getElementById('modalCanchaId').value = selectedCourtId;
    document.getElementById('modalFecha').value = date;
    document.getElementById('modalHoraInicio').value = timeLabel(startHour);
    document.getElementById('modalHoraFin').value = timeLabel(endHour);
    populateReservationTimeSelects(startHour, endHour);
    const duration = Math.max(minReservationDuration, endHour - startHour);
    reservationModalState = { courtId: selectedCourtId, date, startHour, duration };
    syncReservationDuration(duration);
    modalClienteId.value = '';
    clientSearch.value = '';
    document.getElementById('modalMontoPago').value = '0';
    document.getElementById('modalReservaEstado').value = 'reservado';
    document.getElementById('modalReservaMetodo').value = 'efectivo';
    document.getElementById('modalComprobantePago').value = '';
    clientSuggestions.innerHTML = '';
    clientSuggestions.classList.remove('open');
    reservationModal.classList.add('open');
    reservationModal.setAttribute('aria-hidden', 'false');
  }

  const reservationSectionTabs = document.querySelectorAll('[data-reservation-section]');

  function setReservationDetailSection(sectionId = '') {
    reservationSectionTabs.forEach((tab) => {
      const isActive = tab.dataset.reservationSection === sectionId;
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    ['reservationEditSection', 'reservationPaymentSection', 'reservationConsumptionSection'].forEach((id) => {
      document.getElementById(id).open = id === sectionId;
    });
  }

  reservationSectionTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const sectionId = tab.dataset.reservationSection;
      setReservationDetailSection(tab.classList.contains('active') ? '' : sectionId);
    });
  });

  function openDetailModal(item) {
    reservationSaleCart = [];
    renderCart(reservationSaleCart, 'reservationSaleCartList', 'reservationSaleCartInputs');
    selectedReservationSaleProduct = null;
    document.getElementById('reservaVentaProducto').value = '';
    reservaVentaProductoSearch.value = '';
    reservationProductSuggestions.innerHTML = '';
    reservationProductSuggestions.classList.remove('open');
    document.getElementById('reservaVentaTipo').value = 'unidad';
    document.getElementById('reservaVentaCantidad').value = 1;
    refreshReservationProductOptions();
    document.getElementById('reservationDetailTitle').textContent = `Detalle de reserva #${item.id}`;
    setReservationDetailSection('');
    document.getElementById('detailReservaIdPago').value = item.id;
    document.getElementById('detailReservaIdVenta').value = item.id;
    const paymentForm = document.getElementById('reservationPaymentForm');
    const paymentSection = document.getElementById('reservationPaymentSection');
    const paidNotice = document.getElementById('reservationPaidNotice');
    const paymentMethod = document.getElementById('reservationPaymentMethod');
    const paymentSubmit = document.getElementById('reservationPaymentSubmit');
    const isPaid = Number(item.saldo || 0) <= 0;
    const consumoDetalle = reservationConsumptions[String(item.id)] || reservationConsumptions[Number(item.id)] || [];
    const hasPendingReceiptToConfirm = Boolean(item.abono_pendiente_comprobante_path) && item.abono_pendiente_estado === 'revision';
    paidNotice.hidden = !isPaid;
    paymentSection.classList.toggle('is-paid', isPaid);
    paymentSection.classList.toggle('has-pending-receipt', hasPendingReceiptToConfirm);
    document.getElementById('detailPagoIdMetodo').value = item.ultimo_pago_id || '';
    document.getElementById('updatePaymentMethodFlag').value = isPaid && item.ultimo_pago_id ? '1' : '0';
    paymentMethod.value = isPaid ? (item.ultimo_pago_metodo || 'efectivo') : 'efectivo';
    paymentSubmit.textContent = isPaid ? 'Actualizar metodo' : (cashOpenToday ? 'Registrar pago' : 'Caja cerrada');
    paymentSubmit.disabled = isPaid ? !item.ultimo_pago_id : !cashOpenToday;
    paymentForm.dataset.saldo = String(Math.max(0, Number(item.saldo || 0)));
    paymentForm.querySelectorAll('input[name="monto"]').forEach((field) => {
      field.disabled = isPaid || !cashOpenToday;
    });
    document.getElementById('paymentReservationSummary').innerHTML = `
      <table class="payment-summary-table">
        <thead>
          <tr>
            <th>Detalle</th>
            <th>Tipo</th>
            <th>Cant.</th>
            <th>Precio</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Reserva - ${escapeHtml(item.cancha || '')}</td>
            <td>Alquiler</td>
            <td>1</td>
            <td>${money(item.precio_total)}</td>
            <td>${money(item.precio_total)}</td>
          </tr>
          ${consumoDetalle.length ? consumoDetalle.map((consumo) => `
            <tr>
              <td>${escapeHtml(consumo.producto || '')}</td>
              <td>${escapeHtml(consumo.tipo_venta || '')}</td>
              <td>${Number(consumo.cantidad || 0)}</td>
              <td>${money(consumo.precio_unitario)}</td>
              <td>${money(consumo.subtotal)}</td>
            </tr>
          `).join('') : '<tr><td colspan="5" class="muted">Sin consumos registrados.</td></tr>'}
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3">Total reserva</th>
            <th colspan="2">${money(item.total_alcanzado)}</th>
          </tr>
          <tr>
            <th colspan="3">Pagado</th>
            <th colspan="2">${money(item.pagado)}</th>
          </tr>
          <tr>
            <th colspan="3">Pagado efectivo</th>
            <th colspan="2">${money(item.pagado_efectivo)}</th>
          </tr>
          <tr>
            <th colspan="3">Pagado transferencia</th>
            <th colspan="2">${money(item.pagado_transferencia)}</th>
          </tr>
          <tr class="${Number(item.saldo || 0) > 0 ? 'pending-balance' : ''}">
            <th colspan="3">Saldo total</th>
            <th colspan="2">${money(item.saldo)}</th>
          </tr>
        </tfoot>
      </table>
      ${item.comprobante_path ? `<div class="payment-receipt-action"><span>Comprobante</span><button type="button" class="small secondary" id="viewReservationReceipt">Ver comprobante</button></div>` : ''}
      ${item.abono_pendiente_comprobante_path ? `<div class="payment-receipt-action pending-receipt-alert"><span>Comprobante pendiente de confirmar ${item.abono_pendiente_monto ? money(item.abono_pendiente_monto) : ''}</span><button type="button" class="small danger" id="viewPendingReservationReceipt">Ver comprobante</button></div>` : ''}
    `;
    document.getElementById('viewReservationReceipt')?.addEventListener('click', () => openReceiptImageModal(item.comprobante_path));
    document.getElementById('viewPendingReservationReceipt')?.addEventListener('click', () => {
      const needsConfirmation = item.abono_pendiente_estado === 'revision';
      openReceiptImageModal(
        item.abono_pendiente_comprobante_path,
        needsConfirmation ? String(item.abono_pendiente_id || '') : '',
        needsConfirmation ? String(item.id || '') : ''
      );
    });
    document.getElementById('editReservaId').value = item.id;
    selectEditReservationClient({
      id: item.cliente_id,
      nombre: item.cliente,
      telefono: item.telefono
    });
    document.getElementById('editCanchaId').value = item.cancha_id;
    document.getElementById('editFecha').value = item.fecha;
    const editHoraInicioHour = document.getElementById('editHoraInicioHour');
    const editHoraFinHour = document.getElementById('editHoraFinHour');
    const startHour = reservationHour(item.hora_inicio);
    const endHour = reservationHour(item.hora_fin);
    populateEditReservationTimeSelects(startHour, endHour);
    editHoraInicioHour.value = timeLabel(startHour);
    editHoraFinHour.value = timeLabel(endHour);
    syncEditHours();
    document.getElementById('editPrecioTotal').value = money(item.precio_total);
    const reservationEditForm = document.getElementById('reservationEditForm');
    reservationEditForm.dataset.saldo = String(Math.max(0, Number(item.saldo || 0)));
    reservationEditForm.dataset.estadoOriginal = item.estado || 'reservado';
    const editEstado = document.getElementById('editEstado');
    const editEstadoCancelado = document.getElementById('editEstadoCancelado');
    if (editEstadoCancelado) {
      editEstadoCancelado.disabled = Number(item.pagado || 0) > 0;
      editEstadoCancelado.textContent = Number(item.pagado || 0) > 0 ? 'Cancelado (bloqueado por pago)' : 'Cancelado';
    }
    editEstado.value = item.estado === 'cancelado' && Number(item.pagado || 0) > 0 ? 'confirmado' : item.estado;
    document.getElementById('reservationDetail').innerHTML = `
      <dl>
        <div><dt>Cliente</dt><dd>${escapeHtml(item.cliente)} - ${escapeHtml(item.telefono)}</dd></div>
        <div><dt>Cancha</dt><dd>${escapeHtml(item.cancha)}</dd></div>
        <div><dt>Fecha</dt><dd>${escapeHtml(reservationDateLabel(item))}</dd></div>
        <div class="pending-balance"><dt>Saldo</dt><dd>${money(item.saldo)}</dd></div>
      </dl>
    `;
    updateReservationSaleTotal();
    detailModal.classList.add('open');
    detailModal.setAttribute('aria-hidden', 'false');
  }

  function openProductModal(id) {
    const product = products.find((item) => Number(item.id) === Number(id));
    if (!product || !productModal) {
      return;
    }

    document.getElementById('productModalTitle').textContent = 'Editar producto';
    const productForm = document.getElementById('productForm');
    productForm.action = 'editar_producto.php';
    productForm.dataset.editProductId = String(product.id || '');
    document.getElementById('productSubmitButton').textContent = 'Guardar cambios';
    document.getElementById('editProductoId').value = product.id;
    document.getElementById('editProductoNombre').value = product.nombre || '';
    document.getElementById('editProductoNombre').setCustomValidity('');
    document.getElementById('editProductoCodigo').value = product.codigo_barra || '';
    editProductoProveedorId.value = product.proveedor_id || '';
    editProductoProveedorSearch.value = product.proveedor || '';
    editProductoProveedorSuggestions.innerHTML = '';
    editProductoProveedorSuggestions.classList.remove('open');
    document.getElementById('editProductoCategoria').value = product.categoria || '';
    const editCategorySuggestions = categorySuggestions('editProductoCategoria');
    if (editCategorySuggestions) {
      editCategorySuggestions.innerHTML = '';
      editCategorySuggestions.classList.remove('open');
    }
    document.getElementById('editProductoCompra').value = money(product.precio_compra);
    document.getElementById('editProductoVenta').value = money(product.precio_venta);
    document.getElementById('editProductoPackCantidad').value = Number(product.pack_cantidad || 0);
    document.getElementById('editProductoCompraPack').value = money(product.precio_compra_pack);
    document.getElementById('editProductoPackPrecio').value = money(product.precio_pack);
    document.getElementById('editProductoPromoCantidad').value = Number(product.promocion_cantidad || 0);
    document.getElementById('editProductoPromoPrecio').value = money(product.precio_promocion);
    document.getElementById('editProductoStock').value = Number(product.stock || 0);
    document.getElementById('editProductoEstado').value = product.estado || 'activo';
    updateProductDuplicateWarning('editProductoNombre', 'editProductDuplicateName', productForm.dataset.editProductId);
    updateProductPriceWarnings(productForm);
    productModal.classList.add('open');
    productModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editProductoNombre').focus();
  }

  function openCreateProductModal() {
    if (!productModal) {
      return;
    }

    document.getElementById('productModalTitle').textContent = 'Nuevo producto';
    const productForm = document.getElementById('productForm');
    productForm.action = 'guardar_producto.php';
    productForm.dataset.editProductId = '';
    document.getElementById('productSubmitButton').textContent = 'Guardar producto';
    document.getElementById('editProductoId').value = '';
    document.getElementById('editProductoNombre').value = '';
    document.getElementById('editProductoNombre').setCustomValidity('');
    document.getElementById('editProductoCodigo').value = '';
    editProductoProveedorId.value = '';
    editProductoProveedorSearch.value = '';
    editProductoProveedorSuggestions.innerHTML = '';
    editProductoProveedorSuggestions.classList.remove('open');
    document.getElementById('editProductoCategoria').value = '';
    const editCategorySuggestions = categorySuggestions('editProductoCategoria');
    if (editCategorySuggestions) {
      editCategorySuggestions.innerHTML = '';
      editCategorySuggestions.classList.remove('open');
    }
    document.getElementById('editProductoCompra').value = '0';
    document.getElementById('editProductoVenta').value = '0';
    document.getElementById('editProductoPackCantidad').value = '0';
    document.getElementById('editProductoCompraPack').value = '0';
    document.getElementById('editProductoPackPrecio').value = '0';
    document.getElementById('editProductoPromoCantidad').value = '0';
    document.getElementById('editProductoPromoPrecio').value = '0';
    document.getElementById('editProductoStock').value = '0';
    document.getElementById('editProductoEstado').value = 'activo';
    setProductDuplicateWarning('editProductDuplicateName', '');
    updateProductPriceWarnings(productForm);
    productModal.classList.add('open');
    productModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editProductoNombre').focus();
  }

  function openToggleProductModal(button) {
    const modal = document.getElementById('toggleProductModal');
    if (!modal) {
      return;
    }

    const currentStatus = button.dataset.estado || 'activo';
    const nextStatus = currentStatus === 'activo' ? 'inactivo' : 'activo';
    const actionLabel = nextStatus === 'inactivo' ? 'Desactivar' : 'Activar';

    document.getElementById('toggleProductId').value = button.dataset.id || '';
    document.getElementById('toggleProductStatus').value = nextStatus;
    document.getElementById('toggleProductTitle').textContent = `${actionLabel} producto`;
    document.getElementById('toggleProductText').textContent = `${actionLabel} el producto "${button.dataset.nombre || ''}"?`;
    const submitButton = document.getElementById('toggleProductSubmit');
    submitButton.textContent = actionLabel;
    submitButton.classList.toggle('danger', nextStatus === 'inactivo');

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function openDeleteProductModal(button) {
    const modal = document.getElementById('deleteProductModal');
    if (!modal) {
      return;
    }

    document.getElementById('deleteProductId').value = button.dataset.id || '';
    document.getElementById('deleteProductText').textContent = `Eliminar el producto "${button.dataset.nombre || ''}"? Solo se podra eliminar si no esta usado en compras, ventas o consumos.`;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function openCourtModal(id) {
    const court = courts.find((item) => Number(item.id) === Number(id));
    if (!court || !courtModal) {
      return;
    }

    document.getElementById('courtModalTitle').textContent = 'Editar cancha';
    document.getElementById('courtForm').action = 'editar_cancha.php';
    document.getElementById('courtSubmitButton').textContent = 'Guardar cambios';
    document.getElementById('editCanchaModalId').value = court.id;
    document.getElementById('editCanchaNombre').value = court.nombre || '';
    document.getElementById('editCanchaTipo').value = court.tipo || '';
    document.getElementById('editCanchaPrecio').value = money(court.precio_hora);
    document.getElementById('editCanchaEstado').value = court.estado || 'activa';
    courtModal.classList.add('open');
    courtModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editCanchaNombre').focus();
  }

  function openCreateCourtModal() {
    if (!courtModal) {
      return;
    }

    document.getElementById('courtModalTitle').textContent = 'Nueva cancha';
    document.getElementById('courtForm').action = 'guardar_cancha.php';
    document.getElementById('courtSubmitButton').textContent = 'Guardar cancha';
    document.getElementById('editCanchaModalId').value = '';
    document.getElementById('editCanchaNombre').value = '';
    document.getElementById('editCanchaTipo').value = '';
    document.getElementById('editCanchaPrecio').value = '';
    document.getElementById('editCanchaEstado').value = 'activa';
    courtModal.classList.add('open');
    courtModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editCanchaNombre').focus();
  }

  function openDeleteCourtModal(button) {
    const modal = document.getElementById('deleteCourtModal');
    if (!modal) {
      return;
    }

    document.getElementById('deleteCanchaId').value = button.dataset.id || '';
    document.getElementById('deleteCanchaText').textContent = `Eliminar la cancha "${button.dataset.nombre || ''}"? Solo se podra eliminar si no tiene reservas registradas.`;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function openProviderModal(id) {
    const provider = providers.find((item) => Number(item.id) === Number(id));
    if (!provider || !providerModal) {
      return;
    }

    document.getElementById('editProveedorId').value = provider.id;
    document.getElementById('editProveedorNombre').value = provider.nombre || '';
    document.getElementById('editProveedorTelefono').value = provider.telefono || '';
    document.getElementById('editProveedorEmail').value = provider.email || '';
    document.getElementById('editProveedorRuc').value = provider.ruc || '';
    document.getElementById('editProveedorDireccion').value = provider.direccion || '';
    document.getElementById('editProveedorEstado').value = provider.estado || 'activo';
    providerModal.classList.add('open');
    providerModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editProveedorNombre').focus();
  }

  function openToggleProviderModal(button) {
    if (!toggleProviderModal) {
      return;
    }

    const currentStatus = button.dataset.estado || 'activo';
    const nextStatus = currentStatus === 'activo' ? 'inactivo' : 'activo';
    const actionLabel = nextStatus === 'inactivo' ? 'Desactivar' : 'Activar';

    document.getElementById('toggleProviderId').value = button.dataset.id || '';
    document.getElementById('toggleProviderStatus').value = nextStatus;
    document.getElementById('toggleProviderTitle').textContent = `${actionLabel} proveedor`;
    document.getElementById('toggleProviderText').textContent = `${actionLabel} el proveedor "${button.dataset.nombre || ''}"?`;
    const submitButton = document.getElementById('toggleProviderSubmit');
    submitButton.textContent = actionLabel;
    submitButton.classList.toggle('danger', nextStatus === 'inactivo');

    toggleProviderModal.classList.add('open');
    toggleProviderModal.setAttribute('aria-hidden', 'false');
  }

  function openDeleteProviderModal(id) {
    const provider = providers.find((item) => Number(item.id) === Number(id));
    if (!provider || !deleteProviderModal) {
      return;
    }

    document.getElementById('deleteProveedorId').value = provider.id;
    document.getElementById('deleteProveedorText').textContent = `Eliminar el proveedor "${provider.nombre}"? Solo se podra eliminar si no esta asociado en nada.`;
    deleteProviderModal.classList.add('open');
    deleteProviderModal.setAttribute('aria-hidden', 'false');
  }

  const simpleListPageSize = 10;
  const simpleListPages = {
    clients: 1,
    categories: 1,
    providers: 1
  };

  function renderSimpleListPagination(config) {
    const rows = Array.from(document.querySelectorAll(config.rowSelector));
    const query = (config.search?.value || '').trim().toLowerCase();
    const filteredRows = rows.filter((row) => (row.dataset[config.searchDataset] || '').includes(query));
    const totalPages = Math.max(1, Math.ceil(filteredRows.length / simpleListPageSize));
    simpleListPages[config.key] = Math.min(simpleListPages[config.key] || 1, totalPages);
    const currentPage = simpleListPages[config.key];
    const start = (currentPage - 1) * simpleListPageSize;
    const end = start + simpleListPageSize;

    rows.forEach((row) => {
      row.hidden = true;
    });
    filteredRows.slice(start, end).forEach((row) => {
      row.hidden = false;
    });

    if (config.count) {
      config.count.textContent = `${filteredRows.length} ${config.label}`;
    }
    if (config.empty) {
      config.empty.hidden = filteredRows.length > 0;
    }
    if (!config.pagination) {
      return;
    }

    config.pagination.innerHTML = '';
    if (filteredRows.length <= simpleListPageSize) {
      return;
    }

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.className = 'secondary';
    prevButton.textContent = 'Anterior';
    prevButton.disabled = currentPage <= 1;
    prevButton.addEventListener('click', () => {
      simpleListPages[config.key] = Math.max(1, currentPage - 1);
      renderSimpleListPagination(config);
    });

    const pageLabel = document.createElement('span');
    pageLabel.textContent = `Pagina ${currentPage} de ${totalPages}`;

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'secondary';
    nextButton.textContent = 'Siguiente';
    nextButton.disabled = currentPage >= totalPages;
    nextButton.addEventListener('click', () => {
      simpleListPages[config.key] = Math.min(totalPages, currentPage + 1);
      renderSimpleListPagination(config);
    });

    config.pagination.append(prevButton, pageLabel, nextButton);
  }

  function filterClients() {
    renderSimpleListPagination({
      key: 'clients',
      search: clientListSearch,
      count: clientListCount,
      pagination: clientListPagination,
      empty: document.querySelector('[data-client-empty]'),
      rowSelector: '[data-client-row]',
      searchDataset: 'clientSearch',
      label: 'registrado(s)'
    });
  }

  function filterCategories() {
    renderSimpleListPagination({
      key: 'categories',
      search: categoryListSearch,
      count: categoryListCount,
      pagination: categoryListPagination,
      empty: document.querySelector('[data-category-empty]'),
      rowSelector: '[data-category-row]',
      searchDataset: 'categorySearch',
      label: 'categoria(s)'
    });
  }

  function filterProviders() {
    renderSimpleListPagination({
      key: 'providers',
      search: providerSearch,
      count: providerCount,
      pagination: providerListPagination,
      empty: document.querySelector('[data-provider-empty]'),
      rowSelector: '[data-provider-row]',
      searchDataset: 'providerSearch',
      label: 'registrado(s)'
    });
  }

  function setModalCategoryWarning(message = '') {
    const warning = document.getElementById('categoryDuplicateWarning');
    if (!warning) return;

    warning.textContent = message;
    warning.classList.toggle('show', message !== '');
  }

  function normalizeProductName(name) {
    return String(name || '').trim().replace(/\s+/g, ' ').toLowerCase();
  }

  function findProductByName(name, ignoredId = '') {
    const normalized = normalizeProductName(name);
    if (!normalized) {
      return null;
    }

    return products.find((product) => (
      normalizeProductName(product.nombre) === normalized
      && Number(product.id) !== Number(ignoredId || 0)
    )) || null;
  }

  function setProductDuplicateWarning(warningId, message = '') {
    const warning = document.getElementById(warningId);
    if (!warning) return;

    warning.textContent = message;
    warning.classList.toggle('show', message !== '');
  }

  function updateProductDuplicateWarning(inputId, warningId, ignoredId = '') {
    const input = document.getElementById(inputId);
    const existing = findProductByName(input?.value || '', ignoredId);
    const message = existing ? `Ya existe un producto con ese nombre: ${existing.nombre}` : '';
    setProductDuplicateWarning(warningId, message);
    input?.setCustomValidity(message);
    return existing;
  }

  function hasProductDuplicateWarning(warningId) {
    return document.getElementById(warningId)?.classList.contains('show') || false;
  }

  function updateModalCategoryWarning() {
    const name = document.getElementById('editCategoriaNombre')?.value || '';
    const originalName = document.getElementById('editCategoriaOriginalNombre')?.value || '';
    const existing = productCategories.find((category) => normalizeCategory(category) === normalizeCategory(name));
    setModalCategoryWarning(existing && normalizeCategory(existing) !== normalizeCategory(originalName)
      ? `Ya existe la categoria: ${existing}`
      : '');
  }

  function openCategoryModal(id = '', name = '', mode = 'create', targetInputId = '') {
    if (!categoryModal) {
      return;
    }

    const isEdit = mode === 'edit';
    const form = document.getElementById('categoryForm');
    form.action = isEdit ? 'editar_categoria.php' : 'guardar_categoria.php';
    document.getElementById('categoryModalTitle').textContent = isEdit ? 'Editar categoria' : 'Nueva categoria';
    document.getElementById('editCategoriaId').value = id;
    document.getElementById('editCategoriaAccion').value = isEdit ? 'renombrar' : 'crear';
    document.getElementById('editCategoriaOriginalNombre').value = isEdit ? (name || '') : '';
    document.getElementById('categoryTargetInput').value = targetInputId;
    document.getElementById('editCategoriaNombre').value = name || '';
    document.getElementById('categorySubmitButton').textContent = isEdit ? 'Guardar cambios' : 'Guardar categoria';
    setModalCategoryWarning('');
    categoryModal.classList.add('open');
    categoryModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editCategoriaNombre').focus();
    updateModalCategoryWarning();
  }

  function openToggleCategoryModal(button) {
    const modal = document.getElementById('toggleCategoryModal');
    if (!modal) {
      return;
    }

    const currentStatus = button.dataset.estado || 'activo';
    const action = currentStatus === 'activo' ? 'desactivar' : 'activar';
    const actionLabel = action === 'desactivar' ? 'Desactivar' : 'Activar';

    document.getElementById('toggleCategoryId').value = button.dataset.id || '';
    document.getElementById('toggleCategoryAction').value = action;
    document.getElementById('toggleCategoryTitle').textContent = `${actionLabel} categoria`;
    document.getElementById('toggleCategoryText').textContent = `${actionLabel} la categoria "${button.dataset.nombre || ''}"?`;
    const submitButton = document.getElementById('toggleCategorySubmit');
    submitButton.textContent = actionLabel;
    submitButton.classList.toggle('danger', action === 'desactivar');

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function openDeleteCategoryModal(button) {
    const modal = document.getElementById('deleteCategoryModal');
    if (!modal) {
      return;
    }

    document.getElementById('deleteCategoryId').value = button.dataset.id || '';
    document.getElementById('deleteCategoryText').textContent = `Eliminar la categoria "${button.dataset.nombre || ''}"? Solo se podra eliminar si no esta usada por productos.`;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function setSaleFormMode(mode, saleId = null) {
    const isEdit = mode === 'edit';
    saleForm.action = isEdit ? 'editar_venta.php' : 'guardar_venta.php';
    saleVentaId.value = isEdit ? saleId : '';
    saleFormTitle.textContent = isEdit ? `Editando venta #${saleId}` : 'Nueva venta';
    saleSubmitButton.textContent = isEdit ? 'Guardar cambios' : 'Registrar venta';
    cancelSaleEdit.hidden = !isEdit;
    saleSubmitConfirmed = false;
  }

  function resetSaleForm() {
    editingSaleId = null;
    editingSaleOriginalUnits = {};
    setSaleFormMode('new');
    saleCart = [];
    selectedSaleProduct = null;
    if (addSaleItemButton) addSaleItemButton.disabled = true;
    saleClienteId.value = '';
    saleClientSearch.value = '';
    ventaProductoId.value = '';
    ventaProductoSearch.value = '';
    document.getElementById('ventaTipo').value = 'unidad';
    document.getElementById('ventaCantidad').value = 1;
    document.getElementById('saleMethod').value = 'efectivo';
    saleForm.querySelector('[name="observacion"]').value = '';
    renderCart(saleCart, 'saleCartList', 'saleCartInputs');
    updateSaleTotal();
  }

  function setPurchaseFormMode(mode, purchaseId = null) {
    const isEdit = mode === 'edit';
    purchaseForm.action = isEdit ? 'editar_compra.php' : 'guardar_compra.php';
    purchaseCompraId.value = isEdit ? purchaseId : '';
    purchaseFormTitle.textContent = isEdit ? `Editando compra #${purchaseId}` : 'Nueva compra';
    purchaseSubmitButton.textContent = isEdit ? 'Guardar cambios' : 'Registrar compra';
    cancelPurchaseEdit.hidden = !isEdit;
  }

  function resetPurchaseForm() {
    purchaseEditInProgress = false;
    setPurchaseFormMode('new');
    purchaseCart = [];
    selectedPurchaseProduct = null;
    if (addPurchaseItemButton) addPurchaseItemButton.disabled = true;
    purchaseProviderId.value = '';
    purchaseProviderSearch.value = '';
    purchaseProductId.value = '';
    purchaseProductSearch.value = '';
    document.getElementById('purchaseType').value = 'unidad';
    document.getElementById('purchaseQuantity').value = 1;
    document.getElementById('purchasePrice').value = '0';
    document.getElementById('purchaseMethod').value = 'efectivo';
    purchaseForm.querySelector('[name="observacion"]').value = '';
    renderPurchaseCart();
    updatePurchaseTotal();
  }

  function startPurchaseEdit(purchaseId) {
    const purchase = purchases.find((item) => Number(item.id) === Number(purchaseId));
    const details = purchaseItemsById(purchaseId);
    if (!purchase || details.length === 0) {
      showClientMessage('No se pudo cargar el detalle de esta compra para editar.');
      return;
    }
    if (purchase.estado === 'anulada') {
      showClientMessage('Esta compra esta anulada y no se puede editar.');
      return;
    }

    const editCart = details
      .map((item) => {
        const product = products.find((productItem) => Number(productItem.id) === Number(item.producto_id)) || {
          id: Number(item.producto_id),
          nombre: item.producto,
          estado: item.producto_estado || 'activo',
          stock: Number(item.producto_stock || 0),
          pack_cantidad: Number(item.producto_pack_cantidad || 0),
          precio_compra: Number(item.producto_precio_compra || 0),
          precio_compra_pack: Number(item.producto_precio_compra_pack || 0),
          precio_venta: Number(item.producto_precio_venta || 0),
          precio_pack: Number(item.producto_precio_pack || 0)
        };

        return {
          product,
          type: item.tipo_compra,
          quantity: Number(item.cantidad || 0),
          units: Number(item.unidades_agregadas || 0),
          price: Number(item.precio_unitario || 0),
          subtotal: Number(item.subtotal || 0)
        };
      })
      .filter((item) => item.quantity > 0 && item.product.id > 0);

    if (editCart.length === 0) {
      showClientMessage('No se pudieron cargar los productos de esta compra para editar.');
      return;
    }

    purchaseEditInProgress = true;
    selectedPurchaseDetailId = null;
    purchaseDetailModal.classList.remove('open');
    purchaseDetailModal.setAttribute('aria-hidden', 'true');

    setPurchaseFormMode('edit', purchaseId);
    const provider = providers.find((item) => Number(item.id) === Number(purchase.proveedor_id));
    if (provider) {
      selectPurchaseProvider(provider);
    } else {
      purchaseProviderId.value = '';
      purchaseProviderSearch.value = purchase.proveedor || '';
    }

    document.getElementById('purchaseMethod').value = purchase.metodo || 'efectivo';
    purchaseForm.querySelector('[name="observacion"]').value = purchase.observacion || '';
    purchaseCart = editCart;
    lastPurchaseEditStartedAt = performance.now();

    selectedPurchaseProduct = null;
    if (addPurchaseItemButton) addPurchaseItemButton.disabled = true;
    purchaseProductId.value = '';
    purchaseProductSearch.value = '';
    document.getElementById('purchaseType').value = 'unidad';
    document.getElementById('purchaseQuantity').value = 1;
    document.getElementById('purchasePrice').value = '0';
    renderPurchaseCart();
    updatePurchaseTotal();
    showModule('compras');
  }

  function startSaleEdit(saleId) {
    const sale = sales.find((item) => Number(item.id) === Number(saleId));
    const details = saleItemsById(saleId);
    if (!sale || details.length === 0) {
      showClientMessage('No se pudo cargar el detalle de esta venta para editar.');
      return;
    }
    if (sale.estado === 'anulada') {
      showClientMessage('Esta venta esta anulada y no se puede editar.');
      return;
    }

    editingSaleId = Number(saleId);
    editingSaleOriginalUnits = {};
    details.forEach((item) => {
      const productId = Number(item.producto_id);
      editingSaleOriginalUnits[productId] = (editingSaleOriginalUnits[productId] || 0) + Number(item.unidades_descontadas || 0);
    });

    setSaleFormMode('edit', editingSaleId);
    const client = clients.find((item) => Number(item.id) === Number(sale.cliente_id))
      || allClients.find((item) => Number(item.id) === Number(sale.cliente_id));
    if (client) {
      selectSaleClient(client);
    } else {
      saleClienteId.value = '';
      saleClientSearch.value = sale.cliente || '';
    }

    document.getElementById('saleMethod').value = sale.metodo || 'efectivo';
    saleForm.querySelector('[name="observacion"]').value = sale.observacion || '';
    saleCart = details
      .map((item) => {
        const product = products.find((productItem) => Number(productItem.id) === Number(item.producto_id));
        if (!product) return null;
        const price = item.tipo_venta === 'pack'
          ? Number(product.precio_pack || 0)
          : (item.tipo_venta === 'promocion' ? Number(product.precio_promocion || 0) : Number(product.precio_venta || 0));

        return {
          product,
          type: item.tipo_venta,
          quantity: Number(item.cantidad || 0),
          units: Number(item.unidades_descontadas || 0),
          subtotal: price * Number(item.cantidad || 0)
        };
      })
      .filter(Boolean);

    selectedSaleProduct = null;
    if (addSaleItemButton) addSaleItemButton.disabled = true;
    ventaProductoId.value = '';
    ventaProductoSearch.value = '';
    document.getElementById('ventaTipo').value = 'unidad';
    document.getElementById('ventaCantidad').value = 1;
    renderCart(saleCart, 'saleCartList', 'saleCartInputs');
    updateSaleTotal();
    saleDetailModal.classList.remove('open');
    saleDetailModal.setAttribute('aria-hidden', 'true');
    showModule('ventas');
  }

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.modal-backdrop');
      if (!modal) {
        return;
      }
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    });
  });

  document.querySelectorAll('[data-edit-client]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById('editClientModal');
      const form = document.getElementById('editClientForm');
      if (!modal || !form) {
        return;
      }

      form.action = `editar_cliente.php?id=${encodeURIComponent(button.dataset.id || '')}`;
      document.getElementById('editClientName').value = button.dataset.nombre || '';
      document.getElementById('editClientPhone').value = button.dataset.telefono || '';
      document.getElementById('editClientEmail').value = button.dataset.email || '';
      document.getElementById('editClientDocument').value = button.dataset.documento || '';
      document.getElementById('editClientAddress').value = button.dataset.direccion || '';
      document.getElementById('editClientStatus').value = button.dataset.estado || 'activo';

      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('editClientName')?.focus();
    });
  });

  document.querySelectorAll('[data-toggle-client]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById('toggleClientModal');
      const currentStatus = button.dataset.estado || 'activo';
      const nextStatus = currentStatus === 'activo' ? 'inactivo' : 'activo';
      const actionLabel = nextStatus === 'inactivo' ? 'Desactivar' : 'Activar';
      if (!modal) {
        return;
      }

      document.getElementById('toggleClientId').value = button.dataset.id || '';
      document.getElementById('toggleClientStatus').value = nextStatus;
      document.getElementById('toggleClientTitle').textContent = `${actionLabel} cliente`;
      document.getElementById('toggleClientText').textContent = `${actionLabel} el cliente "${button.dataset.nombre || ''}"?`;
      const submitButton = document.getElementById('toggleClientSubmit');
      submitButton.textContent = actionLabel;
      submitButton.classList.toggle('danger', nextStatus === 'inactivo');

      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });

  document.querySelectorAll('[data-delete-client]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById('deleteClientModal');
      if (!modal) {
        return;
      }

      document.getElementById('deleteClientId').value = button.dataset.id || '';
      document.getElementById('deleteClientText').textContent = `Eliminar el cliente "${button.dataset.nombre || ''}"? Solo se podra eliminar si no esta asociado a reservas o ventas.`;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });

  document.querySelectorAll('[data-cash-detail]').forEach((row) => {
    row.addEventListener('click', () => {
      const modal = document.getElementById(`cashDetailModal-${row.dataset.cashDetail}`);
      if (!modal) {
        return;
      }

      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });

  document.querySelectorAll('[data-edit-product]').forEach((button) => {
    button.addEventListener('click', () => openProductModal(button.dataset.editProduct));
  });

  document.getElementById('openProductCreateModal')?.addEventListener('click', openCreateProductModal);

  document.querySelectorAll('[data-toggle-product]').forEach((button) => {
    button.addEventListener('click', () => openToggleProductModal(button));
  });

  document.querySelectorAll('[data-delete-product]').forEach((button) => {
    button.addEventListener('click', () => openDeleteProductModal(button));
  });

  document.querySelectorAll('[data-edit-court]').forEach((button) => {
    button.addEventListener('click', () => openCourtModal(button.dataset.editCourt));
  });

  document.getElementById('openCourtCreateModal')?.addEventListener('click', openCreateCourtModal);

  document.querySelectorAll('[data-delete-court]').forEach((button) => {
    button.addEventListener('click', () => openDeleteCourtModal(button));
  });

  document.querySelectorAll('[data-edit-provider]').forEach((button) => {
    button.addEventListener('click', () => openProviderModal(button.dataset.editProvider));
  });

  document.querySelectorAll('[data-toggle-provider]').forEach((button) => {
    button.addEventListener('click', () => openToggleProviderModal(button));
  });

  document.querySelectorAll('[data-delete-provider]').forEach((button) => {
    button.addEventListener('click', () => openDeleteProviderModal(button.dataset.deleteProvider));
  });

  document.querySelectorAll('[data-digits-only]').forEach((input) => {
    input.addEventListener('input', () => {
      const maxDigits = Number(input.dataset.maxDigits || 0);
      const digits = input.value.replace(/\D/g, '');
      input.value = maxDigits > 0 ? digits.slice(0, maxDigits) : digits;
    });
  });

  document.querySelectorAll('[data-ruc-input]').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/[^0-9-]/g, '');
    });
  });

  clientListSearch?.addEventListener('input', () => {
    simpleListPages.clients = 1;
    filterClients();
  });
  categoryListSearch?.addEventListener('input', () => {
    simpleListPages.categories = 1;
    filterCategories();
  });
  providerSearch?.addEventListener('input', () => {
    simpleListPages.providers = 1;
    filterProviders();
  });
  filterClients();
  filterCategories();
  filterProviders();

  document.querySelectorAll('[data-edit-category]').forEach((button) => {
    button.addEventListener('click', () => openCategoryModal(button.dataset.editCategory, button.dataset.categoryName, 'edit'));
  });

  document.getElementById('openCategoryListModal')?.addEventListener('click', () => {
    openCategoryModal('', '', 'create');
  });

  document.querySelectorAll('[data-toggle-category]').forEach((button) => {
    button.addEventListener('click', () => openToggleCategoryModal(button));
  });

  document.querySelectorAll('[data-delete-category]').forEach((button) => {
    button.addEventListener('click', () => openDeleteCategoryModal(button));
  });

  cancelSaleEdit?.addEventListener('click', resetSaleForm);
  cancelPurchaseEdit?.addEventListener('click', resetPurchaseForm);

  document.querySelectorAll('.money-input').forEach((input) => {
    if (input.dataset.allowEmptyMoney !== '1' || input.value.trim() !== '') {
      formatMoneyInput(input);
    }
    input.addEventListener('input', () => formatMoneyInput(input));
    input.addEventListener('blur', () => {
      if (input.value.trim() === '' && input.dataset.allowEmptyMoney !== '1') {
        input.value = '0';
      }
    });
  });

  ['editCanchaId', 'editFecha', 'editHoraInicioHour', 'editHoraFinHour'].forEach((id) => {
    document.getElementById(id)?.addEventListener('change', () => {
      const startBefore = document.getElementById('editHoraInicioHour')?.value || '';
      const endBefore = document.getElementById('editHoraFinHour')?.value || '';

      updateEditReservationPrice();
      validateEditReservationSchedule(true);

      const startAfter = document.getElementById('editHoraInicioHour')?.value || '';
      const endAfter = document.getElementById('editHoraFinHour')?.value || '';
      const scheduleChangedAutomatically = id === 'editFecha'
        && (startAfter !== startBefore || endAfter !== endBefore);

      if (scheduleChangedAutomatically) {
        showClientMessage('Ya existe otra reserva para esa cancha en la fecha y hora seleccionadas. Se cambio automaticamente al primer horario disponible.');
      }
    });
  });

  document.getElementById('modalMontoPago')?.addEventListener('input', (event) => {
    const amount = Number(moneyDigits(event.currentTarget.value) || 0);
    event.currentTarget.setCustomValidity(amount > 0 && amount < 20000 ? 'El abono minimo es 20.000.' : '');
    document.getElementById('modalReservaEstado').value = amount > 0
      ? 'confirmado'
      : 'reservado';
  });

  document.getElementById('reservationForm')?.addEventListener('submit', (event) => {
    const clientInput = document.getElementById('clientSearch');
    const clientId = document.getElementById('modalClienteId')?.value || '';
    const paymentInput = document.getElementById('modalMontoPago');
    const paymentAmount = Number(moneyDigits(paymentInput?.value || 0) || 0);

    clientInput?.setCustomValidity(clientId ? '' : 'Selecciona un cliente de la lista.');
    paymentInput?.setCustomValidity(paymentAmount > 0 && paymentAmount < 20000 ? 'El abono minimo es 20.000.' : '');

    if (!clientId) {
      event.preventDefault();
      clientInput?.reportValidity();
      return;
    }

    if (paymentAmount > 0 && paymentAmount < 20000) {
      event.preventDefault();
      paymentInput?.reportValidity();
    }
  });

  document.getElementById('reservationEditForm')?.addEventListener('submit', (event) => {
    syncEditHours();
    const form = event.currentTarget;
    const status = document.getElementById('editEstado')?.value || '';
    const balance = Number(form.dataset.saldo || 0);

    if (!document.getElementById('editClienteId')?.value) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const searchInput = document.getElementById('editClientSearch');
      searchInput?.setCustomValidity('Selecciona un cliente de la lista.');
      searchInput?.reportValidity();
      return;
    }

    if (!validateEditReservationSchedule(true)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }

    if (status === 'finalizado' && balance > 0) {
      event.preventDefault();
      event.stopImmediatePropagation();
      document.getElementById('editEstado').value = form.dataset.estadoOriginal || 'confirmado';
      showClientMessage(`No se puede finalizar la reserva porque todavia tiene saldo pendiente de ${money(balance)}.`);
    }
  });

  document.getElementById('modalComprobantePago')?.addEventListener('change', (event) => {
    if (event.currentTarget.files.length > 0) {
      document.getElementById('modalReservaMetodo').value = 'transferencia';
    }
  });

  document.getElementById('modalHoraInicioVisible')?.addEventListener('change', syncReservationTimesFromInputs);
  document.getElementById('modalHoraFinVisible')?.addEventListener('change', syncReservationTimesFromInputs);

  document.getElementById('reservationPaymentForm')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    const updateMethodOnly = form.querySelector('[name="actualizar_metodo_pago"]')?.value === '1';
    if (updateMethodOnly) {
      return;
    }

    const amount = Number(moneyDigits(form.querySelector('[name="monto"]')?.value || 0));
    const balance = Number(form.dataset.saldo || 0);

    if (amount <= 0) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showClientMessage('Ingresa un monto valido.');
      return;
    }

    if (balance <= 0) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showClientMessage('Esta reserva ya esta pagada completamente. No se puede registrar otro pago.');
      return;
    }

    if (amount > balance) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showClientMessage('El monto supera el saldo pendiente de la reserva.');
    }
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      if (form.id === 'cashCloseForm' && !cashCloseConfirmed) {
        return;
      }

      prepareMoneyInputs(form);
    });
  });

  document.addEventListener('input', (event) => {
    const field = event.target;
    if (!shouldAutoCapitalize(field)) {
      return;
    }

    const nextValue = capitalizeFirstLetter(field.value);
    if (nextValue === field.value) {
      return;
    }

    const selectionStart = field.selectionStart;
    const selectionEnd = field.selectionEnd;
    field.value = nextValue;
    if (selectionStart !== null && selectionEnd !== null) {
      field.setSelectionRange(selectionStart, selectionEnd);
    }
  });

  document.querySelectorAll('input, textarea').forEach((field) => {
    field.setAttribute('autocapitalize', shouldAutoCapitalize(field) ? 'sentences' : 'none');
  });

  clientSearch?.addEventListener('input', () => {
    modalClienteId.value = '';
    clientSearch.setCustomValidity(clientSearch.value.trim() === '' ? '' : 'Selecciona un cliente de la lista.');
    renderClientSuggestions(clientSearch.value);
  });

  clientSearch?.addEventListener('focus', () => renderClientSuggestions(clientSearch.value));

  const editClientSearch = document.getElementById('editClientSearch');
  const editClienteId = document.getElementById('editClienteId');
  const editClientSuggestions = document.getElementById('editClientSuggestions');
  editClientSearch?.addEventListener('input', () => {
    editClienteId.value = '';
    editClientSearch.setCustomValidity(editClientSearch.value.trim() === '' ? '' : 'Selecciona un cliente de la lista.');
    renderClientSuggestions(editClientSearch.value, editClientSuggestions, selectEditReservationClient);
  });
  editClientSearch?.addEventListener('focus', () => {
    if (editClienteId.value) return;
    renderClientSuggestions(editClientSearch.value, editClientSuggestions, selectEditReservationClient);
  });

  saleClientSearch?.addEventListener('input', () => {
    saleClienteId.value = '';
    renderClientSuggestions(saleClientSearch.value, saleClientSuggestions, selectSaleClient, true);
  });

  saleClientSearch?.addEventListener('focus', () => renderClientSuggestions(saleClientSearch.value, saleClientSuggestions, selectSaleClient, true));

  purchaseProviderSearch?.addEventListener('input', () => {
    purchaseProviderId.value = '';
    renderProviderSuggestions(purchaseProviderSearch.value);
  });

  purchaseProviderSearch?.addEventListener('focus', () => renderProviderSuggestions(purchaseProviderSearch.value));

  productProviderSearch?.addEventListener('input', () => {
    productProviderId.value = '';
    renderProviderSuggestions(productProviderSearch.value, productProviderSuggestions, selectProductProvider);
  });

  productProviderSearch?.addEventListener('focus', () => renderProviderSuggestions(productProviderSearch.value, productProviderSuggestions, selectProductProvider));

  quickProductProviderSearch?.addEventListener('input', () => {
    quickProductProviderId.value = '';
    renderProviderSuggestions(quickProductProviderSearch.value, quickProductProviderSuggestions, selectQuickProductProvider);
  });

  quickProductProviderSearch?.addEventListener('focus', () => renderProviderSuggestions(quickProductProviderSearch.value, quickProductProviderSuggestions, selectQuickProductProvider));

  editProductoProveedorSearch?.addEventListener('input', () => {
    editProductoProveedorId.value = '';
    renderProviderSuggestions(editProductoProveedorSearch.value, editProductoProveedorSuggestions, selectEditProductProvider);
  });

  editProductoProveedorSearch?.addEventListener('focus', () => renderProviderSuggestions(editProductoProveedorSearch.value, editProductoProveedorSuggestions, selectEditProductProvider));

  ventaProductoSearch?.addEventListener('input', () => {
    selectedSaleProduct = null;
    if (addSaleItemButton) addSaleItemButton.disabled = true;
    ventaProductoId.value = '';
    renderProductSuggestions(ventaProductoSearch.value);
    updateSaleTotal();
  });

  ventaProductoSearch?.addEventListener('focus', () => renderProductSuggestions(ventaProductoSearch.value));

  document.getElementById('openSaleProductSearch')?.addEventListener('click', () => {
    if (saleProductSearchModalInput) {
      saleProductSearchModalInput.value = ventaProductoSearch?.value || '';
    }
    renderSaleProductSearchModal();
    saleProductSearchModal?.classList.add('open');
    saleProductSearchModal?.setAttribute('aria-hidden', 'false');
    setTimeout(() => saleProductSearchModalInput?.focus(), 0);
  });

  saleProductSearchModalInput?.addEventListener('input', renderSaleProductSearchModal);

  ventaProductoSearch?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    const exactBarcode = findProductByBarcode(ventaProductoSearch.value);
    if (exactBarcode) {
      event.preventDefault();
      selectSaleProduct(exactBarcode);
      document.getElementById('ventaCantidad')?.focus();
    }
  });

  reservaVentaProductoSearch?.addEventListener('input', () => {
    selectedReservationSaleProduct = null;
    reservaVentaProductoId.value = '';
    renderProductSuggestions(reservaVentaProductoSearch.value, 'reservation');
    updateReservationSaleTotal();
  });

  reservaVentaProductoSearch?.addEventListener('focus', () => renderProductSuggestions(reservaVentaProductoSearch.value, 'reservation'));

  purchaseProductSearch?.addEventListener('input', () => {
    selectedPurchaseProduct = null;
    if (addPurchaseItemButton) addPurchaseItemButton.disabled = true;
    purchaseProductId.value = '';
    renderPurchaseProductSuggestions(purchaseProductSearch.value);
    updatePurchasePriceForType();
    updatePurchaseTotal();
  });

  purchaseProductSearch?.addEventListener('focus', () => renderPurchaseProductSuggestions(purchaseProductSearch.value));

  purchaseProductSearch?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const exactBarcode = findProductByBarcode(purchaseProductSearch.value);
    if (exactBarcode) {
      selectPurchaseProduct(exactBarcode);
    }
    addPurchaseItem();
  });

  ['purchaseType', 'purchaseQuantity', 'purchasePrice', 'purchaseMethod'].forEach((id) => {
    document.getElementById(id)?.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      addPurchaseItem();
    });
  });

  reservaVentaProductoSearch?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    const exactBarcode = findProductByBarcode(reservaVentaProductoSearch.value);
    if (exactBarcode) {
      event.preventDefault();
      selectSaleProduct(exactBarcode, 'reservation');
      document.getElementById('reservaVentaCantidad')?.focus();
    }
  });

  document.getElementById('openQuickClient')?.addEventListener('click', () => {
    quickClientTarget = 'reservation';
    clientSuggestions.innerHTML = '';
    clientSuggestions.classList.remove('open');
    quickClientModal.classList.add('open');
    quickClientModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickClientForm input[name="nombre"]').focus();
  });

  document.getElementById('openEditQuickClient')?.addEventListener('click', () => {
    quickClientTarget = 'editReservation';
    document.getElementById('quickClientForm')?.reset();
    document.getElementById('quickClientDuplicateName')?.classList.remove('show');
    editClientSuggestions.innerHTML = '';
    editClientSuggestions.classList.remove('open');
    quickClientModal.classList.add('open');
    quickClientModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickClientForm input[name="nombre"]')?.focus();
  });

  document.getElementById('openSaleQuickClient')?.addEventListener('click', () => {
    quickClientTarget = 'sale';
    saleClientSuggestions.innerHTML = '';
    saleClientSuggestions.classList.remove('open');
    quickClientModal.classList.add('open');
    quickClientModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickClientForm input[name="nombre"]').focus();
  });

  document.getElementById('openClientListQuickClient')?.addEventListener('click', () => {
    quickClientTarget = 'list';
    document.getElementById('quickClientForm')?.reset();
    document.getElementById('quickClientDuplicateName')?.classList.remove('show');
    quickClientModal.classList.add('open');
    quickClientModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickClientForm input[name="nombre"]')?.focus();
  });

  function providerSearchForTarget(target) {
    if (target === 'product') return productProviderSearch;
    if (target === 'quickProduct') return quickProductProviderSearch;
    if (target === 'editProduct') return editProductoProveedorSearch;
    return purchaseProviderSearch;
  }

  function openQuickProviderModal(target = 'purchase') {
    quickProviderTarget = target;
    purchaseProviderSuggestions.innerHTML = '';
    purchaseProviderSuggestions.classList.remove('open');
    productProviderSuggestions?.classList.remove('open');
    quickProductProviderSuggestions?.classList.remove('open');
    editProductoProveedorSuggestions?.classList.remove('open');
    const targetSearch = providerSearchForTarget(target);
    document.getElementById('quickProviderName').value = capitalizeFirstLetter(targetSearch?.value.trim() || '');
    quickProviderModal.classList.add('open');
    quickProviderModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickProviderForm input[name="nombre"]').focus();
  }

  document.getElementById('openProviderListQuickProvider')?.addEventListener('click', () => {
    quickProviderTarget = 'list';
    document.getElementById('quickProviderForm')?.reset();
    quickProviderModal.classList.add('open');
    quickProviderModal.setAttribute('aria-hidden', 'false');
    document.querySelector('#quickProviderForm input[name="nombre"]')?.focus();
  });

  document.getElementById('openQuickProvider')?.addEventListener('click', () => {
    openQuickProviderModal('purchase');
  });

  document.querySelectorAll('[data-open-quick-provider]').forEach((button) => {
    button.addEventListener('click', () => openQuickProviderModal(button.dataset.openQuickProvider || 'purchase'));
  });

  function purchaseQuickProductStock() {
    const quantity = Math.max(1, Number(document.getElementById('purchaseQuantity')?.value || 1));
    const type = document.getElementById('purchaseType')?.value || 'unidad';
    const packQuantity = Math.max(0, Number(quickProductPackQuantity?.value || 0));
    return type === 'pack' && packQuantity > 0 ? quantity * packQuantity : quantity;
  }

  function syncQuickProductFromPurchase() {
    if (!quickProductOpenedFromPurchase) {
      return;
    }

    if (quickProductStock) {
      quickProductStock.value = String(purchaseQuickProductStock());
    }
  }

  function applyQuickProductStockToPurchaseQuantity(stockInicial) {
    const quantityInput = document.getElementById('purchaseQuantity');
    if (!quantityInput) {
      return;
    }

    quantityInput.value = String(Math.max(1, Number(stockInicial || 1)));
    updatePurchaseTotal();
  }

  document.getElementById('openQuickProduct')?.addEventListener('click', () => {
    purchaseProductSuggestions.innerHTML = '';
    purchaseProductSuggestions.classList.remove('open');
    quickProductOpenedFromPurchase = true;
    quickProductName.value = capitalizeFirstLetter(purchaseProductSearch.value.trim());
    quickProductName.setCustomValidity('');
    quickProductCompra.value = document.getElementById('purchasePrice')?.value || '0';
    const purchaseProvider = providers.find((provider) => Number(provider.id) === Number(purchaseProviderId?.value || 0));
    if (purchaseProvider) {
      selectQuickProductProvider(purchaseProvider);
    } else {
      quickProductProviderId.value = '';
      quickProductProviderSearch.value = '';
    }
    syncQuickProductFromPurchase();
    updateProductDuplicateWarning('quickProductName', 'quickProductDuplicateName');
    updateProductPriceWarnings(document.getElementById('quickProductForm'));
    quickProductModal.classList.add('open');
    quickProductModal.setAttribute('aria-hidden', 'false');
    quickProductName.focus();
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.client-picker')) {
      clientSuggestions.innerHTML = '';
      clientSuggestions.classList.remove('open');
      saleClientSuggestions.innerHTML = '';
      saleClientSuggestions.classList.remove('open');
      purchaseProviderSuggestions.innerHTML = '';
      purchaseProviderSuggestions.classList.remove('open');
      productProviderSuggestions?.replaceChildren();
      productProviderSuggestions?.classList.remove('open');
      quickProductProviderSuggestions.innerHTML = '';
      quickProductProviderSuggestions.classList.remove('open');
      editProductoProveedorSuggestions.innerHTML = '';
      editProductoProveedorSuggestions.classList.remove('open');
    }

    if (!event.target.closest('.product-picker')) {
      productSuggestions.innerHTML = '';
      productSuggestions.classList.remove('open');
      reservationProductSuggestions.innerHTML = '';
      reservationProductSuggestions.classList.remove('open');
      purchaseProductSuggestions.innerHTML = '';
      purchaseProductSuggestions.classList.remove('open');
    }

    if (!event.target.closest('.category-picker')) {
      document.querySelectorAll('[data-category-suggestions-for]').forEach((suggestions) => {
        suggestions.innerHTML = '';
        suggestions.classList.remove('open');
      });
    }
  });

  document.querySelectorAll('.category-picker input').forEach((input) => {
    input.addEventListener('input', () => renderCategorySuggestions(input.id));
    input.addEventListener('focus', () => renderCategorySuggestions(input.id));
  });

  document.querySelectorAll('[data-add-category]').forEach((button) => {
    button.addEventListener('click', () => addCategoryFromInput(button.dataset.addCategory));
  });

  document.getElementById('editCategoriaNombre')?.addEventListener('input', updateModalCategoryWarning);

  quickProductName?.addEventListener('input', () => updateProductDuplicateWarning('quickProductName', 'quickProductDuplicateName'));
  quickProductName?.addEventListener('blur', () => updateProductDuplicateWarning('quickProductName', 'quickProductDuplicateName'));
  document.getElementById('editProductoNombre')?.addEventListener('input', () => {
    updateProductDuplicateWarning('editProductoNombre', 'editProductDuplicateName', document.getElementById('productForm')?.dataset.editProductId || '');
  });
  document.getElementById('editProductoNombre')?.addEventListener('blur', () => {
    updateProductDuplicateWarning('editProductoNombre', 'editProductDuplicateName', document.getElementById('productForm')?.dataset.editProductId || '');
  });

  document.querySelectorAll('#productForm [name="categoria"], #productForm .money-input, #productForm [name="pack_cantidad"], #productForm [name="promocion_cantidad"], #quickProductForm [name="categoria"], #quickProductForm .money-input, #quickProductForm [name="pack_cantidad"], #quickProductForm [name="promocion_cantidad"]').forEach((input) => {
    input.addEventListener('input', () => input.setCustomValidity(''));
    input.addEventListener('blur', () => validateProductRequiredFields(input.closest('form')));
  });

  document.querySelectorAll('#productForm [name="pack_cantidad"], #productForm [name="precio_compra_pack"], #productForm [name="precio_pack"], #quickProductForm [name="pack_cantidad"], #quickProductForm [name="precio_compra_pack"], #quickProductForm [name="precio_pack"]').forEach((input) => {
    input.addEventListener('input', () => syncProductUnitPricesFromPack(input.closest('form')));
    input.addEventListener('blur', () => syncProductUnitPricesFromPack(input.closest('form')));
  });

  document.querySelectorAll('#productForm [name="pack_cantidad"], #productForm [name="precio_compra_pack"], #productForm [name="precio_pack"], #productForm [name="precio_compra"], #productForm [name="precio_venta"], #productForm [name="promocion_cantidad"], #productForm [name="precio_promocion"], #quickProductForm [name="pack_cantidad"], #quickProductForm [name="precio_compra_pack"], #quickProductForm [name="precio_pack"], #quickProductForm [name="precio_compra"], #quickProductForm [name="precio_venta"], #quickProductForm [name="promocion_cantidad"], #quickProductForm [name="precio_promocion"]').forEach((input) => {
    input.addEventListener('input', () => updateProductPriceWarnings(input.closest('form')));
    input.addEventListener('blur', () => updateProductPriceWarnings(input.closest('form')));
  });

  document.getElementById('productForm')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    updateProductDuplicateWarning('editProductoNombre', 'editProductDuplicateName', event.currentTarget.dataset.editProductId || '');
    syncProductUnitPricesFromPack(form);
    updateProductPriceWarnings(form);
    const requiredFieldsOk = validateProductRequiredFields(form);
    if (hasProductDuplicateWarning('editProductDuplicateName') || !requiredFieldsOk) {
      event.preventDefault();
      const input = document.getElementById('editProductoNombre');
      if (hasProductDuplicateWarning('editProductDuplicateName')) {
        input?.focus();
        input?.reportValidity();
      } else {
        form.reportValidity();
      }
    }
  });

  document.getElementById('categoryForm')?.addEventListener('submit', async (event) => {
    updateModalCategoryWarning();
    if (document.getElementById('categoryDuplicateWarning')?.classList.contains('show')) {
      event.preventDefault();
      return;
    }

    const form = event.currentTarget;
    const isCreate = document.getElementById('editCategoriaAccion')?.value === 'crear';
    const targetInputId = document.getElementById('categoryTargetInput')?.value || '';
    if (!isCreate || targetInputId === '') {
      return;
    }

    event.preventDefault();
    const formData = new FormData(form);
    formData.append('ajax', '1');

    const response = await fetch(form.action, {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (!data.ok) {
      setModalCategoryWarning(data.error || 'No se pudo guardar la categoria.');
      return;
    }

    const categoryName = data.categoria?.nombre || formData.get('nombre');
    if (!productCategories.some((category) => normalizeCategory(category) === normalizeCategory(categoryName))) {
      productCategories.push(categoryName);
      productCategories.sort((a, b) => a.localeCompare(b, 'es-PY'));
    }

    const targetInput = categoryInput(targetInputId);
    if (targetInput) {
      targetInput.value = categoryName;
      targetInput.focus();
    }

    categoryModal.classList.remove('open');
    categoryModal.setAttribute('aria-hidden', 'true');
  });

  document.getElementById('quickClientName')?.addEventListener('input', (event) => {
    const warning = document.getElementById('quickClientDuplicateName');
    const name = normalizeName(event.target.value);

    if (name.length < 5) {
      warning.textContent = '';
      warning.classList.remove('show');
      return;
    }

    const duplicated = allClients.find((client) => normalizeName(client.nombre) === name);

    if (duplicated) {
      warning.textContent = `Ya existe un cliente con ese nombre: ${duplicated.nombre}${duplicated.telefono ? ' - ' + duplicated.telefono : ''}`;
      warning.classList.add('show');
    } else {
      warning.textContent = '';
      warning.classList.remove('show');
    }
  });

  document.querySelectorAll('[data-close-quick-client]').forEach((button) => {
    button.addEventListener('click', () => {
      quickClientModal.classList.remove('open');
      quickClientModal.setAttribute('aria-hidden', 'true');
    });
  });

  document.querySelectorAll('[data-close-quick-provider]').forEach((button) => {
    button.addEventListener('click', () => {
      quickProviderModal.classList.remove('open');
      quickProviderModal.setAttribute('aria-hidden', 'true');
    });
  });

  document.querySelectorAll('[data-close-quick-product]').forEach((button) => {
    button.addEventListener('click', () => {
      quickProductOpenedFromPurchase = false;
      quickProductModal.classList.remove('open');
      quickProductModal.setAttribute('aria-hidden', 'true');
    });
  });

  document.getElementById('quickClientForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const response = await fetch('guardar_cliente_rapido.php', {
      method: 'POST',
      body: new FormData(form)
    });
    const data = await response.json();

    if (!data.ok) {
      showClientMessage(data.error || 'No se pudo guardar el cliente.');
      return;
    }

    if (!clients.some((client) => Number(client.id) === Number(data.cliente.id))) {
      clients.push(data.cliente);
    }

    if (!allClients.some((client) => Number(client.id) === Number(data.cliente.id))) {
      allClients.push(data.cliente);
    }

    if (quickClientTarget === 'list') {
      if (data.existe && data.mensaje) {
        showClientMessage(data.mensaje);
      } else {
        window.location.href = 'index.php#clientes';
        window.location.reload();
      }
      quickClientTarget = 'reservation';
      form.reset();
      quickClientModal.classList.remove('open');
      quickClientModal.setAttribute('aria-hidden', 'true');
      return;
    }

    if (quickClientTarget === 'sale') {
      selectSaleClient(data.cliente);
    } else if (quickClientTarget === 'editReservation') {
      selectEditReservationClient(data.cliente);
    } else {
      selectClient(data.cliente);
    }
    if (data.existe && data.mensaje) {
      showClientMessage(data.mensaje);
    }
    form.reset();
    quickClientModal.classList.remove('open');
    quickClientModal.setAttribute('aria-hidden', 'true');
  });

  document.getElementById('quickProviderForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const response = await fetch('guardar_proveedor_rapido.php', {
      method: 'POST',
      body: new FormData(form)
    });
    const data = await response.json();

    if (!data.ok) {
      showClientMessage(data.error || 'No se pudo guardar el proveedor.');
      return;
    }

    if (!providers.some((provider) => Number(provider.id) === Number(data.proveedor.id))) {
      providers.push(data.proveedor);
    }
    if (quickProviderTarget === 'list') {
      if (data.existe && data.mensaje) {
        showClientMessage(data.mensaje);
      } else {
        window.location.href = 'index.php#proveedores';
        window.location.reload();
      }
      quickProviderTarget = 'purchase';
      form.reset();
      quickProviderModal.classList.remove('open');
      quickProviderModal.setAttribute('aria-hidden', 'true');
      return;
    }

    if (quickProviderTarget === 'product') {
      selectProductProvider(data.proveedor);
    } else if (quickProviderTarget === 'quickProduct') {
      selectQuickProductProvider(data.proveedor);
    } else if (quickProviderTarget === 'editProduct') {
      selectEditProductProvider(data.proveedor);
    } else {
      selectPurchaseProvider(data.proveedor);
    }
    if (data.existe && data.mensaje) {
      showClientMessage(data.mensaje);
    }
    quickProviderTarget = 'purchase';
    form.reset();
    quickProviderModal.classList.remove('open');
    quickProviderModal.setAttribute('aria-hidden', 'true');
  });

  document.getElementById('quickProductForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (form.dataset.submitting === '1') {
      return;
    }

    updateProductDuplicateWarning('quickProductName', 'quickProductDuplicateName');
    syncProductUnitPricesFromPack(form);
    updateProductPriceWarnings(form);
    const requiredFieldsOk = validateProductRequiredFields(form);
    if (hasProductDuplicateWarning('quickProductDuplicateName') || !requiredFieldsOk) {
      quickProductName?.focus();
      if (hasProductDuplicateWarning('quickProductDuplicateName')) {
        quickProductName?.reportValidity();
      } else {
        form.reportValidity();
      }
      return;
    }

    let quickPurchaseStock = 0;
    if (quickProductOpenedFromPurchase) {
      const nombre = String(form.querySelector('[name="nombre"]')?.value || '').trim();
      const precioCompra = Number(moneyDigits(form.querySelector('[name="precio_compra"]')?.value || '0') || 0);
      const stockInicial = Number(form.querySelector('[name="stock"]')?.value || 0);
      quickPurchaseStock = stockInicial;

      if (nombre === '') {
        showClientMessage('Ingresa el nombre del producto.');
        return;
      }
      if (precioCompra <= 0) {
        showClientMessage('Ingresa el precio de compra del producto.');
        return;
      }
      if (stockInicial <= 0) {
        showClientMessage('Ingresa una cantidad de stock valida para el producto.');
        return;
      }
    }

    prepareMoneyInputs(form);
    form.dataset.submitting = '1';
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent;
      submitButton.textContent = 'Procesando...';
    }

    const productFormData = new FormData(form);
    if (quickProductOpenedFromPurchase) {
      productFormData.append('origen', 'compra');
    }
    let data;
    try {
      const response = await fetch('guardar_producto_rapido.php', {
        method: 'POST',
        body: productFormData
      });
      data = await response.json();
    } catch (error) {
      data = { ok: false, error: 'No se pudo guardar el producto.' };
    }

    if (!data.ok) {
      form.dataset.submitting = '';
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = submitButton.dataset.originalText || 'Guardar producto';
      }
      showClientMessage(data.error || 'No se pudo guardar el producto.');
      return;
    }

    const stockLoadedFromQuickPurchase = quickProductOpenedFromPurchase && !data.existe;
    if (stockLoadedFromQuickPurchase) {
      data.producto.stock_ya_cargado = 1;
    }

    const existingIndex = products.findIndex((product) => Number(product.id) === Number(data.producto.id));
    if (existingIndex >= 0) {
      products[existingIndex] = data.producto;
    } else {
      products.push(data.producto);
    }
    renderProductInventorySearch(true);
    selectPurchaseProduct(data.producto);
    if (stockLoadedFromQuickPurchase) {
      applyQuickProductStockToPurchaseQuantity(quickPurchaseStock || data.producto.stock);
    }
    if (data.existe && data.mensaje) {
      showClientMessage(data.mensaje);
    }
    quickProductOpenedFromPurchase = false;
    form.dataset.submitting = '';
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = submitButton.dataset.originalText || 'Guardar producto';
    }
    form.reset();
    form.querySelectorAll('.money-input').forEach((input) => {
      input.value = '0';
    });
    quickProductModal.classList.remove('open');
    quickProductModal.setAttribute('aria-hidden', 'true');
  });

  document.getElementById('prevWeek')?.addEventListener('click', () => {
    const previous = addDays(weekStart, -calendarStepDays());
    weekStart = previous < today ? new Date(today) : previous;
    renderCalendar();
  });

  document.getElementById('nextWeek')?.addEventListener('click', () => {
    weekStart = addDays(weekStart, calendarStepDays());
    renderCalendar();
  });

  document.getElementById('todayWeek')?.addEventListener('click', () => {
    weekStart = new Date(today);
    renderCalendar();
  });

  window.addEventListener('resize', () => {
    renderCalendar();
  });

  function updateSaleTotal() {
    const saleType = document.getElementById('ventaTipo');
    const quantity = document.getElementById('ventaCantidad');
    const total = document.getElementById('ventaTotal');

    if (!saleType || !quantity || !total) {
      return;
    }

    const unitPrice = Number(selectedSaleProduct?.precio_venta || 0);
    const packPrice = Number(selectedSaleProduct?.precio_pack || 0);
    const packQuantity = Number(selectedSaleProduct?.pack_cantidad || 0);
    const promotionPrice = Number(selectedSaleProduct?.precio_promocion || 0);
    const promotionQuantity = Number(selectedSaleProduct?.promocion_cantidad || 0);
    const price = saleType.value === 'pack'
      ? packPrice
      : (saleType.value === 'promocion' ? promotionPrice : unitPrice);
    const amount = clampSaleQuantity('sale');
    total.value = money(price * amount);
    const invalidPack = saleType.value === 'pack' && (packPrice <= 0 || packQuantity <= 0);
    const invalidPromotion = saleType.value === 'promocion' && (promotionPrice <= 0 || promotionQuantity <= 0);
    saleType.setCustomValidity(selectedSaleProduct && (invalidPack || invalidPromotion)
      ? (invalidPromotion ? 'Este producto no tiene promocion configurada.' : 'Este producto no tiene pack configurado.')
      : '');
  }

  function updatePurchaseTotal() {
    const quantity = document.getElementById('purchaseQuantity');
    const price = document.getElementById('purchasePrice');
    const total = document.getElementById('purchaseTotal');
    if (!quantity || !price || !total) return;

    total.value = money(Number(quantity.value || 0) * Number(moneyDigits(price.value) || 0));
  }

  function updatePurchasePriceForType() {
    const typeInput = document.getElementById('purchaseType');
    const priceInput = document.getElementById('purchasePrice');
    const packOption = document.getElementById('purchaseTypePack');
    if (!typeInput || !priceInput) {
      return;
    }

    if (!selectedPurchaseProduct) {
      if (packOption) {
        packOption.disabled = false;
        packOption.textContent = 'Pack';
      }
      return;
    }

    const unitCost = Number(selectedPurchaseProduct.precio_compra || 0);
    const packQuantity = Number(selectedPurchaseProduct.pack_cantidad || 0);
    const packCost = Number(selectedPurchaseProduct.precio_compra_pack || 0);
    const packAllowed = packQuantity > 0;
    if (packOption) {
      packOption.disabled = !packAllowed;
      packOption.textContent = packAllowed ? 'Pack' : 'Pack (no disponible)';
    }
    if (!packAllowed && typeInput.value === 'pack') {
      typeInput.value = 'unidad';
    }

    const nextPrice = typeInput.value === 'pack'
      ? (packCost > 0 ? packCost : unitCost * packQuantity)
      : unitCost;
    priceInput.value = money(nextPrice);
  }

  function addPurchaseItem() {
    // A successful click clears the selection and disables the button
    // synchronously, so a second event from a rapid double click is ignored.
    const now = performance.now();
    if (addPurchaseItemButton?.disabled || now - lastPurchaseItemAddedAt < 700) return;

    const type = document.getElementById('purchaseType')?.value || 'unidad';
    const quantityInput = document.getElementById('purchaseQuantity');
    const priceInput = document.getElementById('purchasePrice');
    const quantity = Number(quantityInput?.value || 0);
    const price = Number(moneyDigits(priceInput?.value) || 0);

    if (!selectedPurchaseProduct || quantity <= 0) {
      showClientMessage('Selecciona un producto e ingresa una cantidad valida.');
      return;
    }
    if (price <= 0) {
      showClientMessage('Ingresa el precio de compra.');
      return;
    }
    if (type === 'pack' && Number(selectedPurchaseProduct.pack_cantidad || 0) <= 0) {
      showClientMessage('Este producto no tiene cantidad por pack configurada.');
      return;
    }

    const stockAlreadyLoaded = Number(selectedPurchaseProduct.stock_ya_cargado || 0) === 1;
    const existing = purchaseCart.find((item) => Number(item.product.id) === Number(selectedPurchaseProduct.id) && item.type === type && Number(item.price) === price && Boolean(item.stockAlreadyLoaded) === stockAlreadyLoaded);
    const units = type === 'pack' ? quantity * Number(selectedPurchaseProduct.pack_cantidad || 0) : quantity;
    if (existing) {
      existing.quantity += quantity;
      existing.units += units;
      existing.subtotal += price * quantity;
    } else {
      purchaseCart.push({
        product: selectedPurchaseProduct,
        type,
        quantity,
        units,
        price,
        subtotal: price * quantity,
        stockAlreadyLoaded
      });
    }

    lastPurchaseItemAddedAt = performance.now();
    selectedPurchaseProduct = null;
    if (addPurchaseItemButton) addPurchaseItemButton.disabled = true;
    purchaseProductId.value = '';
    purchaseProductSearch.value = '';
    document.getElementById('purchaseType').value = 'unidad';
    quantityInput.value = 1;
    priceInput.value = '0';
    updatePurchasePriceForType();
    renderPurchaseCart();
    updatePurchaseTotal();
  }

  function updateCheckoutChange() {
    const total = cartTotal(saleCart);
    const received = Number(moneyDigits(checkoutReceived.value) || 0);
    checkoutChange.textContent = money(Math.max(0, received - total));
    checkoutReceived.setCustomValidity('');
  }

  function openSaleCheckoutModal() {
    const total = cartTotal(saleCart);
    checkoutTotal.textContent = money(total);
    checkoutMethod.value = document.getElementById('saleMethod')?.value || 'efectivo';
    checkoutReceived.value = money(total);
    checkoutPrintTicket.checked = false;
    updateCheckoutChange();
    saleCheckoutModal.classList.add('open');
    saleCheckoutModal.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
      checkoutReceived.focus();
      checkoutReceived.setSelectionRange(0, checkoutReceived.value.length);
      checkoutReceived.select();
    });
  }

  function saleTicketHtml(options = null) {
    const ticketData = options || {
      client: saleClientSearch.value.trim() || 'Sin cliente',
      method: document.getElementById('saleMethod')?.value || 'efectivo',
      total: cartTotal(saleCart),
      received: Number(moneyDigits(checkoutReceived.value) || 0),
      change: Math.max(0, Number(moneyDigits(checkoutReceived.value) || 0) - cartTotal(saleCart)),
      date: new Date().toLocaleString('es-PY'),
      items: saleCart.map((item) => ({
        product: item.product.nombre,
        type: item.type,
        quantity: item.quantity,
        price: cartItemUnitPrice(item),
        subtotal: item.subtotal
      }))
    };
    const itemCount = ticketData.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const rows = ticketData.items.map((item) => {
      const quantity = Number(item.quantity || 0);
      const price = item.price !== undefined
        ? Number(item.price || 0)
        : (quantity > 0 ? Number(item.subtotal || 0) / quantity : 0);
      return `
        <tr>
          <td>${escapeHtml(item.product)} (${escapeHtml(item.type)})</td>
          <td>${quantity}</td>
          <td>${money(price)}</td>
          <td>${money(item.subtotal)}</td>
        </tr>
      `;
    }).join('');

    return `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Ticket de venta</title>
        <style>
          body { font-family: Arial, sans-serif; width: 300px; margin: 0; padding: 12px; color: #111; }
          h1 { font-size: 16px; margin: 0 0 8px; text-align: center; }
          p { margin: 4px 0; font-size: 12px; }
          table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
          th, td { border-bottom: 1px dashed #999; padding: 4px 0; text-align: left; }
          th:nth-child(2), td:nth-child(2), th:nth-child(3), td:nth-child(3), th:nth-child(4), td:nth-child(4) { text-align: right; }
          .items-count { border-top: 1px solid #111; padding-top: 8px; font-weight: 700; }
          .total { display: flex; justify-content: space-between; align-items: center; font-size: 15px; font-weight: 700; border-top: 1px solid #111; padding-top: 8px; }
        </style>
      </head>
      <body>
        <h1>Cancha Sintetica</h1>
        <p>Fecha: ${escapeHtml(ticketData.date)}</p>
        <p>Cliente: ${escapeHtml(ticketData.client)}</p>
        <p>Metodo: ${escapeHtml(ticketData.method)}</p>
        <table>
          <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subt.</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
        <p class="items-count">Items: ${itemCount}</p>
        <p class="total"><span>Total:</span><span>${money(ticketData.total)}</span></p>
      </body>
      </html>
    `;
  }

  function printSaleTicket(options = null) {
    const ticket = window.open('', 'ticket_venta', 'width=360,height=620');
    if (!ticket) {
      showClientMessage('El navegador bloqueo la ventana de impresion. Permite ventanas emergentes para imprimir el ticket.');
      return false;
    }

    ticket.document.write(saleTicketHtml(options));
    ticket.document.close();
    ticket.focus();
    ticket.print();
    return true;
  }

  function submitConfirmedSale(button) {
    saleSubmitConfirmed = true;
    lockSubmitForm(saleForm, button);
    prepareMoneyInputs(saleForm);
    saleForm.submit();
  }

  function openSaleTicketPreview(ticketHtml = saleTicketHtml()) {
    saleTicketPreviewFrame.srcdoc = ticketHtml;
    saleCheckoutModal.classList.remove('open');
    saleCheckoutModal.setAttribute('aria-hidden', 'true');
    saleTicketPreviewModal.classList.add('open');
    saleTicketPreviewModal.setAttribute('aria-hidden', 'false');
  }

  function finishRegisteredSaleTicket() {
    saleTicketPreviewModal.classList.remove('open');
    saleTicketPreviewModal.setAttribute('aria-hidden', 'true');
    window.location.reload();
  }

  function cashCloseTicketHtml(ticketData = cashTicketData) {
    const efectivoContado = ticketData.monto_cierre_efectivo !== undefined
      ? Number(ticketData.monto_cierre_efectivo || 0)
      : Number(moneyDigits(cashCloseForm?.querySelector('[name="monto_cierre_efectivo"]')?.value || '0') || 0);
    const transferenciaContada = ticketData.monto_cierre_transferencia !== undefined
      ? Number(ticketData.monto_cierre_transferencia || 0)
      : Number(moneyDigits(cashCloseForm?.querySelector('[name="monto_cierre_transferencia"]')?.value || '0') || 0);
    const cierreFecha = ticketData.cerrada_en || new Date().toLocaleString('es-PY');
    const diferenciaEfectivo = efectivoContado - Number(ticketData.saldo_efectivo || 0);
    const diferenciaTransferencia = transferenciaContada - Number(ticketData.saldo_transferencia || 0);
    const diferenciaTotal = (efectivoContado + transferenciaContada) - Number(ticketData.saldo_total || 0);

    return `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Ticket de caja</title>
        <style>
          body { font-family: Arial, sans-serif; width: 300px; margin: 0; padding: 12px; color: #111; }
          h1 { font-size: 16px; margin: 0 0 8px; text-align: center; }
          p { margin: 4px 0; font-size: 12px; }
          .difference { font-weight: 700; }
          .negative { color: #b91c1c; }
          .positive { color: #166534; }
          .total { font-size: 14px; font-weight: 700; border-top: 1px solid #111; padding-top: 8px; }
        </style>
      </head>
      <body>
        <h1>Cierre de caja</h1>
        <p>Apertura: ${escapeHtml(ticketData.abierta_en || '-')}</p>
        <p>Usuario apertura: ${escapeHtml(ticketData.usuario_apertura || '-')}</p>
        <p>Cierre: ${escapeHtml(cierreFecha)}</p>
        <p>Usuario cierre: ${escapeHtml(ticketData.usuario_cierre || '-')}</p>
        <p>Monto inicial: ${money(ticketData.monto_inicial)}</p>
        <p>Saldo esperado: ${money(ticketData.saldo_total)}</p>
        <p>Efectivo contado: ${money(efectivoContado)}</p>
        <p class="difference ${diferenciaEfectivo < 0 ? 'negative' : 'positive'}">Dif. efectivo: ${money(diferenciaEfectivo)}</p>
        <p>Transferencia contada: ${money(transferenciaContada)}</p>
        <p class="difference ${diferenciaTransferencia < 0 ? 'negative' : 'positive'}">Dif. transferencia: ${money(diferenciaTransferencia)}</p>
        <p class="total">Total contado: ${money(efectivoContado + transferenciaContada)}</p>
        <p class="difference ${diferenciaTotal < 0 ? 'negative' : 'positive'}">Diferencia total: ${money(diferenciaTotal)}</p>
      </body>
      </html>
    `;
  }

  function printCashCloseTicket(ticketData = cashTicketData) {
    const ticket = window.open('', 'ticket_caja', 'width=380,height=700');
    if (!ticket) {
      showClientMessage('El navegador bloqueo la ventana de impresion. Permite ventanas emergentes para imprimir el ticket.');
      return false;
    }

    ticket.document.write(cashCloseTicketHtml(ticketData));
    ticket.document.close();
    ticket.focus();
    ticket.print();
    return true;
  }

  function validateCashCloseAmounts() {
    const efectivoInput = cashCloseForm?.querySelector('[name="monto_cierre_efectivo"]');
    if (!efectivoInput || moneyDigits(efectivoInput.value) === '') {
      efectivoInput?.setCustomValidity('Ingrese efectivo contado.');
      efectivoInput?.reportValidity();
      efectivoInput?.focus();
      return false;
    }

    efectivoInput.setCustomValidity('');
    return true;
  }

  function saleItemsById(saleId) {
    return saleDetails.filter((item) => Number(item.venta_id) === Number(saleId));
  }

  function purchaseItemsById(purchaseId) {
    return purchaseDetails.filter((item) => Number(item.compra_id) === Number(purchaseId));
  }

  function openPurchaseDetailModal(purchaseId) {
    if (purchaseEditInProgress) return;

    const purchase = purchases.find((item) => Number(item.id) === Number(purchaseId));
    const details = purchaseItemsById(purchaseId);
    if (!purchase || details.length === 0) return;

    selectedPurchaseDetailId = purchaseId;
    cancelPurchaseId.value = purchaseId;
    const isCanceled = purchase.estado === 'anulada';
    cancelPurchaseButton.hidden = isCanceled;
    editPurchaseFromDetail.hidden = isCanceled;
    cancelPurchaseButton.disabled = !isCanceled;
    editPurchaseFromDetail.disabled = !isCanceled;
    purchaseDetailSummary.innerHTML = `
      <dl>
        <div><dt>Fecha</dt><dd>${escapeHtml(new Date(purchase.fecha_compra).toLocaleString('es-PY'))}</dd></div>
        <div><dt>Proveedor</dt><dd>${escapeHtml(purchase.proveedor || 'Sin proveedor')}</dd></div>
        <div><dt>Metodo</dt><dd>${escapeHtml(purchase.metodo)}</dd></div>
        <div><dt>Estado</dt><dd>${escapeHtml(purchase.estado || 'activa')}</dd></div>
        <div><dt>Total</dt><dd>${money(purchase.total)}</dd></div>
      </dl>
    `;
    purchaseDetailItems.innerHTML = `
      <table>
        <thead><tr><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Unidades</th><th>Precio</th><th>Subtotal</th></tr></thead>
        <tbody>
          ${details.map((item) => `
            <tr>
              <td>${escapeHtml(item.producto)}</td>
              <td>${escapeHtml(item.tipo_compra)}</td>
              <td>${Number(item.cantidad)}</td>
              <td>${Number(item.unidades_agregadas)}</td>
              <td>${money(item.precio_unitario)}</td>
              <td>${money(item.subtotal)}</td>
            </tr>
          `).join('')}
          <tr class="cart-total-row"><th colspan="5">Total</th><th>${money(purchase.total)}</th></tr>
        </tbody>
      </table>
    `;
    purchaseDetailModal.classList.add('open');
    purchaseDetailModal.setAttribute('aria-hidden', 'false');
    if (!isCanceled) {
      setTimeout(() => {
        cancelPurchaseButton.disabled = false;
        editPurchaseFromDetail.disabled = false;
      }, 600);
    }
  }

  function openSaleDetailModal(saleId) {
    const sale = sales.find((item) => Number(item.id) === Number(saleId));
    const details = saleItemsById(saleId);
    if (!sale || details.length === 0) return;

    selectedSaleDetailId = saleId;
    cancelSaleId.value = saleId;
    const isCanceled = sale.estado === 'anulada';
    cancelSaleButton.hidden = isCanceled;
    editSaleFromDetail.hidden = isCanceled;
    cancelSaleButton.disabled = !isCanceled;
    editSaleFromDetail.disabled = !isCanceled;
    saleDetailSummary.innerHTML = `
      <dl>
        <div><dt>Fecha</dt><dd>${escapeHtml(new Date(sale.fecha_venta).toLocaleString('es-PY'))}</dd></div>
        <div><dt>Cliente</dt><dd>${escapeHtml(sale.cliente || 'Sin cliente')}</dd></div>
        <div><dt>Metodo</dt><dd>${escapeHtml(sale.metodo)}</dd></div>
        <div><dt>Estado</dt><dd>${escapeHtml(sale.estado || 'activa')}</dd></div>
        <div><dt>Total</dt><dd>${money(sale.total)}</dd></div>
        ${sale.reserva_cancha ? `<div><dt>Reserva</dt><dd>${escapeHtml(sale.reserva_cancha)} - R#${Number(sale.reserva_id)} - ${escapeHtml(sale.reserva_fecha)}</dd></div>` : ''}
      </dl>
    `;
    saleDetailItems.innerHTML = `
      <table>
        <thead><tr><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Unidades</th><th>Subtotal</th></tr></thead>
        <tbody>
          ${details.map((item) => `
            <tr>
              <td>${escapeHtml(item.producto)}</td>
              <td>${escapeHtml(item.tipo_venta)}</td>
              <td>${Number(item.cantidad)}</td>
              <td>${Number(item.unidades_descontadas)}</td>
              <td>${money(item.subtotal)}</td>
            </tr>
          `).join('')}
          <tr class="cart-total-row"><th colspan="4">Total</th><th>${money(sale.total)}</th></tr>
        </tbody>
      </table>
    `;
    saleDetailModal.classList.add('open');
    saleDetailModal.setAttribute('aria-hidden', 'false');
    if (!isCanceled) {
      setTimeout(() => {
        cancelSaleButton.disabled = false;
        editSaleFromDetail.disabled = false;
      }, 600);
    }
  }

  function updateReservationSaleTotal() {
    const saleType = document.getElementById('reservaVentaTipo');
    const quantity = document.getElementById('reservaVentaCantidad');
    const total = document.getElementById('reservaVentaTotal');

    if (!saleType || !quantity || !total) {
      return;
    }

    const unitPrice = Number(selectedReservationSaleProduct?.precio_venta || 0);
    const packPrice = Number(selectedReservationSaleProduct?.precio_pack || 0);
    const packQuantity = Number(selectedReservationSaleProduct?.pack_cantidad || 0);
    const promotionPrice = Number(selectedReservationSaleProduct?.precio_promocion || 0);
    const promotionQuantity = Number(selectedReservationSaleProduct?.promocion_cantidad || 0);
    const price = saleType.value === 'pack'
      ? packPrice
      : (saleType.value === 'promocion' ? promotionPrice : unitPrice);
    const amount = clampSaleQuantity('reservation');
    total.value = money(price * amount);
    const invalidPack = saleType.value === 'pack' && (packPrice <= 0 || packQuantity <= 0);
    const invalidPromotion = saleType.value === 'promocion' && (promotionPrice <= 0 || promotionQuantity <= 0);
    saleType.setCustomValidity(selectedReservationSaleProduct && (invalidPack || invalidPromotion)
      ? (invalidPromotion ? 'Este producto no tiene promocion configurada.' : 'Este producto no tiene pack configurado.')
      : '');
  }

  document.getElementById('ventaTipo')?.addEventListener('change', updateSaleTotal);
  document.getElementById('ventaCantidad')?.addEventListener('input', updateSaleTotal);
  document.querySelectorAll('[data-sale-quantity-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const quantityInput = document.getElementById('ventaCantidad');
      if (!quantityInput) {
        return;
      }

      const step = Number(button.dataset.saleQuantityStep || 0);
      const current = Number(quantityInput.value || 0);
      quantityInput.value = String(current + step);
      updateSaleTotal();
    });
  });
  document.getElementById('purchaseType')?.addEventListener('change', () => {
    updatePurchasePriceForType();
    updatePurchaseTotal();
  });
  document.getElementById('purchaseQuantity')?.addEventListener('input', updatePurchaseTotal);
  document.querySelectorAll('[data-purchase-quantity-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const quantityInput = document.getElementById('purchaseQuantity');
      if (!quantityInput) {
        return;
      }

      const step = Number(button.dataset.purchaseQuantityStep || 0);
      const current = Number(quantityInput.value || 0);
      quantityInput.value = String(Math.max(1, current + step));
      updatePurchaseTotal();
    });
  });
  document.getElementById('purchasePrice')?.addEventListener('input', (event) => {
    formatMoneyInput(event.currentTarget);
    updatePurchaseTotal();
  });
  document.getElementById('reservaVentaTipo')?.addEventListener('change', updateReservationSaleTotal);
  document.getElementById('reservaVentaCantidad')?.addEventListener('input', updateReservationSaleTotal);
  addSaleItemButton?.addEventListener('click', () => addCartItem('sale'));
  addPurchaseItemButton?.addEventListener('click', addPurchaseItem);
  document.getElementById('addReservationSaleItem')?.addEventListener('click', () => addCartItem('reservation'));

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-cart-item]');
    const purchaseButton = event.target.closest('[data-remove-purchase-item]');
    if (!button && !purchaseButton) return;

    if (purchaseButton) {
      purchaseCart.splice(Number(purchaseButton.dataset.removePurchaseItem), 1);
      renderPurchaseCart();
      updatePurchaseTotal();
      return;
    }

    const index = Number(button.dataset.removeCartItem);
    if (button.dataset.cartList === 'reservationSaleCartList') {
      reservationSaleCart.splice(index, 1);
      renderCart(reservationSaleCart, 'reservationSaleCartList', 'reservationSaleCartInputs');
      refreshReservationProductOptions();
      updateReservationSaleTotal();
    } else {
      saleCart.splice(index, 1);
      renderCart(saleCart, 'saleCartList', 'saleCartInputs');
      if (ventaProductoSearch.value.trim() !== '') {
        renderProductSuggestions(ventaProductoSearch.value);
      }
      updateSaleTotal();
    }
  });

  document.addEventListener('change', (event) => {
    const purchaseInput = event.target.closest('[data-purchase-quantity]');
    if (purchaseInput) {
      updatePurchaseCartQuantity(Number(purchaseInput.dataset.purchaseQuantity), purchaseInput.value);
      return;
    }

    const input = event.target.closest('[data-cart-quantity]');
    if (!input) return;

    const index = Number(input.dataset.cartQuantity);
    const cartName = input.dataset.cartList === 'reservationSaleCartList' ? 'reservation' : 'sale';
    updateCartQuantity(cartName, index, input.value);
  });

  document.addEventListener('input', (event) => {
    const cartQuantityInput = event.target.closest('[data-cart-quantity]');
    if (cartQuantityInput) {
      updateCartQuantityLive(cartQuantityInput);
      return;
    }

    const purchasePriceInput = event.target.closest('[data-purchase-price]');
    if (!purchasePriceInput) return;
    updatePurchaseCartPrice(Number(purchasePriceInput.dataset.purchasePrice), purchasePriceInput);
  });

  document.getElementById('purchaseForm')?.addEventListener('submit', (event) => {
    if (performance.now() - lastPurchaseEditStartedAt < 700) {
      event.preventDefault();
      return;
    }
    if (purchaseCart.length === 0) {
      event.preventDefault();
      showClientMessage('Agrega al menos un producto a la compra.');
      return;
    }

    const invalidPrice = purchaseCart.some((item) => Number(item.price || 0) <= 0);
    if (invalidPrice) {
      event.preventDefault();
      showClientMessage('Todos los productos deben tener un precio de compra valido.');
      return;
    }

    // Rebuild the POST fields at the last possible moment so the server always
    // receives the current quantities and edited prices from the cart.
    renderPurchaseCart();
  });

  saleForm?.addEventListener('submit', (event) => {
    if (saleSubmitConfirmed) {
      return;
    }

    event.preventDefault();
    if (saleCart.length === 0) {
      showClientMessage('Agrega al menos un producto a la venta.');
      return;
    }

    openSaleCheckoutModal();
  });

  cashCloseForm?.addEventListener('submit', (event) => {
    if (cashCloseConfirmed) {
      return;
    }

    event.preventDefault();
    if (!validateCashCloseAmounts()) {
      return;
    }

    cashCloseConfirmModal?.classList.add('open');
    cashCloseConfirmModal?.setAttribute('aria-hidden', 'false');
  });

  document.querySelectorAll('[data-close-cash-close]').forEach((button) => {
    button.addEventListener('click', () => {
      cashCloseConfirmModal?.classList.remove('open');
      cashCloseConfirmModal?.setAttribute('aria-hidden', 'true');
    });
  });

  confirmCashClose?.addEventListener('click', () => {
    if (!validateCashCloseAmounts()) {
      return;
    }

    cashCloseConfirmed = true;
    prepareMoneyInputs(cashCloseForm);
    cashCloseForm.submit();
  });

  cashCloseForm?.querySelector('[name="monto_cierre_efectivo"]')?.addEventListener('input', (event) => {
    event.currentTarget.setCustomValidity('');
  });

  printCashCloseTicketButton?.addEventListener('click', () => {
    if (!validateCashCloseAmounts()) {
      return;
    }

    if (!printCashCloseTicket()) {
      return;
    }

    cashCloseConfirmed = true;
    prepareMoneyInputs(cashCloseForm);
    cashCloseForm.submit();
  });

  document.querySelectorAll('[data-reprint-cash-ticket]').forEach((button) => {
    button.addEventListener('click', () => {
      const ticketData = cashClosedTickets[button.dataset.reprintCashTicket];
      if (!ticketData) {
        showClientMessage('No se encontraron los datos de este cierre para reimprimir.');
        return;
      }
      printCashCloseTicket(ticketData);
    });
  });

  checkoutReceived?.addEventListener('input', () => {
    formatMoneyInput(checkoutReceived);
    updateCheckoutChange();
  });

  document.getElementById('confirmSaleSubmit')?.addEventListener('click', async (event) => {
    if (saleForm?.dataset.submitting === '1') {
      return;
    }

    const total = cartTotal(saleCart);
    const received = Number(moneyDigits(checkoutReceived.value) || 0);
    if (checkoutMethod.value === 'efectivo' && received < total) {
      checkoutReceived.setCustomValidity(`El monto recibido debe ser igual o mayor a ${money(total)}.`);
      checkoutReceived.reportValidity();
      checkoutReceived.focus();
      checkoutReceived.select();
      return;
    }

    document.getElementById('saleMethod').value = checkoutMethod.value || 'efectivo';
    if (checkoutPrintTicket.checked) {
      const submitButton = event.currentTarget;
      const ticketHtml = saleTicketHtml();
      saleForm.dataset.submitting = '1';
      submitButton.disabled = true;
      submitButton.textContent = 'Registrando...';
      prepareMoneyInputs(saleForm);

      try {
        const response = await fetch(saleForm.action, {
          method: 'POST',
          body: new FormData(saleForm),
          credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('No se pudo registrar la venta.');
        if (response.url.includes('venta_error=')) {
          window.location.href = response.url;
          return;
        }
        openSaleTicketPreview(ticketHtml);
      } catch (error) {
        saleForm.dataset.submitting = '';
        submitButton.disabled = false;
        submitButton.textContent = 'Confirmar venta';
        showClientMessage(error.message || 'No se pudo registrar la venta.');
      }
      return;
    }

    submitConfirmedSale(event.currentTarget);
  });

  document.getElementById('cancelSaleTicketPrint')?.addEventListener('click', finishRegisteredSaleTicket);
  document.getElementById('closeSaleTicketPreview')?.addEventListener('click', finishRegisteredSaleTicket);

  document.getElementById('printRegisteredSaleTicket')?.addEventListener('click', () => {
    const ticketWindow = saleTicketPreviewFrame?.contentWindow;
    if (ticketWindow) {
      ticketWindow.focus();
      ticketWindow.print();
    }
    finishRegisteredSaleTicket();
  });

  function bindSaleRows(scope = document) {
    scope.querySelectorAll('[data-sale-id]').forEach((row) => {
      row.addEventListener('click', () => openSaleDetailModal(row.dataset.saleId));
    });
  }

  bindSaleRows();

  function bindPurchaseRows(scope = document) {
    scope.querySelectorAll('[data-purchase-id]').forEach((row) => {
      row.addEventListener('click', () => {
        if (performance.now() - lastPurchaseEditStartedAt < 700) return;
        openPurchaseDetailModal(row.dataset.purchaseId);
      });
    });
  }

  bindPurchaseRows();

  editPurchaseFromDetail?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (selectedPurchaseDetailId) {
      startPurchaseEdit(selectedPurchaseDetailId);
    }
  });

  document.getElementById('reprintSaleTicket')?.addEventListener('click', () => {
    const sale = sales.find((item) => Number(item.id) === Number(selectedSaleDetailId));
    const details = saleItemsById(selectedSaleDetailId);
    if (!sale || details.length === 0) return;

    printSaleTicket({
      client: sale.cliente || 'Sin cliente',
      method: sale.metodo,
      total: Number(sale.total || 0),
      date: new Date(sale.fecha_venta).toLocaleString('es-PY'),
      items: details.map((item) => ({
        product: item.producto,
        type: item.tipo_venta,
        quantity: Number(item.cantidad),
        price: Number(item.precio_unitario || 0),
        subtotal: Number(item.subtotal)
      }))
    });
  });

  editSaleFromDetail?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (selectedSaleDetailId) {
      startSaleEdit(selectedSaleDetailId);
    }
  });

  cancelSaleButton?.addEventListener('click', () => {
    const sale = sales.find((item) => Number(item.id) === Number(selectedSaleDetailId));
    if (!sale || sale.estado === 'anulada') {
      showClientMessage('Esta venta ya esta anulada.');
      return;
    }

    cancelSaleConfirmModal.classList.add('open');
    cancelSaleConfirmModal.setAttribute('aria-hidden', 'false');
  });

  cancelPurchaseButton?.addEventListener('click', () => {
    const purchase = purchases.find((item) => Number(item.id) === Number(selectedPurchaseDetailId));
    if (!purchase || purchase.estado === 'anulada') {
      showClientMessage('Esta compra ya esta anulada.');
      return;
    }

    cancelPurchaseConfirmModal.classList.add('open');
    cancelPurchaseConfirmModal.setAttribute('aria-hidden', 'false');
  });

  document.querySelectorAll('[data-close-cancel-sale]').forEach((button) => {
    button.addEventListener('click', () => {
      cancelSaleConfirmModal.classList.remove('open');
      cancelSaleConfirmModal.setAttribute('aria-hidden', 'true');
    });
  });

  document.querySelectorAll('[data-close-cancel-purchase]').forEach((button) => {
    button.addEventListener('click', () => {
      cancelPurchaseConfirmModal.classList.remove('open');
      cancelPurchaseConfirmModal.setAttribute('aria-hidden', 'true');
    });
  });

  confirmCancelSale?.addEventListener('click', () => {
    document.getElementById('cancelSaleForm')?.submit();
  });

  confirmCancelPurchase?.addEventListener('click', () => {
    document.getElementById('cancelPurchaseForm')?.submit();
  });

  document.querySelector('form input[name="origen"][value="reserva"]')?.closest('form')?.addEventListener('submit', (event) => {
    if (reservationSaleCart.length === 0) {
      event.preventDefault();
      showClientMessage('Agrega al menos un producto al consumo de la reserva.');
    }
  });

  document.addEventListener('pointerup', finishSlotSelection);
  document.addEventListener('mouseup', finishSlotSelection);
  document.addEventListener('pointercancel', () => {
    dragSelection = null;
    paintSlotSelection();
  });

  const productInventorySearch = document.getElementById('productInventorySearch');
  const productInventoryRows = document.getElementById('productInventoryRows');
  const productInventoryCount = document.getElementById('productInventoryCount');
  const productInventoryPagination = document.getElementById('productInventoryPagination');
  const initialProductInventoryRows = productInventoryRows?.innerHTML || '';
  const initialProductInventoryCount = productInventoryCount?.textContent || '';

  function productInventoryRow(product) {
    const isActive = product.estado === 'activo';
    const purchasePack = Number(product.pack_cantidad || 0) > 0
      ? money(product.precio_compra_pack)
      : '-';
    const pack = Number(product.pack_cantidad || 0) > 0
      ? `${Number(product.pack_cantidad)} un. / ${money(product.precio_pack)}`
      : '-';
    const promotion = Number(product.promocion_cantidad || 0) > 0
      ? `${Number(product.promocion_cantidad)} por ${money(product.precio_promocion)}`
      : '-';
    <?php if (!$esAdministrador): ?>
    return `
      <tr data-product-row>
        <td>${escapeHtml(product.nombre)}</td>
        <td>${escapeHtml(product.codigo_barra || '-')}</td>
        <td>${escapeHtml(product.proveedor || '-')}</td>
        <td>${escapeHtml(product.categoria || '-')}</td>
        <td>${money(product.precio_venta)}</td>
        <td>${pack}</td>
        <td>${promotion}</td>
        <td>${Number(product.stock || 0)}</td>
        <td><span class="badge ${escapeHtml(product.estado)}">${escapeHtml(product.estado)}</span></td>
      </tr>
    `;
    <?php else: ?>
    return `
      <tr data-product-row>
        <td>${escapeHtml(product.nombre)}</td>
        <td>${escapeHtml(product.codigo_barra || '-')}</td>
        <td>${escapeHtml(product.proveedor || '-')}</td>
        <td>${escapeHtml(product.categoria || '-')}</td>
        <td>${money(product.precio_compra)}</td>
        <td>${money(product.precio_venta)}</td>
        <td>${purchasePack}</td>
        <td>${pack}</td>
        <td>${promotion}</td>
        <td>${Number(product.stock || 0)}</td>
        <td><span class="badge ${escapeHtml(product.estado)}">${escapeHtml(product.estado)}</span></td>
        <td>
          <div class="product-row-actions">
            <button type="button" class="small secondary" data-edit-product="${Number(product.id)}">Editar</button>
            <button type="button" class="small ${isActive ? 'danger' : ''}" data-toggle-product data-id="${Number(product.id)}" data-nombre="${escapeHtml(product.nombre)}" data-estado="${escapeHtml(product.estado || 'activo')}">${isActive ? 'Desactivar' : 'Activar'}</button>
            <button type="button" class="small danger" data-delete-product data-id="${Number(product.id)}" data-nombre="${escapeHtml(product.nombre)}">Eliminar</button>
          </div>
        </td>
      </tr>
    `;
    <?php endif; ?>
  }

  function bindProductInventoryActions(scope = document) {
    scope.querySelectorAll('[data-edit-product]').forEach((button) => {
      button.addEventListener('click', () => openProductModal(button.dataset.editProduct));
    });
    scope.querySelectorAll('[data-toggle-product]').forEach((button) => {
      button.addEventListener('click', () => openToggleProductModal(button));
    });
    scope.querySelectorAll('[data-delete-product]').forEach((button) => {
      button.addEventListener('click', () => openDeleteProductModal(button));
    });
  }

  function renderProductInventorySearch(forceCurrent = false) {
    if (!productInventoryRows || !productInventorySearch) return;
    const term = productInventorySearch.value.trim().toLowerCase();
    if (term === '' && !forceCurrent) {
      productInventoryRows.innerHTML = initialProductInventoryRows;
      productInventoryCount.textContent = initialProductInventoryCount;
      if (productInventoryPagination) {
        productInventoryPagination.hidden = false;
      }
      bindProductInventoryActions(productInventoryRows);
      return;
    }
    const matches = term === ''
      ? products
      : products.filter((product) => `${product.nombre} ${product.codigo_barra || ''} ${product.proveedor || ''} ${product.categoria || ''} ${product.estado || ''}`.toLowerCase().includes(term));
    productInventoryRows.innerHTML = matches.length
      ? matches.map(productInventoryRow).join('')
      : '<tr><td colspan="<?= $esAdministrador ? 11 : 8 ?>">No se encontraron productos con esa busqueda.</td></tr>';
    productInventoryCount.textContent = `${matches.length} producto(s)${term ? ' encontrados' : ''}`;
    if (productInventoryPagination) {
      productInventoryPagination.hidden = true;
    }
    bindProductInventoryActions(productInventoryRows);
  }

  productInventorySearch?.addEventListener('input', () => {
    renderProductInventorySearch();
  });

  const saleListSearch = document.getElementById('saleListSearch');
  const saleListRows = document.getElementById('saleListRows');
  const saleSearchCount = document.getElementById('saleSearchCount');
  const saleListPagination = document.getElementById('saleListPagination');
  const initialSaleListRows = saleListRows?.innerHTML || '';
  const initialSaleSearchCount = saleSearchCount?.textContent || '';

  function saleSearchText(sale) {
    const details = saleItemsById(sale.id)
      .map((item) => `${item.producto} ${item.tipo_venta || ''}`)
      .join(' ');
    return normalizeSearchText(`${sale.id} ${sale.cliente || 'Sin cliente'} ${sale.metodo || ''} ${sale.estado || ''} ${sale.reserva_cancha || ''} ${sale.reserva_id ? `R#${sale.reserva_id}` : ''} ${details}`);
  }

  function saleListRow(sale) {
    return `
      <tr class="clickable-row ${sale.estado === 'anulada' ? 'sale-canceled' : ''}" data-sale-id="${Number(sale.id)}">
        <td>#${Number(sale.id)}</td>
        <td>${escapeHtml(formatDateTime(sale.fecha_venta))}</td>
        <td>${escapeHtml(sale.cliente || 'Sin cliente')}</td>
        <td>
          ${Number(sale.items || 0)} producto(s)
          <span>${Number(sale.unidades || 0)} unidad(es)${sale.reserva_cancha ? ` - ${escapeHtml(sale.reserva_cancha)} - R#${Number(sale.reserva_id)}` : ''}</span>
        </td>
        <td>${escapeHtml(sale.metodo)}</td>
        <td><span class="badge ${escapeHtml(sale.estado)}">${escapeHtml(sale.estado)}</span></td>
        <td>${money(sale.total)}</td>
      </tr>
    `;
  }

  function renderSaleListSearch() {
    if (!saleListRows || !saleListSearch) return;
    const term = normalizeSearchText(saleListSearch.value.trim());
    if (term === '') {
      saleListRows.innerHTML = initialSaleListRows;
      saleSearchCount.textContent = initialSaleSearchCount;
      if (saleListPagination) saleListPagination.hidden = false;
      bindSaleRows(saleListRows);
      return;
    }
    const matches = sales.filter((sale) => saleSearchText(sale).includes(term));
    saleListRows.innerHTML = matches.length
      ? matches.map(saleListRow).join('')
      : '<tr><td colspan="7">No se encontraron ventas con esa busqueda.</td></tr>';
    saleSearchCount.textContent = `${matches.length} venta(s) encontradas`;
    if (saleListPagination) saleListPagination.hidden = true;
    bindSaleRows(saleListRows);
  }

  saleListSearch?.addEventListener('input', renderSaleListSearch);
  saleListSearch?.addEventListener('search', renderSaleListSearch);
  if (saleListSearch?.value.trim()) renderSaleListSearch();

  const purchaseListSearch = document.getElementById('purchaseListSearch');
  const purchaseListRows = document.getElementById('purchaseListRows');
  const purchaseSearchCount = document.getElementById('purchaseSearchCount');
  const purchaseListPagination = document.getElementById('purchaseListPagination');
  const initialPurchaseListRows = purchaseListRows?.innerHTML || '';
  const initialPurchaseSearchCount = purchaseSearchCount?.textContent || '';

  function purchaseSearchText(purchase) {
    const details = purchaseItemsById(purchase.id)
      .map((item) => `${item.producto} ${item.tipo_compra || ''}`)
      .join(' ');
    return normalizeSearchText(`${purchase.id} ${purchase.proveedor || 'Sin proveedor'} ${purchase.metodo || ''} ${purchase.estado || ''} ${details}`);
  }

  function normalizeSearchText(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase();
  }

  function formatDateTime(value) {
    const [datePart, timePart = '00:00:00'] = String(value || '').split(' ');
    const [year, month, day] = datePart.split('-');
    return `${day}/${month}/${year} ${timePart.slice(0, 5)}`;
  }

  function purchaseListRow(purchase) {
    return `
      <tr class="clickable-row ${purchase.estado === 'anulada' ? 'sale-canceled' : ''}" data-purchase-id="${Number(purchase.id)}">
        <td>#${Number(purchase.id)}</td>
        <td>${escapeHtml(formatDateTime(purchase.fecha_compra))}</td>
        <td>${escapeHtml(purchase.proveedor || 'Sin proveedor')}</td>
        <td>
          ${Number(purchase.items || 0)} producto(s)
          <span>${Number(purchase.unidades || 0)} unidad(es)</span>
        </td>
        <td>${escapeHtml(purchase.metodo)}</td>
        <td><span class="badge ${escapeHtml(purchase.estado)}">${escapeHtml(purchase.estado)}</span></td>
        <td>${money(purchase.total)}</td>
      </tr>
    `;
  }

  function renderPurchaseListSearch() {
    if (!purchaseListRows || !purchaseListSearch) return;
    const term = normalizeSearchText(purchaseListSearch.value.trim());
    if (term === '') {
      purchaseListRows.innerHTML = initialPurchaseListRows;
      purchaseSearchCount.textContent = initialPurchaseSearchCount;
      if (purchaseListPagination) {
        purchaseListPagination.hidden = false;
      }
      bindPurchaseRows(purchaseListRows);
      return;
    }
    const matches = purchases.filter((purchase) => purchaseSearchText(purchase).includes(term));
    purchaseListRows.innerHTML = matches.length
      ? matches.map(purchaseListRow).join('')
      : '<tr><td colspan="7">No se encontraron compras con esa busqueda.</td></tr>';
    purchaseSearchCount.textContent = `${matches.length} compra(s) encontradas`;
    if (purchaseListPagination) {
      purchaseListPagination.hidden = true;
    }
    bindPurchaseRows(purchaseListRows);
  }

  purchaseListSearch?.addEventListener('input', renderPurchaseListSearch);
  purchaseListSearch?.addEventListener('search', renderPurchaseListSearch);
  if (purchaseListSearch?.value.trim()) renderPurchaseListSearch();

  const userCreateModal = document.getElementById('userCreateModal');
  const userCreateForm = document.getElementById('userCreateForm');
  const openUserCreateModal = document.getElementById('openUserCreateModal');
  const createUserName = document.getElementById('createUserName');
  const userInventorySearch = document.getElementById('userInventorySearch');
  const userInventoryCount = document.getElementById('userInventoryCount');
  const userInventoryRows = document.getElementById('userInventoryRows');
  const userInventoryEmpty = document.getElementById('userInventoryEmpty');
  const userEditModal = document.getElementById('userEditModal');
  const editUserId = document.getElementById('editUserId');
  const editUserName = document.getElementById('editUserName');
  const editUserUsername = document.getElementById('editUserUsername');
  const editUserPassword = document.getElementById('editUserPassword');
  const editUserRole = document.getElementById('editUserRole');
  const editUserStatus = document.getElementById('editUserStatus');
  const toggleEditPassword = document.getElementById('toggleEditPassword');

  openUserCreateModal?.addEventListener('click', () => {
    userCreateForm?.reset();
    userCreateModal?.classList.add('open');
    userCreateModal?.setAttribute('aria-hidden', 'false');
    createUserName?.focus();
  });

  function filterUsers() {
    if (!userInventoryRows || !userInventorySearch) return;
    const term = normalizeSearchText(userInventorySearch.value.trim());
    let visible = 0;
    userInventoryRows.querySelectorAll('[data-user-row]').forEach((row) => {
      const matches = normalizeSearchText(row.dataset.userSearch || row.textContent).includes(term);
      row.hidden = !matches;
      if (matches) visible += 1;
    });
    if (userInventoryEmpty) userInventoryEmpty.hidden = visible !== 0;
    if (userInventoryCount) {
      userInventoryCount.textContent = `${visible} usuario(s)${term ? ' encontrados' : ''}`;
    }
  }

  userInventorySearch?.addEventListener('input', filterUsers);
  userInventorySearch?.addEventListener('search', filterUsers);

  document.querySelectorAll('[data-edit-user]').forEach((button) => {
    button.addEventListener('click', () => {
      editUserId.value = button.dataset.userId || '';
      editUserName.value = button.dataset.userName || '';
      editUserUsername.value = button.dataset.userUsername || '';
      editUserPassword.value = '';
      editUserPassword.type = 'password';
      toggleEditPassword.textContent = 'Ver';
      editUserRole.value = button.dataset.userRole || 'secretario';
      editUserStatus.value = button.dataset.userStatus || 'activo';
      userEditModal.classList.add('open');
      userEditModal.setAttribute('aria-hidden', 'false');
      editUserName.focus();
    });
  });

  toggleEditPassword?.addEventListener('click', () => {
    const visible = editUserPassword.type === 'text';
    editUserPassword.type = visible ? 'password' : 'text';
    toggleEditPassword.textContent = visible ? 'Ver' : 'Ocultar';
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !shouldProtectSubmit(form)) {
      return;
    }

    lockSubmitForm(form, event.submitter);
  });

  const initialHash = location.hash.replace('#', '');
  const initialModule = initialHash === 'dashboard'
    ? 'caja'
    : (initialHash === 'ventas-list'
    ? 'ventas'
    : (initialHash === 'productos-list' ? 'productos' : (initialHash === 'compras-list' ? 'compras' : (initialHash || 'caja'))));
  if (document.getElementById(initialModule)) {
    showModule(initialModule);
  }
  document.documentElement.classList.remove('app-loading');
  clearTransientUrlParams();

  renderCourtTabs();
  updateReservationMenuNotification();
  renderCalendar();
  setInterval(refreshReservationsFeed, 5000);
  setInterval(refreshCashModuleIfIdle, 7000);
  if (ventaProductoId?.value) {
    const restoredProduct = products.find((product) => Number(product.id) === Number(ventaProductoId.value));
    if (restoredProduct) {
      selectSaleProduct(restoredProduct);
    }
  }
  updateSaleTotal();
  updatePurchaseTotal();
  updateReservationSaleTotal();
  renderCart(saleCart, 'saleCartList', 'saleCartInputs');
  renderPurchaseCart();
  renderCart(reservationSaleCart, 'reservationSaleCartList', 'reservationSaleCartInputs');
  refreshReservationProductOptions();
  if (initialHash === 'ventas-list') {
    setTimeout(() => document.getElementById('ventasList')?.scrollIntoView({ block: 'start' }), 0);
  }
  if (initialHash === 'productos-list') {
    setTimeout(() => document.getElementById('productosList')?.scrollIntoView({ block: 'start' }), 0);
  }
  if (initialHash === 'compras-list') {
    setTimeout(() => document.getElementById('comprasList')?.scrollIntoView({ block: 'start' }), 0);
  }
  if (initialReservationDetailId > 0) {
    const reservationToOpen = reservations.find((item) => Number(item.id) === Number(initialReservationDetailId));
    if (reservationToOpen) {
      showModule('reservas');
      setTimeout(() => openDetailModal(reservationToOpen), 0);
    }
  }
</script>

<?php include 'partials/footer.php'; ?>
