<?php
// src/db.php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // === MODO DIAGNÓSTICO: evita páginas en blanco si la conexión falla
  error_reporting(E_ALL);
  ini_set('display_errors', '1');

  $DB_HOST = '127.0.0.1';      // o 'localhost'
  $DB_NAME = 'itsz_plataform'; // verifica que exista
  $DB_USER = 'root';           // XAMPP por defecto
  $DB_PASS = '';               // XAMPP por defecto: vacío
  $DB_CHAR = 'utf8mb4';

  $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";
  $opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opts);
  } catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Error conectando a la BD</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
  }
  return $pdo;
}
