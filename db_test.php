<?php
$url = getenv("MYSQL_URL");

if (!$url) {
    die("NO EXISTE MYSQL_URL");
}

$db = parse_url($url);

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/') . ";charset=utf8mb4",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "MYSQL CONECTADO";

} catch (Exception $e) {
    echo "ERROR MYSQL";
}
