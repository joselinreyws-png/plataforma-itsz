<?php
// src/helpers.php
declare(strict_types=1);

if (!function_exists('go')) {
  function go(string $url): void {
    header("Location: {$url}");
    exit;
  }
}

// Normaliza Y-m-d seguro; si no matchea, devuelve hoy
function ymd_or_today(?string $s): string {
  if ($s && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
  return date('Y-m-d');
}

// Devuelve [anio, mes, quincena(1|2)] desde fecha Y-m-d
function periodo_from_date(string $ymd): array {
  [$y, $m, $d] = array_map('intval', explode('-', $ymd));
  $q = ($d <= 15) ? 1 : 2;
  return [$y, $m, $q];
}

function is_admin(): bool {
  return !empty($_SESSION['admin']);
}

function is_user(): bool {
  return !empty($_SESSION['user']);
}
