<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../db.php';

use Dompdf\Options;
use Dompdf\Dompdf;

/* ====== SEGURIDAD ====== */
if (empty($_SESSION['admin']) && empty($_SESSION['user'])) {
  http_response_code(403);
  exit('No autorizado');
}

/* ====== VALIDAR ID ====== */
$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
  exit('ID inválido');
}

/* ====== OBTENER DOCUMENTO ====== */
$pdo = db();
$st = $pdo->prepare("SELECT contenido, folio FROM documents WHERE id = ?");
$st->execute([$docId]);
$doc = $st->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
  exit('Documento no encontrado');
}

$contenido = $doc['contenido'];
$folio     = $doc['folio'] ?? 'documento';

/* ====== RUTAS ABSOLUTAS ====== */
$cssUrl = 'http://localhost/plataforma_itsz/css/style.css';
$bgUrl  = 'http://localhost/plataforma_itsz/img/fondo_documento.png';

/* ====== HTML PDF ====== */
$html = <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">

<link rel="stylesheet" href="$cssUrl">

<style>
@page {
  size: letter;
  margin: 0;
}

body {
  margin: 0;
  background: #ffffff;
}

/* ===== HOJA CARTA ===== */
.page {
  position: relative;
  width: 215.9mm;
  min-height: 279.4mm;
  margin: 0 auto;
  background-image: url("$bgUrl");
  background-repeat: no-repeat;
  background-position: top left;
  background-size: 100% 100%;
  background-color: #ffffff;
}

.page::before {
  display: none !important;
}

/* ===== CONTENIDO ===== */
#editor {
  padding-top: 10mm;
  padding-left: 20mm;
  padding-right: 20mm;
  padding-bottom: 20mm;
}

/* ===== OCULTA FIRMAS DEL EDITOR ===== */
.bloque-firmas {
  display: none !important;
}

/* =================================================
   FIRMAS FIJAS PDF (COMO EN LA IMAGEN)
   ================================================= */
.firmas-fijas {
  position: absolute;
  bottom: 34mm;       /* ← ajusta si quieres subir o bajar */
  left: 20mm;
  right: 20mm;
  font-size: 9px;
}

.fila-superior {
  display: table;
  width: 100%;
  margin-bottom: 14mm;
}

.fila-superior .firma {
  display: table-cell;
  width: 50%;
  text-align: center;
}

.fila-inferior {
  text-align: center;
}

.titulo {
position: absolute;
  top: -6mm;          /* altura correcta del título */
  left: 0;
  right: 0;
  text-align: center;
  font-weight: bold;
  font-size: 9px;
}

.linea {
  border-top: 1px solid #000;
  width: 70%;
  margin: 8mm auto 4mm;
  
}

.texto {
  font-size: 9px;
  margin-top: 3mm;
  text-align: center;
}
/* =========================================
   SACAR SOLICITANTE / AUTORIZA DEL CUADRO
   ========================================= */

.firma {
  position: relative;
  padding-top: 8mm; /* espacio para que no choque */
}

.firma .titulo {
  position: absolute;
  top: 7mm;          /* ← sube la leyenda */
  left: 0;
  right: 0;
  text-align: center;
  background: transparent;
}

.fila-inferior {
  margin-top: 14mm;   /* ← baja todo el bloque VO. BO. */
  text-align: center;
}

.fila-inferior .titulo {
  position: static;   /* ← NO flotante */
  margin-bottom: 6mm;
  font-size: 9px;
}

.fila-inferior .linea {
  margin: 0 auto 3mm;
  width: 75%;
}

</style>

</head>

<body>

  <div class="page documento-editable">

    <!-- CONTENIDO DEL DOCUMENTO -->
    <div id="editor">
      $contenido
    </div>

    <!-- ===== FIRMAS FIJAS PDF ===== -->
    <div class="firmas-fijas">

      <div class="fila-superior">
        <div class="firma">
          <div class="titulo">SOLICITANTE:</div>
          <div class="linea"></div>
          <div class="texto">NOMBRE Y FIRMA DEL EMPLEADO (A)</div>
        </div>

        <div class="firma">
          <div class="titulo">AUTORIZA:</div>
          <div class="linea"></div>
          <div class="texto">NOMBRE Y FIRMA DE JEFE(A) INMEDIATO(A)</div>
        </div>
      </div>

      <div class="fila-inferior">
        <div class="titulo">VO. BO.</div>
        <div class="linea"></div>
        <div class="texto">NOMBRE Y FIRMA SUBDIRECTOR(A) DE ADSCRIPCIÓN</div>
      </div>

    </div>
    <!-- ===== FIN FIRMAS ===== -->

  </div>

</body>
</html>
HTML;

/* ====== DOMPDF ====== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

/* ====== DESCARGA ====== */
$dompdf->stream(
  "permiso_$folio.pdf",
  ["Attachment" => true]
);

exit;
