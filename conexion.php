<?php
declare(strict_types=1);

function db(): PDO
{
    $host = $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
    $db   = $_ENV['MYSQLDATABASE'] ?? 'railway';
    $user = $_ENV['MYSQLUSER'] ?? 'root';
    $pass = $_ENV['MYSQLPASSWORD'] ?? 'tkHdDZHqVmiMfkUniVGCodwmttIrTUEb';
    $port = $_ENV['MYSQLPORT'] ?? '3306';

    if ($host === '') {
        die("❌ Render no está enviando variables MySQL");
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
