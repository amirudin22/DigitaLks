<?php
require __DIR__ . '/layout_top.php';

$success = '';
$formError = '';
$keyword = trim($_GET['q'] ?? '');

// ── Handle POST actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('CSRF token tidak valid.');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nis   = trim($_POST['nis']   ?? '');
        $nama  = trim($_POST['nama']  ?? '');
        $kelas = trim($_POST['kelas'] ?? '');

        if ($nis === '' || $nama === '') {
            $formError = 'NIS dan Nama wajib diisi.';
        } elseif (!$db->isConnected()) {
            $formError = 'Database tidak terhubung.';
        } else {
            $ok = $db->addSiswa($adminUser, $nis, $nama, $kelas);
            $success = $ok ? "Siswa \"$nama\" (NIS: $nis) berhasil ditambahkan/diperbarui." : 'Gagal menyimpan. NIS mungkin sudah terdaftar dengan nama berbeda.';
        }
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['id']    ?? 0);
        $nis   = trim($_POST['nis']   ?? '');
        $nama  = trim($_POST['nama']  ?? '');
        $kelas = trim($_POST['kelas'] ?? '');

        if ($id < 1 || $nis === '' || $nama === '') {
            $formError = 'Data tidak valid.';
        } elseif (!$db->isConnected()) {
            $formError = 'Database tidak terhubung.';
        } else {
            $ok = $db->updateSiswa($id, $adminUser, $nis, $nama, $kelas);
            $success = $ok ? "Data siswa berhasil diperbarui." : 'Gagal memperbarui data.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $db->isConnected()) {
            $db->deleteSiswa($id, $adminUser);
            $success = 'Siswa berhasil dihapus.';
        }
    }
}

// ── Ambil daftar siswa ─────────────────────────────────────────────
$siswaList = $db->isConnected() ? $db->getAllSiswa($adminUser, $keyword) : [];
?>

<div class="page-title">
    👨‍🎓 Data Siswa
    <div style="font-size:.875rem; color:var(--text-muted); font-weight:400;">
        Daftar siswa terdaftar — gunakan NIS untuk mengikuti ujian
    </div>
</div>

<?php if (!$db->isConnected()): ?>
<?php if (\Core\Auth::can($adminRole, 'administrator')): ?>
<div class="card" style="border-left:4px solid var(--warning); background:#FFFBEB;">
    <h3 style="color:#92400E; margin-bottom:.75rem;">⚠️ Database MySQL Belum Dikonfigurasi</h3>
    <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
        Data siswa & laporan saat ini hanya disimpan di file JSON. Konfigurasi database MySQL agar data lebih aman dan tidak hilang.
        <a href="<?= $adminBase ?>settings.php" style="color:var(--danger);">Klik di sini untuk pengaturan database</a>.
    </p>
</div>
<?php else: ?>
<div class="card" style="border-left:4px solid var(--warning); background:#FFFBEB;">
    <h3 style="color:#92400E; margin-bottom:.75rem;">ℹ️ Database Belum Aktif</h3>
    <p style="color:var(--text-muted); font-size:.9rem; line-height:1.6;">
        Fitur data siswa memerlukan database. Hubungi <strong>Kepala Sekolah</strong> atau <strong>Administrator</strong> untuk mengaktifkannya melalui halaman Pengaturan.
    </p>
</div>
<?php endif; ?>
<?php else: ?>

<?php if ($success): ?>
<div style="background:#F0FDF4; color:#065F46; border:1px solid #6EE7B7; padding:.875rem 1.25rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; font-size:.9rem;">
    ✅ <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if ($formError): ?>
<div style="background:#FEF2F2; color:#991B1B; border:1px solid #FCA5A5; padding:.875rem 1.25rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; font-size:.9rem;">
    ❌ <?= htmlspecialchars($formError) ?>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:1.5rem; align-items:start;">

    <!-- Kolom Kiri: Form Tambah Siswa -->
    <div class="card" style="border-top:4px solid var(--primary);">
        <h3 style="font-size:1rem; margin-bottom:1.25rem;">➕ Tambah / Update Siswa</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">
                NIS <span style="color:var(--danger);">*</span>
            </label>
            <input type="text" name="nis" class="form-input" placeholder="Contoh: 20240001"
                style="margin-bottom:.875rem; padding:.5rem .75rem;" required inputmode="numeric">

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">
                Nama Lengkap <span style="color:var(--danger);">*</span>
            </label>
            <input type="text" name="nama" class="form-input" placeholder="Contoh: Budi Santoso"
                style="margin-bottom:.875rem; padding:.5rem .75rem;" required>

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">
                Kelas
            </label>
            <input type="text" name="kelas" class="form-input" placeholder="Contoh: 6A / 9B / XII IPA"
                style="margin-bottom:1.25rem; padding:.5rem .75rem;">

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:.75rem;">
                💾 Simpan Siswa
            </button>
            <p style="font-size:.75rem; color:var(--text-muted); margin-top:.75rem; text-align:center;">
                Jika NIS sudah ada, data akan diperbarui otomatis.
            </p>
        </form>
    </div>

    <!-- Kolom Kanan: Daftar Siswa -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.75rem;">
            <h3 style="font-size:1rem; margin:0;">
                Daftar Siswa
                <span style="font-weight:400; color:var(--text-muted); font-size:.85rem;">(<?= count($siswaList) ?> siswa)</span>
            </h3>
            <form method="GET" style="display:flex; gap:.5rem;">
                <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
                    placeholder="🔍 Cari nama, NIS, kelas..."
                    style="padding:.45rem .75rem; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:.85rem; width:200px;">
                <button type="submit" class="btn btn-outline" style="padding:.45rem .75rem; font-size:.85rem;">Cari</button>
                <?php if ($keyword): ?>
                <a href="<?= $adminBase ?>siswa.php" class="btn btn-outline" style="padding:.45rem .75rem; font-size:.85rem;">✕</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($siswaList)): ?>
        <div style="text-align:center; padding:3rem; color:var(--text-muted);">
            <div style="font-size:3rem; margin-bottom:.75rem;">👨‍🎓</div>
            <p><?= $keyword ? 'Tidak ditemukan siswa dengan kata kunci tersebut.' : 'Belum ada siswa terdaftar. Tambahkan melalui form di sebelah kiri.' ?></p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NIS</th>
                        <th>Nama Lengkap</th>
                        <th>Kelas</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($siswaList as $i => $s): ?>
                <tr id="row-<?= $s['id'] ?>">
                    <td style="color:var(--text-muted); font-weight:600;"><?= $i + 1 ?></td>
                    <td>
                        <code style="background:#F3F4F6; padding:.15rem .45rem; border-radius:.25rem; font-size:.8rem;">
                            <?= htmlspecialchars($s['nis']) ?>
                        </code>
                    </td>
                    <td style="font-weight:500;"><?= htmlspecialchars($s['nama']) ?></td>
                    <td>
                        <?php if ($s['kelas']): ?>
                        <span style="background:#EEF2FF; color:var(--primary); padding:2px 8px; border-radius:999px; font-size:.78rem; font-weight:600;">
                            <?= htmlspecialchars($s['kelas']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-muted); font-size:.78rem;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="btn btn-outline"
                            style="font-size:.75rem; padding:.3rem .6rem; margin-right:.25rem;"
                            onclick="editRow(<?= $s['id'] ?>, '<?= addslashes($s['nis']) ?>', '<?= addslashes($s['nama']) ?>', '<?= addslashes($s['kelas']) ?>')">
                            ✏️ Edit
                        </button>
                        <form method="POST" style="display:inline;"
                            onsubmit="return confirm('Hapus siswa <?= htmlspecialchars(addslashes($s['nama'])) ?>? Riwayat ujiannya tetap tersimpan.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="font-size:.75rem; padding:.3rem .6rem;">
                                🗑️
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
     background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:90%; max-width:420px; border-radius:var(--radius-md);
                padding:1.75rem; box-shadow:0 20px 40px rgba(0,0,0,.2);">
        <h3 style="margin-bottom:1.25rem; font-size:1rem;">✏️ Edit Data Siswa</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">NIS *</label>
            <input type="text" name="nis" id="editNis" class="form-input"
                style="margin-bottom:.875rem; padding:.5rem .75rem;" required>

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">Nama Lengkap *</label>
            <input type="text" name="nama" id="editNama" class="form-input"
                style="margin-bottom:.875rem; padding:.5rem .75rem;" required>

            <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:.35rem; color:var(--text-muted);">Kelas</label>
            <input type="text" name="kelas" id="editKelas" class="form-input"
                style="margin-bottom:1.25rem; padding:.5rem .75rem;">

            <div style="display:flex; gap:.75rem;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:2;">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRow(id, nis, nama, kelas) {
    document.getElementById('editId').value    = id;
    document.getElementById('editNis').value   = nis;
    document.getElementById('editNama').value  = nama;
    document.getElementById('editKelas').value = kelas;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
// Tutup modal saat klik luar
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php endif; ?>

<div style="height:2rem;"></div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
