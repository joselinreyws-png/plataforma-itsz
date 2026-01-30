
<?php 
if (!function_exists('url')) { require_once __DIR__ . '/../helpers.php'; } ?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Plataforma ITSZ') ?></title>

  <?php if (!function_exists('url')) { require_once __DIR__ . '/../helpers.php'; } ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= url('/src/assets/style.css') ?>">
</head>
<body>

