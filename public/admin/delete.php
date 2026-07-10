<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

$adminUser = $_SESSION['admin_username'] ?? 'admin';
$id = basename($_GET['id'] ?? '');
$token = $_GET['_token'] ?? '';

if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
    header('Location: index.php');
    exit;
}

if (!empty($id)) {
    $config = require __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../src/Core/FileManager.php';
    require_once __DIR__ . '/../../src/Core/Database.php';

    $fileManager = new \Core\FileManager($config['storage']['path']);
    $db = \Core\Database::getInstance($config['database'] ?? []);
    $fileManager->setDatabase($db);

    $fileManager->deleteJsonAndSync("accounts/{$adminUser}/soal/{$id}");
    $_SESSION['_flash'] = ['msg' => 'Soal berhasil dihapus.', 'type' => 'success'];
}
header('Location: index.php');
exit;