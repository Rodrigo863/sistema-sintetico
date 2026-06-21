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

function textoPdf(string $texto): string
{
    $texto = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto ?: '');
}

final class ReportePdf
{
    private float $w = 842;
    private float $h = 595;
    private array $pages = [];
    private string $content = '';
    private float $y = 32;

    public function __construct(private string $titulo, private string $subtitulo)
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }

        $this->content = '';
        $this->y = 32;
        $this->text(28, $this->y, $this->titulo, 16, true);
        $this->y += 16;
        $this->text(28, $this->y, $this->subtitulo, 9);
        $this->y += 18;
        $this->line(28, $this->y, 814, $this->y);
        $this->y += 14;
    }

    public function section(string $titulo): void
    {
        $this->ensureSpace(24);
        $this->y += 4;
        $this->text(28, $this->y, $titulo, 12, true);
        $this->y += 12;
    }

    public function summary(array $items): void
    {
        $x = 28;
        foreach ($items as $item) {
            [$label, $value] = $item;
            $this->rect($x, $this->y, 188, 42);
            $this->text($x + 8, $this->y + 13, $label, 8);
            $this->text($x + 8, $this->y + 31, $value, 14, true);
            $x += 196;
        }
        $this->y += 56;
    }

    public function table(array $columns, array $rows): void
    {
        $this->ensureSpace(34);
        $x = 28;
        $headerY = $this->y;
        $this->rect(28, $headerY, 786, 16, true);
        foreach ($columns as $column) {
            $this->text($x + 3, $headerY + 11, $column['label'], 7, true);
            $x += $column['w'];
        }
        $this->y += 16;

        if (empty($rows)) {
            $this->row($columns, [['text' => 'Sin datos para el rango seleccionado.', 'colspan' => count($columns)]]);
            return;
        }

        foreach ($rows as $row) {
            $this->row($columns, $row);
        }

        $this->y += 6;
    }

    private function row(array $columns, array $row): void
    {
        $this->ensureSpace(17);
        $x = 28;
        $rowHeight = 16;
        $this->line(28, $this->y, 814, $this->y);

        if (isset($row[0]['colspan'])) {
            $this->text(31, $this->y + 11, $row[0]['text'], 7);
            $this->y += $rowHeight;
            return;
        }

        foreach ($columns as $i => $column) {
            $value = (string)($row[$i] ?? '');
            $maxChars = max(5, (int)floor($column['w'] / 4.2));
            if (strlen($value) > $maxChars) {
                $value = substr($value, 0, max(0, $maxChars - 3)) . '...';
            }
            $align = $column['align'] ?? 'left';
            $textX = $align === 'right' ? $x + $column['w'] - 4 - (strlen($value) * 3.6) : $x + 3;
            $this->text(max($x + 3, $textX), $this->y + 11, $value, 7);
            $x += $column['w'];
        }

        $this->y += $rowHeight;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y + $height > 560) {
            $this->addPage();
        }
    }

    private function text(float $x, float $y, string $text, int $size = 9, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $pdfY = $this->h - $y;
        $this->content .= "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$pdfY} Tm (" . textoPdf($text) . ") Tj ET\n";
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->content .= "0.5 w {$x1} " . ($this->h - $y1) . " m {$x2} " . ($this->h - $y2) . " l S\n";
    }

    private function rect(float $x, float $y, float $w, float $h, bool $filled = false): void
    {
        $pdfY = $this->h - $y - $h;
        $style = $filled ? '0.90 0.94 0.97 rg' : '1 1 1 rg';
        $this->content .= "{$style} {$x} {$pdfY} {$w} {$h} re f 0.75 0.82 0.90 RG {$x} {$pdfY} {$w} {$h} re S\n";
    }

    public function output(): string
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->content = '';
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($i) => (($i * 2) + 3) . ' 0 R', array_keys($this->pages))) . '] /Count ' . count($this->pages) . ' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($this->pages as $content) {
            $contentId = count($objects) + 2;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->w} {$this->h}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string)$offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS cantidad_ventas, COALESCE(SUM(total), 0) AS total_ventas
     FROM ventas
     WHERE estado <> 'anulada' AND DATE(fecha_venta) BETWEEN ? AND ?"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$resumenVentas = $stmt->fetch() ?: [];

$stmt = $pdo->prepare(
    "SELECT v.id AS venta_id, v.fecha_venta, COALESCE(cl.nombre, 'Sin cliente') AS cliente,
            r.id AS reserva_id, ca.nombre AS cancha, p.nombre AS producto, vd.tipo_venta,
            vd.cantidad, vd.unidades_descontadas, vd.precio_unitario,
            COALESCE(vd.costo_unitario, p.precio_compra, 0) AS costo_unitario, vd.subtotal,
            (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0)) AS costo_total,
            (vd.subtotal - (vd.unidades_descontadas * COALESCE(vd.costo_unitario, p.precio_compra, 0))) AS ganancia
     FROM venta_detalles vd
     INNER JOIN ventas v ON v.id = vd.venta_id
     INNER JOIN productos p ON p.id = vd.producto_id
     LEFT JOIN clientes cl ON cl.id = v.cliente_id
     LEFT JOIN reservas r ON r.id = v.reserva_id
     LEFT JOIN canchas ca ON ca.id = r.cancha_id
     WHERE v.estado <> 'anulada' AND DATE(v.fecha_venta) BETWEEN ? AND ?
     ORDER BY v.fecha_venta ASC, v.id ASC, vd.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$ventasDetalle = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT p.id AS pago_id, p.fecha_pago, p.monto, p.metodo, p.concepto,
            r.id AS reserva_id, r.fecha AS reserva_fecha, r.hora_inicio, r.hora_fin,
            c.nombre AS cliente, ca.nombre AS cancha
     FROM pagos p
     INNER JOIN reservas r ON r.id = p.reserva_id
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     WHERE DATE(p.fecha_pago) BETWEEN ? AND ?
     ORDER BY p.fecha_pago ASC, p.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$pagosDetalle = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.precio_total, r.estado,
            c.nombre AS cliente, ca.nombre AS cancha,
            COALESCE(pagos.total_pagado, 0) AS total_pagado,
            COALESCE(consumos.total_consumo, 0) AS total_consumo,
            r.precio_total + COALESCE(consumos.total_consumo, 0) - COALESCE(pagos.total_pagado, 0) AS saldo
     FROM reservas r
     INNER JOIN clientes c ON c.id = r.cliente_id
     INNER JOIN canchas ca ON ca.id = r.cancha_id
     LEFT JOIN (SELECT reserva_id, SUM(monto) AS total_pagado FROM pagos GROUP BY reserva_id) pagos ON pagos.reserva_id = r.id
     LEFT JOIN (SELECT reserva_id, SUM(total) AS total_consumo FROM ventas WHERE reserva_id IS NOT NULL AND estado <> 'anulada' GROUP BY reserva_id) consumos ON consumos.reserva_id = r.id
     WHERE r.fecha BETWEEN ? AND ?
     ORDER BY r.fecha ASC, r.hora_inicio ASC, r.id ASC"
);
$stmt->execute([$reporteDesde, $reporteHasta]);
$reservasDetalle = $stmt->fetchAll();

$gananciaVentas = array_sum(array_map(static fn(array $fila): float => (float)$fila['ganancia'], $ventasDetalle));
$costoVentas = array_sum(array_map(static fn(array $fila): float => (float)$fila['costo_total'], $ventasDetalle));
$totalPagosReservas = array_sum(array_map(static fn(array $fila): float => (float)$fila['monto'], $pagosDetalle));
$reservasCobradas = count(array_unique(array_map(static fn(array $fila): int => (int)$fila['reserva_id'], $pagosDetalle)));
$tituloRango = date('d/m/Y', strtotime($reporteDesde)) . ' al ' . date('d/m/Y', strtotime($reporteHasta));

$pdf = new ReportePdf('Reporte detallado', 'Sistema de reservas - generado ' . date('d/m/Y H:i') . ' - rango ' . $tituloRango);
$pdf->summary([
    ['Total ventas', formatearGuaranies($resumenVentas['total_ventas'] ?? 0)],
    ['Costo ventas', formatearGuaranies($costoVentas)],
    ['Ganancia ventas', formatearGuaranies($gananciaVentas)],
    ['Ingresos reservas', formatearGuaranies($totalPagosReservas)],
]);

$pdf->section('Detalle de ventas y ganancia');
$pdf->table(
    [
        ['label' => 'Fecha', 'w' => 72], ['label' => 'Venta', 'w' => 34], ['label' => 'Cliente/Reserva', 'w' => 112],
        ['label' => 'Producto', 'w' => 110], ['label' => 'Tipo', 'w' => 38], ['label' => 'Cant.', 'w' => 36, 'align' => 'right'],
        ['label' => 'Unid.', 'w' => 36, 'align' => 'right'], ['label' => 'P. venta', 'w' => 56, 'align' => 'right'],
        ['label' => 'Subtotal', 'w' => 58, 'align' => 'right'], ['label' => 'Costo unit.', 'w' => 64, 'align' => 'right'],
        ['label' => 'Costo', 'w' => 58, 'align' => 'right'], ['label' => 'Ganancia', 'w' => 62, 'align' => 'right'],
    ],
    array_map(static function (array $fila): array {
        return [
            date('d/m/Y H:i', strtotime($fila['fecha_venta'])),
            '#' . $fila['venta_id'],
            $fila['cliente'] . ($fila['reserva_id'] ? ' / R#' . $fila['reserva_id'] . ' ' . $fila['cancha'] : ' / directa'),
            $fila['producto'],
            $fila['tipo_venta'],
            (string)(int)$fila['cantidad'],
            (string)(int)$fila['unidades_descontadas'],
            formatearGuaranies($fila['precio_unitario']),
            formatearGuaranies($fila['subtotal']),
            formatearGuaranies($fila['costo_unitario']),
            formatearGuaranies($fila['costo_total']),
            formatearGuaranies($fila['ganancia']),
        ];
    }, $ventasDetalle)
);

$pdf->section('Pagos de reservas');
$pdf->table(
    [
        ['label' => 'Fecha pago', 'w' => 82], ['label' => 'Pago', 'w' => 38], ['label' => 'Reserva', 'w' => 46],
        ['label' => 'Cliente', 'w' => 116], ['label' => 'Cancha', 'w' => 72], ['label' => 'Fecha reserva', 'w' => 116],
        ['label' => 'Concepto', 'w' => 70], ['label' => 'Metodo', 'w' => 70], ['label' => 'Monto', 'w' => 70, 'align' => 'right'],
    ],
    array_map(static function (array $fila): array {
        return [
            date('d/m/Y H:i', strtotime($fila['fecha_pago'])),
            '#' . $fila['pago_id'],
            '#' . $fila['reserva_id'],
            $fila['cliente'],
            $fila['cancha'],
            date('d/m/Y', strtotime($fila['reserva_fecha'])) . ' ' . substr($fila['hora_inicio'], 0, 5) . '-' . substr($fila['hora_fin'], 0, 5),
            $fila['concepto'],
            $fila['metodo'],
            formatearGuaranies($fila['monto']),
        ];
    }, $pagosDetalle)
);

$pdf->section('Reservas del rango');
$pdf->table(
    [
        ['label' => 'Reserva', 'w' => 48], ['label' => 'Fecha', 'w' => 62], ['label' => 'Horario', 'w' => 64],
        ['label' => 'Cliente', 'w' => 118], ['label' => 'Cancha', 'w' => 70], ['label' => 'Estado', 'w' => 62],
        ['label' => 'Alquiler', 'w' => 68, 'align' => 'right'], ['label' => 'Consumos', 'w' => 68, 'align' => 'right'],
        ['label' => 'Pagado', 'w' => 68, 'align' => 'right'], ['label' => 'Saldo', 'w' => 68, 'align' => 'right'],
    ],
    array_map(static function (array $fila): array {
        return [
            '#' . $fila['id'],
            date('d/m/Y', strtotime($fila['fecha'])),
            substr($fila['hora_inicio'], 0, 5) . '-' . substr($fila['hora_fin'], 0, 5),
            $fila['cliente'],
            $fila['cancha'],
            $fila['estado'],
            formatearGuaranies($fila['precio_total']),
            formatearGuaranies($fila['total_consumo']),
            formatearGuaranies($fila['total_pagado']),
            formatearGuaranies(max(0, (float)$fila['saldo'])),
        ];
    }, $reservasDetalle)
);

$filename = 'reporte_detallado_' . $reporteDesde . '_' . $reporteHasta . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $pdf->output();
