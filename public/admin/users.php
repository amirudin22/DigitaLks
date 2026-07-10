<?php
require __DIR__ . '/layout_top.php';

$adminRole = $_SESSION['admin_role'] ?? '';
if (!\Core\Auth::can($adminRole, 'kepala_sekolah')) {
    echo "<div class='card' style='text-align:center;padding:4rem 2rem;'><h3 style='color:var(--danger);margin-bottom:1rem;'>Akses Ditolak</h3><p style='color:var(--text-muted);'>Hanya Kepala Sekolah yang dapat mengelola user.</p><a href='index.php' class='btn btn-primary' style='margin-top:1rem;'>← Dashboard</a></div>";
    require __DIR__ . '/layout_bottom.php';
    exit;
}

$auth = $config['auth'];
$msg = ''; $err = '';

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('CSRF token tidak valid.');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'guru';
        $nama     = trim($_POST['nama'] ?? '');

        if (empty($username) || empty($password) || empty($nama)) {
            $err = 'Semua field wajib diisi.';
        } else {
            $result = $auth->addUser($username, $password, $role, $nama);
            if ($result['ok']) {
                $msg = "User \"$nama\" ($username) berhasil ditambahkan.";
            } else {
                $err = $result['error'];
            }
        }
    }

    if ($action === 'edit') {
        $orig    = $_POST['orig_username'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'guru';
        $nama     = trim($_POST['nama'] ?? '');

        if (empty($username) || empty($nama)) {
            $err = 'Username dan Nama wajib diisi.';
        } elseif ($username !== $orig && !empty($username)) {
            // Ganti username: delete old, add new
            $auth->deleteUser($orig);
            $result = $auth->addUser($username, $password ?: bin2hex(random_bytes(4)), $role, $nama);
            if ($result['ok']) {
                if (!empty($password)) $auth->updateUser($username, $password, null, null);
                $msg = 'User berhasil diperbarui.';
            } else {
                $err = $result['error'];
            }
        } else {
            $result = $auth->updateUser($username, $password ?: null, $role, $nama);
            if ($result['ok']) $msg = 'User berhasil diperbarui.';
            else $err = $result['error'];
        }
    }

    if ($action === 'delete') {
        $username = $_POST['username'] ?? '';
        if ($username === $_SESSION['admin_username']) {
            $err = 'Tidak bisa menghapus akun sendiri.';
        } else {
            $result = $auth->deleteUser($username);
            if ($result['ok']) $msg = "User \"$username\" berhasil dihapus.";
            else $err = $result['error'];
        }
    }
}

$users = $auth->getUsers();
$roleLabels = ['guru' => 'Guru', 'administrator' => 'Administrator', 'kepala_sekolah' => 'Kepala Sekolah'];
?>

<div class="page-title">
    <span>👥 Kelola User</span>
    <a href="<?= $adminBase ?>index.php" class="btn btn-outline">← Dashboard</a>
</div>

<?php if ($msg): ?><div style="background:#F0FDF4;color:#065F46;border:1px solid #6EE7B7;padding:.875rem 1.25rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:.9rem;">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div style="background:#FEF2F2;color:#991B1B;border:1px solid #FCA5A5;padding:.875rem 1.25rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:.9rem;">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:1.5rem; align-items:start;">

    <!-- Tambah User -->
    <div class="card" style="border-top:4px solid var(--primary);">
        <h3 style="font-size:1rem;margin-bottom:1.25rem;">➕ Tambah User Baru</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="guru">Guru</option>
                    <option value="administrator">Administrator</option>
                    <option value="kepala_sekolah">Kepala Sekolah</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.75rem;">Tambah User</button>
        </form>
    </div>

    <!-- Daftar User -->
    <div class="card">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Daftar User (<?= count($users) ?>)</h3>
        <?php if (empty($users)): ?>
        <div style="text-align:center;padding:2rem;color:var(--text-muted);">Belum ada user.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table" style="font-size:.85rem;">
                <thead><tr>
                    <th>Username</th><th>Nama</th><th>Role</th><th style="text-align:center;">Aksi</th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><code style="background:#F3F4F6;padding:.15rem .4rem;border-radius:.25rem;font-size:.8rem;"><?= htmlspecialchars($u['username']) ?></code></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($u['nama']) ?></td>
                    <td><span class="badge <?= $u['role'] === 'kepala_sekolah' ? 'badge-warning' : ($u['role'] === 'administrator' ? 'badge-success' : 'badge-gray') ?>"><?= htmlspecialchars($roleLabels[$u['role']] ?? $u['role']) ?></span></td>
                    <td style="text-align:center;">
                        <button class="btn btn-outline" style="font-size:.75rem;padding:.3rem .6rem;margin-right:.25rem;"
                            onclick="editUser('<?= addslashes($u['username']) ?>','<?= addslashes($u['nama']) ?>','<?= $u['role'] ?>')">✏️</button>
                        <?php if ($u['username'] !== $_SESSION['admin_username']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus user <?= addslashes($u['username']) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                            <button type="submit" class="btn btn-danger" style="font-size:.75rem;padding:.3rem .6rem;">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;width:90%;max-width:420px;border-radius:var(--radius-md);padding:1.75rem;box-shadow:0 20px 40px rgba(0,0,0,.2);">
        <h3 style="margin-bottom:1.25rem;font-size:1rem;">✏️ Edit User</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="orig_username" id="editOrig">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="editUsername" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" id="editNama" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password <small style="display:inline;color:var(--text-muted);">(kosongkan jika tidak diubah)</small></label>
                <input type="password" name="password" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="editRole" class="form-select">
                    <option value="guru">Guru</option>
                    <option value="administrator">Administrator</option>
                    <option value="kepala_sekolah">Kepala Sekolah</option>
                </select>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeEdit()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:2;">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(username, nama, role) {
    document.getElementById('editOrig').value = username;
    document.getElementById('editUsername').value = username;
    document.getElementById('editNama').value = nama;
    document.getElementById('editRole').value = role;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
