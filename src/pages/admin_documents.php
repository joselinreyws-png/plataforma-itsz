<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

// Solo administradores
if (empty($_SESSION['admin'])) {
  // si hay user normal activo, sácalo a su flujo
  if (!empty($_SESSION['user'])) {
    go('/plataforma_itsz/src/pages/document_new.php');
  }
  go('/plataforma_itsz/src/pages/login.php');
}

$pdo = db();

/* ====== Filtros (opcionales) ====== */
$q_name    = trim($_GET['name'] ?? '');
$date_from = trim($_GET['from'] ?? '');
$date_to   = trim($_GET['to'] ?? '');
$order     = $_GET['order'] ?? 'created_desc'; // created_desc | created_asc

$where  = [];
$params = [];

if ($q_name !== '') {
  $where[]  = "COALESCE(u.nombre,'') LIKE ?";
  $params[] = '%'.$q_name.'%';
}
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
  $where[]  = "DATE(d.created_at) >= ?";
  $params[] = $date_from;
}
if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
  $where[]  = "DATE(d.created_at) <= ?";
  $params[] = $date_to;
}

$sql = "SELECT d.id, d.folio, d.titulo, d.created_at, d.updated_at,
               u.nombre, u.numero_empleado, u.rol
        FROM documents d
        LEFT JOIN users u ON u.id = d.user_id";
if ($where) { $sql .= " WHERE ".implode(' AND ', $where); }

switch ($order) {
  case 'created_asc':  $sql .= " ORDER BY d.created_at ASC"; break;
  default:             $sql .= " ORDER BY d.created_at DESC"; // created_desc
}

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$title = "Administrador | Documentos";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b132b;color:#e6edf6;font-family:system-ui,Segoe UI,Arial}
    .wrap{min-height:100dvh;padding:24px}
    .panel{background:#1c2541;border-radius:14px;padding:16px;box-shadow:0 12px 30px rgba(0,0,0,.25)}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .btn{display:inline-block;padding:8px 10px;border-radius:8px;background:#e5e7eb;color:#111;text-decoration:none;font-weight:700}
    .btn-dark{background:#111827;color:#fff}
    .filters{display:grid;grid-template-columns:1.6fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px}
    @media (max-width:900px){.filters{grid-template-columns:1fr 1fr}}
    .filters input,.filters select{width:100%;padding:10px;border-radius:10px;border:1px solid #31426e;background:#0d1633;color:#e6edf6}
    table{width:100%;border-collapse:collapse;background:#0b132b;color:#fff;border-radius:12px;overflow:hidden}
    th,td{padding:10px 12px;border-bottom:1px solid #233554;font-size:14px}
    th{background:#0f1b3a;text-align:left}
    tbody tr:hover{background:#0f1d42}
    .actions a{margin-right:6px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div class="topbar">
      <h2 style="margin:0">Administrador — Documentos</h2>
      <div style="display:flex;gap:8px">
        <a class="btn" href="/plataforma_itsz/src/pages/logout.php">Cerrar sesión</a>
      </div>
    </div>

    <form class="filters" method="get">
      <input type="text"   name="name" value="<?= htmlspecialchars($q_name) ?>" placeholder="Buscar por nombre (ej. Ana Pérez)">
      <input type="date"   name="from" value="<?= htmlspecialchars($date_from) ?>">
      <input type="date"   name="to"   value="<?= htmlspecialchars($date_to) ?>">
      <select name="order">
        <option value="created_desc" <?= $order==='created_desc'?'selected':'' ?>>Más recientes</option>
        <option value="created_asc"  <?= $order==='created_asc'?'selected':''  ?>>Más antiguos</option>
      </select>
      <div style="grid-column:1 / -1; display:flex; gap:8px; justify-content:space-between">
        <button class="btn" style="background:#5bc0be;color:#062b2a;width:100%" type="submit">Aplicar filtros</button>
        <a class="btn" href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>">Limpiar</a>
      </div>
    </form>

    <div style="overflow:auto;border-radius:12px">
      <table>
        <thead>
          <tr>
            <th>Folio</th>
            <th>Título</th>
            <th>Empleado</th>
            <th>Núm. emp.</th>
            <th>Rol</th>
            <th>Creado</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7">Sin resultados.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['folio'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['titulo'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['nombre'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['numero_empleado'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['rol'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
             <td class="actions">
    <!-- Ver en modo edición/lectura -->
    <a class="btn" href="/plataforma_itsz/src/pages/document_view.php?id=<?= (int)$r['id'] ?>">Ver</a>

    <!-- Descargar PDF (solo admin) -->
    <a class="btn btn-primary" 
       href="/plataforma_itsz/src/pages/document_pdf.php?id=<?= (int)$r['id'] ?>&download=1">
       Descargar PDF
    </a>
</td>

            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
