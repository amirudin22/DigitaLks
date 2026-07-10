<?php
// Pastikan session dimulai hanya sekali
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Core/FileManager.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/Database.php';
require_once __DIR__ . '/../../src/Core/PromptResolver.php';

$fileManager = new \Core\FileManager($config['storage']['path']);
$security    = new \Core\Security($fileManager);
$db          = \Core\Database::getInstance($config['database'] ?? []);

// 1. Validasi Slug dan Cari File Soal
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    die("Akses tidak sah. Harap gunakan link (slug) yang diberikan Guru/Orang Tua Anda.");
}

$fileManager->setDatabase($db);

// Cari soal via index.json → MySQL → scan folder
$result = $fileManager->findBySlug($slug);
$soalData = null;
$soalFilePath = null;
$quizOwner = null;

if ($result) {
    $status = $result['data']['metadata']['status'] ?? '';
    if ($status === 'active' || $status === 'completed') {
        $soalData = $result['data'];
        $soalFilePath = $result['path'];
        $quizOwner = $result['guru'];
    }
}

if (!$soalData) {
    die("Latihan soal tidak ditemukan atau mungkin sudah dinonaktifkan / dicabut oleh Orang Tua.");
}

$pinTarget = $soalData['metadata']['pin'];
$quizId = $soalData['metadata']['slug'];

// Jika sudah dikumpulkan dan tidak ada sesi di browser ini
if (($soalData['metadata']['status'] ?? '') === 'completed' && !isset($_SESSION['quiz_auth'][$slug])) {
    if (!isset($_GET['state']) || $_GET['state'] !== 'result') {
        die("Latihan ini sudah dikerjakan dan telah ditutup secara otomatis.");
    }
}

// State Management
$state = $_GET['state'] ?? 'pin'; // pin, onboarding, quiz, result
$error = '';

// Copy Protection defaults (diinisialisasi di sini agar tersedia di semua state)
$cpConfig = null;
$cpMeta   = null;
$modeSoal = 'latihan_harian';
$isUjianAkhir = false;
$cpActive = false;
$blockCopy = false;
$resolvedRole = null;

/** Proses Validasi PIN */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pin'])) {
    $pinInput = $_POST['pin'] ?? '';
    
    if (!$security->checkRateLimit('pin_attempt_' . $slug, 3, 600)) {
        $error = "Terlalu banyak percobaan PIN salah. Harap tunggu 10 Menit sebelum mencoba lagi.";
    } else {
        if ($pinInput === $pinTarget) {
            $_SESSION['quiz_auth'][$slug] = true;
            $security->resetRateLimit('pin_attempt_' . $slug);
            
            if (isset($soalData['hasil']) && is_array($soalData['hasil'])) {
                header("Location: ?slug=" . urlencode($slug) . "&state=result");
                exit;
            } else {
                header("Location: ?slug=" . urlencode($slug) . "&state=onboarding");
                exit;
            }
        } else {
            $security->recordFailedAttempt('pin_attempt_' . $slug, 3, 600);
            $error = "PIN salah! Silakan coba lagi.";
        }
    }
}

/** Proses Onboarding */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_onboarding'])) {
    if (isset($_SESSION['quiz_auth'][$slug])) {
        $nisSiswa = $security->sanitize($_POST['nis'] ?? '');

        // Ambil nama dari DB (bukan dari POST, supaya tidak bisa dimanipulasi)
        $siswaDiDB = $db->isConnected() ? $db->getSiswaByNis($quizOwner, $nisSiswa) : null;

        if ($db->isConnected() && !$siswaDiDB) {
            // NIS tidak terdaftar — tolak
            $error = 'NIS tidak terdaftar. Hubungi gurumu untuk mendaftarkan NIS kamu terlebih dahulu.';
        } else {
            // Ambil nama dari DB jika ada, fallback ke POST jika DB offline
            $namaFinal = $siswaDiDB ? $siswaDiDB['nama'] : $security->sanitize($_POST['nama'] ?? 'Anak Hebat');

            $_SESSION['quiz_session'][$slug] = [
                'nama'       => $namaFinal,
                'nis'        => $nisSiswa,
                'cita_cita'  => $security->sanitize($_POST['cita_cita'] ?? ''),
                'start_time' => time()
            ];
            header("Location: ?slug=" . urlencode($slug) . "&state=quiz");
            exit;
        }
    }
}


/** Validasi Auth Akses ke Page Internal */
if (in_array($state, ['onboarding', 'quiz', 'result'])) {
    if (!isset($_SESSION['quiz_auth'][$slug])) {
        header("Location: ?slug=" . urlencode($slug) . "&state=pin");
        exit;
    }
}

// Jika quiz sudah dikerjakan, paksa ke halaman hasil
if ($state === 'quiz' && isset($soalData['hasil']) && is_array($soalData['hasil'])) {
    header("Location: ?slug=" . urlencode($slug) . "&state=result");
    exit;
}

/** Proses Submit Jawaban Akhir */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    if (!isset($_SESSION['quiz_auth'][$slug])) {
        die("Unauthenticated submission.");
    }
    
    if (isset($soalData['hasil']) && is_array($soalData['hasil'])) {
        header("Location: ?slug=" . urlencode($slug) . "&state=result");
        exit;
    }
    
    $jawabanSiswa = $_POST['ans'] ?? [];
    $penjelasanSiswa = $_POST['penjelasan'] ?? [];
    $benar = 0;
    $total = count($soalData['soal']);
    $detailHasil = [];
    
    foreach ($soalData['soal'] as $s) {
        $idSoal = $s['id'];
        $jwbBenar = $s['jawaban_benar'] ?? '';
        $jwbSiswa = $jawabanSiswa[$idSoal] ?? '';
        $isCorrect = false;
        
        if (!empty($jwbBenar) && $jwbBenar === $jwbSiswa) {
            $benar++;
            $isCorrect = true;
        }
        
        $detailHasil[$idSoal] = [
            'jwb_siswa' => $jwbSiswa,
            'is_correct' => $isCorrect,
            'penjelasan' => trim($penjelasanSiswa[$idSoal] ?? '')
        ];    }
    
    $nisSiswa    = $_SESSION['quiz_session'][$slug]['nis']  ?? '';
    $namaSiswa   = $_SESSION['quiz_session'][$slug]['nama'] ?? 'Unknown';
    $skorAkhir   = $total > 0 ? round(($benar / $total) * 100) : 0;
    $waktuKumpul = gmdate('Y-m-d H:i:s'); // format MySQL

    $soalData['hasil'] = [
        'skor'         => $skorAkhir,
        'benar'        => $benar,
        'salah'        => $total - $benar,
        'total'        => $total,
        'detail'       => $detailHasil,
        'nama'         => $namaSiswa,
        'nis'          => $nisSiswa,
        'cita_cita'    => $_SESSION['quiz_session'][$slug]['cita_cita'] ?? '',
        'waktu_kumpul' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    $soalData['metadata']['status'] = 'completed';
    $fileManager->writeJsonAndSync($soalFilePath, $soalData);

    // ── Simpan ke MySQL (trigger laporan guru) ──
    $db->saveResult([
        'guru_username'  => $quizOwner,
        'soal_id'        => basename($soalFilePath),
        'soal_judul'     => $soalData['metadata']['judul']          ?? '',
        'mata_pelajaran' => $soalData['metadata']['mata_pelajaran'] ?? '',
        'nama_siswa'     => $namaSiswa,
        'nis'            => $nisSiswa,
        'skor'           => $skorAkhir,
        'total_soal'     => $total,
        'jumlah_benar'   => $benar,
        'jumlah_salah'   => $total - $benar,
        'waktu_kumpul'   => $waktuKumpul,
    ]);

    header("Location: ?slug=" . urlencode($slug) . "&state=result");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($soalData['metadata']['judul']) ?> - DigitLKS</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        window.MathJax = {
            tex: { inlineMath: [['\\(', '\\)']] }
        };
    </script>
    <!-- MathLive CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mathlive/dist/mathlive.core.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mathlive/dist/mathlive.css">

<style>
math-field {
    width: 100%;
    min-height: 60px;
    font-size: 20px;
    padding: 10px;
    border: 2px dashed #FFB347;
    border-radius: 12px;
    background: #FFFDF7;
}

</style>
    <style>
        :root {
            --brand-primary: #FF7E67;
            --brand-secondary: #FFB347;
            --brand-bg: #FFF8F0;
            --text-dark: #2D3748;
            --text-light: #718096;
            --box-shadow: 0 10px 15px -3px rgba(255, 126, 103, 0.1), 0 4px 6px -2px rgba(255, 126, 103, 0.05);
            --card-radius: 1.5rem;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Fredoka', sans-serif;
            background-color: var(--brand-bg);
            color: var(--text-dark);
            -webkit-tap-highlight-color: transparent;
        }        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1.5rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        h1, h2 { color: var(--brand-primary); }
        .text-center { text-align: center; }
        .card {
            background: #FFFFFF;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            width: 100%;
            margin-top: 1rem;
        }
        .input-fat {
            width: 100%;
            padding: 1rem;
            font-size: 1.25rem;
            border: 2px solid #E2E8F0;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-family: 'Fredoka', sans-serif;
            text-align: center;
            letter-spacing: 2px;
            transition: all 0.3s;
        }
        .input-fat:focus {
            border-color: var(--brand-primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(255, 126, 103, 0.2);
        }
        .btn-fat {
            width: 100%;
            padding: 1rem;
            font-size: 1.25rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: white;
            border: none;
            border-radius: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(255, 126, 103, 0.3);
            font-family: 'Fredoka', sans-serif;
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .btn-fat:active {            transform: scale(0.98);
            box-shadow: 0 2px 4px rgba(255, 126, 103, 0.3);
        }
        .timer-bar {
            background: #FFFFFF;
            padding: 1rem;
            border-radius: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 1.25rem;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 1rem;
            z-index: 100;
        }
        .timer-warning {
            color: #E53E3E;
            animation: pulse 1s infinite alternate;
        }
        @keyframes pulse { from { opacity: 1; } to { opacity: 0.7; } }
        .question-card { display: none; }
        .question-card.active {
            display: block;
            animation: slideIn 0.3s ease-out forwards;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .q-text {
            font-size: 1.125rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            white-space: pre-wrap;
            font-weight: 500;
        }
        .option-label {
            display: block;
            border: 2px solid #E2E8F0;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .option-label input[type="radio"] { display: none; }        .option-label input[type="radio"]:checked + .opt-indicator {
            background-color: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }
        .option-label:has(input[type="radio"]:checked) {
            border-color: var(--brand-primary);
            background-color: #FFF5F5;
        }
        .opt-indicator {
            display: inline-flex;
            width: 32px;
            height: 32px;
            border: 2px solid #CBD5E0;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 600;
            color: #718096;
            transition: all 0.2s;
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .progress-dots {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #E2E8F0;
            transition: background 0.3s;
        }
        .dot.answered { background: var(--brand-secondary); }
        .dot.active {
            background: var(--brand-primary);
            transform: scale(1.3);
        }
        .result-score {
            font-size: 4rem;
            color: var(--brand-primary);
            font-weight: 700;            margin: 1rem 0;
            line-height: 1;
        }
        .review-box {
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            background: #F7FAFC;
            border: 1px solid #E2E8F0;
        }
        .correct { color: #38A169; }
        .wrong { color: #E53E3E; }
        .hint-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1.2rem;
            margin-top: 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            background: linear-gradient(135deg, #FEFCBF, #FEF3C7);
            border: 1.5px solid #FCD34D;
            border-radius: 0.75rem;
            color: #92400E;
            cursor: pointer;
            touch-action: manipulation;
            min-height: 48px;
            font-family: 'Fredoka', sans-serif;
            transition: all 0.2s;
        }
        .hint-btn:active {
            transform: scale(0.97);
        }
        

    </style>
</head>
<body>
<div class="container">
    <!-- STATE: PIN -->
    <?php if ($state === 'pin'): ?>
    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <div style="margin-bottom: 2rem; text-align: center;">
            <div style="font-size: 4rem;">🧩</div>
            <h1 style="font-size: 2rem; margin-top: 1rem;">
                <?= htmlspecialchars($soalData['metadata']['judul']) ?>
            </h1>
            <p style="color: var(--text-light); font-size: 1.1rem; margin-top: 0.5rem;">
                Kelas <?= htmlspecialchars($soalData['metadata']['kelas_target']) ?> • 
                <?= htmlspecialchars($soalData['metadata']['mata_pelajaran']) ?>
            </p>
        </div>        <form method="POST" class="card" style="margin-top:0;">
            <?php if ($error): ?>
            <div style="background: #FED7D7; color: #C53030; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; text-align: center; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <h2 class="text-center" style="margin-bottom: 1.5rem; font-size: 1.25rem;">Masukkan PIN Akses 🔒</h2>
            <input type="password" inputmode="numeric" pattern="[0-9]*" name="pin" class="input-fat"
                placeholder="Masukan 4 Digit PIN" maxlength="4" required autofocus>
            <button type="submit" name="submit_pin" class="btn-fat">Mulai Latihan</button>
            <div style="text-align: center; margin-top: 1.5rem; color: var(--text-light); font-size: 0.9rem;">
                Minta PIN ini ke Orang Tua kamu ya!
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- STATE: ONBOARDING -->
    <?php if ($state === 'onboarding'): ?>
    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <form method="POST" class="card" id="onboardingForm">
            <div class="text-center" style="font-size: 3rem; margin-bottom: 1rem;">👋</div>
            <h2 class="text-center" style="margin-bottom: 0.5rem;">Halo, Jagoan!</h2>
            <p class="text-center" style="color: var(--text-light); margin-bottom: 2rem;">Masukkan NIS kamu untuk mulai latihan!</p>

            <?php if ($error): ?>
            <div style="background:#FED7D7; color:#C53030; padding:1rem; border-radius:1rem; margin-bottom:1.5rem; text-align:center; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Step 1: NIS input -->
            <label style="display:block; font-weight:600; margin-bottom:0.5rem; color:var(--text-dark);">Nomor Induk Siswa (NIS): <span style="color:#E53E3E;">*</span></label>
            <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                <input type="text" id="nisInput" name="nis" class="input-fat"
                    style="text-align:left; letter-spacing:2px; margin-bottom:0; flex:1;"
                    placeholder="Contoh: 20240001" required autocomplete="off" inputmode="numeric"
                    oninput="resetNisState()" autofocus>
                <button type="button" id="btnCekNis" onclick="cekNis()"
                    style="padding:0 1.5rem; font-size:1rem; font-weight:600;
                           background:linear-gradient(135deg,var(--brand-primary),var(--brand-secondary));
                           color:white; border:none; border-radius:1rem; cursor:pointer; white-space:nowrap;
                           box-shadow:0 4px 6px rgba(255,126,103,.3);">
                    Cek NIS
                </button>
            </div>
            <p style="font-size:0.8rem; color:var(--text-light); margin-bottom:1.5rem; text-align:center;">
                📋 NIS ada di kartu pelajar atau buku raport kamu
            </p>

            <!-- Status lookup NIS -->
            <div id="nisStatus" style="display:none; padding:0.75rem 1rem; border-radius:1rem; margin-bottom:1rem; font-size:0.9rem; text-align:center;"></div>

            <!-- Step 2: Nama (auto-fill, readonly) —  muncul setelah NIS valid -->
            <div id="namaSection" style="display:none;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem; color:var(--text-dark);">Nama Kamu:</label>
                <input type="text" id="namaInput" name="nama" class="input-fat"
                    style="text-align:left; letter-spacing:normal; background:#F0FDF4; color:#065F46;
                           border-color:#6EE7B7; cursor:not-allowed;"
                    readonly>
                <p style="font-size:0.8rem; color:var(--text-light); margin-top:-0.75rem; margin-bottom:1.5rem; text-align:center;">
                    ✅ Nama diambil otomatis dari data sekolah
                </p>

                <!-- Cita-cita -->
                <label style="display:block; font-weight:600; margin-bottom:0.5rem; color:var(--text-dark);">Cita-citamu Kelak:</label>
                <input type="text" name="cita_cita" class="input-fat" style="text-align:left; letter-spacing:normal;"
                    placeholder="Contoh: Dokter, Astronot, Youtuber">

                <button type="submit" name="submit_onboarding" id="btnMulai" class="btn-fat" style="margin-top:1rem;">
                    Aku Siap! Mulai Waktu 🚀
                </button>
            </div>
        </form>
    </div>

    <script>
    const API_URL = '/s/api_siswa.php';
    const GURU_OWNER = '<?= addslashes($quizOwner) ?>';

    function resetNisState() {
        document.getElementById('nisStatus').style.display = 'none';
        document.getElementById('namaSection').style.display = 'none';
        document.getElementById('namaInput') && (document.getElementById('namaInput').value = '');
    }

    async function cekNis() {
        const nis = document.getElementById('nisInput').value.trim();
        const statusEl = document.getElementById('nisStatus');
        const namaSection = document.getElementById('namaSection');
        const btn = document.getElementById('btnCekNis');

        if (!nis) {
            alert('Harap isi NIS terlebih dahulu!');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Mengecek...';
        statusEl.style.display = 'none';

        try {
            const res = await fetch(`${API_URL}?nis=${encodeURIComponent(nis)}&guru=${encodeURIComponent(GURU_OWNER)}`);
            const data = await res.json();

            if (data.found) {
                // Sukses — tampilkan nama
                document.getElementById('namaInput').value = data.nama;
                namaSection.style.display = 'block';
                statusEl.style.display = 'block';
                statusEl.style.background = '#F0FDF4';
                statusEl.style.color = '#065F46';
                statusEl.style.border = '1.5px solid #6EE7B7';
                statusEl.textContent = '✅ NIS ditemukan! Halo, ' + data.nama + (data.kelas ? ' (Kelas ' + data.kelas + ')' : '') + '!';
            } else if (data.fallback) {
                // DB offline — fallback ke input manual
                document.getElementById('namaInput').removeAttribute('readonly');
                document.getElementById('namaInput').style.background = '';
                document.getElementById('namaInput').style.cursor = 'text';
                document.getElementById('namaInput').placeholder = 'Tuliskan namamu';
                namaSection.style.display = 'block';
                statusEl.style.display = 'block';
                statusEl.style.background = '#FFFBEB';
                statusEl.style.color = '#92400E';
                statusEl.style.border = '1.5px solid #FCD34D';
                statusEl.textContent = '⚠️ Sistem offline. Silakan isi nama secara manual.';
            } else {
                // NIS tidak ditemukan
                statusEl.style.display = 'block';
                statusEl.style.background = '#FEF2F2';
                statusEl.style.color = '#991B1B';
                statusEl.style.border = '1.5px solid #FCA5A5';
                statusEl.textContent = '❌ ' + (data.message || 'NIS tidak ditemukan.');
                namaSection.style.display = 'none';
            }
        } catch (e) {
            statusEl.style.display = 'block';
            statusEl.style.background = '#FEF2F2';
            statusEl.style.color = '#991B1B';
            statusEl.textContent = '❌ Gagal terhubung ke server. Coba lagi.';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Cek NIS';
        }
    }

    // Tekan Enter di field NIS langsung cek
    document.getElementById('nisInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); cekNis(); }
    });
    </script>
    <?php endif; ?>


    <!-- STATE: QUIZ -->
    <?php if ($state === 'quiz'): ?>
    <?php
        $isTanpaBatas = ((int)($soalData['metadata']['timer_menit'] ?? 20) === 0);
        $durationSeconds = $isTanpaBatas ? 0 : (int)($soalData['metadata']['timer_menit'] ?? 20) * 60;
        $startTime = $_SESSION['quiz_session'][$slug]['start_time'] ?? time();
        $elapsed = time() - $startTime;
        $remaining = $isTanpaBatas ? -1 : max(0, $durationSeconds - $elapsed);
        
        $rawPrompt = $soalData['metadata']['explanation_prompt'] ?? '';
        $explanationPrompt = !empty(trim($rawPrompt)) 
            ? htmlspecialchars(trim($rawPrompt)) 
            : 'Mengapa kamu memilih jawaban tersebut? Coba jelaskan dengan kalimatmu sendiri! 📝';

        // Copy Protection config
        $cpConfig = $config['copy_protection'] ?? null;
        $modeSoal = $soalData['metadata']['mode_soal'] ?? 'latihan_harian';
        $isUjianAkhir = ($modeSoal === 'ujian_akhir');

        // Ambil metadata copy protection dari soal, atau buat fallback
        $cpMeta = $soalData['metadata']['copy_protection'] ?? null;
        // Selalu gunakan mata_pelajaran dari metadata soal sebagai sumber kebenaran
        $mataPelajaran = trim($soalData['metadata']['mata_pelajaran'] ?? 'Umum');
        if (!$cpMeta) {
            // Soal lama / tanpa copy_protection metadata
            $cpMeta = [
                'enabled'        => !$isUjianAkhir,
                'tipe_mapel'     => $mataPelajaran,
                'thinking_level' => 'medium',
                'block_copy'     => $isUjianAkhir,
            ];
        } else {
            // Soal baru: sinkronkan tipe_mapel dengan mata_pelajaran aktual
            // (tipe_mapel di cpMeta mungkin berbeda karena disimpan saat edit lama)
            $cpMeta['tipe_mapel'] = $mataPelajaran;
        }
        $cpActive = $cpConfig && !$isUjianAkhir && ($cpMeta['enabled'] ?? true);
        $blockCopy = $cpActive && ($cpMeta['block_copy'] ?? false);

        // ── Resolusi role via PromptResolver (shared logic) ──
        $resolution = \Core\PromptResolver::resolveRole($cpConfig ?? [], $mataPelajaran);
        $resolvedRole = $resolution['role'];

        // Merge global levels jika role tidak punya sendiri
        if (empty($resolvedRole['levels']) && !empty($cpConfig['levels'])) {
            $resolvedRole['levels'] = $cpConfig['levels'];
        }
    ?>

    
    <?php if ($isTanpaBatas): ?>
    <div class="timer-bar" id="timer-ui" style="background: linear-gradient(135deg, #F0FDF4, #DCFCE7); border: 1.5px solid #BBF7D0;">
        <span style="display:flex; align-items:center; gap: 0.5rem; color:#059669; font-size:1rem; font-weight:700;">⏾ Tanpa Batas Waktu</span>
        <button type="button" onclick="submitQuizDirectly()"
            style="background:transparent; border:none; color:var(--brand-primary); font-weight:700; font-size:1rem; cursor:pointer;">KUMPULKAN</button>
    </div>
    <?php else: ?>
    <div class="timer-bar" id="timer-ui">
        <span style="display:flex; align-items:center; gap: 0.5rem;">⏱️ <span id="time-display">00:00</span></span>
        <button type="button" onclick="submitQuizDirectly()"
            style="background:transparent; border:none; color:var(--brand-primary); font-weight:700; font-size:1rem; cursor:pointer;">KUMPULKAN</button>
    </div>
    <?php endif; ?>

    <!-- Progress Tracker UI -->
    <div class="card" style="padding: 1rem;">
        <div class="progress-dots" id="dots-container">
            <?php foreach ($soalData['soal'] as $i => $s): ?>
            <div class="dot" id="dot-<?= $i ?>" onclick="goToQuestion(<?= $i ?>)" style="cursor:pointer;" title="Soal <?= $i + 1 ?>"></div>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="POST" id="quiz-form">
        <?php if (empty($soalData['soal'])): ?>
        <div class='card'><p>🙈 Tidak ada soal ditemukan. Hubungi guru kamu untuk info lebih lanjut.</p></div>
        <?php endif; ?>
        
        <?php foreach ($soalData['soal'] as $idx => $soal): ?>
        <div class="card question-card <?= $idx === 0 ? 'active' : '' ?>" id="q-pane-<?= $idx ?>">
            <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-light); font-weight:600; margin-bottom: 1rem; border-bottom: 2px dashed #E2E8F0; padding-bottom: 0.5rem;">
                <span>SOAL NOMOR <?= $idx + 1 ?> DARI <?= count($soalData['soal']) ?></span>
                <?php if ($cpActive || $blockCopy): ?>
                <button type="button" class="hint-btn" onclick="copyHint(<?= $idx ?>)">💡 Hint</button>
                <?php endif; ?>
            </div>
            <div class="q-text"><?= htmlspecialchars($soal['pertanyaan']) ?></div>
            
            <div class="options-container">
                <?php foreach (['A', 'B', 'C', 'D'] as $opt):
                    if (empty($soal['opsi'][$opt])) continue; ?>
                <label class="option-label">
                    <input type="radio" name="ans[<?= $soal['id'] ?>]" value="<?= $opt ?>" class="radio-sync" data-qindex="<?= $idx ?>">
                    <div style="display:flex; align-items:flex-start;">
                        <span class="opt-indicator"><?= $opt ?></span>
                        <span style="padding-top:0.2rem;"><?= htmlspecialchars($soal['opsi'][$opt]) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:1rem;">

    <button type="button" class="btn-fat" style="font-size:1rem; padding:0.75rem;"

        onclick="toggleMathInput(<?= $soal['id'] ?>)">

        ✍️ Tulis Coretan Matematika

    </button>

    <div id="math-wrap-<?= $soal['id'] ?>" style="display:none; margin-top:1rem;">

        <math-field id="math-<?= $soal['id'] ?>"></math-field>

    </div>

</div>
            
            <?php if ($isTanpaBatas): ?>
            <div style="margin-top: 1.5rem; padding: 1rem 1.25rem; background: #F0FDF4; border-radius: 1rem; border: 1.5px dashed #6EE7B7;">
                <label style="display:block; font-weight:600; color:#065F46; font-size:0.95rem; margin-bottom:0.5rem;">
                    💬 <?= $explanationPrompt ?>
                </label>
                <textarea name="penjelasan[<?= $soal['id'] ?>]"
                    style="width:100%; padding:0.75rem; border-radius:0.75rem; border:1.5px solid #A7F3D0; font-family:'Fredoka',sans-serif; font-size:1rem; color:#1a1a1a; resize:vertical; min-height:80px;"
                    placeholder="Tulis pendapatmu di sini..."></textarea>
                <small style="color:#6B7280; font-style:italic; font-size:0.8rem;">⚠️ Kolom ini hanya catatan — tidak mempengaruhi nilai kamu 😊</small>
            </div>
            <?php endif; ?>

            <?php if ($isUjianAkhir): ?>
            <div style="margin-top:1rem; text-align:center; padding:1rem; background:#F3F4F6; border-radius:0.75rem; color:#6B7280; font-size:0.9rem;">
                🔒 Soal ujian ini tidak dapat disalin demi integritas penilaian.
            </div>
            <?php endif; ?>

            <div class="nav-buttons">
                <?php if ($idx > 0): ?>
                <button type="button" class="btn-fat" style="background:#E2E8F0; color:#4A5568; flex:1;" onclick="goToQuestion(<?= $idx - 1 ?>)">⬅️ Mundur</button>
                <?php endif; ?>
                <?php if ($idx < count($soalData['soal']) - 1): ?>
                <button type="button" class="btn-fat" style="flex:2;" onclick="goToQuestion(<?= $idx + 1 ?>)">Lanjut ➡️</button>
                <?php else: ?>
                <button type="button" class="btn-fat" style="flex:2;" onclick="submitQuizDirectly()">Selesai & Kumpul! 🏁</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <input type="hidden" name="submit_quiz" value="1">
    </form>

    <script>
        // Timer Logic
        let remainingSeconds = <?= $remaining ?>;
        const timeDisplay = document.getElementById('time-display');
        const timerUI = document.getElementById('timer-ui');
        
        function updateTimer() {            if (remainingSeconds < 0) return;
            let m = Math.floor(remainingSeconds / 60);
            let s = remainingSeconds % 60;
            timeDisplay.innerText = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            if (remainingSeconds <= 60) timerUI.classList.add('timer-warning');
            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                timeDisplay.innerText = "WAKTU HABIS!";
                document.getElementById('quiz-form').submit();
            }
            remainingSeconds--;
        }
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();

        // Navigation & Local Storage
        let currentQ = 0;
        const totalQ = <?= count($soalData['soal']) ?>;
        const slug = "<?= addslashes($slug) ?>";
        
        function updateDots() {
            for (let i = 0; i < totalQ; i++) {
                let dot = document.getElementById('dot-' + i);
                dot.classList.remove('active', 'answered');
                let isAnswered = document.querySelector('input[data-qindex="' + i + '"]:checked');
                if (isAnswered) dot.classList.add('answered');
                if (i === currentQ) dot.classList.add('active');
            }
        }
        
        function goToQuestion(idx) {
            document.querySelectorAll('.question-card').forEach(c => c.classList.remove('active'));
            document.getElementById('q-pane-' + idx).classList.add('active');
            currentQ = idx;
            updateDots();
            window.scrollTo(0, 0);
        }
        
        function submitQuizDirectly() {
            if (confirm("Yakin sudah selesai dan mau dikumpulkan sekarang? Jawaban tidak bisa diubah setelah ini!")) {
                document.getElementById('quiz-form').submit();
            }
        }
        
        const storageKey = 'cache_cici_' + slug;
        function restoreAns() {
            let saved = localStorage.getItem(storageKey);
            if (saved) {
                try {
                    let parsed = JSON.parse(saved);                    for (let name in parsed) {
                        let r = document.querySelector('input[name="' + name + '"][value="' + parsed[name] + '"]');
                        if (r) r.checked = true;
                    }
                } catch (e) {}
            }
            updateDots();
        }
        
        document.querySelectorAll('.radio-sync').forEach(r => {
            r.addEventListener('change', function () {
                let state = {};
                document.querySelectorAll('.radio-sync:checked').forEach(c => state[c.name] = c.value);
                localStorage.setItem(storageKey, JSON.stringify(state));
                updateDots();
            });
        });
        restoreAns();


    </script>
    <?php endif; ?>

    <!-- STATE: RESULT -->
    <?php if ($state === 'result'): ?>
    <?php
        $hasil = $soalData['hasil'] ?? ['skor' => 0, 'benar' => 0, 'total' => 0, 'detail' => []];
        $namaAnak = htmlspecialchars($hasil['nama'] ?? 'Anak Hebat');
        $citaCita = htmlspecialchars($hasil['cita_cita'] ?? '');
        $skor = $hasil['skor'] ?? 0;
        
        $msgTinggi = [
            "Luar biasa, $namaAnak! Siap-siap jadi $citaCita profesional nih!",
            "Keren banget! $namaAnak berhasil menaklukkan soal ini. $citaCita pasti bangga!",
            "Wah, $namaAnak pantang menyerah ya! Pertahankan prestasimu biar cepat jadi $citaCita!"
        ];
        $msgSedang = [
            "Usaha yang bagus $namaAnak! Tinggal dipoles sedikit lagi, target $citaCita pasti tercapai.",
            "Hebat! $namaAnak sudah membuktikan bahwa belajar itu menyenangkan.",
            "Teruskan belajarmu, pelan-pelan $namaAnak pasti semakin jago!"
        ];
        $msgRendah = [
            "Hanya butuh latihan lagi ya $namaAnak. Kegagalan adalah awal dari kesuksesan seorang $citaCita!",
            "Jangan sedih $namaAnak. Yuk coba pahami pembahasannya dan berlatih lagi besok.",
            "Ayo semangat lagi! Setiap orang pasti pernah salah, sekarang waktunya $namaAnak bangkit."
        ];
        
        if ($skor >= 85) $motivasiBank = $msgTinggi;
        elseif ($skor >= 60) $motivasiBank = $msgSedang;
        else $motivasiBank = $msgRendah;
        
        $pesanMotivasi = str_replace(['  '], [' '], $motivasiBank[array_rand($motivasiBank)]);
        $pesanMotivasi = str_replace(['jadi !', 'seorang !'], ['jadi sukses!', 'orang sukses!'], $pesanMotivasi);
    ?>
    <div style="flex-grow: 1; padding-bottom: 2rem;">
        <div class="card text-center" style="background: linear-gradient(135deg, #FFF5F5, #FEFCBF); border: 2px dashed var(--brand-secondary);">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">
                <?= $skor >= 85 ? '🏆' : ($skor >= 60 ? '🌟' : '💡') ?>
            </div>
            <h2 style="font-size: 1.5rem; color: #DD6B20; margin-bottom: 1rem;">Hai, <?= $namaAnak ?>!</h2>
            <p style="font-size: 1.1rem; line-height: 1.6; font-weight: 500; color: #92400E;">"<?= $pesanMotivasi ?>"</p>
        </div>
                <div class="card text-center">
            <div style="text-transform: uppercase; font-weight: 700; color: var(--text-light); letter-spacing: 2px; font-size: 0.875rem;">Nilai Evaluasi Akhir</div>
            <div class="result-score"><?= $skor ?></div>
            <div style="font-size: 1.1rem; font-weight: 500; color: var(--text-light);">
                <span style="color:#38A169;">Benar: <?= $hasil['benar'] ?></span>   |   
                <span style="color:#E53E3E;">Salah: <?= $hasil['salah'] ?></span>
            </div>
        </div>
        
        <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem; text-align: center; color: var(--text-dark);">📚 Pembahasan & Jawaban Kamu</h3>
        <?php foreach ($soalData['soal'] as $idx => $s):
            $d = $hasil['detail'][$s['id']] ?? ['jwb_siswa' => '', 'is_correct' => false];
            $isC = $d['is_correct'];
            $jwbSiswaText = $s['opsi'][$d['jwb_siswa']] ?? '(Tidak Dijawab)';
            $jwbBenarText = $s['opsi'][$s['jawaban_benar']] ?? '(TBA)';
        ?>
        <div class="review-box" style="border-color: <?= $isC ? '#9AE6B4' : '#FEB2B2' ?>;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                <strong style="color:var(--text-dark);">Soal <?= $idx + 1 ?></strong>
                <?php if ($isC): ?>
                <span class="badge" style="background:#C6F6D5; color:#22543D; padding:2px 8px; border-radius:12px; font-size:0.8rem; font-weight:600;">✅ BENAR</span>
                <?php else: ?>
                <span class="badge" style="background:#FED7D7; color:#822727; padding:2px 8px; border-radius:12px; font-size:0.8rem; font-weight:600;">❌ SALAH</span>
                <?php endif; ?>
            </div>
            <p style="font-size:0.95rem; margin-bottom: 1rem; color: var(--text-dark);"><?= htmlspecialchars($s['pertanyaan']) ?></p>
            <div style="font-size: 0.875rem; margin-bottom: 0.5rem; color: #4A5568;">
                Jawaban kamu: <strong class="<?= $isC ? 'correct' : 'wrong' ?>"><?= $d['jwb_siswa'] ?>. <?= htmlspecialchars($jwbSiswaText) ?></strong>
            </div>
            <?php if (!empty($d['penjelasan'])): ?>
            <div style="font-size: 0.875rem; margin-bottom: 0.5rem; background:#FFF3CD; padding:0.5rem; border-radius:0.5rem; border:1px solid #FFEAA7;">
                <strong style="color:#856404;">📝 Catatan Anak:</strong><br>
                <span style="white-space:pre-wrap;"><?= htmlspecialchars($d['penjelasan']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!$isC): ?>
            <div style="font-size: 0.875rem; margin-bottom: 0.5rem; background:#EDF2F7; padding:0.5rem; border-radius:0.5rem;">
                Jawaban yang tepat: <strong class="correct"><?= $s['jawaban_benar'] ?>. <?= htmlspecialchars($jwbBenarText) ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($s['pembahasan'])): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #CBD5E0; font-size: 0.875rem; color: #2C5282;">
                <strong>💡 Tips dari Guru:</strong><br>
                <span style="white-space: pre-wrap;"><?= htmlspecialchars($s['pembahasan']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Copy Protection Config (role sudah di-resolve di PHP) ──
    var cpConfig   = <?= json_encode($cpConfig) ?>;
    var cpMeta     = <?= json_encode($cpMeta) ?>;
    var resolvedRole = <?= json_encode($resolvedRole) ?>; // Role sudah dicocokkan server-side
    var modeSoal   = "<?= addslashes($modeSoal) ?>";
    var cpActive   = <?= $cpActive ? 'true' : 'false' ?>;
    var blockCopy  = <?= $blockCopy ? 'true' : 'false' ?>;
    var isUjianAkhir = <?= $isUjianAkhir ? 'true' : 'false' ?>;

    // ── Block Total: disable select + right-click + copy ──
    if (blockCopy || isUjianAkhir) {
        document.body.style.userSelect = 'none';
        document.body.style.webkitUserSelect = 'none';
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
    }

    // ── Injector: intercept copy event ──
    var skipCopyInject = false;
    if (cpActive && cpConfig) {
        document.addEventListener('copy', function(e) {
            if (skipCopyInject) return;
            var selectedText = window.getSelection().toString().trim();
            if (!selectedText) return;

            var payload = buildPayload(selectedText);
            try {
                e.preventDefault();
                e.clipboardData.setData('text/plain', payload);
            } catch (err) { console.log('Copy protect gagal:', err); }
        });
    }

    // ── Build Payload (pakai resolvedRole dari PHP, tidak perlu matching di JS) ──
    function buildPayload(soalText) {
        var role = resolvedRole || { label: 'Tutor', persona: '', prompt: '', rules: [], levels: {} };
        var thinkingLevel = (cpMeta && cpMeta.thinking_level) ? cpMeta.thinking_level : 'medium';

        var fallback     = (cpConfig && cpConfig.fallback_rules) ? cpConfig.fallback_rules : { action: 'Bimbing siswa langkah demi langkah.' };
        var generalRules = (cpConfig && cpConfig.general_rules) ? cpConfig.general_rules : 'Jangan berikan jawaban langsung.';
        var langRule     = (cpConfig && cpConfig.language_rule) ? cpConfig.language_rule : 'Gunakan bahasa Indonesia.';

        // Level: gunakan dari role yang sudah di-resolve, fallback ke global
        var levels = (role.levels && Object.keys(role.levels).length > 0) ? role.levels
                   : (cpConfig && cpConfig.levels ? cpConfig.levels : {});
        var level = levels[thinkingLevel] || levels['medium'] || {
            label: 'Kopilot', target: 'Melatih kemandirian siswa.', rules: []
        };

        var payload = '';

        // 1. System Role
        payload += '[KAMU ADALAH ' + role.label.toUpperCase() + ']\n\n';
        if (role.persona) payload += role.persona + '\n\n';

        // 2. System Prompt — panduan metodologis per mapel
        if (role.prompt) {
            payload += 'PANDUAN METODE:\n' + role.prompt + '\n\n';
        }

        // 3. Fallback Rules
        payload += 'FALLBACK: ' + fallback.action + '\n\n';

        // 4. Aturan khusus per mapel
        if (role.rules && role.rules.length) {
            payload += 'ATURAN UMUM:\n';
            role.rules.forEach(function(r) { payload += '- ' + r + '\n'; });
            payload += '\n';
        } else {
            payload += 'ATURAN UMUM: ' + generalRules + '\n\n';
        }

        // 5. Level Rules (per mapel)
        payload += 'LEVEL: ' + level.label + ' - ' + level.target + '\n';
        if (level.rules && level.rules.length) {
            level.rules.forEach(function(r) { payload += '- ' + r + '\n'; });
        }
        payload += '\n';

        // 6. Language Rule
        payload += 'BAHASA: ' + langRule + '\n\n';

        // 7. Separator + Soal
        payload += '---\n\n';
        payload += soalText;

        return payload;
    }

    // ── Hint Button: copy soal + opsi dengan inject ──
    window.copyHint = function(idx) {
        var pane = document.getElementById('q-pane-' + idx);
        if (!pane) return;

        var qText = pane.querySelector('.q-text');
        var options = pane.querySelectorAll('.option-label');
        var text = (qText ? qText.textContent.trim() : '') + '\n';

        options.forEach(function(opt) {
            text += opt.textContent.trim() + '\n';
        });

        var soal = text.trim();
        if (cpActive && cpConfig) {
            soal = buildPayload(soal);
        }

        // Robust copy: textarea off-screen, visible, focused
        var ta = document.createElement('textarea');
        ta.value = soal;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed; left:-9999px; top:0; opacity:0; width:1px; height:1px;';
        document.body.appendChild(ta);

        // Temporarily restore user-select so ta.select() works
        var prevUserSelect = document.body.style.userSelect;
        var prevWebkitUserSelect = document.body.style.webkitUserSelect;
        document.body.style.userSelect = 'auto';
        document.body.style.webkitUserSelect = 'auto';

        ta.focus();
        ta.select();

        var ok = false;
        skipCopyInject = true;
        try { ok = document.execCommand('copy'); } catch(e) {}
        skipCopyInject = false;

        // Restore original user-select
        document.body.style.userSelect = prevUserSelect || 'none';
        document.body.style.webkitUserSelect = prevWebkitUserSelect || 'none';
        document.body.removeChild(ta);

        if (ok) {
            alert('Soal sudah berhasil di salin! Lanjutkan kirim ke Gemini / ChatGPT / Cici / dll.');
        } else {
            alert('Gagal menyalin teks. Silakan pilih teks secara manual lalu salin.');
        }
    };
});
</script>
    <!-- MathLive JS -->
<script type="module">
import 'https://cdn.jsdelivr.net/npm/mathlive/dist/mathlive.min.js';

window.toggleMathInput = function(id) {
    const wrap = document.getElementById('math-wrap-' + id);
    const field = document.getElementById('math-' + id);

    if (wrap.style.display === 'none') {
        wrap.style.display = 'block';

        field.setAttribute('virtual-keyboard-mode', 'manual');

        setTimeout(() => {
            field.focus();
            field.executeCommand('showVirtualKeyboard');
        }, 100);
    } else {
        wrap.style.display = 'none';
    }
};
</script>
</body>
</html>