<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$adminUser = $_SESSION['admin_username'] ?? 'admin';

require_once __DIR__ . '/../../src/Core/FileManager.php';
require_once __DIR__ . '/../../src/Core/Security.php';

$fileManager = new \Core\FileManager($config['storage']['path']);
$security = new \Core\Security($fileManager);

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header('Location: index.php');
    exit;
}

$filename = basename($id);
$dataPath = "accounts/{$adminUser}/soal/{$filename}";
$data = $fileManager->readJson($dataPath);

if (!$data) {
    header('Location: index.php');
    exit;
}

// CSRF validation inline (before require layout_top.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf_token'] ?? '';
    $expected = $_SESSION['_csrf_token'] ?? '';
    if (empty($expected) || !hash_equals($expected, $token)) {
        $_SESSION['_flash'] = ['msg' => 'CSRF token tidak valid.', 'type' => 'error'];
        header('Location: index.php');
        exit;
    }

    $jumlah = max(1, (int)($_POST['jumlah'] ?? 1));
    $nama_baru = trim($_POST['nama_baru'] ?? $data['metadata']['judul'] . ' (Clone)');
    
    for ($i = 0; $i < $jumlah; $i++) {
        $newFilename = uniqid('soal_') . '.json';
        $newPath = "accounts/{$adminUser}/soal/{$newFilename}";

        $cloneData = $data;
        
        $cloneData['metadata']['judul'] = $jumlah > 1 ? $nama_baru . " " . ($i + 1) : $nama_baru;
        $cloneData['metadata']['created_at'] = date('Y-m-d H:i:s');
        $cloneData['metadata']['pin'] = $security->generatePin();
        $cloneData['metadata']['slug'] = $security->generateSlug($data['metadata']['mata_pelajaran']);
        $cloneData['metadata']['status'] = 'draft';

        if (isset($cloneData['hasil'])) {
            unset($cloneData['hasil']);
        }

        if (isset($cloneData['soal']) && is_array($cloneData['soal'])) {
            shuffle($cloneData['soal']);
        }

        $fileManager->writeJsonAndSync($newPath, $cloneData);
    }

    $_SESSION['_flash'] = ['msg' => 'Berhasil menggandakan ' . $jumlah . ' soal.', 'type' => 'success'];
    header('Location: index.php');
    exit;
}

require __DIR__ . '/layout_top.php';
?>
<div class="page-title">
    Clone Latihan Soal
    <a href="<?= $adminBase ?>index.php" class="btn btn-outline">← Kembali</a>
</div>

<div class="card" style="max-width: 600px;">
    <form method="post">
        <?= csrfField() ?>
        <div class="form-group">
            <label class="form-label">Soal Asal</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($data['metadata']['judul'] ?? '') ?>" disabled>
        </div>
        <div class="form-group">
            <label class="form-label">Nama Soal Baru</label>
            <input type="text" name="nama_baru" class="form-input" value="<?= htmlspecialchars($data['metadata']['judul'] ?? '') ?> (Clone)" required>
            <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">Jika jumlah lebih dari 1, otomatis akan ditambahkan angka (1, 2, 3...) di belakang nama</small>
        </div>
        <div class="form-group">
            <label class="form-label">Jumlah Clone</label>
            <input type="number" name="jumlah" class="form-input" value="1" min="1" max="100" required>
            <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">Berapa salinan yang ingin dibuat sekaligus?</small>
        </div>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Mulai proses clone?')">📋 Jalankan Clone</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
