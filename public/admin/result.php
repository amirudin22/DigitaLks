<?php
require __DIR__ . '/layout_top.php';

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
    echo "<pre>";
    echo "Path: " . $dataPath . "\n";
    echo "Data: ";
    print_r($data);
    echo "</pre>";
    require __DIR__ . '/layout_bottom.php';
    exit;
}

if (($data['metadata']['status'] ?? '') !== 'completed' || empty($data['hasil'])) {
    echo "Soal ini belum dikerjakan dan dikumpulkan oleh Anak.";
    echo "<br><a href='index.php' class='btn btn-outline' style='margin-top:1rem;'>Kembali</a>";
    require __DIR__ . '/layout_bottom.php';
    exit;
}

$meta = $data['metadata'];
$hasil = $data['hasil'];

$skor = $hasil['skor'] ?? 0;
$benar = $hasil['benar'] ?? 0;
$salah = $hasil['salah'] ?? 0;
$total = $hasil['total'] ?? 0;
$namaAnak = $hasil['nama'] ?? '-';
$citaCita = $hasil['cita_cita'] ?? '-';
$tz = new DateTimeZone($config['app']['timezone'] ?? 'Asia/Jakarta');
$waktuKumpul = isset($hasil['waktu_kumpul'])
    ? (new DateTime($hasil['waktu_kumpul']))->setTimezone($tz)->format('d M Y, H:i:s')
    : '-';
?>

<div class="page-title">
    Laporan Evaluasi Anak
    <div>
        <a href="generate.php?id=<?= urlencode($id) ?>&regen=1&_token=<?= htmlspecialchars(csrfToken()) ?>" class="btn btn-outline"
            onclick="return confirm('Ini akan merobek/menghapus nilai rapor anak ini, mereset pertanyaannya, dan membuat PIN baru agar bisa dikerjakan ulang dari awal. Anda yakin?')"
            style="margin-right:0.5rem;" title="Hapus Hasil & Mulai Ulang">Ulangi Tes (Reset)</a>
        <a href="<?= $adminBase ?>index.php" class="btn btn-primary" style="font-size:0.875rem;">← Kembali</a>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Kiri: Kartu Profil Anak -->
    <div class="card" style="text-align:center; padding: 2rem;">
        <div
            style="width: 80px; height: 80px; border-radius: 50%; background-color: #E0E7FF; color: var(--primary); font-size: 2.5rem; display:inline-flex; align-items:center; justify-content:center; margin-bottom: 1rem; font-weight:700;">
            <?= strtoupper(substr($namaAnak, 0, 1)) ?>
        </div>
        <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--text-main);">
            <?= htmlspecialchars($namaAnak) ?>
        </h2>
        <div style="font-weight: 600; color: var(--text-muted); margin-bottom: 1.5rem;">Calon
            <?= htmlspecialchars($citaCita) ?>
        </div>

        <div style="border-top: 1px dashed var(--border); padding-top: 1rem; text-align: left; font-size: 0.875rem;">
            <div style="margin-bottom: 0.5rem;"><strong>Dikumpulkan:</strong> <span style="float:right;">
                    <?= $waktuKumpul ?> WIB
                </span></div>
            <div style="margin-bottom: 0.5rem;"><strong>Judul Tes:</strong> <span
                    style="float:right; text-align:right; width:60%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                    title="<?= htmlspecialchars($meta['judul']) ?>">
                    <?= htmlspecialchars($meta['judul']) ?>
                </span></div>
        </div>
    </div>

    <!-- Kanan: Statistik Cepat -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:center; align-items:center;">
        <div
            style="text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 2px; font-size: 0.875rem; margin-bottom: 0.5rem;">
            NILAI AKHIR MATEMATIKA</div>
        <div
            style="font-size: 4rem; color: <?= $skor >= 85 ? 'var(--success)' : ($skor >= 60 ? 'var(--warning)' : 'var(--danger)') ?>; font-weight: 700; line-height: 1; margin-bottom: 1.5rem;">
            <?= $skor ?>
        </div>

        <div
            style="display:flex; gap: 2rem; border-top: 1px solid var(--border); padding-top: 1rem; width:100%; justify-content:center;">
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:var(--success);">
                    <?= $benar ?>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Benar</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:var(--danger);">
                    <?= $salah ?>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Salah</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:var(--text-main);">
                    <?= $total ?>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Total Soal</div>
            </div>
        </div>
    </div>
</div>

<h3 style="font-size: 1.125rem; margin-bottom: 1rem;">Rincian Pengerjaan Soal</h3>
<div style="background:var(--surface); border-radius:var(--radius-md); box-shadow:var(--shadow-sm); overflow:hidden;">
    <table class="table">
        <thead style="background:#F9FAFB;">
            <tr>
                <th style="width: 50px; text-align:center;">No</th>
                <th style="width: 50%;">Pertanyaan</th>
                <th style="text-align:center;">Jwb. Siswa</th>
                <th style="text-align:center;">Kunci Jwb.</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['soal'] as $idx => $s):
                $d = $hasil['detail'][$s['id']] ?? ['jwb_siswa' => '', 'is_correct' => false];
                $isC = $d['is_correct'];
                ?>
                <tr>
                    <td style="text-align:center; font-weight:600; color:var(--text-muted);">
                        <?= $idx + 1 ?>
                    </td>
                    <td>
                        <div style="font-size: 0.9rem; margin-bottom:0.5rem; line-height:1.5; white-space:pre-wrap;">
                            <?= htmlspecialchars($s['pertanyaan']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            [A]
                            <?= htmlspecialchars($s['opsi']['A'] ?? '') ?> &nbsp;|&nbsp;
                            [B]
                            <?= htmlspecialchars($s['opsi']['B'] ?? '') ?> &nbsp;|&nbsp;
                            [C]
                            <?= htmlspecialchars($s['opsi']['C'] ?? '') ?> &nbsp;|&nbsp;
                            [D]
                            <?= htmlspecialchars($s['opsi']['D'] ?? '') ?>
                        </div>
                    </td>
                    <td
                        style="text-align:center; font-weight:700; font-size: 1.1rem; color: <?= $isC ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $d['jwb_siswa'] !== '' ? htmlspecialchars($d['jwb_siswa']) : '-' ?>
                    </td>
                    <td style="text-align:center; font-weight:700; font-size: 1.1rem; color:var(--text-main);">
                        <?= htmlspecialchars($s['jawaban_benar']) ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($isC): ?>
                            <span style="font-size:1.5rem;">✅</span>
                        <?php else: ?>
                            <span style="font-size:1.5rem;">❌</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="height: 2rem;"></div>

<!-- Laporan Evaluasi Anak (Catatan & Penjelasan) -->
<div class="card" style="background: linear-gradient(135deg, #FFF7F0, #FEF3C7); border: 2px dashed #FBBF24;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h3 style="font-size:1.125rem; color:#D97706; margin:0;">📝 Laporan Evaluasi Anak</h3>
        <span style="font-size:0.875rem; color:#7C2D12;">Catatan & Penjelasan dari Anak</span>
    </div>

    <div style="background:#FFFFFF; border-radius:var(--radius-md); padding:1.5rem; margin-bottom:1rem;">
        <div style="display:flex; gap:2rem; margin-bottom:1.5rem;">
            <div style="flex:1;">
                <div style="font-size:0.875rem; color:#6B7280; margin-bottom:0.5rem;">Nama Anak</div>
                <div style="font-weight:600; color:#1A1A1A;"><?= htmlspecialchars($hasil['nama'] ?? '-') ?></div>
            </div>
            <div style="flex:1;">
                <div style="font-size:0.875rem; color:#6B7280; margin-bottom:0.5rem;">Cita-cita</div>
                <div style="font-weight:600; color:#1A1A1A;"><?= htmlspecialchars($hasil['cita_cita'] ?? '-') ?></div>
            </div>
            <div style="flex:1;">
                <div style="font-size:0.875rem; color:#6B7280; margin-bottom:0.5rem;">Waktu Kumpul</div>
                <div style="font-weight:600; color:#1A1A1A;"><?= $waktuKumpul ?> WIB</div>
            </div>
        </div>

        <div style="border-top:1px solid #E2E8F0; padding-top:1rem; margin-bottom:1.5rem;"></div>

        <div style="background:#FEF3C7; border-left:4px solid #F59E0B; padding:1rem; border-radius:var(--radius-md); margin-bottom:1rem;">
            <div style="font-weight:600; color:#92400E; margin-bottom:0.5rem;">🎯 Ringkasan Evaluasi</div>
            <div style="font-size:0.875rem; color:#6B7280; line-height:1.5;">
                <p><strong>Skor Akhir:</strong> <?= $skor ?> (<?= $benar ?>/<?= $total ?> soal benar)</p>
                <p><strong>Catatan Guru:</strong> <?= $skor >= 85 ? 'Sangat baik! Pertahankan prestasi ini.' : ($skor >= 60 ? 'Bagus! Tingkatkan lagi.' : 'Terus berlatih dan jangan menyerah.') ?></p>
            </div>
        </div>

        <?php if (!empty($hasil['detail'])): ?>
            <div style="margin-bottom:1rem;">
                <div style="font-weight:600; color:#1A1A1A; margin-bottom:0.5rem;">💬 Catatan & Penjelasan Anak</div>
                <?php foreach ($hasil['detail'] as $idSoal => $detail): ?>
                    <?php if (!empty($detail['penjelasan'])): ?>
                        <div style="background:#F0F9FF; border:1px solid #BEE3F8; border-radius:var(--radius-md); padding:1rem; margin-bottom:0.5rem;">
                            <div style="font-size:0.875rem; color:#2C5282; margin-bottom:0.25rem;">
                                <strong>Soal <?= array_search($idSoal, array_column($data['soal'], 'id')) + 1 ?>:</strong>
                            </div>
                            <div style="font-size:0.875rem; color:#1A202C; white-space:pre-wrap;"><?= htmlspecialchars($detail['penjelasan']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($hasil['detail']) || !isset($hasil['detail'][0]['penjelasan'])): ?>
            <div style="text-align:center; color:#6B7280; font-size:0.875rem;">
                <em>Belum ada catatan atau penjelasan dari anak.</em>
            </div>
        <?php endif; ?>
    </div>

    <div style="display:flex; gap:1rem;">
        <a href="<?= $adminBase ?>index.php" class="btn btn-outline" style="flex:1;">Kembali ke Dashboard</a>
        <a href="generate.php?id=<?= urlencode($id) ?>&regen=1&_token=<?= htmlspecialchars(csrfToken()) ?>" class="btn btn-danger" style="flex:1;">Reset & Mulai Ulang</a>
    </div>
</div>

<div style="height: 2rem;"></div>

<!-- Button to view child's result interface -->
<div style="text-align:center; margin-bottom:2rem;">
    <a href="../s/index.php?slug=<?= urlencode($meta['slug']) ?>&state=result" class="btn btn-primary" style="padding:0.75rem 1.5rem; font-size:0.875rem;">
        👧 Lihat Hasil Evaluasi Anak (Seperti yang Dilihat Anak)
    </a>
</div>

<div style="height: 3rem;"></div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
