<?php
session_start();
if (empty($_SESSION['user'])) { header('Location: /plataforma_itsz/src/pages/login.php'); exit; }
$u = $_SESSION['user'];
?><!doctype html><meta charset="utf-8">
<h2>Dashboard OK</h2>
<p>Usuario: <b><?=htmlspecialchars($u['nombre'])?></b> (<?=htmlspecialchars($u['rol'])?>)</p>
<a href="/plataforma_itsz/src/pages/document_new.php">Crear documento</a> |
<a href="/plataforma_itsz/src/pages/logout.php">Salir</a>
