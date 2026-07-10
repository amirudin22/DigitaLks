<?php
/**
 * DEBUG HELPER — Trace Prompt Resolution
 * Akses: /public/s/debug_prompt.php?slug=SLUG_SOAL
 * Hanya aktif di environment development.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$config = require __DIR__ . '/../../config/config.php';

if (($config['app']['env'] ?? 'production') !== 'development') {
    http_response_code(403);
    die('Forbidden: Debug page is only available in development environment.');
}

require_once __DIR__ . '/../../src/Core/FileManager.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/Database.php';
require_once __DIR__ . '/../../src/Core/PromptResolver.php';

$fileManager = new \Core\FileManager($config['storage']['path']);
$db = \Core\Database::getInstance($config['database'] ?? []);
$fileManager->setDatabase($db);

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    die('<b>Tambahkan ?slug=SLUG_SOAL di URL</b>');
}

$result = $fileManager->findBySlug($slug);
if (!$result) {
    die('<b>Soal tidak ditemukan: ' . htmlspecialchars($slug) . '</b>');
}

$soalData = $result['data'];
$cpConfig = $config['copy_protection'] ?? null;

$meta         = $soalData['metadata'] ?? [];
$modeSoal     = $meta['mode_soal'] ?? 'latihan_harian';
$isUjianAkhir = ($modeSoal === 'ujian_akhir');

$cpMeta       = $meta['copy_protection'] ?? null;
$mataPelajaran = trim($meta['mata_pelajaran'] ?? 'Umum');

if (!$cpMeta) {
    $cpMeta = [
        'enabled'        => !$isUjianAkhir,
        'tipe_mapel'     => $mataPelajaran,
        'thinking_level' => 'medium',
        'block_copy'     => $isUjianAkhir,
    ];
} else {
    $cpMeta['tipe_mapel'] = $mataPelajaran;
}

$cpActive  = $cpConfig && !$isUjianAkhir && ($cpMeta['enabled'] ?? true);
$blockCopy = $cpActive && ($cpMeta['block_copy'] ?? false);

// ── Step-by-step trace resolusi ──
$trace  = [];
$systemRoles = $cpConfig['system_roles'] ?? [];

// Step 1: normalizeMapel
$canonical = \Core\PromptResolver::normalizeMapel($mataPelajaran);
$trace[] = [
    'step'  => '1. normalizeMapel()',
    'input' => $mataPelajaran,
    'output'=> $canonical,
    'note'  => 'Peta internal: "' . strtolower($mataPelajaran) . '" → "' . $canonical . '"',
];

// Step 2: exact match
$cleanMapel = strtolower($canonical);
$exactKey   = null;
foreach ($systemRoles as $key => $roleData) {
    if (strtolower($key) === $cleanMapel) {
        $exactKey = $key;
        break;
    }
}
$trace[] = [
    'step'  => '2. exact_match (case-insensitive)',
    'input' => '"' . $cleanMapel . '" vs ' . count($systemRoles) . ' keys',
    'output'=> $exactKey ? 'DITEMUKAN: "' . $exactKey . '"' : 'TIDAK ADA MATCH',
    'note'  => $exactKey ? 'Langsung return role "' . $exactKey . '"' : 'Lanjut ke substring match',
];

// Step 3: substring match (jika exact gagal)
$subKey = null;
if (!$exactKey) {
    $roleKeys = array_keys($systemRoles);
    usort($roleKeys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($roleKeys as $key) {
        $cleanKey = strtolower(trim($key));
        if ($cleanKey === 'umum') continue;
        if (strpos($cleanMapel, $cleanKey) !== false || strpos($cleanKey, $cleanMapel) !== false) {
            $subKey = $key;
            break;
        }
    }
}
$trace[] = [
    'step'  => '3. substring_match',
    'input' => $exactKey ? '(dilewati — sudah match di step 2)' : '"' . $cleanMapel . '" vs keys (tanpa umum)',
    'output'=> $exactKey ? '-' : ($subKey ? 'DITEMUKAN: "' . $subKey . '"' : 'TIDAK ADA MATCH'),
    'note'  => $exactKey ? '-' : ($subKey ? 'Partial match' : 'Lanjut ke fallback Umum'),
];

// Step 4: fallback
$resolvedMethod = $exactKey ? 'exact_match' : ($subKey ? 'substring_match' : 'fallback_umum');
$resolvedKey    = $exactKey ?: ($subKey ?: 'Umum');
$trace[] = [
    'step'  => '4. Hasil akhir',
    'input' => '-',
    'output'=> 'key="' . $resolvedKey . '", method="' . $resolvedMethod . '"',
    'note'  => $resolvedMethod === 'fallback_umum' ? '⚠️ TIDAK MATCH — pakai Umum!' : '✅ Berhasil resolve',
];

// Resolusi final
$resolution   = \Core\PromptResolver::resolveRole($cpConfig ?? [], $mataPelajaran);
$resolvedRole = $resolution['role'];

$promptKeys = array_keys($systemRoles);
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Debug Prompt Resolution</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 2rem; max-width: 960px; margin: auto; }
h2 { color: #00d4ff; }
h3 { color: #ffd700; border-bottom: 1px solid #333; padding-bottom: 4px; margin-top: 2rem; }
table { border-collapse: collapse; width: 100%; margin-bottom: 1.5rem; }
td, th { border: 1px solid #333; padding: 8px 12px; text-align: left; }
th { background: #16213e; color: #00d4ff; }
tr:nth-child(even) { background: #0f0f2e; }
.ok { color: #00ff88; font-weight: bold; }
.warn { color: #ff6b6b; font-weight: bold; }
.info { color: #ffd700; font-weight: bold; }
pre { background: #111; padding: 1rem; border-radius: 8px; overflow: auto; font-size: 0.85rem; max-height: 400px; }
.pill { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.85em; }
.pill-ok { background: #0a3a0a; color: #00ff88; }
.pill-warn { background: #3a0a0a; color: #ff6b6b; }
.trace-step { background: #16213e; border-left: 3px solid #00d4ff; padding: 10px 14px; margin-bottom: 8px; border-radius: 0 6px 6px 0; }
.trace-step.ok { border-left-color: #00ff88; }
.trace-step.warn { border-left-color: #ff6b6b; }
.trace-label { color: #00d4ff; font-weight: bold; font-size: 0.9rem; }
.trace-detail { font-size: 0.82rem; color: #aaa; margin-top: 4px; }
.key-match { background: #0a3a0a; padding: 3px 8px; border-radius: 4px; display: inline-block; margin: 2px 0; }
.key-nomatch { background: #2a2a2a; padding: 3px 8px; border-radius: 4px; display: inline-block; margin: 2px 0; color: #666; }
</style>
</head>
<body>
<h2>Debug Prompt Resolution</h2>
<p>Slug: <b class="info"><?= htmlspecialchars($slug) ?></b></p>

<h3>Metadata Soal</h3>
<table>
<tr><th>Field</th><th>Nilai</th></tr>
<tr><td>judul</td><td><?= htmlspecialchars($meta['judul'] ?? '-') ?></td></tr>
<tr><td><b>mata_pelajaran</b></td><td><b class="info">"<?= htmlspecialchars($mataPelajaran) ?>"</b></td></tr>
<tr><td>mode_soal</td><td><?= htmlspecialchars($modeSoal) ?></td></tr>
<tr><td>isUjianAkhir</td><td><?= $isUjianAkhir ? '<span class="warn">true</span>' : 'false' ?></td></tr>
<tr><td>copy_protection ada?</td><td><?= isset($meta['copy_protection']) ? '<span class="ok">Ya</span>' : '<span class="warn">Tidak (fallback)</span>' ?></td></tr>
</table>

<h3>Trace Resolusi (Step-by-Step)</h3>
<?php foreach ($trace as $t): ?>
<div class="trace-step <?= $t['note'] === '⚠️ TIDAK MATCH — pakai Umum!' ? 'warn' : ($t['note'] === '✅ Berhasil resolve' ? 'ok' : '') ?>">
    <div class="trace-label"><?= htmlspecialchars($t['step']) ?></div>
    <div class="trace-detail">
        Input: <code><?= htmlspecialchars($t['input']) ?></code><br>
        Output: <code class="<?= str_contains($t['output'], 'TIDAK') ? 'warn' : 'ok' ?>"><?= htmlspecialchars($t['output']) ?></code><br>
        Catatan: <?= htmlspecialchars($t['note']) ?>
    </div>
</div>
<?php endforeach; ?>

<h3>Status Copy Protection</h3>
<table>
<tr><th>Cek</th><th>Nilai</th><th>Dampak</th></tr>
<tr><td>cpConfig tersedia?</td>
    <td><?= $cpConfig ? '<span class="ok">Ya</span>' : '<span class="warn">Tidak</span>' ?></td>
    <td>Wajib ada untuk hint aktif</td></tr>
<tr><td>isUjianAkhir</td>
    <td><?= $isUjianAkhir ? '<span class="warn">true</span>' : '<span class="ok">false</span>' ?></td>
    <td>true = hint dimatikan</td></tr>
<tr><td>cpMeta[enabled]</td>
    <td><?= ($cpMeta['enabled'] ?? true) ? '<span class="ok">true</span>' : '<span class="warn">false</span>' ?></td>
    <td>false = hint dimatikan</td></tr>
<tr><td><b>cpActive (hint tampil?)</b></td>
    <td><?= $cpActive ? '<span class="ok pill pill-ok">AKTIF</span>' : '<span class="warn pill pill-warn">TIDAK AKTIF</span>' ?></td>
    <td><b>Menentukan hint button muncul</b></td></tr>
</table>

<h3>Hasil Resolusi Role</h3>
<table>
<tr><th>Item</th><th>Nilai</th></tr>
<tr><td>Input</td><td>"<?= htmlspecialchars($mataPelajaran) ?>" → lowercase: "<?= htmlspecialchars($cleanMapel) ?>"</td></tr>
<tr><td>Key ditemukan</td><td><b class="<?= $resolvedKey === 'Umum' ? 'warn' : 'ok' ?>">"<?= htmlspecialchars($resolvedKey) ?>"</b></td></tr>
<tr><td>Metode</td><td><?= htmlspecialchars($resolution['method']) ?></td></tr>
<tr><td>Label role</td><td><?= htmlspecialchars($resolvedRole['label'] ?? '-') ?></td></tr>
<tr><td>Persona ada?</td><td><?= !empty($resolvedRole['persona']) ? '<span class="ok">Ya</span>' : '<span class="warn">Kosong</span>' ?></td></tr>
<tr><td>Prompt ada?</td><td><?= !empty($resolvedRole['prompt']) ? '<span class="ok">Ya</span>' : '<span class="warn">Kosong</span>' ?></td></tr>
<tr><td>Rules count</td><td><?= count($resolvedRole['rules'] ?? []) ?> rules</td></tr>
<tr><td>Levels</td><td><?= implode(', ', array_keys($resolvedRole['levels'] ?? [])) ?: '-' ?></td></tr>
</table>

<h3>Semua key system_roles (<?= count($promptKeys) ?> key)</h3>
<p>Input: <b class="info">"<?= htmlspecialchars($cleanMapel) ?>"</b></p>
<div style="margin: 1rem 0;">
<?php
foreach ($promptKeys as $k) {
    $low = strtolower(trim($k));
    $isMatch = ($low === $cleanMapel);
    $cls = $isMatch ? 'key-match' : 'key-nomatch';
    $arrow = $isMatch ? ' ← EXACT MATCH' : '';
    echo '<span class="' . $cls . '">' . htmlspecialchars($k) . ' <small>(' . htmlspecialchars($low) . ')</small>' . htmlspecialchars($arrow) . '</span> ';
}
?>
</div>

<h3>Raw JSON Metadata Soal</h3>
<pre><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h3>Raw Role Data</h3>
<pre><?= htmlspecialchars(json_encode($resolvedRole, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h3>Diagnosa Mendalam</h3>
<?php
$promptsFile = __DIR__ . '/../../config/prompts.php';
$fileExists  = file_exists($promptsFile);
$fileMtime   = $fileExists ? date('Y-m-d H:i:s', filemtime($promptsFile)) : 'N/A';
$fileSize    = $fileExists ? filesize($promptsFile) . ' bytes' : 'N/A';

// Load manual untuk bandingkan
$manualLoad = null;
if ($fileExists) {
    $manualLoad = require $promptsFile;
}
$manualKeys = is_array($manualLoad) ? array_keys($manualLoad['system_roles'] ?? []) : [];
?>
<table>
<tr><th>Cek</th><th>Nilai</th></tr>
<tr><td>File prompts.php path</td><td><code><?= htmlspecialchars($promptsFile) ?></code></td></tr>
<tr><td>File exists?</td><td><?= $fileExists ? '<span class="ok">Ya</span>' : '<span class="warn">TIDAK</span>' ?></td></tr>
<tr><td>File mtime</td><td><?= htmlspecialchars($fileMtime) ?></td></tr>
<tr><td>File size</td><td><?= htmlspecialchars($fileSize) ?></td></tr>
<tr><td>APP_ENV</td><td><?= htmlspecialchars($config['app']['env'] ?? '(not set)') ?></td></tr>
<tr><td>OPcache active?</td><td><?php echo function_exists('opcache_get_status') ? (json_encode(@opcache_get_status()['opcache_enabled'] ?? false)) : 'extension tidak ada'; ?></td></tr>
</table>

<h4>$cpConfig top-level keys (runtime via config.php):</h4>
<pre><?php
echo "Type: " . gettype($cpConfig) . "\n";
echo "Count: " . (is_array($cpConfig) ? count($cpConfig) : 'N/A') . "\n";
if (is_array($cpConfig)) {
    foreach ($cpConfig as $k => $v) {
        $type = gettype($v);
        $extra = '';
        if ($k === 'system_roles' && is_array($v)) {
            $extra = ' → keys: [' . implode(', ', array_keys($v)) . '] (' . count($v) . ' items)';
        } elseif (is_string($v)) {
            $extra = ' → "' . mb_substr($v, 0, 60) . '"';
        }
        echo "  $k ($type)$extra\n";
    }
}
?></pre>

<h4>$systemRoles keys (runtime via config.php):</h4>
<pre><?php
echo "Type: " . gettype($systemRoles) . "\n";
echo "Count: " . (is_array($systemRoles) ? count($systemRoles) : 'N/A') . "\n";
if (is_array($systemRoles) && !empty($systemRoles)) {
    foreach ($systemRoles as $k => $v) {
        echo "  \"$k\" → label: \"" . ($v['label'] ?? '-') . "\"\n";
    }
} else {
    echo "  KOSONG atau tidak ada!\n";
}
?></pre>

<h4>Manual load prompts.php (direct require):</h4>
<pre><?php
echo "Type: " . gettype($manualLoad) . "\n";
echo "system_roles keys: [" . implode(', ', $manualKeys) . '] (' . count($manualKeys) . " items)\n";
foreach ($manualKeys as $k) {
    echo "  \"$k\"\n";
}
?></pre>

<h4>Perbandingan:</h4>
<pre><?php
$runtimKeys = array_keys($systemRoles);
$missing    = array_diff($manualKeys, $runtimKeys);
$extra      = array_diff($runtimKeys, $manualKeys);
echo "Runtime keys:   [" . implode(', ', $runtimKeys) . "]\n";
echo "Manual keys:    [" . implode(', ', $manualKeys) . "]\n";
echo "Missing (ada di manual, hilang di runtime): " . (empty($missing) ? 'TIDAK ADA (OK)' : implode(', ', $missing)) . "\n";
echo "Extra (ada di runtime, tidak di manual): " . (empty($extra) ? 'TIDAK ADA (OK)' : implode(', ', $extra)) . "\n";
if (!empty($missing)) {
    echo "\n>>> KEMUNGKINAN: OPcache atau file caching menyajikan versi lama prompts.php!\n";
    echo ">>> SOLUSI: Restart PHP-FPM / Apache / Nginx, atau matikan OPcache di dev.\n";
}
?></pre>
</body>
</html>
