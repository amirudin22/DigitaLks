<?php
$hideSidebar = true;
require __DIR__ . '/layout_top.php';

require_once __DIR__ . '/../../src/Core/PromptGenerator.php';
use Core\PromptGenerator;

$promptGenerator = new PromptGenerator();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$result = null;
$prompt = null;
$error = null;

// Handle form submission
if ($method === 'POST' && $action === 'generate') {
    if (!validateCsrf()) die('CSRF token tidak valid.');
    $params = [
        'kelas' => $_POST['kelas'] ?? '',
        'mata_pelajaran' => $_POST['mata_pelajaran'] ?? '',
        'kurikulum' => $_POST['kurikulum'] ?? 'K13',
        'materi' => $_POST['materi'] ?? '',
        'jumlah_soal' => $_POST['jumlah_soal'] ?? 10,
        'tingkat_kesulitan' => $_POST['tingkat_kesulitan'] ?? 'SEDANG',
        'mode_ujian' => $_POST['mode_ujian'] ?? 'LATIHAN_HARIAN',
    ];

    $result = $promptGenerator->generatePrompt($params);

    if (!$result['success']) {
        $error = implode(', ', $result['errors']);
    } else {
        $prompt = $result['prompt'];
    }
}

// Get export format
if ($method === 'GET' && $action === 'export') {
    $format = $_GET['format'] ?? 'text';
    $promptData = $_GET['data'] ?? '';

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="prompt_' . date('YmdHis') . '.json"');
        echo $promptData;
    } else {
        $promptJson = json_decode($promptData, true);
        $promptText = $promptJson['prompt_text'] ?? $promptData;

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="prompt_' . date('YmdHis') . '.txt"');
        echo $promptText;
    }
    exit;
}
?>

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    display: block !important;
}
.content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.gen-header {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}
.gen-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
}
.gen-header p {
    font-size: 14px;
    opacity: 0.9;
}
.gen-grid {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 20px;
    margin-bottom: 30px;
}
.gen-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.gen-card h2 {
    font-size: 18px;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #667eea;
    padding-bottom: 10px;
}
.gen-card .form-group { margin-bottom: 18px; }
.gen-card .form-group label {
    display: block; margin-bottom: 6px; font-weight: 500;
    color: #333; font-size: 14px;
}
.gen-card .form-group input,
.gen-card .form-group select,
.gen-card .form-group textarea {
    width: 100%; padding: 10px 12px;
    border: 1px solid #ddd; border-radius: 6px;
    font-family: inherit; font-size: 14px;
}
.gen-card .form-group input:focus,
.gen-card .form-group select:focus,
.gen-card .form-group textarea:focus {
    outline: none; border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}
.gen-card .form-group textarea { resize: vertical; min-height: 100px; }
.gen-card .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.gen-card .form-row.full { grid-template-columns: 1fr; }
.gen-card .hint { font-size: 12px; color: #666; margin-top: 4px; font-style: italic; }
.gen-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white; padding: 12px 24px; border: none; border-radius: 6px;
    font-weight: 600; cursor: pointer; font-size: 14px;
    transition: all 0.3s; width: 100%; margin-top: 10px;
}
.gen-btn:hover {
    transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.4);
}
.materi-ref {
    min-height: 120px; max-height: 200px;
    background: #f8f9fa; color: #333; font-size: 12px; line-height: 1.6;
    overflow-y: auto; padding: 10px 12px;
    border: 1px solid #e0e0e0; border-radius: 6px;
}
.error-box { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #f5c6cb; }
.success-box { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #c3e6cb; }
.preview-tabs { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #eee; }
.preview-tabs button {
    background: none; color: #666; border: none; padding: 10px 15px;
    font-weight: 500; cursor: pointer; border-bottom: 3px solid transparent;
    margin: 0; width: auto; font-size: 13px;
}
.preview-tabs button.active { background: none; color: #667eea; border-bottom-color: #667eea; }
.preview-content { display: none; background: #f8f9fa; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; color: #333; }
.preview-content.active { display: block; }
.copy-btn { background: #28a745; padding: 8px 16px; margin: 0; width: auto; }
.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
.stat-box { background: #f0f4ff; padding: 12px; border-radius: 6px; text-align: center; border-left: 4px solid #667eea; }
.stat-label { font-size: 12px; color: #666; margin-bottom: 4px; }
.stat-value { font-size: 18px; font-weight: bold; color: #667eea; }
.info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; color: #0c5aa0; }
.info-box strong { display: block; margin-bottom: 4px; }
@media (max-width: 1024px) { .gen-grid { grid-template-columns: 1fr; } }
</style>

<div class="gen-header">
    <h1>🤖 Generate JSON Prompt untuk AI</h1>
    <p>Buat prompt deterministic untuk generate soal berkualitas menggunakan AI QWEN</p>
</div>

<div class="gen-grid">
    <div class="gen-card">
        <h2>📝 Konfigurasi Prompt</h2>

        <?php if ($error): ?>
            <div class="error-box">❌ Error: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="?action=generate">
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="kelas">Kelas</label>
                    <select id="kelas" name="kelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <optgroup label="SD">
                            <option value="1 SD">Kelas 1 SD</option>
                            <option value="2 SD">Kelas 2 SD</option>
                            <option value="3 SD">Kelas 3 SD</option>
                            <option value="4 SD">Kelas 4 SD</option>
                            <option value="5 SD">Kelas 5 SD</option>
                            <option value="6 SD">Kelas 6 SD</option>
                        </optgroup>
                        <optgroup label="SMP">
                            <option value="7 SMP">Kelas 7 SMP</option>
                            <option value="8 SMP">Kelas 8 SMP</option>
                            <option value="9 SMP">Kelas 9 SMP</option>
                        </optgroup>
                        <optgroup label="SMA">
                            <option value="10 SMA">Kelas 10 SMA</option>
                            <option value="11 SMA">Kelas 11 SMA</option>
                            <option value="12 SMA">Kelas 12 SMA</option>
                        </optgroup>
                    </select>
                    <div class="hint">Pilih tingkat kelas/jenjang pendidikan</div>
                </div>

                <div class="form-group">
                    <label for="mata_pelajaran">Mata Pelajaran</label>
                    <select id="mata_pelajaran" name="mata_pelajaran" required>
                        <option value="">-- Pilih Mapel --</option>
                        <option value="Matematika">Matematika</option>
                        <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                        <option value="IPA">IPA (Sains)</option>
                        <option value="IPS">IPS (Sosial)</option>
                        <option value="Bahasa Inggris">Bahasa Inggris</option>
                        <option value="PKn">PKn (Civics)</option>
                        <option value="Seni">Seni</option>
                        <option value="Olahraga">Olahraga</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                    <div class="hint">Mata pelajaran yang akan dibuat soalnya</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="kurikulum">Kurikulum</label>
                    <select id="kurikulum" name="kurikulum" required>
                        <option value="K13">Kurikulum 2013 (K13)</option>
                        <option value="KURIKULUM_MERDEKA">Kurikulum Merdeka</option>
                        <option value="AADC">AADC (Adjusted & Developed)</option>
                        <option value="CUSTOM">Custom/Lainnya</option>
                    </select>
                    <div class="hint">Standar kurikulum yang digunakan</div>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="materi_reference_display">📚 Referensi Materi (Pilih Topik dari Daftar Ini)</label>
                    <textarea id="materi_reference_display" class="materi-ref" readonly
                        placeholder="Pilih Kelas dan Mata Pelajaran untuk melihat topik yang tersedia..."></textarea>
                    <div class="hint">Referensi topik untuk membantu Anda memilih materi yang sesuai.</div>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="materi">Materi/Topik (Detail)</label>
                    <textarea id="materi" name="materi" required
                        placeholder="Contoh: Persamaan Linear Dua Variabel, Bilangan Bulat, Sistem Pernapasan Manusia"></textarea>
                    <div class="hint">
                        Topik spesifik. Untuk mode <strong>TRY OUT</strong>, materi otomatis di-mix.
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label for="jumlah_soal">Jumlah Soal</label>
                    <input type="number" id="jumlah_soal" name="jumlah_soal" min="1" max="100" value="10" required>
                    <div class="hint">1-100 soal</div>
                </div>
                <div class="form-group">
                    <label for="tingkat_kesulitan">Tingkat Kesulitan</label>
                    <select id="tingkat_kesulitan" name="tingkat_kesulitan" required>
                        <option value="MUDAH">Mudah</option>
                        <option value="SEDANG" selected>Sedang</option>
                        <option value="SULIT">Sulit</option>
                        <option value="CAMPURAN">Campuran</option>
                    </select>
                    <div class="hint">Level kesulitan soal</div>
                </div>
                <div class="form-group">
                    <label for="mode_ujian">Mode Ujian</label>
                    <select id="mode_ujian" name="mode_ujian" required>
                        <option value="LATIHAN_HARIAN">Latihan Harian</option>
                        <option value="TRY_OUT">Try Out (Kelas 6/9/12 - UNAS)</option>
                        <option value="ULANGAN_HARIAN">Ulangan Harian</option>
                        <option value="MID_TERM">Mid Term</option>
                        <option value="FINAL_EXAM">Final Exam</option>
                    </select>
                    <div class="hint">Jenis ujian yang akan dibuat</div>
                </div>
            </div>

            <button type="submit" class="gen-btn" style="margin-top:20px;">🚀 Generate Prompt</button>
        </form>
    </div>

    <div class="gen-card">
        <h2>📋 Preview & Copy</h2>

        <?php if ($prompt): ?>
            <div class="success-box">✅ Prompt berhasil di-generate!</div>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Soal</div>
                    <div class="stat-value"><?= $prompt['requirements']['jumlah_soal'] ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tingkat</div>
                    <div class="stat-value"><?= substr($prompt['metadata']['tingkat_kesulitan'], 0, 10) ?></div>
                </div>
            </div>

            <div class="preview-tabs">
                <button class="preview-tab-btn active" data-tab="text-preview">📄 Text Format</button>
                <button class="preview-tab-btn" data-tab="json-preview">{ } JSON</button>
            </div>

            <div id="text-preview" class="preview-content active">
                <pre><?= htmlspecialchars($result['prompt_text']) ?></pre>
            </div>

            <div id="json-preview" class="preview-content">
                <pre><?= htmlspecialchars(json_encode($prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px;">
                <button class="gen-btn copy-btn" onclick="copyToClipboard('text-preview')">📋 Copy Text</button>
                <button class="gen-btn copy-btn" onclick="downloadPrompt('text')">⬇️ Download TXT</button>
            </div>

            <div class="info-box" style="margin-top:15px;">
                <strong>📌 Cara Pakai:</strong>
                1. Copy prompt di atas<br>
                2. Paste ke QWEN/Claude/ChatGPT<br>
                3. Tunggu AI generate soal<br>
                4. Copy output soal<br>
                5. Paste di "Paste Text Mode"
            </div>
        <?php else: ?>
            <div style="padding:40px;text-align:center;color:#999;">
                <p style="font-size:48px;margin-bottom:10px;">📝</p>
                <p>Isi form di sebelah kiri untuk generate prompt</p>
                <p style="font-size:12px;margin-top:15px;">Prompt akan ditampilkan di sini</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="text-align:center;">
    <a href="<?= $adminBase ?>index.php" style="color:white;text-decoration:none;font-size:14px;">← Kembali ke Dashboard</a>
</div>

<script>
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    navigator.clipboard.writeText(el.innerText).then(() => {
        alert('✅ Prompt berhasil di-copy!\n\n💡 Pastikan cek hasil generate AI sebelum digunakan.');
    }).catch(() => alert('❌ Gagal copy'));
}

function downloadPrompt(format) {
    const el = document.getElementById(format === 'json' ? 'json-preview' : 'text-preview');
    const blob = new Blob([el.innerText], { type: format === 'json' ? 'application/json' : 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'prompt_' + Date.now() + '.' + (format === 'json' ? 'json' : 'txt');
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

document.querySelectorAll('.preview-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.preview-content, .preview-tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(this.getAttribute('data-tab')).classList.add('active');
        this.classList.add('active');
    });
});

const matériReference = <?= json_encode(PromptGenerator::MATERI_REFERENCE, JSON_UNESCAPED_UNICODE) ?>;

function showMateriReference() {
    const kelas = document.getElementById('kelas').value;
    const mapel = document.getElementById('mata_pelajaran').value;
    const displayArea = document.getElementById('materi_reference_display');
    if (!kelas) { displayArea.value = '⚠️ Pilih kelas terlebih dahulu'; return; }
    const list = matériReference[kelas];
    if (!list) { displayArea.value = '⚠️ Referensi belum tersedia'; return; }
    let text = '📚 REFERENSI MATERI UNTUK ' + kelas + '\n' + '='.repeat(40) + '\n\n';
    if (mapel && list[mapel]) {
        text += 'Mata Pelajaran: ' + mapel + '\n' + '-'.repeat(40) + '\n';
        list[mapel].forEach(m => { text += '• ' + m + '\n'; });
    } else {
        Object.entries(list).forEach(([subj, topics]) => {
            text += subj + ':\n';
            topics.forEach(m => { text += '  • ' + m + '\n'; });
            text += '\n';
        });
    }
    displayArea.value = text;
}

document.addEventListener('DOMContentLoaded', function() {
    const kelasEl = document.getElementById('kelas');
    const mapelEl = document.getElementById('mata_pelajaran');
    const modeEl = document.getElementById('mode_ujian');
    const materiField = document.querySelector('textarea[name="materi"]');
    const TRYOUT_ALLOWED = ['6 SD', '9 SMP', '12 SMA'];

    function update() {
        const k = kelasEl.value, m = modeEl.value;
        const opt = modeEl.querySelector('option[value="TRY_OUT"]');
        if (TRYOUT_ALLOWED.includes(k)) { opt.style.display = 'block'; opt.disabled = false; }
        else { opt.style.display = 'none'; opt.disabled = true; if (m === 'TRY_OUT') modeEl.value = 'LATIHAN_HARIAN'; }
        showMateriReference();
    }

    if (kelasEl) kelasEl.addEventListener('change', update);
    if (mapelEl) mapelEl.addEventListener('change', showMateriReference);
    update();
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
