<?php
// Session & auth BEFORE layout_top (karena CSV export perlu output headers sendiri)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$adminUser = $_SESSION['admin_username'] ?? 'admin';
require_once __DIR__ . '/../../src/Core/Database.php';
$db = \Core\Database::getInstance($config['database'] ?? []);

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-siswa.csv"');

    $output = fopen('php://output', 'w');
    // BOM untuk Excel (UTF-8)
    fputs($output, "\xEF\xBB\xBF");

    $detailNis   = trim($_GET['nis'] ?? '');
    $detailNama  = trim($_GET['nama'] ?? '');
    $mapelFilter = trim($_GET['mapel'] ?? '');
    $keyword     = trim($_GET['q'] ?? '');

    if (!$db->isConnected()) {
        fputcsv($output, ['Error: Database tidak terhubung']);
        fclose($output);
        exit;
    }

    if ($detailNis !== '') {
        // Export detail satu siswa
        $rows = $db->getStudentHistory($adminUser, $detailNis, $detailNama, $mapelFilter);
        fputcsv($output, ['Mata Pelajaran', 'Judul Soal', 'Skor', 'Total Soal', 'Benar', 'Salah', 'Waktu']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['mata_pelajaran'],
                $r['soal_judul'],
                $r['skor'],
                $r['total_soal'],
                $r['jumlah_benar'],
                $r['jumlah_salah'],
                $r['waktu_kumpul'],
            ]);
        }
    } else {
        // Export daftar siswa
        $rows = $db->searchStudents($adminUser, trim($_GET['q'] ?? ''));
        fputcsv($output, ['Nama', 'NIS', 'Total Ujian', 'Rata-rata Skor', 'Terakhir Ujian']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['nama_siswa'],
                $r['nis'],
                $r['total_ujian'],
                $r['rata_skor'],
                $r['terakhir_ujian'],
            ]);
        }
    }

    fclose($output);
    exit;
}

require __DIR__ . '/layout_top.php';

$dbOk = $db->isConnected();

// ── URL params ──
$detailNis   = trim($_GET['nis']   ?? '');
$detailNama  = trim($_GET['nama']  ?? '');
$mapelFilter = trim($_GET['mapel'] ?? '');
$keyword     = trim($_GET['q']     ?? '');
$isDetail    = ($detailNis !== '');

// ── Data ──
$stats       = [];
$siswaList   = [];
$historyData = [];
$mapelList   = [];
$overviewSiswa = [];

if ($dbOk) {
    if ($isDetail) {
        $historyData   = $db->getStudentHistory($adminUser, $detailNis, $detailNama, $mapelFilter);
        $mapelList     = $db->getMapelList($adminUser);
        $totalUjian    = count($historyData);
        $totalBenar    = array_sum(array_column($historyData, 'jumlah_benar'));
        $totalSalah    = array_sum(array_column($historyData, 'jumlah_salah'));
        $totalSoal     = $totalBenar + $totalSalah;
        $avgSkor       = $totalUjian > 0
            ? round(array_sum(array_column($historyData, 'skor')) / $totalUjian, 1)
            : 0;
    } else {
        $stats     = $db->getOverviewStats($adminUser);
        $allSiswa  = $db->searchStudents($adminUser, $keyword);
        $totalSiswa = count($allSiswa);
        $page      = max(1, (int)($_GET['p'] ?? 1));
        $perPage   = 20;
        $totalPages = max(1, ceil($totalSiswa / $perPage));
        $siswaList = array_slice($allSiswa, ($page - 1) * $perPage, $perPage);
    }
}
?>

<style>
.skor-bar-wrap { display:flex; align-items:center; gap:.6rem; }
.skor-bar-bg   { flex:1; height:8px; background:var(--border); border-radius:99px; overflow:hidden; }
.skor-bar-fill { height:100%; border-radius:99px; }
.badge-skor    { display:inline-block; padding:.2rem .55rem; border-radius:99px; font-size:.72rem; font-weight:700; }
.stat-card     { background:var(--surface); border-radius:var(--radius-md); padding:1.25rem 1.5rem;
                 box-shadow:var(--shadow-sm); display:flex; flex-direction:column; gap:.4rem; }
.stat-icon  { font-size:1.75rem; }
.stat-value { font-size:2rem; font-weight:700; color:var(--primary); line-height:1; }
.stat-label { font-size:.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
#chartWrap  { position:relative; height:300px; }
</style>

<div class="page-title">
    📊 Laporan Siswa
    <?php if ($isDetail): ?>
    <div style="display:flex; gap:.5rem;">
        <a href="<?= $adminBase ?>laporan.php" class="btn btn-outline" style="font-size:.85rem;">← Kembali ke Daftar</a>
        <a href="?export=csv&nis=<?= urlencode($detailNis) ?>&nama=<?= urlencode($detailNama) ?><?= $mapelFilter ? '&mapel=' . urlencode($mapelFilter) : '' ?>" class="btn btn-outline" style="font-size:.8rem;">⬇ CSV</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$dbOk): ?>
<?php if (\Core\Auth::can($adminRole, 'administrator')): ?>
<div class="card" style="border-left:4px solid var(--warning); background:#FFFBEB;">
    <h3 style="color:#92400E; margin-bottom:.75rem;">⚠️ Database MySQL Belum Dikonfigurasi</h3>
    <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
        Data laporan saat ini hanya disimpan di file JSON. Konfigurasi database MySQL agar data lebih aman dan tidak hilang.
        <a href="<?= $adminBase ?>settings.php" style="color:var(--danger);">Klik di sini untuk pengaturan database</a>.
    </p>
</div>
<?php else: ?>
<div class="card" style="border-left:4px solid var(--warning); background:#FFFBEB;">
    <h3 style="color:#92400E; margin-bottom:.75rem;">ℹ️ Database Belum Aktif</h3>
    <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
        Fitur laporan memerlukan database. Hubungi <strong>Kepala Sekolah</strong> atau <strong>Administrator</strong> untuk mengaktifkannya melalui halaman Pengaturan.
    </p>
</div>
<?php endif; ?>

<?php elseif (!$isDetail): ?>
<!-- ══════════════════════════════════════════════════════════════════
     MODE DAFTAR: Pilih siswa terlebih dahulu
═══════════════════════════════════════════════════════════════════ -->

<!-- Overview Stats Global -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:1rem; margin-bottom:1.75rem;">
    <?php
    $statItems = [
        ['👥', $stats['total_siswa']  ?? 0,    'Total Siswa'],
        ['📝', $stats['total_ujian']  ?? 0,    'Total Ujian'],
        ['⭐', $stats['rata_skor']    ?? '-',   'Rata-rata Skor'],
        ['✅', $stats['total_benar']  ?? 0,    'Total Benar'],
        ['❌', $stats['total_salah']  ?? 0,    'Total Salah'],
    ];
    foreach ($statItems as [$icon, $val, $lbl]): ?>
    <div class="stat-card">
        <span class="stat-icon"><?= $icon ?></span>
        <span class="stat-value"><?= $val ?></span>
        <span class="stat-label"><?= $lbl ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Cari Siswa -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.75rem;">
        <h3 style="font-size:1rem; margin:0;">
            👥 Pilih Siswa untuk Melihat Laporan
        </h3>
        <form method="GET" style="display:flex; gap:.5rem;">
            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
                placeholder="🔍 Cari nama atau NIS..."
                style="padding:.45rem .75rem; border:1px solid var(--border); border-radius:var(--radius-sm);
                       font-size:.85rem; width:220px; font-family:inherit;">
            <button type="submit" class="btn btn-outline" style="padding:.45rem .75rem; font-size:.85rem;">Cari</button>
            <?php if ($keyword): ?>
                    <a href="<?= $adminBase ?>laporan.php" class="btn btn-outline" style="padding:.45rem .75rem; font-size:.85rem;">✕</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="margin-bottom:1rem;">
        <a href="?export=csv<?= $keyword ? '&q=' . urlencode($keyword) : '' ?>" class="btn btn-outline" style="font-size:.8rem;">
            ⬇ Download CSV
        </a>
    </div>

    <?php if (empty($siswaList)): ?>
    <div style="text-align:center; padding:3rem; color:var(--text-muted);">
        <div style="font-size:3rem; margin-bottom:.75rem;"><?= $keyword ? '🔍' : '📭' ?></div>
        <p>
            <?php if ($keyword): ?>
                Tidak ditemukan siswa dengan kata kunci "<strong><?= htmlspecialchars($keyword) ?></strong>".
            <?php else: ?>
                Belum ada data evaluasi siswa.<br>
                <span style="font-size:.875rem; margin-top:.5rem; display:block;">
                    Data muncul otomatis setelah siswa menyelesaikan ujian pertama.
                </span>
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.875rem;">
            <thead style="background:#F9FAFB;">
                <tr>
                    <th>#</th>
                    <th>Nama Siswa</th>
                    <th>NIS</th>
                    <th style="text-align:center;">Ujian</th>
                    <th>Rata-rata Skor</th>
                    <th>Terakhir Ujian</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($siswaList as $i => $s):
                $avg   = (float)($s['rata_skor'] ?? 0);
                $warna = $avg >= 85 ? '#10B981' : ($avg >= 60 ? '#F59E0B' : '#EF4444');
                $tgl   = $s['terakhir_ujian'] ? date('d M Y', strtotime($s['terakhir_ujian'])) : '-';
            ?>
            <tr>
                <td style="color:var(--text-muted); font-weight:600;"><?= $i + 1 ?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:.5rem; font-weight:600;">
                        <span style="width:32px; height:32px; border-radius:50%; background:#EEF2FF;
                               color:var(--primary); display:inline-flex; align-items:center; justify-content:center;
                               font-size:.85rem; font-weight:700; flex-shrink:0;">
                            <?= strtoupper(mb_substr($s['nama_siswa'], 0, 1)) ?>
                        </span>
                        <?= htmlspecialchars($s['nama_siswa']) ?>
                    </div>
                </td>
                <td><code style="background:#F3F4F6; padding:.15rem .4rem; border-radius:.25rem; font-size:.8rem;"><?= htmlspecialchars($s['nis'] ?: '-') ?></code></td>
                <td style="text-align:center; font-weight:700; color:var(--primary);"><?= $s['total_ujian'] ?>x</td>
                <td>
                    <div class="skor-bar-wrap">
                        <div class="skor-bar-bg">
                            <div class="skor-bar-fill" style="width:<?= min(100, $avg) ?>%; background:<?= $warna ?>;"></div>
                        </div>
                        <span class="badge-skor" style="background:<?= $warna ?>22; color:<?= $warna ?>;">
                            <?= number_format($avg, 1) ?>
                        </span>
                    </div>
                </td>
                <td style="color:var(--text-muted); font-size:.82rem;"><?= $tgl ?></td>
                <td style="text-align:center;">
                    <a href="<?= $adminBase ?>laporan.php?nis=<?= urlencode($s['nis']) ?>&nama=<?= urlencode($s['nama_siswa']) ?>"
                       class="btn btn-primary" style="font-size:.78rem; padding:.35rem .9rem;">
                        📈 Lihat Laporan
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:.5rem;margin-top:1rem;">
        <?php if ($page > 1): ?>
        <a href="?p=<?= $page - 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?>" class="btn btn-outline" style="padding:.4rem .8rem;">← Prev</a>
        <?php endif; ?>
        <span style="font-weight:600;padding:0 1rem;color:var(--text-main);font-size:.85rem;">
            <?= $page ?> / <?= $totalPages ?>
        </span>
        <?php if ($page < $totalPages): ?>
        <a href="?p=<?= $page + 1 ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?>" class="btn btn-outline" style="padding:.4rem .8rem;">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════
     MODE DETAIL: Laporan satu siswa yang sudah dipilih
═══════════════════════════════════════════════════════════════════ -->

<!-- Header Kartu Siswa -->
<div class="card" style="margin-bottom:1.5rem; display:flex; align-items:center; justify-content:space-between;
     flex-wrap:wrap; gap:1rem; padding:1.25rem 1.5rem; border-left:4px solid var(--primary);">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:52px; height:52px; border-radius:50%; background:#EEF2FF; color:var(--primary);
             font-size:1.5rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <?= strtoupper(mb_substr($detailNama, 0, 1)) ?>
        </div>
        <div>
            <div style="font-size:1.15rem; font-weight:700; color:var(--text-main);"><?= htmlspecialchars($detailNama) ?></div>
            <div style="font-size:.82rem; color:var(--text-muted);">NIS: <code style="background:#F3F4F6; padding:.1rem .35rem; border-radius:.2rem;"><?= htmlspecialchars($detailNis) ?></code></div>
        </div>
    </div>

    <!-- Dropdown filter mapel — submit otomatis saat berubah -->
    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
        <label style="font-size:.82rem; font-weight:600; color:var(--text-muted); white-space:nowrap;">📚 Filter Mata Pelajaran:</label>
        <form method="GET" id="mapelForm">
            <input type="hidden" name="nis"  value="<?= htmlspecialchars($detailNis) ?>">
            <input type="hidden" name="nama" value="<?= htmlspecialchars($detailNama) ?>">
            <select name="mapel" onchange="document.getElementById('mapelForm').submit()"
                style="padding:.45rem .75rem; border:1px solid var(--border); border-radius:var(--radius-sm);
                       font-size:.875rem; font-family:inherit; min-width:180px;">
                <option value="">Semua Mata Pelajaran</option>
                <?php foreach ($mapelList as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= $mapelFilter === $m ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($mapelFilter): ?>
        <a href="<?= $adminBase ?>laporan.php?nis=<?= urlencode($detailNis) ?>&nama=<?= urlencode($detailNama) ?>"
           class="btn btn-outline" style="font-size:.8rem; padding:.4rem .75rem;">✕ Reset Filter</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($historyData)): ?>
<div class="card" style="text-align:center; padding:3rem; color:var(--text-muted);">
    <div style="font-size:3rem; margin-bottom:.75rem;">📭</div>
    <p>Belum ada riwayat ujian<?= $mapelFilter ? ' untuk mapel <strong>' . htmlspecialchars($mapelFilter) . '</strong>' : '' ?>.</p>
    <?php if ($mapelFilter): ?>
    <a href="<?= $adminBase ?>laporan.php?nis=<?= urlencode($detailNis) ?>&nama=<?= urlencode($detailNama) ?>"
       class="btn btn-outline" style="margin-top:1rem;">Tampilkan Semua Mapel</a>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- Kartu Statistik Ringkas Siswa -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <?php
    $sc = $avgSkor >= 85 ? '#10B981' : ($avgSkor >= 60 ? '#F59E0B' : '#EF4444');
    $infoCards = [
        ['⭐', $avgSkor,    'Rata-rata Skor', $sc],
        ['📝', $totalUjian, 'Total Ujian',    'var(--primary)'],
        ['✅', $totalBenar, 'Total Benar',    '#10B981'],
        ['❌', $totalSalah, 'Total Salah',    '#EF4444'],
        ['📋', $totalSoal,  'Total Soal',     '#7C3AED'],
        ['🎯', $totalSoal > 0 ? round($totalBenar/$totalSoal*100,1).'%' : '-', 'Akurasi', '#059669'],
    ];
    foreach ($infoCards as [$ic, $vl, $lb, $cl]): ?>
    <div class="stat-card" style="border-top:3px solid <?= $cl ?>;">
        <span class="stat-icon"><?= $ic ?></span>
        <span class="stat-value" style="color:<?= $cl ?>;"><?= $vl ?></span>
        <span class="stat-label"><?= $lb ?></span>
    </div>
    <?php endforeach; ?>
</div>

<?php
// Siapkan data chart
$chartLabels = [];
$chartSkor   = [];
$chartBenar  = [];
$chartSalah  = [];
foreach ($historyData as $idx => $h) {
    $chartLabels[] = 'Ujian ' . ($idx + 1);
    $chartSkor[]   = (float)$h['skor'];
    $chartBenar[]  = (int)$h['jumlah_benar'];
    $chartSalah[]  = (int)$h['jumlah_salah'];
}
?>

<!-- Chart Skor -->
<div class="card" style="margin-bottom:1.5rem;">
    <h3 style="font-size:.95rem; margin-bottom:1.25rem; color:var(--text-main);">
        📈 Perkembangan Skor
        <?php if ($mapelFilter): ?>
        <span style="font-size:.8rem; background:#EEF2FF; color:var(--primary); padding:2px 8px; border-radius:999px; margin-left:.5rem;">
            <?= htmlspecialchars($mapelFilter) ?>
        </span>
        <?php endif; ?>
    </h3>
    <div id="chartWrap"><canvas id="lineChart"></canvas></div>
</div>

<!-- Tabel Riwayat -->
<div class="card" style="margin-bottom:2rem;">
    <h3 style="font-size:.95rem; margin-bottom:1rem;">🗂 Riwayat Ujian</h3>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead style="background:#F9FAFB;">
                <tr>
                    <th>#</th>
                    <th>Judul Soal</th>
                    <th>Mata Pelajaran</th>
                    <th style="text-align:center;">Skor</th>
                    <th style="text-align:center;">Benar</th>
                    <th style="text-align:center;">Salah</th>
                    <th style="text-align:center;">Total</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($historyData as $i => $row):
                $s = (float)$row['skor'];
                $w = $s >= 85 ? '#10B981' : ($s >= 60 ? '#F59E0B' : '#EF4444');
            ?>
            <tr>
                <td style="color:var(--text-muted); font-weight:600;"><?= $i + 1 ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($row['soal_judul']) ?></td>
                <td>
                    <span style="background:#F0FDF4; color:#065F46; padding:2px 8px; border-radius:999px; font-size:.78rem;">
                        <?= htmlspecialchars($row['mata_pelajaran']) ?>
                    </span>
                </td>
                <td style="text-align:center;">
                    <span class="badge-skor" style="background:<?= $w ?>22; color:<?= $w ?>;"><?= number_format($s, 0) ?></span>
                </td>
                <td style="text-align:center; color:#10B981; font-weight:700;"><?= $row['jumlah_benar'] ?></td>
                <td style="text-align:center; color:#EF4444; font-weight:700;"><?= $row['jumlah_salah'] ?></td>
                <td style="text-align:center; color:var(--text-muted);"><?= $row['total_soal'] ?></td>
                <td style="font-size:.8rem; color:var(--text-muted);"><?= date('d M Y, H:i', strtotime($row['waktu_kumpul'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels  = <?= json_encode($chartLabels) ?>;
    const tooltip = <?= json_encode(array_map(fn($h) =>
        $h['soal_judul'] . ' — ' . $h['mata_pelajaran'] . ' (' . date('d/m/y', strtotime($h['waktu_kumpul'])) . ')',
        $historyData
    )) ?>;
    const skor   = <?= json_encode($chartSkor) ?>;
    const benar  = <?= json_encode($chartBenar) ?>;
    const salah  = <?= json_encode($chartSalah) ?>;

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Skor (%)',
                    data: skor,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79,70,229,.08)',
                    borderWidth: 2.5,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: skor.map(v => v>=85 ? '#10B981' : v>=60 ? '#F59E0B' : '#EF4444'),
                    tension: .35,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Benar',
                    data: benar,
                    borderColor: '#10B981',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: .35,
                    borderDash: [5,3],
                    yAxisID: 'y2',
                },
                {
                    label: 'Salah',
                    data: salah,
                    borderColor: '#EF4444',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: .35,
                    borderDash: [5,3],
                    yAxisID: 'y2',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { family: 'Inter', size: 12 } } },
                tooltip: {
                    backgroundColor: '#1F2937',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        title: (items) => tooltip[items[0].dataIndex]
                    }
                }
            },
            scales: {
                x: { grid: { color: '#F3F4F6' }, ticks: { maxRotation: 0 } },
                y: {
                    type: 'linear', position: 'left', min: 0, max: 100,
                    title: { display: true, text: 'Skor (%)' },
                    grid: { color: '#F3F4F6' }
                },
                y2: {
                    type: 'linear', position: 'right',
                    title: { display: true, text: 'Jumlah Soal' },
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
})();
</script>

<?php endif; // end empty historyData ?>
<?php endif; // end if dbOk / mode ?>

<div style="height:2rem;"></div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
