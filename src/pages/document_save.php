<?php 
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user']) && empty($_SESSION['admin'])) {
  go('/plataforma_itsz/src/pages/login.php');
}

/* ================= IMAGEN DE FONDO ================= */
$imgBase = '/plataforma_itsz/img';
$imagen_fondo = $_POST['imagen_fondo'] ?? ($imgBase . '/fondo_documento.png');
/* =================================================== */

$docId     = (int)($_POST['id'] ?? 0);
$contenido = $_POST['contenido'] ?? '';

if ($docId <= 0) {
  http_response_code(400);
  exit('ID inválido');
}

$pdo = db();

/* Verificar dueño o admin */
$st = $pdo->prepare("SELECT user_id FROM documents WHERE id = ?");
$st->execute([$docId]);
$row = $st->fetch();

if (!$row) {
  http_response_code(404);
  exit('Documento no encontrado');
}

if (empty($_SESSION['admin'])) {
  if (empty($_SESSION['user']) || (int)$_SESSION['user']['id'] !== (int)$row['user_id']) {
    http_response_code(403);
    exit('No autorizado');
  }
}

/* ================= GUARDAR DOCUMENTO ================= */
$up = $pdo->prepare("
  UPDATE documents
  SET contenido = ?, imagen_fondo = ?, updated_at = NOW()
  WHERE id = ?
");

$up->execute([
  $contenido,
  $imagen_fondo,
  $docId
]);
/* ==================================================== */

go('/plataforma_itsz/src/pages/document_view.php?id=' . $docId . '&saved=1');
