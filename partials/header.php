<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservas de Cancha Sintetica</title>
  <?php if (!empty($useAppLoading)): ?>
    <script>
      document.documentElement.classList.add('app-loading');
      window.setTimeout(function () {
        document.documentElement.classList.remove('app-loading');
      }, 800);
    </script>
  <?php endif; ?>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
