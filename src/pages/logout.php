<?php
declare(strict_types=1);
session_start();
$_SESSION = [];
session_destroy();
header('Location: /plataforma_itsz/src/pages/login.php', true, 302);
exit;
