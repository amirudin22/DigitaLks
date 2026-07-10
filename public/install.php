<?php
/**
 * Installer — first-run wizard
 * Shows only when no user with role 'kepala_sekolah' exists.
 * Creates the first kepala_sekolah account (highest role).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$config = require __DIR__ . '/../config/config.php';
$auth   = $config['auth'];
$appName = $config['app']['name'];

// ── Check if kepala_sekolah already exists ──
$hasKepsek = false;
foreach ($auth->getUsers() as $u) {
    if (($u['role'] ?? '') === 'kepala_sekolah') {
        $hasKepsek = true;
        break;
    }
}

// Already has kepala_sekolah → redirect
if ($hasKepsek) {
    header('Location: admin/index.php');
    exit;
}

// ── Handle form submission ──
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $username   = trim($_POST['username'] ?? '');
    $nama       = trim($_POST['nama'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';

    if (empty($username) || empty($nama) || empty($password)) {
        $error = 'Semua field wajib diisi.';
    } elseif ($password !== $confirm) {
        $error = 'Password dan konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        $result = $auth->addUser($username, $password, 'kepala_sekolah', $nama);
        if ($result['ok']) {
            // Auto-login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $username;
            $_SESSION['admin_role']      = 'kepala_sekolah';
            $_SESSION['admin_nama']      = $nama;
            session_regenerate_id(true);

            // Also seed any .env AUTH_USERS as administrator (skip duplicates)
            $envUsers = \Core\EnvLoader::parseAuthUsers(\Core\EnvLoader::get('AUTH_USERS', ''));
            foreach ($envUsers as $envU => $envP) {
                if ($envU !== $username) {
                    $auth->addUser($envU, $envP, 'administrator', $envU);
                }
            }

            header('Location: admin/index.php');
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal membuat akun.';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installasi — <?= htmlspecialchars($appName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .card h1 {
            font-size: 1.5rem;
            color: #1F2937;
            margin-bottom: 0.25rem;
        }
        .card .sub {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 1.75rem;
            line-height: 1.5;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .btn {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 0.5rem;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }
        .error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .steps {
            background: #F9FAFB;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            color: #6B7280;
            line-height: 1.7;
        }
        .steps strong { color: #374151; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Selamat Datang!</h1>
        <p class="sub">Sebelum menggunakan <?= htmlspecialchars($appName) ?>, buat akun <strong>Kepala Sekolah</strong> terlebih dahulu. Akun ini memiliki wewenang tertinggi untuk mengelola user lain.</p>

        <div class="steps">
            <strong>📋 Langkah Installasi:</strong><br>
            1. Isi data Kepala Sekolah di bawah<br>
            2. Klik "Buat Akun"<br>
            3. Selesai — kamu akan langsung masuk ke dashboard
        </div>

        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="cth: kepsek" required autofocus>
            </div>
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" placeholder="Nama Kepala Sekolah" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm">Konfirmasi Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Ketik ulang password" required minlength="6">
            </div>
            <button type="submit" name="install" class="btn">🔐 Buat Akun Kepala Sekolah</button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:0.75rem;color:#9CA3AF;">
            Setelah installasi, kamu bisa menambah user Guru & Administrator dari panel Kelola User.
        </p>
    </div>
</body>
</html>
