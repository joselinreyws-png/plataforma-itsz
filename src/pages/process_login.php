<?php
declare(strict_types=1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

$ADMIN_PASS = 'CAMBIA_ESTA_CLAVE'; // cámbiala

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  go('/plataforma_itsz/src/pages/login.php');
}

$rol = trim($_POST['rol'] ?? '');

if ($rol === 'administrador') {
  $pass = $_POST['admin_pass'] ?? '';
  if ($pass !== $ADMIN_PASS) {
    go('/plataforma_itsz/src/pages/login.php?err=' . urlencode('Contraseña de administrador incorrecta.'));
  }

  // sesión de admin
  $_SESSION['admin'] = ['id' => 1, 'nombre' => 'Administrador'];
  unset($_SESSION['user']);
  go('/plataforma_itsz/src/pages/admin_documents.php');

} elseif ($rol === 'docente' || $rol === 'administrativo') {
  $numero = trim($_POST['numero_empleado'] ?? '');
  $nombre = trim($_POST['nombre'] ?? '');

  if ($numero === '' || $nombre === '') {
    go('/plataforma_itsz/src/pages/login.php?err=' . urlencode('Ingresa nombre y número de empleado.'));
  }

  // buscar / crear
  $sel = $pdo->prepare("SELECT id, nombre, numero_empleado, rol FROM users WHERE numero_empleado = ? LIMIT 1");
  $sel->execute([$numero]);
  $u = $sel->fetch();

  if ($u) {
    if ($u['nombre'] !== $nombre || $u['rol'] !== $rol) {
      $up = $pdo->prepare("UPDATE users SET nombre = ?, rol = ? WHERE id = ?");
      $up->execute([$nombre, $rol, (int)$u['id']]);
    }
    $userId = (int)$u['id'];
  } else {
    $ins = $pdo->prepare("INSERT INTO users (nombre, numero_empleado, rol, created_at) VALUES (?, ?, ?, NOW())");
    $ins->execute([$nombre, $numero, $rol]);
    $userId = (int)$pdo->lastInsertId();
  }

  $_SESSION['user'] = [
    'id'              => $userId,
    'nombre'          => $nombre,
    'numero_empleado' => $numero,
    'rol'             => $rol,
  ];
  unset($_SESSION['admin']);

  // A dónde quieres enviarlo tras login:
  go('/plataforma_itsz/src/pages/document_new.php'); // o permiso_new.php según tu flujo

} else {
  go('/plataforma_itsz/src/pages/login.php?err=' . urlencode('Selecciona un rol válido.'));
}
