<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = conectarDB();

function redirigirErrorCliente(string $mensaje): void
{
    $_SESSION['cliente_error'] = $mensaje;
    redirigir('index.php#clientes');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $estado = $_POST['estado'] ?? 'activo';
    $notas = trim($_POST['notas'] ?? '');

    if ($nombre === '') {
        redirigirErrorCliente('El nombre es obligatorio.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirigirErrorCliente('El email no tiene un formato valido.');
    }

    if (!in_array($estado, ['activo', 'inactivo'], true)) {
        $estado = 'activo';
    }

    $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
    $stmt->execute([$nombre, $id]);
    $clienteExistente = $stmt->fetch();

    if ($clienteExistente) {
        redirigirErrorCliente('Ya existe otro cliente con ese nombre: ' . $clienteExistente['nombre'] . '.');
    }

    if ($telefono !== '') {
        $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE telefono = ? AND id <> ? LIMIT 1');
        $stmt->execute([$telefono, $id]);
        $clienteExistente = $stmt->fetch();

        if ($clienteExistente) {
            redirigirErrorCliente('Ya existe otro cliente con ese telefono: ' . $clienteExistente['nombre'] . '.');
        }
    }

    if ($documento !== '') {
        $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE documento = ? AND id <> ? LIMIT 1');
        $stmt->execute([$documento, $id]);
        $clienteExistente = $stmt->fetch();

        if ($clienteExistente) {
            redirigirErrorCliente('Ya existe otro cliente con ese documento: ' . $clienteExistente['nombre'] . '.');
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE clientes
         SET nombre = ?, telefono = ?, email = ?, documento = ?, direccion = ?, estado = ?, notas = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $nombre,
        $telefono,
        $email ?: null,
        $documento ?: null,
        $direccion ?: null,
        $estado,
        $notas ?: null,
        $id,
    ]);

    redirigir('index.php');
}

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die('Cliente no encontrado.');
}

include 'partials/header.php';
?>

<main class="container">
  <section class="panel">
    <h1>Editar cliente</h1>
    <form action="editar_cliente.php?id=<?= $id ?>" method="post" class="grid">
      <label>Nombre <input type="text" name="nombre" value="<?= e($cliente['nombre']) ?>" required></label>
      <label>Tel&eacute;fono <input type="text" name="telefono" value="<?= e($cliente['telefono']) ?>"></label>
      <label>Email <input type="email" name="email" value="<?= e($cliente['email']) ?>"></label>
      <label>Documento <input type="text" name="documento" value="<?= e($cliente['documento']) ?>"></label>
      <label>Direcci&oacute;n <input type="text" name="direccion" value="<?= e($cliente['direccion']) ?>"></label>
      <label>
        Estado
        <select name="estado">
          <option value="activo" <?= $cliente['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
          <option value="inactivo" <?= $cliente['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
        </select>
      </label>
      <label class="wide">Notas <textarea name="notas" rows="4"><?= e($cliente['notas']) ?></textarea></label>
      <button type="submit">Actualizar</button>
      <a class="btn secondary" href="index.php">Cancelar</a>
    </form>
  </section>
</main>

<?php include 'partials/footer.php'; ?>
