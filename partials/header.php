<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservas de Cancha Sintetica</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (function_exists('usuarioAutenticado') && usuarioAutenticado()): ?>
  <?php $usuarioHeader = usuarioActual(); ?>
  <header class="app-header">
    <div>
      <strong>Sistema de reservas</strong>
      <span><?= e($usuarioHeader['nombre'] ?? 'Usuario') ?></span>
    </div>
    <a class="btn small secondary" href="logout.php">Cerrar sesion</a>
  </header>
<?php endif; ?>
