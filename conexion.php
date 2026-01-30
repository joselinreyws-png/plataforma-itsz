<?php
declare(strict_types=1);

function db(): PDO
{
    $host = $_ENV['DB_HOST'] ?? 'dpg-xxxxx.render.com';
    $db   = $_ENV['DB_NAME'] ?? '´plataforma';
    $user = $_ENV['DB_USER'] ?? 'plataforma_user';
    $pass = $_ENV['DB_PASS'] ?? '2f81397e486e8b18b94530710c92debe';
    $port = $_ENV['DB_PORT'] ?? '3306';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
