<?php
function db() {

    $host = getenv("MYSQLHOST");
    $port = getenv("MYSQLPORT");
    $db   = getenv("MYSQLDATABASE");
    $user = getenv("MYSQLUSER");
    $pass = getenv("MYSQLPASSWORD");

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;

    } catch (PDOException $e) {
        die("Error conexión DB");
    }
}
