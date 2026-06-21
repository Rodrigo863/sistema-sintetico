<?php
require 'config.php';

if (usuarioAutenticado()) {
    redirigir('index.php');
}

$error = '';
$volver = rutaRetornoSegura($_GET['volver'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $volver = rutaRetornoSegura($_POST['volver'] ?? 'index.php');

    if (iniciarSesionSistema($usuario, $password)) {
        redirigir($volver ?: 'index.php');
    }

    $error = 'Usuario o contrasena incorrectos.';
}

$bodyClass = 'login-screen';
include 'partials/header.php';
?>

<main class="login-page">
  <section class="login-stage" aria-labelledby="loginTitle">
    <div class="login-brand">
      <div class="login-brand-icon" aria-hidden="true"><span></span></div>
      <h1 id="loginTitle">SINTETICO</h1>
      <p>Sistema de gestion de canchas</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="login-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="off">
      <input type="hidden" name="volver" value="<?= e($volver) ?>">

      <label for="loginUsuario">
        <span>Usuario</span>
        <input id="loginUsuario" type="text" name="usuario" value="<?= e($_POST['usuario'] ?? '') ?>" placeholder="Usuario" autocomplete="off" autofocus required>
      </label>

      <label for="loginPassword">
        <span>Contrasena</span>
        <input id="loginPassword" type="password" name="password" placeholder="Contrasena" autocomplete="new-password" required>
      </label>

      <button type="submit">INICIAR SESION</button>
    </form>

    <div class="login-divider" aria-hidden="true">
      <span></span>
      <i></i>
      <span></span>
    </div>
  </section>
</main>

<?php include 'partials/footer.php'; ?>
