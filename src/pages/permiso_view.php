<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user']) && empty($_SESSION['admin'])) {
  header('Location: /plataforma_itsz/src/pages/login.php'); exit;
}

$pdo     = db();
$user    = $_SESSION['user'] ?? null;        // usuario normal
$isAdmin = !empty($_SESSION['admin']);       // admin
$imgBase    = '/plataforma_itsz/img';
$logocabeza = $imgBase . '/logocabeza.png';

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --------- Valores de tipo/fecha (default o query) -----------
$permitidos = ['2h','economico','comision','otros'];
$tipoParam  = $_GET['tipo']  ?? '2h';
$fechaParam = $_GET['fecha'] ?? date('Y-m-d');
if (!in_array($tipoParam, $permitidos, true)) $tipoParam = '2h';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam)) $fechaParam = date('Y-m-d');

// Para mensajes UI:
$errorMsg = '';
$okMsg    = '';

// =============================================================
// POST: Guardar con VALIDACIÓN (y registrar en user_permisos)
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
  $docId   = (int)($_POST['id'] ?? 0);
  $html    = (string)($_POST['contenido'] ?? '');
  $tipoIn  = $_POST['tipo_permiso']  ?? $tipoParam;
  $fechaIn = $_POST['fecha_permiso'] ?? $fechaParam;

  if (!in_array($tipoIn, $permitidos, true)) $tipoIn = '2h';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIn)) $fechaIn = date('Y-m-d');

  // Datos de periodo
  $ts       = strtotime($fechaIn);
  $anio     = (int)date('Y', $ts);
  $mes      = (int)date('n', $ts);
  $quin     = ((int)date('j', $ts) <= 15) ? 1 : 2;

  try {
    // 1) Si NO es admin: validar y registrar user_permisos
    if (!$isAdmin && $user) {
      $uid = (int)$user['id'];

      if ($tipoIn === '2h') {
        // Sumatoria del mes (ambas quincenas)
        $sum = $pdo->prepare("
          SELECT COALESCE(SUM(usados),0) AS total
          FROM user_permisos
          WHERE user_id=? AND tipo='2h' AND anio=? AND mes=?
        ");
        $sum->execute([$uid, $anio, $mes]);
        $totalMes = (int)$sum->fetchColumn();

        if ($totalMes >= 2) {
          $errorMsg = "Ya alcanzaste el límite de 2 permisos de 2 horas en este mes. No se guardó el documento.";
        } else {
          // upsert quincena correspondiente (+1)
          $sel = $pdo->prepare("
            SELECT usados FROM user_permisos
            WHERE user_id=? AND tipo='2h' AND anio=? AND mes=? AND quincena=?
            FOR UPDATE
          ");
          $pdo->beginTransaction();
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
          // 2) guardar documento
          $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
          $updDoc->execute([$html, $docId]);
          $pdo->commit();

          $okMsg = "Guardado y registrado 2h (".($totalMes+1)."/2 en el mes).";
        }
      } else {
        // Tipos sin límite: registrar movimiento (opcional, suma 1)
        $sel = $pdo->prepare("
          SELECT usados FROM user_permisos
          WHERE user_id=? AND tipo=? AND anio=? AND mes=? AND quincena=? FOR UPDATE
        ");
        $pdo->beginTransaction();
        $sel->execute([(int)$user['id'], $tipoIn, $anio, $mes, $quin]);
        $row = $sel->fetch();
        if ($row) {
          $upd = $pdo->prepare("
            UPDATE user_permisos
            SET usados = usados + 1
            WHERE user_id=? AND tipo=? AND anio=? AND mes=? AND quincena=?
          ");
          $upd->execute([(int)$user['id'], $tipoIn, $anio, $mes, $quin]);
        } else {
          $ins = $pdo->prepare("
            INSERT INTO user_permisos (user_id, tipo, anio, mes, quincena, usados)
            VALUES (?, ?, ?, ?, ?, 1)
          ");
          $ins->execute([(int)$user['id'], $tipoIn, $anio, $mes, $quin]);
        }
        // Guardar documento
        $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
        $updDoc->execute([$html, $docId]);
        $pdo->commit();
        $okMsg = "Guardado y registrado permiso “$tipoIn”.";
      }
    } else {
      // Admin: guarda sin tocar contadores
      $updDoc = $pdo->prepare("UPDATE documents SET contenido=? WHERE id=?");
      $updDoc->execute([$html, $docId]);
      $okMsg = "Guardado (admin).";
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errorMsg = "Error al guardar: ".htmlspecialchars($e->getMessage());
  }
}

// =============================================================
// Cargar o crear documento (igual que te dejé antes)
// =============================================================
function etiquetaTipo(string $t): string {
  return [
    '2h'        => 'PERMISO DE 2 HORAS',
    'economico' => 'PERMISO ECONÓMICO',
    'comision'  => 'COMISIÓN OFICIAL',
    'otros'     => 'OTROS',
  ][$t] ?? 'PERMISO';
}

$tipoActual  = $tipoParam;
$fechaActual = $fechaParam;

if ($docId > 0) {
  $st = $pdo->prepare("SELECT * FROM documents WHERE id=?");
  $st->execute([$docId]);
  $doc = $st->fetch();
  if (!$doc) { http_response_code(404); exit('Documento no encontrado'); }
  $contenido = $doc['contenido'];
  $folio     = $doc['folio'] ?? '';
  $title     = $doc['titulo'] ?? 'Documento';
} else {
  if (!$user) { http_response_code(403); exit('No autorizado'); }
  $rolUsuario = (string)($user['rol'] ?? 'docente');
  $prefijo    = ($rolUsuario === 'administrativo') ? 'A' : 'D';

  $pdo->beginTransaction();
  try {
    $q = $pdo->prepare("SELECT MAX(folio_num) AS mx FROM documents WHERE rol=? FOR UPDATE");
    $q->execute([$rolUsuario]);
    $row     = $q->fetch();
    $nextNum = (int)($row['mx'] ?? 0) + 1;
    $folio   = $prefijo . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);

    // Datos visibles
    $noEmp  = htmlspecialchars($user['numero_empleado'] ?? '—', ENT_QUOTES, 'UTF-8');
    $nom    = htmlspecialchars($user['nombre'] ?? '—', ENT_QUOTES, 'UTF-8');
    $puesto = htmlspecialchars($user['rol'] ?? '—', ENT_QUOTES, 'UTF-8');

    ob_start(); ?>
    <!-- ***** CONTENIDO EDITABLE (idéntico a tu diseño) ***** -->
    <div style="font-family: Arial, Helvetica, sans-serif;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
        <div style="font-size:12px; line-height:1.2;">
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
          </div>
        </div>
        <div style="text-align:right; font-size:12px;"></div>
      </div>

      <div style="text-align:center; margin-top:5px;">
        <div style="font-size:14px; font-weight:bold;">INSTITUTO TECNOLÓGICO SUPERIOR DE ZONGOLICA</div>
        <div style="font-size:12px; margin-top:8px; font-weight:bold;">SUBDIRECCIÓN ADMINISTRATIVA / DEPARTAMENTO DE RECURSOS HUMANOS</div>
        <div style="font-size:12px; font-weight:bold; margin-top:8px;">
          FORMATO DE ENTRADA SALIDA PARA INCIDENCIAS EN ASISTENCIA DE PERSONAL
        </div>
      </div>

      <div style="margin-top:40px; font-size:10px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div style="flex:1; padding-right:10px; margin:13px 0;">
            <strong>NO. EMPLEADO (A):</strong>
            <span contenteditable="true"
                  style="display:inline-block; min-width:120px; border-bottom:1px solid #000; padding:0 4px; margin-left:100px;">
              <?= $noEmp ?>
            </span>
          </div>
          <div style="min-width:170px; text-align:right; margin:6px 0;">
            <div style="display:inline-block; padding:4px 8px; border:1px solid #000; border-radius:4px;">
              <strong>FOLIO:</strong>
              <span style="margin-left:6px;"><?= htmlspecialchars($folio) ?></span>
            </div>
          </div>
        </div>

        <div>
          <strong>CAMPUS O EXTENSIÓN:</strong>
          <span contenteditable="true"
                style="display:inline-block; width:220px; border-bottom:1px solid #000; padding:0 4px; margin-left:79px;">
            Nogales
          </span>
        </div>

        <div style="margin:6px 0;">
          <strong>NOMBRE DE EMPLEADO (A):</strong>
          <span contenteditable="true"
                style="display:inline-block; width:420px; border-bottom:1px solid #000; padding:0 4px; margin-left:56px;">
            <?= $nom ?>
          </span>
        </div>

        <div style="margin:6px 0;">
          <strong>PUESTO:</strong>
          <span contenteditable="true"
                style="display:inline-block; width:420px; border-bottom:1px solid #000; padding:0 4px; margin-left:152px;">
            <?= $puesto ?>
          </span>
        </div>

        <div style="margin:6px 0;">
          <strong>SUBDIRECCIÓN ADSCRITA:</strong>
          <span contenteditable="true"
                style="display:inline-block; width:224px; border-bottom:1px solid #000; padding:0 4px; margin-left:62px;">
            Administrativa
          </span>
        </div>
      </div>

      <table style="width:100%; border-collapse:collapse; margin-top:40px; font-size:10px;">
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
          <?php
            $rows = [
              'comision'  => 'COMISIÓN OFICIAL',
              'lactancia' => 'HORA DE LACTANCIA',
              'medico'    => 'ACUDIR AL MÉDICO (SOLO CON CONSTANCIA MÉDICA DEL IMSS)',
              'economico' => 'PERMISO ECONÓMICO',
              '2h'        => 'PERMISO DE 2 HORAS',
              'otros'     => 'OTROS',
            ];
            foreach ($rows as $clave=>$texto):
              $resaltar = ($clave === $tipoActual) || ($clave==='otros' && $tipoActual==='otros');
          ?>
          <tr style="<?= $resaltar ? 'background:#fffbe6' : '' ?>">
            <td style="border:1px solid #000; padding:6px;"><?= htmlspecialchars($texto) ?></td>
            <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
            <td style="border:1px solid #000; padding:6px;" contenteditable="true"><?= $resaltar ? htmlspecialchars($fechaActual) : '' ?></td>
            <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
            <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          </tr>
          <?php endforeach; ?>

          <tr>
            <td colspan="5" style="border:1px solid #000; padding:0;">
              <table style="width:100%; border-collapse:collapse; table-layout:fixed; font-size:10px;">
                <tr><td contenteditable="true" style="height:20px; border-bottom:1px solid #000; padding:4px;"></td></tr>
                <tr><td style="height:20px; border-bottom:1px solid #000; text-align:center; font-weight:bold;">OBSERVACIONES:</td></tr>
                <tr><td contenteditable="true" style="height:20px; border-bottom:1px solid #000; padding:4px;"></td></tr>
                <tr><td contenteditable="true" style="height:20px; border-bottom:1px solid #000; padding:4px;"></td></tr>
                <tr><td contenteditable="true" style="height:20px; padding:4px;"></td></tr>
              </table>
            </td>
          </tr>
        </tbody>
      </table>

      <div style="display:flex; justify-content:space-between; gap:16px; margin-top:28px; font-size:10px;">
        <div style="flex:1;">
          <div style="text-align:center; margin-top:6px;"><strong>SOLICITANTE:</strong></div>
          <div style="border-bottom:1px solid #000; height:2px; margin-top:48px;"></div>
          <div style="text-align:center; margin-top:10px;">NOMBRE Y FIRMA DEL EMPLEADO (A)</div>
        </div>
        <div style="flex:1;">
          <div style="text-align:center; margin-top:6px;"><strong>AUTORIZA:</strong></div>
          <div style="border-bottom:1px solid #000; height:2px; margin-top:48px;"></div>
          <div style="text-align:center; margin-top:10px;">NOMBRE Y FIRMA DE JEFE(A) INMEDIATO(A)</div>
        </div>
      </div>

      <div style="text-align:center; margin-top:45px; font-size:10px;">
        <div><strong>VO. BO.</strong></div>
        <div style="border-bottom:1px solid #000; height:2px; margin:20px 100px 6px;"></div>
        <div>"NOMBRE Y FIRMA SUBDIRECTOR(A) DE ADSCRIPCIÓN"</div>
      </div>
    </div>
    <?php
    $contenido = ob_get_clean();

    $ins = $pdo->prepare("INSERT INTO documents (user_id, rol, folio_num, folio, titulo, contenido)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([(int)$user['id'], $rolUsuario, $nextNum, $folio, 'Formato de Solicitud', $contenido]);
    $docId = (int)$pdo->lastInsertId();
    $pdo->commit();

    $title = "Documento $folio";
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Error generando folio: '.htmlspecialchars($e->getMessage()));
  }
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title ?? 'Documento') ?></title>
<style>
  body{margin:0;background:#0b132b;font-family:Inter,system-ui,Segoe UI,Arial}
  .page-wrap{display:grid;place-items:center;min-height:100dvh;padding:24px}
  .page{background:#fff;color:#000;width:794px;min-height:1123px;padding:32px 40px;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
  .toolbar{display:flex;gap:8px;align-items:center;margin-bottom:10px;width:794px}
  .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
  .btn-primary{background:#5bc0be;color:#062b2a}
  .btn-gray{background:#e5e7eb}
  #editor[contenteditable="true"]{outline:2px solid transparent}
  #editor[contenteditable="true"]:focus{outline:2px solid #5bc0be; outline-offset:2px}

  /* Panel amarillo grande */
  .panel-permiso{
    position:fixed; right:16px; top:16px; width:340px; max-width:92vw;
    background:#fffbe6; border:2px solid #f59e0b; border-radius:12px;
    box-shadow:0 12px 30px rgba(0,0,0,.35); padding:12px; z-index:9999
  }
  .panel-permiso h4{margin:0 0 8px; font-size:14px}
  .panel-permiso label{display:block; font-size:12px; margin:6px 0 4px}
  .panel-permiso input,.panel-permiso select{
    width:100%; padding:8px 10px; border-radius:8px; border:1px solid #f59e0b; background:#fff9c4
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
      <!-- estos dos se sincronizan con el panel -->
      <input type="hidden" name="tipo_permiso"  id="tipo_permiso_hidden"  value="<?= htmlspecialchars($tipoActual) ?>">
      <input type="hidden" name="fecha_permiso" id="fecha_permiso_hidden" value="<?= htmlspecialchars($fechaActual) ?>">

      <button type="submit" class="btn btn-primary" <?= $errorMsg ? 'disabled' : '' ?>>Guardar</button>
      <a href="permiso_view.php?id=15&print=1">Descargar</a>
     <a href="/plataforma_itsz/src/pages/document_pdf.php?id=<?= $doc['id'] ?>"
     class="btn btn-primary">
     Descargar PDF
     </a>
      <a class="btn btn-gray" href="/plataforma_itsz/src/pages/logout.php" style="text-decoration:none">Cerrar sesión</a>
      <?php if ($isAdmin): ?>
        <a class="btn btn-gray" href="/plataforma_itsz/src/pages/admin_documents.php" style="text-decoration:none">Panel admin</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="page" id="editor" contenteditable="true"><?= $contenido ?></div>
</div>

<!-- Panel Amarillo (control de permiso) -->
<div class="panel-permiso">
  <h4><span class="badge">Editable</span> Parámetros del permiso</h4>
  <label>Tipo</label>
  <select id="tipo_permiso">
    <option value="2h"        <?= $tipoActual==='2h'?'selected':'' ?>>Permiso de 2 horas</option>
    <option value="economico" <?= $tipoActual==='economico'?'selected':'' ?>>Económico</option>
    <option value="comision"  <?= $tipoActual==='comision'?'selected':'' ?>>Comisión oficial</option>
    <option value="otros"     <?= $tipoActual==='otros'?'selected':'' ?>>Otros</option>
  </select>
  <label>Fecha</label>
  <input type="date" id="fecha_permiso" value="<?= htmlspecialchars($fechaActual) ?>">

  <?php
  // Mostrar conteo actual para 2h (solo usuario normal)
  $info2h = '';
  if (!$isAdmin && $user) {
    $tsNow = strtotime($fechaActual);
    $anioX = (int)date('Y',$tsNow);
    $mesX  = (int)date('n',$tsNow);
    $sum = $pdo->prepare("SELECT COALESCE(SUM(usados),0) FROM user_permisos WHERE user_id=? AND tipo='2h' AND anio=? AND mes=?");
    $sum->execute([(int)$user['id'], $anioX, $mesX]);
    $usadosMes = (int)$sum->fetchColumn();
    $info2h = "Permisos 2h usados este mes: $usadosMes / 2";
  }
  ?>
  <div class="msg" style="color:#111"><?= $info2h ?></div>

  <?php if ($okMsg): ?>
    <div class="msg ok"><?= htmlspecialchars($okMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg err"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>
</div>

<script>
  // Sincroniza panel → campos ocultos del form
  const tipoSel   = document.getElementById('tipo_permiso');
  const fechaInp  = document.getElementById('fecha_permiso');
  const tipoH     = document.getElementById('tipo_permiso_hidden');
  const fechaH    = document.getElementById('fecha_permiso_hidden');

  const frm = document.getElementById('saveForm');
  frm.addEventListener('submit', () => {
    document.getElementById('contenido').value = document.getElementById('editor').innerHTML;
    tipoH.value  = tipoSel.value;
    fechaH.value = fechaInp.value;
  });
</script>
</body>
</html>
