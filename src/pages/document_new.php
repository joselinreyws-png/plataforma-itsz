<?php
declare(strict_types=1);
session_start();

/* Evita pantallas en blanco durante desarrollo */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

/* Solo usuarios con sesión (docente/administrativo) */
if (empty($_SESSION['user'])) {
  go('/plataforma_itsz/src/pages/login.php');
}
/* ================= IMAGEN DE FONDO ================= */
$imgBase = '/plataforma_itsz/img';
$bgDoc   = $imgBase . '/fondo_documento.png';

if (isset($documento) && !empty($documento['imagen_fondo'])) {
    $bgDoc = $documento['imagen_fondo'];
}

$user = $_SESSION['user'];
$pdo  = db();

/* ==== Generar folio consecutivo por rol (A/D) ==== */
$prefix = ($user['rol'] === 'administrativo') ? 'A' : 'D';

$pdo->beginTransaction();
try {
  // Bloquea por rol para evitar colisiones
  $stmt = $pdo->prepare("SELECT MAX(folio_num) AS mx FROM documents WHERE rol = ? FOR UPDATE");
  $stmt->execute([$user['rol']]);
  $row     = $stmt->fetch();
  $nextNum = (int)($row['mx'] ?? 0) + 1;
  $folio   = $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);

  // Datos escapados para la plantilla
  $nombreSafe = htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
  $numEmpSafe = htmlspecialchars($user['numero_empleado'] ?? '', ENT_QUOTES, 'UTF-8');
  $rolSafe    = htmlspecialchars($user['rol'] ?? '', ENT_QUOTES, 'UTF-8');
  $folioSafe  = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');

  

  /* ==== PLANTILLA DEL DOCUMENTO (editable en document_view) ==== */
  $plantilla = <<<HTML
  <div id="documento"
     contenteditable="true"
     style="background-image: url('<?php echo $bgDoc; ?>');">
</div>
  <div style="font-family: Arial, Helvetica, sans-serif;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
      <div style="display:flex; align-items:center; gap:12px;">
      </div>
      <div style="text-align:right; font-size:12px;">
        <div><strong>FOLIO:</strong> <span>{$folioSafe}</span></div>
      </div>
    </div>

   <div style="margin-top:100px;">
  
  <div style="text-align:center;">
    <div style="font-size:14px; font-weight:bold;">
      INSTITUTO TECNOLÓGICO SUPERIOR DE ZONGOLICA
    </div>

    <div style="font-size:12px; margin-top:8px; font-weight:bold;">
      SUBDIRECCIÓN ADMINISTRATIVA / DEPARTAMENTO DE RECURSOS HUMANOS
    </div>

    <div style="font-size:12px; font-weight:bold; margin-top:8px;">
      FORMATO DE ENTRADA SALIDA PARA INCIDENCIAS EN ASISTENCIA DE PERSONAL
    </div>
  </div>

</div>
    <input type="hidden" name="imagen_fondo" value="<?php echo $bgDoc; ?>">


    <!-- Cabecera con líneas -->
     <div class="editor-documento" contenteditable="true"
     style="background-image: url('<?php echo $bgDoc; ?>');
            background-repeat: no-repeat;
            background-position: center top;
            background-size: cover;">

    <div style="margin-top:24px; font-size:10px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div style="flex:1; padding-right:10px; margin:13px 0;">
          <strong>NO. EMPLEADO (A):</strong>
          <span contenteditable="true" style="display:inline-block; min-width:120px; border-bottom:1px solid #000; padding:0 4px; margin-left:100px;">{$numEmpSafe}</span>
        </div>
        <div style="min-width:170px; text-align:right; margin:6px 0;">
          <div style="display:inline-block; padding:4px 8px; border:1px solid #000; border-radius:4px;">
            <strong>NG/DP/2026/:</strong>
            <span style="margin-left:6px;">{$folioSafe}</span>
          </div>
        </div>
      </div>

      <div>
        <strong>CAMPUS O EXTENSIÓN:</strong>
        <span contenteditable="true" style="display:inline-block; width:220px; border-bottom:1px solid #000; padding:0 4px; margin-left:79px;">Nogales</span>
      </div>

      <div style="margin:6px 0;">
        <strong>NOMBRE DE EMPLEADO (A):</strong>
        <span contenteditable="true" style="display:inline-block; width:420px; border-bottom:1px solid #000; padding:0 4px; margin-left:56px;">{$nombreSafe}</span>
      </div>

      <div style="margin:6px 0;">
        <strong>PUESTO:</strong>
        <span contenteditable="true" style="display:inline-block; width:420px; border-bottom:1px solid #000; padding:0 4px; margin-left:152px;">{$rolSafe}</span>
      </div>

      <div style="margin:6px 0;">
        <strong>SUBDIRECCIÓN ADSCRITA:</strong>
        <span contenteditable="true" style="display:inline-block; width:224px; border-bottom:1px solid #000; padding:0 4px; margin-left:62px;">Administrativa</span>
      </div>
    </div>

    <!-- Tabla -->
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
        <tr>
          <td style="border:1px solid #000; padding:6px;">COMISIÓN OFICIAL</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:6px;">HORA DE LACTANCIA</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:6px;">ACUDIR AL MÉDICO (SOLO CON CONSTANCIA MÉDICA DEL IMSS)</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:6px;">PERMISO ECONÓMICO</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:6px;">PERMISO DE 2 HORAS</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>
         <tr>
          <td style="border:1px solid #000; padding:6px;">OTRO</td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
          <td style="border:1px solid #000; padding:6px;" contenteditable="true"></td>
        </tr>


        <!-- Observaciones -->
        <tr>
          <td colspan="5" style="border:1px solid #000; padding:0;">
            <table style="width:100%; border-collapse:collapse; table-layout:fixed; font-size:10px;">
              <tr><td contenteditable="true" style="heigh70; border-bottom:1px solid #000; padding:4px;"></td></tr>
              <tr><td style="height:20px; border-bottom:1px solid #000; text-align:center; font-weight:bold;">OBSERVACIONES:</td></tr>
              <tr><td contenteditable="true" style="height:20px; border-bottom:1px solid #000; padding:4px;"></td></tr>
              <tr><td contenteditable="true" style="height:20px; border-bottom:1px solid #000; padding:4px;"></td></tr>
              <tr><td contenteditable="true" style="height:20px; padding:4px;"></td></tr>
            </table>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ===== BLOQUE DE FIRMAS ===== -->
<div class="bloque-firmas">

  <div style="display:flex; justify-content:space-between; gap:16px; margin-top:28px; font-size:10px;">
    <div style="flex:1;">
      <div style="text-align:center; margin-bottom:44px;">
        <strong>SOLICITANTE:</strong>
      </div>
      <div style="border-bottom:1px solid #000; width:280px; margin:0 auto;"></div>
      <div style="text-align:center; margin-top:10px;">
        NOMBRE Y FIRMA DEL EMPLEADO (A)
      </div>
    </div>

    <div style="flex:1;">
      <div style="text-align:center; margin-bottom:44px;">
        <strong>AUTORIZA:</strong>
      </div>
      <div style="border-bottom:1px solid #000; width:280px; margin:0 auto;"></div>
      <div style="text-align:center; margin-top:10px;">
        NOMBRE Y FIRMA DE JEFE(A) INMEDIATO(A)
      </div>
    </div>
  </div>

  <div style="text-align:center; margin-top:45px; font-size:10px;">
    <div><strong>VO. BO.</strong></div>
    <div style="border-bottom:1px solid #000; height:48px; margin:20px 100px 6px;"></div>
    <div>NOMBRE Y FIRMA SUBDIRECTOR(A) DE ADSCRIPCIÓN</div>
  </div>

</div>
<!-- ===== FIN BLOQUE DE FIRMAS ===== -->

  </div>
HTML;

  /* Guardar documento y obtener id */
  $ins = $pdo->prepare("
    INSERT INTO documents (user_id, rol, folio_num, folio, titulo, contenido, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $ins->execute([(int)$user['id'], $user['rol'], $nextNum, $folio, 'Formato de Solicitud', $plantilla]);

  $docId = (int)$pdo->lastInsertId();
  $pdo->commit();

  // Redirigir al editor
  go('/plataforma_itsz/src/pages/document_view.php?id=' . $docId . '&saved=1');

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo "Error generando documento: " . htmlspecialchars($e->getMessage());
  exit;
}