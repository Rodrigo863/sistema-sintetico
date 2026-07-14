<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#configuracion');
}

function volverFacturacion(string $mensaje, bool $error = true): void
{
    $clave = $error ? 'facturacion_error' : 'facturacion_mensaje';
    redirigir('index.php?' . $clave . '=' . rawurlencode($mensaje) . '#configuracion');
}

$modalidad = $_POST['modalidad_facturacion'] ?? 'pendiente';
$anchoPapel = $_POST['ancho_papel_mm'] ?? '80';
$razonSocial = trim($_POST['razon_social'] ?? '');
$nombreFantasia = trim($_POST['nombre_fantasia'] ?? '');
$ruc = trim($_POST['ruc'] ?? '');
$actividadEconomica = trim($_POST['actividad_economica'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$numeracionId = (int)($_POST['numeracion_id'] ?? 0);
$timbrado = trim($_POST['timbrado'] ?? '');
$establecimiento = trim($_POST['establecimiento'] ?? '');
$puntoExpedicion = trim($_POST['punto_expedicion'] ?? '');
$numeroDesde = max(1, (int)($_POST['numero_desde'] ?? 1));
$numeroHasta = max(1, (int)($_POST['numero_hasta'] ?? 9999999));
$proximoNumero = max(1, (int)($_POST['proximo_numero'] ?? $numeroDesde));
$vigenciaDesde = trim($_POST['vigencia_desde'] ?? '');
$vigenciaHasta = trim($_POST['vigencia_hasta'] ?? '');
$estadoNumeracion = $_POST['estado_numeracion'] ?? 'pendiente';

if (!in_array($modalidad, ['pendiente', 'autoimpresor', 'ekuatia', 'ekuatia_i'], true)) {
    volverFacturacion('Modalidad de facturacion invalida.');
}
if (!in_array($anchoPapel, ['58', '80'], true)) {
    volverFacturacion('Selecciona un ancho de papel valido.');
}
if (!in_array($estadoNumeracion, ['pendiente', 'activo', 'inactivo'], true)) {
    volverFacturacion('Estado de numeracion invalido.');
}
if ($ruc !== '' && !preg_match('/^[0-9-]+$/', $ruc)) {
    volverFacturacion('El RUC solo puede contener numeros y guion.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    volverFacturacion('Ingresa un email valido.');
}
if ($timbrado !== '' && !preg_match('/^[0-9]+$/', $timbrado)) {
    volverFacturacion('El timbrado solo puede contener numeros.');
}
foreach (['establecimiento' => $establecimiento, 'punto de expedicion' => $puntoExpedicion] as $nombre => $valor) {
    if ($valor !== '' && !preg_match('/^[0-9]{3}$/', $valor)) {
        volverFacturacion("El {$nombre} debe tener exactamente 3 digitos.");
    }
}
if ($numeroDesde > $numeroHasta) {
    volverFacturacion('El numero desde no puede ser mayor que el numero hasta.');
}
if ($proximoNumero < $numeroDesde || $proximoNumero > $numeroHasta) {
    volverFacturacion('El proximo numero debe estar dentro del rango autorizado.');
}
if ($vigenciaDesde !== '' && $vigenciaHasta !== '' && $vigenciaDesde > $vigenciaHasta) {
    volverFacturacion('La vigencia desde no puede ser posterior a la vigencia hasta.');
}

$pdo = conectarDB();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO empresa_configuracion
            (id, razon_social, nombre_fantasia, ruc, actividad_economica, direccion, telefono, email, modalidad_facturacion, ancho_papel_mm)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            razon_social = VALUES(razon_social), nombre_fantasia = VALUES(nombre_fantasia), ruc = VALUES(ruc),
            actividad_economica = VALUES(actividad_economica), direccion = VALUES(direccion), telefono = VALUES(telefono),
            email = VALUES(email), modalidad_facturacion = VALUES(modalidad_facturacion), ancho_papel_mm = VALUES(ancho_papel_mm)"
    );
    $stmt->execute([
        $razonSocial ?: null,
        $nombreFantasia ?: null,
        $ruc ?: null,
        $actividadEconomica ?: null,
        $direccion ?: null,
        $telefono ?: null,
        $email ?: null,
        $modalidad,
        $anchoPapel,
    ]);

    $valoresNumeracion = [
        $timbrado ?: null,
        $establecimiento ?: null,
        $puntoExpedicion ?: null,
        $numeroDesde,
        $numeroHasta,
        $proximoNumero - 1,
        $vigenciaDesde ?: null,
        $vigenciaHasta ?: null,
        $estadoNumeracion,
    ];

    if ($numeracionId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE comprobante_numeraciones
             SET timbrado = ?, establecimiento = ?, punto_expedicion = ?, numero_desde = ?, numero_hasta = ?,
                 ultimo_numero = ?, vigencia_desde = ?, vigencia_hasta = ?, estado = ?
             WHERE id = ? AND empresa_id = 1 AND tipo_documento = 'factura'"
        );
        $stmt->execute([...$valoresNumeracion, $numeracionId]);
        if ($stmt->rowCount() === 0) {
            $stmtVerificar = $pdo->prepare("SELECT id FROM comprobante_numeraciones WHERE id = ? AND empresa_id = 1 AND tipo_documento = 'factura'");
            $stmtVerificar->execute([$numeracionId]);
            if (!$stmtVerificar->fetch()) {
                throw new RuntimeException('No se encontro la numeracion de factura para actualizar.');
            }
        }
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO comprobante_numeraciones
                (empresa_id, tipo_documento, timbrado, establecimiento, punto_expedicion, numero_desde, numero_hasta,
                 ultimo_numero, vigencia_desde, vigencia_hasta, estado)
             VALUES (1, 'factura', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute($valoresNumeracion);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    volverFacturacion('No se pudo guardar la configuracion de facturacion. Revisa los datos e intenta nuevamente.');
}

volverFacturacion('Configuracion de facturacion guardada correctamente.', false);
