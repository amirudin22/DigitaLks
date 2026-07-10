<?php
// Pastikan session dimulai hanya sekali
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
header("Location: ../index.php");
exit;