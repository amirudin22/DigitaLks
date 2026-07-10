<?php
/**
 * API endpoint: lookup NIS siswa
 * Dipanggil via fetch() dari halaman soal s/index.php saat onboarding
 * Response: JSON { found: bool, nama: string, kelas: string }
 */
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Core/Database.php';
require_once __DIR__ . '/../../src/Core/FileManager.php';
require_once __DIR__ . '/../../src/Core/Security.php';

$db = \Core\Database::getInstance($config['database'] ?? []);

// Validasi input
$nis  = trim($_GET['nis']  ?? '');
$guru = trim($_GET['guru'] ?? '');

if ($nis === '' || $guru === '') {
    echo json_encode(['found' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

if (!$db->isConnected()) {
    // Jika DB tidak tersambung, biarkan siswa isi nama manual (mode fallback)
    echo json_encode(['found' => false, 'fallback' => true, 'message' => 'DB tidak terhubung']);
    exit;
}

$siswa = $db->getSiswaByNis($guru, $nis);

if ($siswa) {
    echo json_encode([
        'found' => true,
        'nama'  => $siswa['nama'],
        'kelas' => $siswa['kelas'],
    ]);
} else {
    echo json_encode([
        'found'   => false,
        'message' => 'NIS tidak ditemukan. Hubungi gurumu untuk mendaftarkan NIS kamu.',
    ]);
}
