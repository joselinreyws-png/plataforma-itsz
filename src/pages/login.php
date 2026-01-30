<?php
declare(strict_types=1);
session_start();

/* ==== Evitar página en blanco: mostrar errores en desarrollo ==== */
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ==== Helpers mínimos inline (por si helpers.php falla) ==== */
if (!function_exists('go')) {
  function go(string $url): void {
    header("Location: {$url}");
    exit;
  }
}

/* ==== DB mínima (por si db.php falla). Si ya tienes db.php, usa require_once y comenta esto ==== */
if (!function_exists('db')) {
  function db(): PDO {
    $DB_HOST = '127.0.0.1';
    $DB_NAME = 'itsz_plataform';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_CHAR = 'utf8mb4';
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $DB_USER, $DB_PASS, $opts);
  }
}

/* ==== Si ya hay sesión, manda a donde toca ==== */
if (!empty($_SESSION['admin'])) {
  go('/plataforma_itsz/src/pages/admin_documents.php');
}
if (!empty($_SESSION['user'])) {
  go('/plataforma_itsz/src/pages/document_new.php');
}

/* ==== Si prefieres usar tus propios helpers/db, descomenta: ==== */
// require_once __DIR__ . '/../helpers.php';
// require_once __DIR__ . '/../db.php';

$pdo = db();
$error = '';

/* Cambia esta clave por la que uses de admin */
$ADMIN_PASS = 'ITSZ_2025';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rol = trim($_POST['rol'] ?? '');

  if ($rol === 'administrador') {
    $pass = $_POST['admin_pass'] ?? '';
    if ($pass !== $ADMIN_PASS) {
      $error = 'Contraseña de administrador incorrecta.';
    } else {
      $_SESSION['admin'] = [
        'id'     => 1,
        'nombre' => 'Administrador',
      ];
      unset($_SESSION['user']);
      go('/plataforma_itsz/src/pages/admin_documents.php');
    }
  } elseif ($rol === 'docente' || $rol === 'administrativo') {
    $numero = trim($_POST['numero_empleado'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    if ($numero === '' || $nombre === '') {
      $error = 'Ingresa nombre y número de empleado.';
    } else {
      // Buscar o crear usuario
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

      go('/plataforma_itsz/src/pages/document_new.php');
    }
  } else {
    $error = 'Selecciona un rol válido.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Plataforma ITSZ — Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{min-height:100dvh;display:grid;place-items:center;background:#0b132b;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial}
  .card{width:min(480px,100%);background:#1c2541;color:#fff;padding:20px;border-radius:14px;box-shadow:0 12px 30px rgba(0,0,0,.25)}
  label{display:block;margin:8px 0 4px;font-weight:600}
  input,select,button{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #31426e;background:#0d1633;color:#e6edf6}
  button{background:#5bc0be;color:#062b2a;border:0;font-weight:800;cursor:pointer;margin-top:12px}
  .err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:8px 12px;border-radius:8px;margin-bottom:10px}
</style>
</head>
<body>
  <form class="card" method="post" action="/plataforma_itsz/src/pages/login.php">
    <h2 style="margin:0 0 10px">Plataforma ITSZ — Login</h2>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <label for="rol">Rol</label>
    <select id="rol" name="rol" required
      onchange="
        const r=this.value;
        document.getElementById('area-user').style.display = (r==='docente'||r==='administrativo')?'block':'none';
        document.getElementById('area-admin').style.display = (r==='administrador')?'block':'none';
      ">
      <option value="" selected>Selecciona...</option>
      <option value="docente">Docente</option>
      <option value="administrativo">Administrativo</option>
      <option value="administrador">Administrador</option>
    </select>

    <div id="area-user" style="display:none">
      <label for="numero_empleado">Número de empleado</label>
      <input id="numero_empleado" name="numero_empleado" placeholder="p.ej. 12345">

      <label for="nombre">Nombre completo</label>
      <input id="nombre" name="nombre" placeholder="p.ej. Ana Pérez">
    </div>

    <div id="area-admin" style="display:none">
      <label for="admin_pass">Contraseña de administrador</label>
      <input id="admin_pass" name="admin_pass" type="password" placeholder="••••••••">
    </div>

    <button type="submit">Entrar</button>
  </form>
</body>
</html>
