<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// CSRF check
$token = $_GET['_token'] ?? '';
$expected = $_SESSION['_csrf_token'] ?? '';
if (empty($expected) || !hash_equals($expected, $token)) {
    $_SESSION['_flash'] = ['msg' => 'CSRF token tidak valid.', 'type' => 'error'];
    header('Location: index.php');
    exit;
}

require __DIR__ . '/layout_top.php';

$id = basename($_GET['id'] ?? '');
$regen = $_GET['regen'] ?? 0;

if (empty($id)) {
    echo "ID file tidak valid.";
    require __DIR__ . '/layout_bottom.php';
    exit;
}

$dataPath = "accounts/{$adminUser}/soal/{$id}";
$data = $fileManager->readJson($dataPath);

if (!$data) {
    echo "Data soal tidak ditemukan.";
    require __DIR__ . '/layout_bottom.php';
    exit;
}

if ($regen == 1) {
    $data['metadata']['pin'] = $security->generatePin();
    $data['metadata']['status'] = 'active';
    if (isset($data['hasil'])) {
        unset($data['hasil']);
    }
    $fileManager->writeJsonAndSync($dataPath, $data);

    echo "<div class='card' style='text-align:center; padding: 4rem 2rem;'>";
    echo "<h2 style='color:var(--success); margin-bottom:1rem;'>Berhasil Generate PIN Baru!</h2>";
    echo "<p style='margin-bottom:2rem; color:var(--text-muted);'>PIN lama sudah hangus dan tidak bisa lagi digunakan untuk membuka URL latihan ini.</p>";

    echo "<div style='background:#F3F4F6; padding:1.5rem; border-radius:var(--radius-md); display:inline-block; font-size:2rem; letter-spacing:0.2em; font-weight:700; color:var(--text-main); margin-bottom:2rem;'>";
    echo htmlspecialchars($data['metadata']['pin']);
    echo "</div><br>";

    echo "<a href='index.php' class='btn btn-primary'>Kembali ke Dashboard</a>";
    echo "</div>";
} else {
    echo "Aksi tidak diijinkan.";
}

require __DIR__ . '/layout_bottom.php';
?>
