<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/layout_top.php';
require_once __DIR__ . '/../../src/Core/PromptResolver.php';

$id = basename($_GET['id'] ?? '');
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('CSRF token tidak valid.');

    // Collect metadata
    $data['metadata']['judul'] = $_POST['judul'] ?? 'Tanpa Judul';
    $rawMapel = $_POST['mata_pelajaran'] ?? 'Umum';
    $data['metadata']['mata_pelajaran'] = \Core\PromptResolver::normalizeMapel($rawMapel);
    $data['metadata']['kelas_target'] = $_POST['kelas_target'] ?? '-';
    $data['metadata']['timer_menit'] = (int) ($_POST['timer_menit'] ?? 20);
    $data['metadata']['mode_soal'] = $_POST['mode_soal'] ?? 'latihan_harian';
    // Simpan prompt penjelasan (hanya untuk mode tanpa batas waktu)
    if ((int) ($_POST['timer_menit'] ?? 20) === 0) {
        $data['metadata']['explanation_prompt'] = trim($_POST['explanation_prompt'] ?? '');
    } else {
        $data['metadata']['explanation_prompt'] = ''; // Hapus jika ganti ke timer biasa
    }

    // Copy Protection — simpan metadata
    $cpEnabled = ($_POST['cp_enabled'] ?? '0') === '1';
    $cpBlock   = ($_POST['cp_block_copy'] ?? '0') === '1';
    // Force settings untuk ujian_akhir
    if ($data['metadata']['mode_soal'] === 'ujian_akhir') {
        $cpEnabled = false;
        $cpBlock   = true;
    }
    $data['metadata']['copy_protection'] = [
        'enabled'        => $cpEnabled,
        'tipe_mapel'     => $data['metadata']['mata_pelajaran'],
        'thinking_level' => $_POST['cp_thinking_level'] ?? 'medium',
        'block_copy'     => $cpBlock,
    ];

    // Collect answers, pembahasan, pertanyaan, opsi
    $soalPosts = $_POST['soal'] ?? [];
    foreach ($data['soal'] as $index => &$item) {
        $sid = $item['id'];
        if (isset($soalPosts[$sid])) {
            if (isset($soalPosts[$sid]['pertanyaan'])) {
                $item['pertanyaan'] = trim($soalPosts[$sid]['pertanyaan']);
            }
            if (isset($soalPosts[$sid]['opsi']) && is_array($soalPosts[$sid]['opsi'])) {
                foreach (['A', 'B', 'C', 'D'] as $opt) {
                    if (isset($soalPosts[$sid]['opsi'][$opt])) {
                        $item['opsi'][$opt] = trim($soalPosts[$sid]['opsi'][$opt]);
                    }
                }
            }
            $item['jawaban_benar'] = strtoupper($soalPosts[$sid]['jawaban_benar'] ?? '');
            $item['pembahasan'] = $soalPosts[$sid]['pembahasan'] ?? '';
        }
    }

    $action = $_POST['action'] ?? 'save_draft';

    if ($action === 'shuffle') {
        shuffle($data['soal']);
        $success = "Urutan soal berhasil diacak dan disimpan.";
        // Do not change publish status
    } elseif ($action === 'publish') {
        // Validate all answers filled
        $allFilled = true;
        foreach ($data['soal'] as $item) {
            if (empty($item['jawaban_benar'])) {
                $allFilled = false;
                break;
            }
        }

        if ($allFilled) {
            $data['metadata']['status'] = 'active';
            if (empty($data['metadata']['pin'])) {
                $data['metadata']['pin'] = $security->generatePin();
            }
            if (empty($data['metadata']['slug'])) {
                $data['metadata']['slug'] = $security->generateSlug($data['metadata']['mata_pelajaran']);
            }
            $success = "Latihan berhasil diterbitkan! PIN akses: <strong>" . $data['metadata']['pin'] . "</strong>";
        } else {
            $error = "Gagal menerbitkan. Pastikan seluruh soal telah memiliki Kunci Jawaban (A/B/C/D).";
            $data['metadata']['status'] = 'draft';
        }
    } else {
        $data['metadata']['status'] = 'draft';
        $success = "Draft berhasil disimpan.";
    }

    $fileManager->writeJsonAndSync($dataPath, $data);
}

$meta = $data['metadata'];
$isPublish = ($meta['status'] ?? 'draft') === 'active';
?>

<div class="page-title">
    Editor Soal & Kunci Jawaban
    <a href="<?= $adminBase ?>index.php" class="btn btn-outline" style="font-size:0.875rem;">← Kembali</a>
</div>

<?php if ($error): ?>
    <div
        style="background-color: #FEE2E2; color: var(--danger); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div
        style="background-color: #D1FAE5; color: #065F46; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <?= $success ?>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card"
        style="display:flex; gap: 1rem; flex-wrap:wrap; background-color: #F9FAFB; border: 1px solid var(--border);">
        <div style="flex:2; min-width:250px;">
            <label class="form-label" style="font-weight:600;">Judul Latihan</label>
            <input type="text" name="judul" class="form-input" value="<?= htmlspecialchars($meta['judul']) ?>" required>
        </div>
        <div style="flex:1; min-width:150px;">
            <label class="form-label" style="font-weight:600;">Mata Pelajaran</label>
            <select name="mata_pelajaran" id="mapelSelect" class="form-select" required>
                <?php
                $currentMapel = \Core\PromptResolver::normalizeMapel(trim($meta['mata_pelajaran'] ?? 'Umum'));
                
                // Daftar lengkap mata pelajaran yang mendukung berbagai kurikulum (K13 & Merdeka)
                $mapelGroups = [
                    'Eksakta' => [
                        'Matematika', 'Fisika', 'Kimia', 'Matematika Peminatan', 'Matematika Wajib'
                    ],
                    'Sains & Teknologi' => [
                        'IPA', 'IPAS', 'Biologi', 'Informatika', 'Prakarya', 'TIK'
                    ],
                    'Sosial' => [
                        'IPS', 'Sejarah', 'Geografi', 'Sosiologi', 'Ekonomi', 'Sejarah Indonesia', 'Antropologi'
                    ],
                    'Bahasa' => [
                        'Bahasa Indonesia', 'Bahasa Inggris', 'Bahasa Daerah', 'Bahasa Asing'
                    ],
                    'Lainnya' => [
                        'PKn', 'Seni', 'Seni Budaya', 'Olahraga', 'PJOK', 
                        'PA (Pendidikan Agama)', 'Pendidikan Agama Islam', 'Pendidikan Agama Kristen', 
                        'Pendidikan Agama Katolik', 'Pendidikan Agama Hindu', 'Pendidikan Agama Buddha', 
                        'Pendidikan Agama Khonghucu', 'Muatan Lokal', 'Umum'
                    ]
                ];

                // Cek kecocokan case-insensitive untuk $currentMapel
                $found = false;
                foreach ($mapelGroups as $group => $mapels) {
                    foreach ($mapels as $m) {
                        if (strcasecmp($m, $currentMapel) === 0) {
                            $currentMapel = $m;
                            $found = true;
                            break 2;
                        }
                    }
                }
                
                // Jika mapel kustom (misal dari database/import), tambahkan ke Lainnya
                if (!$found && !empty($currentMapel)) {
                    $mapelGroups['Lainnya'][] = $currentMapel;
                }

                foreach ($mapelGroups as $group => $mapels): ?>
                    <optgroup label="<?= $group ?>">
                        <?php foreach ($mapels as $m): ?>
                            <option value="<?= $m ?>" <?= $currentMapel === $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0; min-width:80px;">
            <label class="form-label" style="font-weight:600;">Kelas</label>
            <input type="text" name="kelas_target" class="form-input"
                value="<?= htmlspecialchars($meta['kelas_target']) ?>">
        </div>
        <div style="flex:0; min-width:150px;">
            <label class="form-label" style="font-weight:600;">Durasi Waktu</label>
            <select name="timer_menit" id="timer-select" class="form-select" onchange="toggleExplanationPrompt(this.value)">
                <?php foreach ($config['timer_options'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($meta['timer_menit'] ?? 20) == $t ? 'selected' : '' ?>>
                        <?= $t === 0 ? '⏾ Tanpa Batas Waktu' : $t . ' Menit' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0; min-width:150px;">
            <label class="form-label" style="font-weight:600;">Mode Soal</label>
            <select name="mode_soal" id="modeSoalSelect" class="form-select" onchange="toggleCopyProtection(this.value)">
                <?php
                $currentMode = $meta['mode_soal'] ?? 'latihan_harian';
                $modeOptions = [
                    'latihan_harian' => 'Latihan Harian',
                    'ulangan_harian' => 'Ulangan Harian',
                    'try_out'        => 'Try Out',
                    'ujian_akhir'    => 'Ujian Akhir',
                ];
                foreach ($modeOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $currentMode === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Copy Protection Section -->
    <?php
    $cp = $meta['copy_protection'] ?? [];
    $cpEnabled = $cp['enabled'] ?? false;
    $cpBlock   = $cp['block_copy'] ?? false;
    $cpLevel   = $cp['thinking_level'] ?? 'medium';
    $isUjianAkhir = ($meta['mode_soal'] ?? 'latihan_harian') === 'ujian_akhir';
    ?>
    <div id="copy-protection-box" style="margin-top: 1rem; display: <?= $isUjianAkhir ? 'none' : 'block' ?>;">
        <div class="card" style="border-left: 4px solid var(--primary); background: #FFF8F0;">
            <div style="display:flex; align-items:center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span style="font-size: 1.25rem;">📋</span>
                <strong style="color: var(--text-main); font-size: 0.95rem;">Copy Protection — Smart AI Tutor Injection</strong>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5;">
                Aktifkan untuk menyuntikkan prompt ke clipboard saat siswa menyalin soal. AI akan membimbing proses berpikir, bukan sekadar memberi jawaban.
            </p>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items:end;">
                <div>
                    <label class="form-label" style="font-weight:600;">Aktifkan</label>
                    <select name="cp_enabled" class="form-select">
                        <option value="1" <?= $cpEnabled ? 'selected' : '' ?>>Ya</option>
                        <option value="0" <?= !$cpEnabled ? 'selected' : '' ?>>Tidak</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-weight:600;">Level Berpikir</label>
                    <select name="cp_thinking_level" class="form-select">
                        <option value="low" <?= $cpLevel === 'low' ? 'selected' : '' ?>>Low — Fundamental</option>
                        <option value="medium" <?= $cpLevel === 'medium' ? 'selected' : '' ?>>Medium — Kopilot</option>
                        <option value="high" <?= $cpLevel === 'high' ? 'selected' : '' ?>>High — HOTS (Sokratik)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-weight:600;">Blokir Total (Copy)</label>
                    <select name="cp_block_copy" class="form-select">
                        <option value="1" <?= $cpBlock ? 'selected' : '' ?>>Ya — Aktifkan tombol Hint</option>
                        <option value="0" <?= !$cpBlock ? 'selected' : '' ?>>Tidak — Copy bebas dengan inject</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Info box saat Ujian Akhir -->
    <div id="copy-protection-info" style="margin-top: 1rem; display: <?= $isUjianAkhir ? 'block' : 'none' ?>;">
        <div class="card" style="border-left: 4px solid #EF4444; background: #FEF2F2;">
            <div style="display:flex; align-items:center; gap: 0.5rem;">
                <span style="font-size: 1.25rem;">🔒</span>
                <span style="color: #991B1B; font-size: 0.9rem; font-weight:500;">
                    Copy protection dinonaktifkan otomatis untuk mode <strong>Ujian Akhir</strong>.
                    Siswa tidak dapat menyalin soal demi integritas penilaian.
                </span>
            </div>
        </div>
    </div>

    <!-- Explanation Prompt Box (hanya muncul saat Tanpa Batas Waktu dipilih) -->
    <div id="explanation-prompt-box" style="display: <?= ($meta['timer_menit'] ?? 20) == 0 ? 'block' : 'none' ?>; margin-top: 1rem;">
        <div class="card" style="border-left: 4px solid #10B981; background: #F0FDF4;">
            <div style="display:flex; align-items:center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span style="font-size: 1.25rem;">💬</span>
                <strong style="color: #065F46; font-size: 0.95rem;">Kolom Catatan / Penjelasan Anak (Mode Tanpa Batas Waktu)</strong>
            </div>
            <p style="font-size: 0.85rem; color: #047857; margin-bottom: 1rem; line-height: 1.5;">
                Pada mode <strong>Tanpa Batas Waktu</strong>, anak akan diberi kolom teks di setiap soal untuk menjelaskan alasan memilih jawaban tersebut.
                Kolom ini bersifat <strong>catatan saja</strong> dan tidak mempengaruhi nilai/jawaban anak.
            </p>
            <label class="form-label" style="font-weight:600; color: #065F46;">Pertanyaan/Instruksi untuk Anak <span style="font-weight:400; color:#6B7280;">(opsional)</span></label>
            <textarea name="explanation_prompt" class="form-textarea" rows="2"
                placeholder="Contoh: Mengapa kamu memilih jawaban tersebut? Coba jelaskan dengan kalimatmu sendiri!"
                style="resize:vertical; border-color: #6EE7B7;"><?= htmlspecialchars($meta['explanation_prompt'] ?? '') ?></textarea>
            <small style="color: #6B7280; font-style: italic;">Jika dikosongkan, anak tetap mendapat kolom penjelasan dengan teks default.</small>
        </div>
    </div>

    <script>
    function toggleExplanationPrompt(value) {
        var box = document.getElementById('explanation-prompt-box');
        box.style.display = (parseInt(value) === 0) ? 'block' : 'none';
    }

    function toggleCopyProtection(mode) {
        var cpBox = document.getElementById('copy-protection-box');
        var cpInfo = document.getElementById('copy-protection-info');
        if (mode === 'ujian_akhir') {
            cpBox.style.display = 'none';
            cpInfo.style.display = 'block';
        } else {
            cpBox.style.display = 'block';
            cpInfo.style.display = 'none';
        }
    }
    </script>

    <h3 style="margin: 2.5rem 0 1rem; font-size:1.125rem; color:var(--text-main);">📝 Detail Soal (Total:
        <?= count($data['soal']) ?>)
    </h3>

    <?php foreach ($data['soal'] as $index => $soal):
        $sid = $soal['id'];
        $jwb = $soal['jawaban_benar'] ?? '';
        ?>
        <div class="card" style="padding-bottom: 1.5rem; border-left: 4px solid var(--primary);">
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.75rem;">
                <strong style="color:var(--text-main); font-size:1.05rem;">
                    No.
                    <?= $index + 1 ?> <span style="color:var(--text-muted); font-size:0.875rem; font-weight:normal;">(
                        <?= htmlspecialchars($soal['tag'] ?? '') ?>)
                    </span>
                </strong>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-weight:600; display:block; margin-bottom:0.5rem;">Pertanyaan</label>
                <textarea name="soal[<?= $sid ?>][pertanyaan]" class="form-textarea" rows="4" style="resize:vertical; width:100%;"><?= htmlspecialchars($soal['pertanyaan'] ?? '') ?></textarea>
            </div>

            <label class="form-label" style="font-weight:600; display:block; margin-bottom:0.5rem;">Pilihan Jawaban</label>
            <div
                style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                    <div
                        style="padding: 0.75rem; border:1px solid <?= $jwb === $opt ? 'var(--primary)' : 'var(--border)' ?>; border-radius: var(--radius-sm); <?= $jwb === $opt ? 'background-color:#EEF2FF;' : 'background-color:#F9FAFB;' ?>">
                        <strong style="color: var(--primary); margin-bottom:4px; display:block;">
                            Pilihan <?= $opt ?>
                        </strong>
                        <textarea name="soal[<?= $sid ?>][opsi][<?= $opt ?>]" class="form-textarea" rows="2" style="font-size:0.9rem; resize:vertical; width:100%;"><?= htmlspecialchars($soal['opsi'][$opt] ?? '') ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex; gap:1.5rem; align-items:flex-start;">
                <div style="width: 150px;">
                    <label class="form-label"
                        style="font-weight:700; color:var(--danger); display:block; margin-bottom:0.5rem;">Kunci Jawaban
                        <span style="color:red">*</span></label>
                    <select name="soal[<?= $sid ?>][jawaban_benar]" class="form-select" <?= $isPublish ? 'disabled' : '' ?>
                        style="font-weight:600; text-align:center;">
                        <option value="">- PILIH -</option>
                        <option value="A" <?= $jwb === 'A' ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= $jwb === 'B' ? 'selected' : '' ?>>B</option>
                        <option value="C" <?= $jwb === 'C' ? 'selected' : '' ?>>C</option>
                        <option value="D" <?= $jwb === 'D' ? 'selected' : '' ?>>D</option>
                    </select>
                    <?php if ($isPublish): ?>
                        <!-- Retain value when disabled -->
                        <input type="hidden" name="soal[<?= $sid ?>][jawaban_benar]" value="<?= htmlspecialchars($jwb) ?>">
                    <?php endif; ?>
                </div>

                <div style="flex-grow:1;">
                    <label class="form-label" style="font-weight:600;">Pembahasan / Cara Penyelesaian (Opsional)</label>
                    <textarea name="soal[<?= $sid ?>][pembahasan]" class="form-textarea" rows="3"
                        placeholder="Tulis cara/pembahasan singkat jika soal ini salah saat dikerjakan..."
                        style="resize:vertical;"><?= htmlspecialchars($soal['pembahasan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Sticky Bottom Actions -->
    <div
        style="position: sticky; bottom: 1rem; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 1.25rem 2rem; border: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center; z-index:10; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">

        <div>
            Status:
            <?php if ($isPublish): ?>
                <span style="color:var(--success); font-weight:700; letter-spacing:0.5px;">🟢 AKTIF</span>
                <div style="font-size:0.875rem; margin-top:0.25rem;">
                    URL: <a href="../s/index.php?slug=<?= urlencode($meta['slug']) ?>" target="_blank"
                        style="color:var(--primary); font-weight:600;">../s/index.php?slug=
                        <?= urlencode($meta['slug']) ?>
                    </a>
                </div>
            <?php else: ?>
                <span
                    style="font-weight:700; color:var(--text-muted); background:#E5E7EB; padding: 0.2rem 0.5rem; border-radius:4px;">⚫
                    DRAFT</span>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap: 0.75rem;">
            <button type="submit" name="action" value="shuffle" class="btn btn-outline" style="background:#F3F4F6;"
                onclick="return confirm('Acak urutan soal sekarang? Perubahan yang belum disimpan juga akan ikut tersimpan.')">🔀 Acak Soal</button>
            <?php if (!$isPublish): ?>
                <button type="submit" name="action" value="save_draft" class="btn btn-outline">💾 Simpan Draft</button>
                <button type="submit" name="action" value="publish" class="btn btn-primary"
                    onclick="return confirm('Anda yakin semua kunci jawaban sudah benar? Latihan yang diterbitkan akan menghasilkan tautan unik beserta 4 digit PIN.')">✅
                    Terbitkan Soal & Generate PIN</button>
            <?php else: ?>
                <!-- Can revert to draft -->
                <button type="submit" name="action" value="save_draft" class="btn btn-danger"
                    onclick="return confirm('Kembali menjadi draft akan membuat PIN sebelumnya (<?= $meta['pin'] ?>) otomatis HANGUS dan anak tidak bisa membuka soal ini lagi. Lanjutkan?')">Batal
                    Terbitkan (Jadikan Draft)</button>
                <a href="generate.php?id=<?= urlencode($id) ?>&regen=1" class="btn btn-outline"
                    onclick="return confirm('Ini akan mereset PIN (PIN lama hangus). Yakin?')">🔄 Generate PIN Baru</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php require __DIR__ . '/layout_bottom.php'; ?>