<?php
require_once "conexion.php";

try {
    db();
    echo "✅ Conectado a MySQL en Render correctamente";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage();
}
