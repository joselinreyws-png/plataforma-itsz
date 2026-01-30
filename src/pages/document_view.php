<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user']) && empty($_SESSION['admin'])) {
  go('/plataforma_itsz/src/pages/login.php');
}

$pdo     = db();
$user    = $_SESSION['user'] ?? null;
$isAdmin = !empty($_SESSION['admin']);

// ---------- Parámetros ----------
$docId      = (int)($_GET['id'] ?? 0);
$tipoParam  = $_GET['tipo']  ?? '2h';
$fechaParam = $_GET['fecha'] ?? date('Y-m-d');
$permitidos = ['2h','economico','comision','otros'];

if (!in_array($tipoParam, $permitidos, true)) $tipoParam = '2h';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam)) $fechaParam = date('Y-m-d');


$errorMsg = '';
$okMsg    = '';

// ---------- POST: Guardar (con conteo) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['accion'] ?? '') === 'guardar')) {
  $docId   = (int)($_POST['id'] ?? 0);
  $html    = (string)($_POST['contenido'] ?? '');
  $tipoIn  = $_POST['tipo_permiso']  ?? $tipoParam;
  $fechaIn = $_POST['fecha_permiso'] ?? $fechaParam;

  if (!in_array($tipoIn, $permitidos, true)) $tipoIn = '2h';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIn)) $fechaIn = date('Y-m-d');

  $ts   = strtotime($fechaIn);
  $anio = (int)date('Y', $ts);
  $mes  = (int)date('n', $ts);
  $dia  = (int)date('j', $ts);
  $quin = ($dia <= 15) ? 1 : 2;

  try {
    if (!$isAdmin && $user) {
      $uid = (int)$user['id'];

      if ($tipoIn === '2h') {
        // Sumar lo usado en el mes
        $sum = $pdo->prepare("
          SELECT COALESCE(SUM(usados),0)
          FROM user_permisos
          WHERE user_id=? AND tipo='2h' AND anio=? AND mes=?
        ");
        $sum->execute([$uid, $anio, $mes]);
        $totalMes = (int)$sum->fetchColumn();

        if ($totalMes >= 2) {
          $errorMsg = "Ya alcanzaste el límite de 2 permisos de 2 horas en este mes. No se guardó el documento.";
        } else {
          // Upsert por quincena (transacción)
          $pdo->beginTransaction();

          $sel = $pdo->prepare("
            SELECT usados FROM user_permisos
            WHERE user_id=? AND tipo='2h' AND anio=? AND mes=? AND quincena=?
            FOR UPDATE
          ");
          $sel->execute([$uid, $anio, $mes, $quin]);
          $row = $sel->fetch();

          if ($row) {
            $upd = $pdo->prepare("
              UPDATE user_permisos
              SET usados = usados + 1
              WHERE user_id=? AND tipo='2h' AND anio=? AND mes=? AND quincena=?
            ");
            $upd->execute([$uid, $anio, $mes, $quin]);
          } else {
            $ins = $pdo->prepare("
              INSERT INTO user_permisos (user_id, tipo, anio, mes, quincena, usados)
              VALUES (?, '2h', ?, ?, ?, 1)
            ");
            $ins->execute([$uid, $anio, $mes, $quin]);
          }

          $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
          $updDoc->execute([$html, $docId]);

          $pdo->commit();
          $okMsg = "Guardado y registrado 2h (".($totalMes+1)."/2 en el mes).";
        }
      } else {
        // Otros tipos: solo guardamos documento (si quieres, aquí también puedes sumar a user_permisos)
        $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
        $updDoc->execute([$html, $docId]);
        $okMsg = "Guardado.";
      }
    } else {
      // Admin: guarda sin tocar conteos
      $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
      $updDoc->execute([$html, $docId]);
      $okMsg = "Guardado (admin).";
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errorMsg = "Error al guardar: ".htmlspecialchars($e->getMessage());
  }
}

// ---------- Cargar documento ----------
$st = $pdo->prepare("SELECT d.*, u.nombre, u.numero_empleado, u.rol
                     FROM documents d
                     LEFT JOIN users u ON u.id = d.user_id
                     WHERE d.id=?");
$st->execute([$docId]);
$doc = $st->fetch();

if (!$doc) {
  http_response_code(404);
  exit('Permiso/Documento no encontrado');
}

$contenido = $doc['contenido'] ?: '';   // puede arrancar vacío si se creó nuevo
$folio     = $doc['folio']    ?? '';
$title     = 'Documento '.$folio;

$imgBase = '/plataforma_itsz/img';
$bgDoc   = $imgBase . '/fondo_documento.png';



// Si no había contenido, insertamos la plantilla mínima con el folio
if ($contenido === '') {
  ob_start(); ?>
  <div style="font-family: Arial, Helvetica, sans-serif;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
      <div style="display:flex; align-items:center; gap:12px;">
      </div>
      <div style="text-align:right; font-size:12px;">
        <div><strong>FOLIO:</strong> <?= htmlspecialchars($folio) ?></div>
      </div>
    </div>

     <div style="text-align:center;">
      <div style="font-size:14px; font-weight:bold;">INSTITUTO TECNOLÓGICO SUPERIOR DE ZONGOLICA</div>
      <div style="font-size:12px; margin-top:8px; font-weight:bold;">SUBDIRECCIÓN ADMINISTRATIVA / DEPARTAMENTO DE RECURSOS HUMANOS</div>
      <div style="font-size:12px; font-weight:bold; margin-top:8px;">FORMATO DE ENTRADA SALIDA PARA INCIDENCIAS EN ASISTENCIA DE PERSONAL</div>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-top:22px; font-size:10px;">
      <thead>
        <tr>
          <th style="border:1px solid #000; padding:6px; text-align:left;">TIPO DE PERMISO</th>
          <th style="border:1px solid #000; padding:6px; text-align:left;">NO. PERMISO<br><small>(ECONÓMICOS 10, DOS HORAS 24)</small></th>
          <th style="border:1px solid #000; padding:6px; text-align:left;">FECHA Y/O PERIODO A JUSTIFICAR</th>
          <th style="border:1px solid #000; padding:6px; text-align:left;">HORA DE INGRESO</th>
          <th style="border:1px solid #000; padding:6px; text-align:left;">HORA DE SALIDA</th>
        </tr>
      </thead>
      <tbody>
        <tr><td style="border:1px solid #000; padding:6px;">PERMISO DE 2 HORAS</td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td></tr>
        <tr><td style="border:1px solid #000; padding:6px;">PERMISO ECONÓMICO</td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td></tr>
        <tr><td style="border:1px solid #000; padding:6px;">COMISIÓN OFICIAL</td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td><td style="border:1px solid #000; padding:6px;"></td></tr>
      </tbody>
    </table>
  </div>
  <?php
  $contenido = ob_get_clean();

  // Guardamos la plantilla inicial en BD para que ya tenga algo editable
  $up0 = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
  $up0->execute([$contenido, $docId]);
}
$printFlag = isset($_GET['print']) ? (int)$_GET['print'] : 0;

// ====== ADMIN: DESCARGA DIRECTA DE PDF ======
if ($printFlag === 1 && $isAdmin) {
  header('Location: /plataforma_itsz/src/pages/document_pdf.php?id=' . $docId);
  exit;
}

// ========== HTML ==========
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<style>
  /* Asegura que el contenedor puede tener un pseudo-elemento detrás */
.page{
  position: relative;
  background: transparent;  /* no color de fondo para que se vea la imagen */
  overflow: hidden;         /* evita desbordes del fondo */
}

/* Imagen de fondo, sin interferir con el texto */
.page::before{
  content: "";
  position: absolute;
  inset: 0;                 /* top:0; right:0; bottom:0; left:0 */
  background-image: url('<?= $bgDoc ?>');
  background-repeat: no-repeat;
  background-position: -150 center top;  /* o 'center center' según tu diseño */
  background-size: cover;           /* 'contain' si quieres que no recorte */
  opacity: .40;                     /* ajusta la intensidad 0..1 */
  pointer-events: none;             /* no bloquea selección de texto */
  z-index: 0;
}

/* todo el contenido del editor por encima del fondo */
#editor{ position: relative; z-index: 1; }




  body{margin:0;background:#0b132b;font-family:Inter,system-ui,Segoe UI,Arial}
  .page-wrap{display:grid;place-items:center;min-height:100dvh;padding:24px}
  .page{background:#fff;color:#000;width:794px;min-height:1123px;padding:32px 40px;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
  .toolbar{display:flex;gap:8px;align-items:center;margin-bottom:10px;width:794px}
  .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
  .btn-primary{background:#5bc0be;color:#062b2a}
  .btn-gray{background:#e5e7eb}
  #editor[contenteditable="true"]{outline:2px solid transparent}
  #editor[contenteditable="true"]:focus{outline:2px solid #5bc0be; outline-offset:2px}

  .panel-permiso{
    position:fixed; right:16px; top:16px; width:340px; max-width:92vw;
    background:#fff3cd; border:2px solid #f59e0b; border-radius:12px;
    box-shadow:0 12px 30px rgba(0,0,0,.35); padding:12px; z-index:9999
  }
  .panel-permiso h4{margin:0 0 8px; font-size:14px}
  .panel-permiso label{display:block; font-size:12px; margin:6px 0 4px}
  .panel-permiso input,.panel-permiso select{
    width:100%; padding:8px 10px; border-radius:8px; border:1px solid #f59e0b; background:#fff9db
  }
  .badge{display:inline-block; font-size:12px; padding:2px 8px; border-radius:999px; background:#111827; color:#fff}
  .msg{margin-top:8px; font-size:12px}
  .msg.ok{color:#065f46; background:#e6ffed; border:1px solid #a7f3d0; padding:6px 8px; border-radius:8px}
  .msg.err{color:#991b1b; background:#fee2e2; border:1px solid #fecaca; padding:6px 8px; border-radius:8px}

  @media print{ .panel-permiso, .toolbar{display:none !important} .page{width:210mm; min-height:297mm; margin:0; box-shadow:none; border-radius:0} }
</style>
</head>
<body>

<div class="page-wrap">
  <div class="toolbar no-print">
    <form method="post" id="saveForm" style="display:flex;gap:8px">
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="<?= (int)$docId ?>">
      <input type="hidden" name="contenido" id="contenido">
      <input type="hidden" name="tipo_permiso"  id="tipo_permiso_hidden"  value="<?= htmlspecialchars($tipoParam) ?>">
      <input type="hidden" name="fecha_permiso" id="fecha_permiso_hidden" value="<?= htmlspecialchars($fechaParam) ?>">

      <button type="submit" class="btn btn-primary" <?= $errorMsg ? 'disabled' : '' ?>>Guardar</button>

      
      <a class="btn btn-gray" href="/plataforma_itsz/src/pages/logout.php" style="text-decoration:none">Cerrar sesión</a>
      <?php if ($isAdmin): ?>
        <a class="btn btn-gray" href="/plataforma_itsz/src/pages/admin_documents.php" style="text-decoration:none">Panel admin</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="page" id="editor" contenteditable="true"><?= $contenido ?></div>
</div>

<!-- Panel naranja (conteo) -->
<div class="panel-permiso">
  <h4><span class="badge">Editable</span> Parámetros del permiso</h4>
  <label>Tipo</label>
  <select id="tipo_permiso">
    <option value="2h"        <?= $tipoParam==='2h'?'selected':'' ?>>Permiso de 2 horas</option>
    <option value="economico" <?= $tipoParam==='economico'?'selected':'' ?>>Económico</option>
    <option value="comision"  <?= $tipoParam==='comision'?'selected':'' ?>>Comisión oficial</option>
    <option value="otros"     <?= $tipoParam==='otros'?'selected':'' ?>>Otros</option>
  </select>

  <label>Fecha</label>
  <input type="date" id="fecha_permiso" value="<?= htmlspecialchars($fechaParam) ?>">

  <?php
  $estado2h = '';
  if (!$isAdmin && $user) {
    $tsNow = strtotime($fechaParam);
    $anioX = (int)date('Y',$tsNow);
    $mesX  = (int)date('n',$tsNow);
    $sum = $pdo->prepare("SELECT COALESCE(SUM(usados),0) FROM user_permisos WHERE user_id=? AND tipo='2h' AND anio=? AND mes=?");
    $sum->execute([(int)$user['id'], $anioX, $mesX]);
    $usadosMes = (int)$sum->fetchColumn();
    $estado2h = "Permisos 2h usados este mes: $usadosMes / 2";
  }
  ?>
  <div class="msg" style="color:#111"><?= $estado2h ?></div>

  <?php if ($okMsg): ?>
    <div class="msg ok"><?= htmlspecialchars($okMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg err"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>
</div>

<script>
  const tipoSel   = document.getElementById('tipo_permiso');
  const fechaInp  = document.getElementById('fecha_permiso');
  const tipoH     = document.getElementById('tipo_permiso_hidden');
  const fechaH    = document.getElementById('fecha_permiso_hidden');

  document.getElementById('saveForm').addEventListener('submit', () => {
    document.getElementById('contenido').value = document.getElementById('editor').innerHTML;
    tipoH.value  = tipoSel.value;
    fechaH.value = fechaInp.value;
  });

</script>
</body>
</html>
