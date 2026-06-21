<?php
require 'config.php';

$pdo = conectarDB();
$pdo->exec("ALTER TABLE venta_detalles ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL AFTER precio_unitario");

$hoy = date('Y-m-d');
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
    "SELECT COUNT(*) AS cantidad_ventas, COALESCE(SUM(total), 0) AS total_ventas
     FROM ventas
     WHERE estado <> 'anulada'
       AND DATE(fecha_venta) BETWEEN ? AND ?"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$resumenVentas = $stmt->fetch() ?: ['cantidad_ventas' => 0, 'total_ventas' => 0];

$stmt = $pdo->prepare(
    "SELECT v.id AS venta_id, v.fecha_venta, v.total AS total_venta, v.metodo,
            COALESCE(cl.nombre, 'Sin cliente') AS cliente,
            r.id AS reserva_id, r.fecha AS reserva_fecha, ca.nombre AS cancha,
            p.nombre AS producto, vd.tipo_venta, vd.cantidad, vd.unidades_descontadas,
            vd.precio_unitario, COALESCE(vd.costo_unitario, p.precio_compra, 0) AS costo_unitario,
            vd.subtotal,
            (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0)) AS costo_total,
            (vd.subtotal - (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0))) AS ganancia
     FROM venta_detalles vd
     INNER JOIN ventas v ON v.id = vd.venta_id
     INNER JOIN productos p ON p.id = vd.producto_id
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     LEFT JOIN reservas r ON r.id = v.reserva_id
     LEFT JOIN canchas ca ON ca.id = r.cancha_id
     WHERE v.estado <> 'anulada'
       AND DATE(v.fecha_venta) BETWEEN ? AND ?
     ORDER BY v.fecha_venta ASC, v.id ASC, vd.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$ventasDetalle = $stmt->fetchAll();
$gananciaVentas = array_sum(array_map(static fn(array $fila): float => (float)$fila['ganancia'], $ventasDetalle));
$costoVentas = array_sum(array_map(static fn(array $fila): float => (float)$fila['costo_total'], $ventasDetalle));

$stmt = $pdo->prepare(
    "SELECT p.id AS pago_id, p.fecha_pago, p.monto, p.metodo, p.concepto,
            r.id AS reserva_id, r.fecha AS reserva_fecha, r.hora_inicio, r.hora_fin, r.precio_total, r.estado,
            c.nombre AS cliente, ca.nombre AS cancha,
            COALESCE(consumos.total_consumo, 0) AS total_consumo
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     LEFT JOIN (
       SELECT reserva_id, SUM(total) AS total_consumo
       FROM ventas
       WHERE reserva_id IS NOT NULL AND estado <> 'anulada'
       GROUP BY reserva_id
     ) consumos ON consumos.reserva_id = r.id
     WHERE DATE(p.fecha_pago) BETWEEN ? AND ?
     ORDER BY p.fecha_pago ASC, p.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$pagosDetalle = $stmt->fetchAll();
$totalPagosReservas = array_sum(array_map(static fn(array $fila): float => (float)$fila['monto'], $pagosDetalle));
$reservasCobradas = count(array_unique(array_map(static fn(array $fila): int => (int)$fila['reserva_id'], $pagosDetalle)));

$stmt = $pdo->prepare(
    "SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.precio_total, r.estado,
            c.nombre AS cliente, ca.nombre AS cancha,
            COALESCE(pagos.total_pagado, 0) AS total_pagado,
            COALESCE(consumos.total_consumo, 0) AS total_consumo,
            r.precio_total + COALESCE(consumos.total_consumo, 0) - COALESCE(pagos.total_pagado, 0) AS saldo
     FROM reservas r
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     LEFT JOIN (
       SELECT reserva_id, SUM(monto) AS total_pagado
       FROM pagos
       GROUP BY reserva_id
     ) pagos ON pagos.reserva_id = r.id
     LEFT JOIN (
       SELECT reserva_id, SUM(total) AS total_consumo
       FROM ventas
       WHERE reserva_id IS NOT NULL AND estado <> 'anulada'
       GROUP BY reserva_id
     ) consumos ON consumos.reserva_id = r.id
     WHERE r.fecha BETWEEN ? AND ?
     ORDER BY r.fecha ASC, r.hora_inicio ASC, r.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reservasDetalle = $stmt->fetchAll();

$tituloRango = date('d/m/Y', strtotime($reporteDesde)) . ' al ' . date('d/m/Y', strtotime($reporteHasta));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte detallado <?= e($tituloRango) ?></title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; color: #132033; background: #f4f7fb; font-size: 12px; }
    .toolbar { position: sticky; top: 0; display: flex; justify-content: space-between; gap: 10px; padding: 10px 16px; background: #0f766e; color: #fff; z-index: 2; }
    .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #0f766e; text-decoration: none; font-weight: 700; cursor: pointer; }
    main { padding: 18px; }
    h1 { margin: 0 0 4px; font-size: 22px; }
    h2 { margin: 22px 0 8px; font-size: 15px; color: #0f766e; }
    .muted { color: #64748b; }
    .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 14px 0; }
    .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: #fff; }
    .card span { display: block; color: #64748b; margin-bottom: 4px; }
    .card strong { display: block; font-size: 18px; color: #0f766e; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; background: #fff; }
    th, td { border: 1px solid #d8e0ec; padding: 5px 6px; text-align: left; vertical-align: top; }
    th { background: #e8eef5; font-weight: 700; }
    .num { text-align: right; white-space: nowrap; }
    .nowrap { white-space: nowrap; }
    .negative { color: #b91c1c; font-weight: 700; }
    .positive { color: #047857; font-weight: 700; }
    .section-note { margin: -4px 0 8px; color: #64748b; }
    @media print {
      @page { size: landscape; margin: 10mm; }
      body { background: #fff; font-size: 10px; }
      main { padding: 0; }
      .toolbar { display: none; }
      .card { break-inside: avoid; }
      h2 { break-after: avoid; }
      table { page-break-inside: auto; }
      tr { page-break-inside: avoid; page-break-after: auto; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <div>Reporte detallado - <?= e($tituloRango) ?></div>
    <div>
      <a href="index.php?reporte_desde=<?= e($reporteDesde) ?>&reporte_hasta=<?= e($reporteHasta) ?>#reportes">Volver</a>
      <a href="reportes_descargar_pdf.php?reporte_desde=<?= e($reporteDesde) ?>&reporte_hasta=<?= e($reporteHasta) ?>">Descargar PDF</a>
      <button type="button" onclick="window.print()">Imprimir</button>
    </div>
  </div>

  <main>
    <h1>Reporte detallado</h1>
    <p class="muted">Sistema de reservas - generado el <?= e(date('d/m/Y H:i')) ?> - rango <?= e($tituloRango) ?></p>

    <section class="summary">
      <div class="card"><span>Total ventas</span><strong><?= formatearGuaranies($resumenVentas['total_ventas'] ?? 0) ?></strong><small><?= (int)($resumenVentas['cantidad_ventas'] ?? 0) ?> venta(s)</small></div>
      <div class="card"><span>Costo ventas</span><strong><?= formatearGuaranies($costoVentas) ?></strong><small>Costo unitario por unidades</small></div>
      <div class="card"><span>Ganancia ventas</span><strong><?= formatearGuaranies($gananciaVentas) ?></strong><small>Venta menos costo</small></div>
      <div class="card"><span>Ingresos reservas</span><strong><?= formatearGuaranies($totalPagosReservas) ?></strong><small><?= $reservasCobradas ?> reserva(s), <?= count($pagosDetalle) ?> pago(s)</small></div>
    </section>

    <h2>Detalle de ventas y ganancia</h2>
    <p class="section-note">Incluye ventas normales y consumos vinculados a reservas. No incluye ventas anuladas.</p>
    <table>
      <thead>
        <tr>
          <th>Fecha</th><th>Venta</th><th>Cliente / reserva</th><th>Producto</th><th>Tipo</th>
          <th class="num">Cant.</th><th class="num">Unid.</th><th class="num">P. venta</th>
          <th class="num">Subtotal</th><th class="num">Costo unit.</th><th class="num">Costo</th><th class="num">Ganancia</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ventasDetalle)): ?>
          <tr><td colspan="12">Sin ventas en el rango seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($ventasDetalle as $fila): ?>
            <?php $ganancia = (float)$fila['ganancia']; ?>
            <tr>
              <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($fila['fecha_venta']))) ?></td>
              <td>#<?= (int)$fila['venta_id'] ?></td>
              <td>
                <?= e($fila['cliente']) ?><br>
                <?= $fila['reserva_id'] ? 'Reserva #' . (int)$fila['reserva_id'] . ' - ' . e($fila['cancha'] ?? '-') : 'Venta directa' ?>
              </td>
              <td><?= e($fila['producto']) ?></td>
              <td><?= e($fila['tipo_venta']) ?></td>
              <td class="num"><?= (int)$fila['cantidad'] ?></td>
              <td class="num"><?= (int)$fila['unidades_descontadas'] ?></td>
              <td class="num"><?= formatearGuaranies($fila['precio_unitario']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['subtotal']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['costo_unitario']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['costo_total']) ?></td>
              <td class="num <?= $ganancia < 0 ? 'negative' : 'positive' ?>"><?= formatearGuaranies($ganancia) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <h2>Pagos de reservas</h2>
    <p class="section-note">Filtrado por fecha de pago. Estos importes explican la tarjeta Ingresos reservas.</p>
    <table>
      <thead>
        <tr>
          <th>Fecha pago</th><th>Pago</th><th>Reserva</th><th>Cliente</th><th>Cancha</th>
          <th>Fecha reserva</th><th>Concepto</th><th>M&eacute;todo</th><th class="num">Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pagosDetalle)): ?>
          <tr><td colspan="9">Sin pagos de reservas en el rango seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($pagosDetalle as $fila): ?>
            <tr>
              <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($fila['fecha_pago']))) ?></td>
              <td>#<?= (int)$fila['pago_id'] ?></td>
              <td>#<?= (int)$fila['reserva_id'] ?></td>
              <td><?= e($fila['cliente']) ?></td>
              <td><?= e($fila['cancha']) ?></td>
              <td><?= e(date('d/m/Y', strtotime($fila['reserva_fecha']))) ?> <?= e(substr($fila['hora_inicio'], 0, 5)) ?>-<?= e(substr($fila['hora_fin'], 0, 5)) ?></td>
              <td><?= e($fila['concepto']) ?></td>
              <td><?= e($fila['metodo']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['monto']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <h2>Reservas del rango</h2>
    <p class="section-note">Filtrado por fecha de alquiler. Muestra alquiler, consumos, pagos y saldo.</p>
    <table>
      <thead>
        <tr>
          <th>Reserva</th><th>Fecha</th><th>Horario</th><th>Cliente</th><th>Cancha</th><th>Estado</th>
          <th class="num">Alquiler</th><th class="num">Consumos</th><th class="num">Pagado</th><th class="num">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reservasDetalle)): ?>
          <tr><td colspan="10">Sin reservas en el rango seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($reservasDetalle as $fila): ?>
            <?php $saldo = max(0, (float)$fila['saldo']); ?>
            <tr>
              <td>#<?= (int)$fila['id'] ?></td>
              <td><?= e(date('d/m/Y', strtotime($fila['fecha']))) ?></td>
              <td><?= e(substr($fila['hora_inicio'], 0, 5)) ?>-<?= e(substr($fila['hora_fin'], 0, 5)) ?></td>
              <td><?= e($fila['cliente']) ?></td>
              <td><?= e($fila['cancha']) ?></td>
              <td><?= e($fila['estado']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['precio_total']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['total_consumo']) ?></td>
              <td class="num"><?= formatearGuaranies($fila['total_pagado']) ?></td>
              <td class="num"><?= formatearGuaranies($saldo) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>
