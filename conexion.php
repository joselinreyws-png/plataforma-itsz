<?php

function db() {
    $host = $_ENV['MYSQLHOST'];
    $db   = $_ENV['MYSQLDATABASE'];
    $user = $_ENV['MYSQLUSER'];
    $pass = $_ENV['MYSQLPASSWORD'];
    $port = $_ENV['MYSQLPORT'];

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        die("❌ Error conexión BD: " . $e->getMessage());
    }
}
