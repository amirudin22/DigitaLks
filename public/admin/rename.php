<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf_token'] ?? '';
    $expected = $_SESSION['_csrf_token'] ?? '';
    if (empty($expected) || !hash_equals($expected, $token)) {
        $_SESSION['_flash'] = ['msg' => 'CSRF token tidak valid.', 'type' => 'error'];
        header('Location: index.php');
        exit;
    }

    $config = require __DIR__ . '/../../config/config.php';
    $adminUser = $_SESSION['admin_username'] ?? 'admin';

    require_once __DIR__ . '/../../src/Core/FileManager.php';
    $fileManager = new \Core\FileManager($config['storage']['path']);

    $id = $_POST['id'] ?? '';
    $newTitle = trim($_POST['new_title'] ?? '');

    if (!empty($id) && !empty($newTitle)) {
        $filename = basename($id);
        $dataPath = "accounts/{$adminUser}/soal/{$filename}";
        $data = $fileManager->readJson($dataPath);

        if ($data) {
            $data['metadata']['judul'] = trim(mb_substr($newTitle, 0, 200));
            $fileManager->writeJsonAndSync($dataPath, $data);
            $_SESSION['_flash'] = ['msg' => 'Judul berhasil diubah.', 'type' => 'success'];
        }
    }
}

header('Location: index.php');
exit;
