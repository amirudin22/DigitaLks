<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Load config early — needed for install check & AJAX handlers
$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) die('Config not found');
$config = require $configPath;

$storageDir = rtrim($config['storage']['path'], '/');
$dbJsonPath = $storageDir . '/config/database.json';

// ── Redirect ke install jika belum ada kepsek ──
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    $hasKepsek = false;
    foreach ($config['auth']->getUsers() as $u) {
        if (($u['role'] ?? '') === 'kepala_sekolah') { $hasKepsek = true; break; }
    }
    if (!$hasKepsek) {
        header('Location: ../install.php');
        exit;
    }
}

// ── Init CSRF early (needed for AJAX handlers that run before layout_top) ──
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$hasValidCsrf = ($_SERVER['REQUEST_METHOD'] !== 'POST')
    || ($_POST['_csrf_token'] ?? '') !== '' && hash_equals($_SESSION['_csrf_token'], $_POST['_csrf_token'] ?? '')
    || isset($_SERVER['HTTP_X_CSRF_TOKEN']) && hash_equals($_SESSION['_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);

// ── AJAX Handlers (sebelum layout, biar JSON murni) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    require_once __DIR__ . '/../../src/Core/FileManager.php';
    $fileManager = new \Core\FileManager($config['storage']['path']);

    if ($_GET['action'] === 'test_db') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            echo json_encode(['ok' => false, 'message' => 'Sesi tidak valid.']);
            exit;
        }
        if (!$hasValidCsrf) {
            echo json_encode(['ok' => false, 'message' => 'CSRF token tidak valid.']);
            exit;
        }
        try {
            $host   = $_POST['host'] ?? 'localhost';
            $port   = $_POST['port'] ?? '3306';
            $dbname = $_POST['dbname'] ?? '';
            $user   = $_POST['user'] ?? '';
            $pass   = $_POST['pass'] ?? '';
            if (empty($dbname)) throw new Exception('Nama database harus diisi');
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            echo json_encode(['ok' => true, 'message' => 'Terhubung!']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Gagal terhubung ke database. Periksa konfigurasi.']);
        }
        exit;
    }

    if ($_GET['action'] === 'sync_all') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!$hasValidCsrf) {
            echo json_encode(['ok' => false, 'error' => 'CSRF token tidak valid.']);
            exit;
        }
        require_once __DIR__ . '/../../src/Core/Database.php';
        $db = \Core\Database::getInstance($config['database'] ?? []);
        $fileManager->setDatabase($db);
        echo json_encode($fileManager->syncAllToMySql());
        exit;
    }

    if ($_GET['action'] === 'rebuild_all') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!$hasValidCsrf) {
            echo json_encode(['ok' => false, 'error' => 'CSRF token tidak valid.']);
            exit;
        }
        echo json_encode($fileManager->rebuildAllIndexes());
        exit;
    }
}

// ── Login check — redirect ke index.php ──
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ../index.php');
    exit;
}

// ── Include admin layout ──
require_once 'layout_top.php';

$adminUser = $_SESSION['admin_username'] ?? 'admin';
$adminRole = $_SESSION['admin_role'] ?? '';
$isAdmin   = \Core\Auth::can($adminRole, 'administrator');

// Path per-guru WhatsApp
$waPath = $storageDir . '/accounts/' . $adminUser . '/whatsapp.json';

// ── Load current DB config ──
$dbJsonExists = file_exists($dbJsonPath);
$dbFromFile = $dbJsonExists ? json_decode(file_get_contents($dbJsonPath), true) : null;
$dbConfig = $config['database'];

// ── Groq config ──
$groqConfigPath = $storageDir . '/config/groq.json';
$groqFromFile = file_exists($groqConfigPath) ? json_decode(file_get_contents($groqConfigPath), true) : null;
$groqKey  = $groqFromFile['api_key'] ?? '';
$groqModel = $groqFromFile['model'] ?? $config['groq']['model'];

// ── Save Settings ──
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!validateCsrf()) die('CSRF token tidak valid.');
    try {
        // WhatsApp (per-guru)
        if (isset($_POST['wa_names'], $_POST['wa_numbers'])) {
            $contacts = [];
            foreach ($_POST['wa_names'] as $i => $name) {
                $num = preg_replace('/[^0-9]/', '', trim($_POST['wa_numbers'][$i] ?? ''));
                $name = trim($name);
                if (!empty($name) && !empty($num)) {
                    $contacts[] = ['name' => $name, 'number' => $num];
                }
            }
            $dir = dirname($waPath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $written = file_put_contents($waPath, json_encode(['contacts' => $contacts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            if ($written === false) {
                throw new Exception('Gagal simpan kontak WhatsApp');
            }
            $config['whatsapp']['contacts'] = $contacts;
        }

        // Database
        $dbHost   = trim($_POST['db_host'] ?? '');
        $dbPort   = trim($_POST['db_port'] ?? '');
        $dbName   = trim($_POST['db_name'] ?? '');
        $dbUser   = trim($_POST['db_user'] ?? '');
        $dbPass   = $_POST['db_pass'] ?? '';

        if ($dbPass === '********') {
            $dbPass = $dbConfig['pass'] ?? '';
        }

        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            if (file_exists($dbJsonPath)) @unlink($dbJsonPath);
            file_put_contents($dbJsonPath, '{}');
            $msg = 'Disimpan! (database dikosongkan)';
        } else {
            $dbPayload = [
                'host'   => $dbHost,
                'port'   => $dbPort ?: '3306',
                'dbname' => $dbName,
                'user'   => $dbUser,
                'pass'   => $dbPass,
            ];
            $written = file_put_contents($dbJsonPath, json_encode($dbPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            if ($written === false) throw new Exception('Gagal simpan database.json');

            // Auto-migrate
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort ?: '3306', $dbName);
                $pdo = new \PDO($dsn, $dbUser, $dbPass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 5,
                ]);

                $pdo->exec("CREATE TABLE IF NOT EXISTS student_results (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    guru_username  VARCHAR(100)   NOT NULL,
                    soal_id        VARCHAR(255)   NOT NULL,
                    soal_judul     VARCHAR(255)   NOT NULL DEFAULT '',
                    mata_pelajaran VARCHAR(100)   NOT NULL DEFAULT '',
                    nama_siswa     VARCHAR(255)   NOT NULL,
                    nis            VARCHAR(50)    NOT NULL DEFAULT '',
                    skor           DECIMAL(5,2)   NOT NULL DEFAULT 0,
                    total_soal     INT            NOT NULL DEFAULT 0,
                    jumlah_benar   INT            NOT NULL DEFAULT 0,
                    jumlah_salah   INT            NOT NULL DEFAULT 0,
                    waktu_kumpul   DATETIME       NOT NULL,
                    created_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_result (soal_id, nama_siswa, nis)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS students (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    guru_username VARCHAR(100) NOT NULL,
                    nis           VARCHAR(50)  NOT NULL,
                    nama          VARCHAR(255) NOT NULL,
                    kelas         VARCHAR(50)  NOT NULL DEFAULT '',
                    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_siswa (guru_username, nis)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_index (
                    slug           VARCHAR(100) PRIMARY KEY,
                    guru_username  VARCHAR(100) NOT NULL,
                    filename       VARCHAR(255) NOT NULL,
                    judul          VARCHAR(255) NOT NULL DEFAULT '',
                    mata_pelajaran VARCHAR(100) NOT NULL DEFAULT '',
                    kelas_target   VARCHAR(50)  NOT NULL DEFAULT '',
                    status         VARCHAR(20)  NOT NULL DEFAULT 'draft',
                    pin            VARCHAR(4)   NOT NULL DEFAULT '',
                    timer_menit    INT          NOT NULL DEFAULT 20,
                    created_at     DATETIME     NOT NULL,
                    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_guru  (guru_username),
                    INDEX idx_status (status),
                    INDEX idx_mapel (mata_pelajaran)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $msg = 'Disimpan! Koneksi berhasil dan tabel database siap digunakan.';
            } catch (\Throwable $migrateErr) {
                $msg = 'Disimpan! Tapi gagal migrasi tabel: ' . $migrateErr->getMessage();
            }
        }

        // Groq API (kepala_sekolah only)
        if (\Core\Auth::can($adminRole, 'kepala_sekolah') && isset($_POST['groq_api_key'])) {
            $newGroqKey   = trim($_POST['groq_api_key']);
            $newGroqModel = trim($_POST['groq_model'] ?? 'mixtral-8x7b-32768');

            if ($newGroqKey === '********') {
                $newGroqKey = $groqKey;
            }

            $groqDir = dirname($groqConfigPath);
            if (!is_dir($groqDir)) mkdir($groqDir, 0755, true);
            file_put_contents($groqConfigPath, json_encode([
                'api_key' => $newGroqKey,
                'model'   => $newGroqModel,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            $groqKey = $newGroqKey;
            $groqModel = $newGroqModel;
        }

        // Account: change nama & password
        $newNama = trim($_POST['nama'] ?? '');
        $curPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (!empty($newNama) || !empty($newPass)) {
            $userInfo = $config['auth']->login($adminUser, $curPass);
            if (!$userInfo) {
                $err = 'Password saat ini salah.';
            } else {
                if (!empty($newNama)) {
                    $config['auth']->updateUser($adminUser, null, null, $newNama);
                    $_SESSION['admin_nama'] = $newNama;
                }
                if (!empty($newPass)) {
                    if (strlen($newPass) < 6) {
                        $err = 'Password baru minimal 6 karakter.';
                    } elseif ($newPass !== $confPass) {
                        $err = 'Konfirmasi password baru tidak cocok.';
                    } else {
                        $config['auth']->updateUser($adminUser, $newPass, null, null);
                    }
                }
                if (empty($err)) $msg = ($msg ? $msg . ' ' : '') . 'Akun berhasil diperbarui.';
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

$dbConfig = $config['database'];
?>

<style>
    .settings-container{max-width:700px;margin:0 auto}
    .settings-card{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:1rem}
    .settings-card h4{color:#3b82f6;margin:0 0 1rem}
    .settings-card p{color:#64748b;font-size:0.875rem;margin:0 0 1rem}
    .settings-card input,.settings-card select{width:100%;padding:0.75rem;margin:0.4rem 0 0.8rem;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;font-size:16px}
    .settings-card .btn{display:inline-block;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:600;cursor:pointer;border:1px solid transparent;font-size:0.875rem;text-align:center}
    .settings-card .btn-block{width:100%;justify-content:center}
    .settings-card label{font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.25rem}
    .settings-card small{color:#64748b;display:block;margin-top:0.25rem}
    .settings-card hr{border:0;border-top:1px solid #e2e8f0;margin:1.5rem 0}
    .settings-card .alert{padding:0.75rem;border-radius:8px;margin-bottom:1rem;font-size:0.9rem;word-break:break-word}
    .settings-card .alert-success{background:#dcfce7;color:#166534}
    .settings-card .alert-danger{background:#fef2f2;color:#991b1b}
    .settings-card .flex{display:flex;gap:0.75rem}
    .settings-card .flex-1{flex:1}
    .settings-card .flex input{margin:0}
    .settings-card .input-wrap{position:relative}
    .settings-card .toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.2rem;color:#64748b}
    .settings-card .toggle:hover{color:#3b82f6}
    .settings-card .wa-row{display:flex;gap:0.5rem;margin-bottom:0.5rem}
    .settings-card .wa-row input{margin:0;flex:1}
    .settings-card .status-ok{color:#166534;background:#dcfce7;padding:0.5rem 0.75rem;border-radius:6px;display:inline-flex;align-items:center;gap:0.4rem;font-weight:600}
    .settings-card .status-err{color:#991b1b;background:#fef2f2;padding:0.5rem 0.75rem;border-radius:6px;display:inline-flex;align-items:center;gap:0.4rem;font-weight:600}
    .settings-card .status-idle{color:#64748b;background:#f1f5f9;padding:0.5rem 0.75rem;border-radius:6px;display:inline-flex;align-items:center;gap:0.4rem}
    .settings-card .btn-success{background:#22c55e;color:#fff}
    .settings-card .btn-secondary{background:#64748b;color:#fff}
    .settings-card .btn-danger{background:#ef4444;color:#fff}
    .settings-card .btn-outline{background:transparent;color:#3b82f6;border:1px solid #3b82f6}
    .settings-card .btn-primary{background:#3b82f6;color:#fff}
    @media (max-width: 600px) {
        .settings-card .flex{flex-direction:column}
        .settings-card .flex input{width:100%}
        .settings-card .wa-row{flex-wrap:wrap}
        .settings-card .wa-row input{min-width:120px}
    }
</style>

<div class="settings-container">

    <?php if($msg) echo "<div class='alert alert-success'>" . htmlspecialchars($msg) . "</div>"; ?>
    <?php if($err) echo "<div class='alert alert-danger'>" . htmlspecialchars($err) . "</div>"; ?>

    <form method="post">
        <?= csrfField() ?>

        <!-- ─── WhatsApp ─── -->
        <div class="settings-card">
            <h4>Kontak WhatsApp</h4>
            <div id="wa-contacts">
                <?php
                $contacts = $config['whatsapp']['contacts'] ?? [];
                if (empty($contacts) && !empty($config['whatsapp']['child_number'])) {
                    $contacts[] = ['name' => 'Kontak Utama', 'number' => $config['whatsapp']['child_number']];
                }
                if (empty($contacts)) $contacts[] = ['name' => '', 'number' => ''];
                foreach ($contacts as $contact):
                ?>
                <div class="wa-row">
                    <input type="text" name="wa_names[]" value="<?=htmlspecialchars($contact['name'])?>" placeholder="Nama (Cth: Budi / Wali Murid)" required>
                    <input type="tel" name="wa_numbers[]" value="<?=htmlspecialchars($contact['number'])?>" placeholder="62812..." required>
                    <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()" style="padding:0.5rem 1rem">X</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addContact()" style="padding:0.4rem 0.8rem;font-size:0.875rem">+ Tambah Kontak</button>
            <small style="margin-top:0.5rem">Format nomor: internasional tanpa + atau spasi.</small>
        </div>

        <?php if ($isAdmin): ?>
        <!-- ─── Database ─── -->
        <div class="settings-card">
            <h4>Database (Opsional)</h4>
            <p>Jika dikosongkan, sistem berfungsi tanpa database (beberapa fitur laporan tidak tersedia). Tabel akan dibuat otomatis setelah disimpan.</p>

            <div class="flex">
                <div class="flex-1">
                    <label>Host</label>
                    <input type="text" name="db_host" id="db_host" value="<?=htmlspecialchars($dbConfig['host'] ?? '')?>" placeholder="localhost">
                </div>
                <div style="min-width:80px">
                    <label>Port</label>
                    <input type="text" name="db_port" id="db_port" value="<?=htmlspecialchars($dbConfig['port'] ?? '3306')?>" placeholder="3306">
                </div>
            </div>

            <label>Nama Database</label>
            <input type="text" name="db_name" id="db_name" value="<?=htmlspecialchars($dbConfig['dbname'] ?? '')?>" placeholder="portalsoal">

            <label>Username</label>
            <input type="text" name="db_user" id="db_user" value="<?=htmlspecialchars($dbConfig['user'] ?? '')?>" placeholder="root" autocomplete="off">

            <label>Password</label>
            <div class="input-wrap">
                <input type="password" name="db_pass" id="db_pass" value="<?=htmlspecialchars($dbFromFile && isset($dbFromFile['pass']) ? '********' : '')?>" placeholder="(kosong)" autocomplete="off" style="padding-right:40px">
                <button type="button" class="toggle" onclick="togglePwd('db_pass')">👁️</button>
            </div>
            <small>Kosongkan jika tidak ada password. Gunakan <strong>********</strong> untuk mempertahankan password yang sudah tersimpan.</small>

            <div style="margin-top:1rem;display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
                <button type="button" class="btn btn-success test-btn" id="test-db-btn" onclick="testConnection()">Test Connection</button>
                <span id="db-status"><span class="status-idle">Belum diuji</span></span>
            </div>

            <hr>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
                <button type="button" class="btn btn-outline" onclick="syncAll()">Sync All → MySQL</button>
                <button type="button" class="btn btn-outline" onclick="rebuildAll()">Rebuild Index.json</button>
                <span id="bulk-status"></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (\Core\Auth::can($adminRole, 'kepala_sekolah')): ?>
        <!-- ─── AI Generator (Groq) ─── -->
        <div class="settings-card">
            <h4>AI Generator (Groq API)</h4>
            <p>Konfigurasi API key dan model untuk fitur AI Generator Soal.</p>

            <label>API Key</label>
            <div class="input-wrap">
                <input type="password" name="groq_api_key" id="groq_api_key" value="<?=htmlspecialchars($groqKey ? '********' : '')?>" placeholder="gsk_..." autocomplete="off" style="padding-right:40px">
                <button type="button" class="toggle" onclick="togglePwd('groq_api_key')">👁️</button>
            </div>
            <small>Gunakan <strong>********</strong> untuk mempertahankan key yang sudah tersimpan.</small>

            <label>Model</label>
            <select name="groq_model" style="width:100%;padding:0.75rem;margin:0.4rem 0 0.8rem;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;font-size:16px">
                <?php
                $models = [
                    'mixtral-8x7b-32768'         => 'Mixtral 8x7B',
                    'llama-3.3-70b-versatile'     => 'Llama 3.3 70B',
                    'llama-3.1-8b-instant'        => 'Llama 3.1 8B',
                    'deepseek-r1-distill-llama-70b' => 'DeepSeek R1 Distill Llama 70B',
                    'deepseek-r1-distill-qwen-32b' => 'DeepSeek R1 Distill Qwen 32B',
                    'gemma2-9b-it'                => 'Gemma 2 9B',
                    'qwen-2.5-32b'                => 'Qwen 2.5 32B',
                    'qwen-2.5-coder-32b'          => 'Qwen 2.5 Coder 32B',
                ];
                foreach ($models as $val => $label): ?>
                <option value="<?=$val?>" <?=$groqModel === $val ? 'selected' : ''?>><?=$label?></option>
                <?php endforeach; ?>
            </select>
            <small>Model Groq gratis — lihat daftar lengkap di <a href="https://console.groq.com/docs/models" target="_blank">Groq Docs</a>.</small>
        </div>
        <?php endif; ?>

        <!-- ─── Account Info & Password ─── -->
        <div class="settings-card">
            <h4>Informasi Akun</h4>
            <p>Login sebagai: <strong><?=htmlspecialchars($_SESSION['admin_username'] ?? '')?></strong></p>

            <label>Nama Lengkap</label>
            <input type="text" name="nama" value="<?=htmlspecialchars($_SESSION['admin_nama'] ?? $_SESSION['admin_username'] ?? '')?>" placeholder="Nama lengkap">

            <hr>

            <label>Password Saat Ini</label>
            <div class="input-wrap">
                <input type="password" name="current_password" id="cur_pass" placeholder="Diperlukan untuk mengubah data akun" autocomplete="off" style="padding-right:40px">
                <button type="button" class="toggle" onclick="togglePwd('cur_pass')">👁️</button>
            </div>

            <label>Password Baru (kosongkan jika tidak diubah)</label>
            <div class="input-wrap">
                <input type="password" name="new_password" id="new_pass" placeholder="Minimal 6 karakter" autocomplete="off" style="padding-right:40px">
                <button type="button" class="toggle" onclick="togglePwd('new_pass')">👁️</button>
            </div>

            <label>Konfirmasi Password Baru</label>
            <div class="input-wrap">
                <input type="password" name="confirm_password" id="conf_pass" placeholder="Ketik ulang password baru" autocomplete="off" style="padding-right:40px">
                <button type="button" class="toggle" onclick="togglePwd('conf_pass')">👁️</button>
            </div>
            <small>Kosongkan password baru jika tidak ingin mengganti password.</small>
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:0.75rem;flex-wrap:wrap">
            <button type="submit" name="save" class="btn btn-primary" style="background:#3b82f6;color:#fff;border:none">Simpan</button>
        </div>
    </form>
</div>

<script>
var CSRF_TOKEN = '<?= csrfToken() ?>';
function addContact() {
    var c = document.getElementById('wa-contacts');
    var d = document.createElement('div');
    d.className = 'wa-row';
    d.innerHTML = '<input type="text" name="wa_names[]" value="" placeholder="Nama (Cth: Budi / Wali Murid)" required><input type="tel" name="wa_numbers[]" value="" placeholder="62812..." required><button type="button" class="btn btn-danger" onclick="this.parentElement.remove()" style="padding:0.5rem 1rem">X</button>';
    c.appendChild(d);
}

function togglePwd(id) {
    var e = document.getElementById(id);
    if(!e) return;
    e.type = e.type === 'password' ? 'text' : 'password';
}

function syncAll() {
    var btn = event.target;
    var st = document.getElementById('bulk-status');
    btn.disabled = true;
    st.innerHTML = '<span class="status-idle">Menyinkronkan...</span>';
    fetch('?action=sync_all', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                st.innerHTML = '<span class="status-ok">Sinkron: ' + d.synced + ' berhasil, ' + d.errors + ' gagal (' + d.total_guru + ' guru)</span>';
            } else {
                st.innerHTML = '<span class="status-err">' + escapeHtml(d.error || 'Gagal') + '</span>';
            }
        })
        .catch(function(e) { st.innerHTML = '<span class="status-err">' + escapeHtml(e.message) + '</span>'; })
        .finally(function() { btn.disabled = false; });
}

function rebuildAll() {
    var btn = event.target;
    var st = document.getElementById('bulk-status');
    btn.disabled = true;
    st.innerHTML = '<span class="status-idle">Membangun ulang index...</span>';
    fetch('?action=rebuild_all', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                st.innerHTML = '<span class="status-ok">Index dibangun ulang untuk ' + d.rebuilt + ' guru</span>';
            } else {
                st.innerHTML = '<span class="status-err">' + escapeHtml(d.error || 'Gagal') + '</span>';
            }
        })
        .catch(function(e) { st.innerHTML = '<span class="status-err">' + escapeHtml(e.message) + '</span>'; })
        .finally(function() { btn.disabled = false; });
}

function testConnection() {
    var btn = document.getElementById('test-db-btn');
    var status = document.getElementById('db-status');
    btn.disabled = true;
    status.innerHTML = '<span class="status-idle">Mengetes...</span>';

    var formData = new FormData();
    formData.append('_csrf_token', CSRF_TOKEN);
    formData.append('host', document.getElementById('db_host').value || 'localhost');
    formData.append('port', document.getElementById('db_port').value || '3306');
    formData.append('dbname', document.getElementById('db_name').value || '');
    formData.append('user', document.getElementById('db_user').value || '');
    formData.append('pass', document.getElementById('db_pass').value === '********' ? '' : document.getElementById('db_pass').value);

    fetch('?action=test_db', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            status.innerHTML = d.ok
                ? '<span class="status-ok">Terhubung</span>'
                : '<span class="status-err">Gagal: ' + escapeHtml(d.message) + '</span>';
        })
        .catch(function(e) { status.innerHTML = '<span class="status-err">Error: ' + escapeHtml(e.message) + '</span>'; })
        .finally(function() { btn.disabled = false; });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
</script>

<?php require_once 'layout_bottom.php'; ?>
