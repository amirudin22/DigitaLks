<?php require __DIR__ . '/layout_top.php'; ?>
<div class="page-title">
    Dashboard
    <div class="page-actions">
        <a href="<?= $adminBase ?>upload.php" class="btn btn-primary">+ Buat Latihan</a>
        <a href="<?= $adminBase ?>prompt_generator_quickstart.php" class="btn btn-secondary" style="background: #667eea;" target="_blank">🤖 Generate Prompt</a>
        <a href="<?= $adminBase ?>settings.php" class="btn btn-outline" style="border-color: var(--warning); color: var(--warning);">⚙️ Settings</a>
    </div>
</div>

<?php
$waContacts = $config['whatsapp']['contacts'] ?? [];
if (empty($waContacts) && !empty($config['whatsapp']['child_number'])) {
    $waContacts[] = ['name' => 'Kirim WA', 'number' => $config['whatsapp']['child_number']];
}

// Shared data
$files = $fileManager->listSoalFiles($adminUser);
usort($files, function($a, $b) {
    $timeA = isset($a['metadata']['created_at']) ? strtotime($a['metadata']['created_at']) : 0;
    $timeB = isset($b['metadata']['created_at']) ? strtotime($b['metadata']['created_at']) : 0;
    return $timeB <=> $timeA;
});

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$itemsPerPage = 10;
$totalItems = count($files);
$totalPages = ceil($totalItems / $itemsPerPage);
if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $itemsPerPage;
$paginatedFiles = array_slice($files, $offset, $itemsPerPage);

$csrfT = htmlspecialchars(csrfToken());

function renderStatus($meta) {
    $status = $meta['status'] ?? 'draft';
    $pin = htmlspecialchars($meta['pin'] ?? '');
    if ($status === 'draft') {
        return '<span class="badge badge-gray">Draft</span>';
    } elseif ($status === 'completed') {
        return '<span class="badge" style="background: var(--primary); color: white;">Sudah Dikerjakan</span>';
    } else {
        $html = '<span class="badge badge-success">Aktif</span>';
        if ($pin) $html .= ' <small>PIN: <strong style="letter-spacing:1px;color:var(--text-main)">' . $pin . '</strong></small>';
        return $html;
    }
}

function renderTimer($meta) {
    $timerVal = $meta['timer_menit'] ?? null;
    if ($timerVal === null || $timerVal === '') return '-';
    if ((int)$timerVal === 0) return '<span style="color:#059669;font-weight:600;">Tanpa Batas</span>';
    return (int)$timerVal . ' Menit';
}

function renderWaButton($meta, $config, $waContacts) {
    $slug = $meta['slug'] ?? '';
    if (empty($waContacts)) return '';
    $quizUrl = $config['app']['url'] . '/s/index.php?slug=' . urlencode($slug);
    $pin = $meta['pin'] ?? '';
    $judul = $meta['judul'] ?? 'Latihan Soal';
    $message = "Halo! Ini link latihan soal untuk kamu:\n\n📚 " . $judul . "\n🔗 " . $quizUrl . "\n🔑 PIN: " . $pin . "\n\nSelamat belajar! 💪";

    if (count($waContacts) === 1) {
        $contact = $waContacts[0];
        $waUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $contact['number']) . '?text=' . urlencode($message);
        return '<a href="' . $waUrl . '" target="_blank" class="btn btn-outline" style="color:#25D366;border-color:#25D366;" title="Kirim ke WhatsApp">📱 ' . htmlspecialchars($contact['name'] ?: 'Kirim WA') . '</a>';
    } else {
        return '<button type="button" class="btn btn-outline" style="color:#25D366;border-color:#25D366;" onclick="openWaModal(this.getAttribute(\'data-msg\'))" data-msg="' . htmlspecialchars($message) . '">📱 WA ▾</button>';
    }
}

function renderWaDropdownItem($meta, $config, $waContacts) {
    $slug = $meta['slug'] ?? '';
    if (empty($waContacts)) return '';
    $quizUrl = $config['app']['url'] . '/s/index.php?slug=' . urlencode($slug);
    $pin = $meta['pin'] ?? '';
    $judul = $meta['judul'] ?? 'Latihan Soal';
    $message = "Halo! Ini link latihan soal untuk kamu:\n\n📚 " . $judul . "\n🔗 " . $quizUrl . "\n🔑 PIN: " . $pin . "\n\nSelamat belajar! 💪";

    if (count($waContacts) === 1) {
        $contact = $waContacts[0];
        $waUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $contact['number']) . '?text=' . urlencode($message);
        return '<a href="' . $waUrl . '" target="_blank" style="color:#25D366;">📱 Kirim WA</a>';
    } else {
        return '<button type="button" style="color:#25D366;" onclick="openWaModal(this.getAttribute(\'data-msg\'))" data-msg="' . htmlspecialchars($message) . '">📱 Kirim WA</button>';
    }
}
?>

<div class="card">
    <h3 style="margin-bottom:1.25rem;font-size:1.125rem;">Daftar Latihan Soal</h3>

    <?php if (empty($paginatedFiles)): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-muted);">Belum ada soal. Silakan buat latihan baru terlebih dahulu.</div>
    <?php else: ?>

    <!-- DESKTOP TABLE -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Judul & Mapel</th>
                    <th>Status</th>
                    <th>Timer</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paginatedFiles as $file):
                    $meta = $file['metadata'];
                    $slug = $meta['slug'] ?? '';
                    $isDraft = ($meta['status'] ?? 'draft') === 'draft';
                    $isCompleted = ($meta['status'] ?? '') === 'completed';
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <strong><?= htmlspecialchars($meta['judul'] ?? 'Tanpa Judul') ?></strong>
                                <button type="button" class="btn btn-outline rename-btn" style="padding:2px 6px;font-size:14px;border:none;background:transparent;" onclick="quickRename('<?= urlencode($file['filename']) ?>', '<?= htmlspecialchars(addslashes($meta['judul'] ?? 'Tanpa Judul')) ?>')" title="Quick Rename">✏️</button>
                            </div>
                            <small style="color:var(--text-muted)"><?= htmlspecialchars($meta['mata_pelajaran'] ?? '') ?> - Kelas <?= htmlspecialchars($meta['kelas_target'] ?? '') ?></small>
                        </td>
                        <td><?= renderStatus($meta) ?></td>
                        <td><?= renderTimer($meta) ?></td>
                        <td><?= isset($meta['created_at']) ? date('d M Y, H:i', strtotime($meta['created_at'])) : '-' ?></td>
                        <td style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                            <?php if ($isDraft): ?>
                                <a href="<?= $adminBase ?>edit.php?id=<?= urlencode($file['filename']) ?>" class="btn btn-primary">Lengkapi</a>
                            <?php elseif ($isCompleted): ?>
                                <a href="<?= $adminBase ?>result.php?id=<?= urlencode($file['filename']) ?>" class="btn" style="background-color:var(--success);color:white;" title="Lihat Nilai">📊 Hasil</a>
                            <?php else: ?>
                                <a href="../s/index.php?slug=<?= urlencode($slug) ?>" target="_blank" class="btn btn-outline" title="Buka Soal">Buka Soal</a>
                                <a href="<?= $adminBase ?>edit.php?id=<?= urlencode($file['filename']) ?>" class="btn btn-outline" title="Edit">✎</a>
                                <?= renderWaButton($meta, $config, $waContacts) ?>
                            <?php endif; ?>
                            <a href="<?= $adminBase ?>clone.php?id=<?= urlencode($file['filename']) ?>&_token=<?= $csrfT ?>" class="btn btn-outline" style="background:#EEF2FF;color:var(--primary);" title="Clone">📋</a>
                            <a href="<?= $adminBase ?>delete.php?id=<?= urlencode($file['filename']) ?>&_token=<?= $csrfT ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus soal ini?')" title="Hapus">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards" style="display:none;">
        <?php foreach ($paginatedFiles as $file):
            $meta = $file['metadata'];
            $slug = $meta['slug'] ?? '';
            $filename = urlencode($file['filename']);
            $isDraft = ($meta['status'] ?? 'draft') === 'draft';
            $isCompleted = ($meta['status'] ?? '') === 'completed';
            $judul = htmlspecialchars(addslashes($meta['judul'] ?? 'Tanpa Judul'));
            ?>
            <div class="quiz-card">
                <div class="quiz-card-header">
                    <div>
                        <div class="quiz-card-title">
                            <?= htmlspecialchars($meta['judul'] ?? 'Tanpa Judul') ?>
                            <button type="button" class="rename-btn" onclick="quickRename('<?= $filename ?>', '<?= $judul ?>')" title="Rename">✏️</button>
                        </div>
                        <div class="quiz-card-mapel"><?= htmlspecialchars($meta['mata_pelajaran'] ?? '-') ?> — Kelas <?= htmlspecialchars($meta['kelas_target'] ?? '') ?></div>
                    </div>
                </div>
                <div class="quiz-card-meta">
                    <?= renderStatus($meta) ?>
                    <span class="meta-item">⏱️ <?= renderTimer($meta) ?></span>
                    <span class="meta-item">📅 <?= isset($meta['created_at']) ? date('d M Y', strtotime($meta['created_at'])) : '-' ?></span>
                </div>
                <div class="quiz-card-actions">
                    <?php if ($isDraft): ?>
                        <a href="<?= $adminBase ?>edit.php?id=<?= $filename ?>" class="btn btn-primary" style="flex:1;justify-content:center;">Lengkapi</a>
                    <?php elseif ($isCompleted): ?>
                        <a href="<?= $adminBase ?>result.php?id=<?= $filename ?>" class="btn" style="background-color:var(--success);color:white;flex:1;justify-content:center;">📊 Lihat Hasil</a>
                    <?php else: ?>
                        <a href="../s/index.php?slug=<?= urlencode($slug) ?>" target="_blank" class="btn btn-outline" style="flex:1;justify-content:center;">Buka Soal</a>
                    <?php endif; ?>

                    <div class="card-more-wrap">
                        <button type="button" class="card-more-btn" onclick="toggleCardMenu(this)" aria-label="Opsi lain">⋮</button>
                        <div class="card-more-menu">
                            <?php if (!$isDraft && !$isCompleted): ?>
                                <a href="<?= $adminBase ?>edit.php?id=<?= $filename ?>">✎ Edit</a>
                                <?= renderWaDropdownItem($meta, $config, $waContacts) ?>
                            <?php endif; ?>
                            <a href="<?= $adminBase ?>clone.php?id=<?= $filename ?>&_token=<?= $csrfT ?>">📋 Clone</a>
                            <button type="button" class="menu-danger" onclick="if(confirm('Hapus soal ini?')) window.location='<?= $adminBase ?>delete.php?id=<?= $filename ?>&_token=<?= $csrfT ?>'">🗑️ Hapus</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php if (isset($totalPages) && $totalPages > 1): ?>
<div class="page-pagination" style="display:flex;justify-content:center;align-items:center;gap:0.5rem;">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">← Prev</a>
    <?php else: ?>
        <span class="btn btn-outline" style="opacity:0.5;cursor:not-allowed;">← Prev</span>
    <?php endif; ?>
    <span style="font-weight:600;padding:0 0.75rem;color:var(--text-main);font-size:0.875rem;">
        Hal <?= $page ?> / <?= $totalPages ?>
    </span>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">Next →</a>
    <?php else: ?>
        <span class="btn btn-outline" style="opacity:0.5;cursor:not-allowed;">Next →</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal Kirim WA -->
<div id="waModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;width:90%;max-width:400px;border-radius:8px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;flex-direction:column;max-height:80vh;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <h3 style="margin:0;font-size:1.125rem;">Pilih Kontak WA</h3>
            <button onclick="closeWaModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
        <input type="text" id="waSearch" placeholder="🔍 Cari nama kontak..." style="width:100%;padding:0.5rem 0.75rem;margin-bottom:10px;border:1px solid var(--border);border-radius:var(--radius-sm);box-sizing:border-box;">
        <div id="waList" style="flex:1;overflow-y:auto;margin-bottom:10px;"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
            <button id="waPrev" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">← Prev</button>
            <span id="waPageInfo" style="font-size:0.75rem;color:var(--text-muted);"></span>
            <button id="waNext" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Next →</button>
        </div>
    </div>
</div>

<script>
const waContacts = <?= json_encode($waContacts) ?>;
let currentWaMessage = "";
let currentWaPage = 1;
const waItemsPerPage = 5;
let filteredContacts = waContacts;

function openWaModal(message) {
    currentWaMessage = message;
    document.getElementById('waModal').style.display = 'flex';
    document.getElementById('waSearch').value = '';
    filteredContacts = waContacts;
    currentWaPage = 1;
    renderWaList();
}
function closeWaModal() {
    document.getElementById('waModal').style.display = 'none';
}
function renderWaList() {
    const listDiv = document.getElementById('waList');
    listDiv.innerHTML = '';
    const maxPage = Math.ceil(filteredContacts.length / waItemsPerPage) || 1;
    if (currentWaPage > maxPage) currentWaPage = maxPage;
    const start = (currentWaPage - 1) * waItemsPerPage;
    const pageContacts = filteredContacts.slice(start, start + waItemsPerPage);

    if (pageContacts.length === 0) {
        listDiv.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:1rem;">Kontak tidak ditemukan</div>';
    }
    pageContacts.forEach(c => {
        const num = c.number.replace(/[^0-9]/g, '');
        listDiv.innerHTML += '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border);"><div><div style="font-weight:600;font-size:0.875rem;">' + c.name + '</div><div style="font-size:0.75rem;color:var(--text-muted);">' + c.number + '</div></div><a href="https://wa.me/' + num + '?text=' + encodeURIComponent(currentWaMessage) + '" target="_blank" class="btn" style="background:#25D366;color:#fff;padding:0.25rem 0.75rem;font-size:0.75rem;text-decoration:none;">Kirim ↗</a></div>';
    });
    document.getElementById('waPageInfo').textContent = 'Hal ' + currentWaPage + ' / ' + maxPage;
    document.getElementById('waPrev').style.opacity = currentWaPage === 1 ? '0.5' : '1';
    document.getElementById('waNext').style.opacity = currentWaPage === maxPage ? '0.5' : '1';
}
document.getElementById('waSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    filteredContacts = waContacts.filter(c => c.name.toLowerCase().includes(term) || c.number.includes(term));
    currentWaPage = 1;
    renderWaList();
});
document.getElementById('waPrev').addEventListener('click', () => { if (currentWaPage > 1) { currentWaPage--; renderWaList(); } });
document.getElementById('waNext').addEventListener('click', () => { const max = Math.ceil(filteredContacts.length / waItemsPerPage); if (currentWaPage < max) { currentWaPage++; renderWaList(); } });

function quickRename(id, currentTitle) {
    var newTitle = prompt("Masukkan nama baru:", currentTitle);
    if (newTitle !== null && newTitle.trim() !== "" && newTitle !== currentTitle) {
        document.getElementById('rename_id').value = id;
        document.getElementById('rename_title').value = newTitle.trim();
        document.getElementById('formQuickRename').submit();
    }
}

function toggleCardMenu(btn) {
    var menu = btn.nextElementSibling;
    var wasOpen = menu.classList.contains('open');
    closeAllCardMenus();
    if (!wasOpen) menu.classList.add('open');
}
function closeAllCardMenus() {
    document.querySelectorAll('.card-more-menu.open').forEach(function(m) { m.classList.remove('open'); });
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.card-more-wrap')) closeAllCardMenus();
});
document.addEventListener('touchstart', function(e) {
    if (!e.target.closest('.card-more-wrap')) closeAllCardMenus();
});
</script>

<form id="formQuickRename" method="post" action="<?= $adminBase ?>rename.php" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="rename_id">
    <input type="hidden" name="new_title" id="rename_title">
</form>

<?php require __DIR__ . '/layout_bottom.php'; ?>
