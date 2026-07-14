<?php

date_default_timezone_set('America/Asuncion');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DB_HOST = 'localhost';
const DB_NAME = 'sistema_clientes';
const DB_USER = 'root';
const DB_PASS = '';

const ADMIN_USER = 'administrador';
const ADMIN_INITIAL_PASSWORD = 'admin123';

function conectarDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        asegurarTablaUsuarios($pdo);

        return $pdo;
    } catch (PDOException $e) {
        die('No se pudo conectar a la base de datos: ' . $e->getMessage());
    }
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function formatearGuaranies(float|int|string|null $valor): string
{
    return number_format((float)($valor ?? 0), 0, ',', '.');
}

function leerMonto(mixed $valor): float
{
    $texto = preg_replace('/[^\d,.-]/', '', (string)($valor ?? '0'));
    $texto = str_replace('.', '', $texto);
    $texto = str_replace(',', '.', $texto);

    return (float)$texto;
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function asegurarTablaUsuarios(PDO $pdo): void
{
    static $preparada = false;

    if ($preparada) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS usuarios (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $tipoRol = (string)$pdo->query(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol'"
    )->fetchColumn();
    if (!str_contains($tipoRol, 'secretario')) {
        $pdo->exec("ALTER TABLE usuarios MODIFY rol ENUM('administrador', 'usuario', 'secretario') NOT NULL DEFAULT 'secretario'");
        $pdo->exec("UPDATE usuarios SET rol = 'secretario' WHERE rol = 'usuario'");
        $pdo->exec("ALTER TABLE usuarios MODIFY rol ENUM('administrador', 'secretario') NOT NULL DEFAULT 'secretario'");
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
    $stmt->execute([ADMIN_USER]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, usuario, password_hash, rol, estado)
             VALUES (?, ?, ?, 'administrador', 'activo')"
        );
        $stmt->execute(['Administrador', ADMIN_USER, password_hash(ADMIN_INITIAL_PASSWORD, PASSWORD_DEFAULT)]);
    }

    $preparada = true;
}

function rutaRetornoSegura(?string $url): string
{
    $url = trim((string)($url ?? ''));

    if ($url === '' || str_contains($url, "\r") || str_contains($url, "\n")) {
        return 'index.php';
    }

    $partes = parse_url($url);
    if ($partes === false || isset($partes['scheme']) || isset($partes['host'])) {
        return 'index.php';
    }

    return $url;
}

function usuarioAutenticado(): bool
{
    return !empty($_SESSION['usuario']) && !empty($_SESSION['usuario']['id']);
}

function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function esAdministrador(): bool
{
    return (usuarioActual()['rol'] ?? '') === 'administrador';
}

function requiereAdministradorModulo(string $modulo = 'caja'): void
{
    requiereLogin();

    if (esAdministrador()) {
        return;
    }

    $_SESSION['acceso_error'] = 'Tu rol de secretario no tiene permiso para acceder a esa funcion.';
    redirigir('index.php#' . preg_replace('/[^a-z0-9_-]/i', '', $modulo));
}

function iniciarSesionSistema(string $usuario, string $password): bool
{
    $usuario = trim($usuario);
    $pdo = conectarDB();

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado = 'activo' LIMIT 1");
    $stmt->execute([$usuario]);
    $usuarioSistema = $stmt->fetch();

    if (!$usuarioSistema || !password_verify($password, $usuarioSistema['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int)$usuarioSistema['id'],
        'nombre' => $usuarioSistema['nombre'],
        'usuario' => $usuarioSistema['usuario'],
        'rol' => $usuarioSistema['rol'],
    ];
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([(int)$usuarioSistema['id']]);

    return true;
}

function requiereAdministrador(): void
{
    requiereLogin();

    if (esAdministrador()) {
        return;
    }

    redirigir('index.php?usuario_error=' . urlencode('Solo el administrador puede realizar esta accion.') . '#configuracion');
}

function cerrarSesionSistema(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function requiereLogin(): void
{
    if (usuarioAutenticado()) {
        return;
    }

    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        http_response_code(401);
        exit('Sesion requerida');
    }

    $actual = basename(parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH) ?: 'index.php');
    $destino = $actual !== 'index.php' ? '?volver=' . urlencode(rutaRetornoSegura($_SERVER['REQUEST_URI'] ?? 'index.php')) : '';
    redirigir('login.php' . $destino);
}

$archivoActual = basename($_SERVER['SCRIPT_NAME'] ?? '');
$rutasPublicas = [
    'login.php',
    'logout.php',
    'reservas_publicas.php',
    'guardar_reserva_publica.php',
];

if (!in_array($archivoActual, $rutasPublicas, true)) {
    requiereLogin();
}

$rutasSoloAdministrador = [
    'guardar_proveedor.php', 'guardar_proveedor_rapido.php', 'editar_proveedor.php', 'eliminar_proveedor.php',
    'guardar_cancha.php', 'editar_cancha.php', 'eliminar_cancha.php',
    'guardar_categoria.php', 'editar_categoria.php', 'eliminar_categoria.php',
    'guardar_producto.php', 'guardar_producto_rapido.php', 'editar_producto.php', 'eliminar_producto.php', 'cambiar_estado_producto.php',
    'guardar_compra.php', 'editar_compra.php', 'anular_compra.php',
    'reportes_pdf.php', 'reportes_descargar_pdf.php',
];

if (in_array($archivoActual, $rutasSoloAdministrador, true)) {
    requiereAdministradorModulo();
}
