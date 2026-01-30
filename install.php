<?php
require "conexion.php";
$pdo = db();

$sql = file_get_contents("database.sql");
$pdo->exec($sql);

echo "BASE DE DATOS INSTALADA";
