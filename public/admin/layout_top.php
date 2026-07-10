<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$adminUser = $_SESSION['admin_username'] ?? 'admin';

// Backward compat: existing sessions tanpa role dianggap administrator
if (!isset($_SESSION['admin_role'])) {
    $_SESSION['admin_role'] = 'administrator';
    $_SESSION['admin_nama'] = $adminUser;
}
$adminRole = $_SESSION['admin_role'];

require_once __DIR__ . '/../../src/Core/FileManager.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/Database.php';

$fileManager = new \Core\FileManager($config['storage']['path']);
$security    = new \Core\Security($fileManager);
$db          = \Core\Database::getInstance($config['database'] ?? []);
$fileManager->setDatabase($db);

// ── Per-guru WhatsApp contacts ──
$storageDir = rtrim($config['storage']['path'], '/');
$waPath = $storageDir . '/accounts/' . $adminUser . '/whatsapp.json';
if (file_exists($waPath)) {
    $waData = json_decode(file_get_contents($waPath), true);
    if (is_array($waData) && isset($waData['contacts'])) {
        $config['whatsapp']['contacts'] = $waData['contacts'];
    }
}

$currentURL = basename($_SERVER['PHP_SELF']);
// Base path admin — aman dari root redirect maupun direct access
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// ── CSRF Token (init before validation) ──
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken(): string {
    return $_SESSION['_csrf_token'];
}
function csrfField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($_SESSION['_csrf_token']) . '">';
}
function validateCsrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['_csrf_token'] ?? '';
    return hash_equals($_SESSION['_csrf_token'], $token);
}

// ── Flash Messages ──
$flashMsg = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
function setFlash(string $msg, string $type = 'success'): void {
    $_SESSION['_flash'] = ['msg' => $msg, 'type' => $type];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigitLks</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>window.MathJax = { tex: { inlineMath: [['\\(', '\\)']] } };</script>
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --bg: #F3F4F6;
            --surface: #FFFFFF;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --danger: #EF4444;
            --success: #10B981;
            --warning: #F59E0B;
            --radius-md: 0.75rem;
            --radius-sm: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* ── Hamburger toggle ── */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 1001;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.5rem 0.65rem;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            line-height: 1;
        }

        /* ── Sidebar ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.3);
            z-index: 998;
        }

        .sidebar {
            width: 250px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 999;
            transform: translateX(-100%);
            transition: transform 0.25s ease;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            padding-left: 0.5rem;
        }

        .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }
        .nav-link:hover { background-color: var(--bg); color: var(--text-main); }
        .nav-link.active { background-color: #EEF2FF; color: var(--primary); }

        .user-info {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        /* ── Main Content ── */
        .content {
            padding: 2rem 3rem;
            min-height: 100vh;
        }

        /* ── Components ── */
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-outline { background-color: transparent; border-color: var(--border); color: var(--text-main); }
        .btn-outline:hover { background-color: var(--bg); }
        .btn-danger { background-color: #FEF2F2; color: var(--danger); border-color: #FCA5A5; }
        .btn-danger:hover { background-color: #FEE2E2; }
        .btn-secondary { background: #64748b; color: #fff; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: inherit;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td {
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
        }
        .table td { font-size: 0.875rem; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-gray { background: #F3F4F6; color: #374151; }

        /* ── Mobile: hide table, show quiz cards ── */
        @media (max-width: 767px) {
            .menu-toggle { display: block; }
            .sidebar-overlay.active { display: block; }
            body.sidebar-open { overflow: hidden; }

            .content {
                padding: 1rem;
                padding-top: 3.5rem;
            }

            .page-title {
                font-size: 1.2rem;
                flex-direction: column;
                align-items: flex-start;
            }
            .page-title .page-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
                width: 100%;
            }
            .page-title .page-actions .btn {
                flex: 1;
                min-width: 0;
                justify-content: center;
                font-size: 0.75rem;
                padding: 0.5rem 0.5rem;
            }

            .card { padding: 1rem; }

            .table-wrap { display: none; }
            .mobile-cards { display: flex !important; flex-direction: column; gap: 0.75rem; }

            .quiz-card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-md);
                padding: 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.625rem;
            }
            .quiz-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .quiz-card-title {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--text-main);
                flex: 1;
                min-width: 0;
                word-break: break-word;
            }
            .quiz-card-title .rename-btn {
                font-size: 0.8rem;
                background: none;
                border: none;
                cursor: pointer;
                padding: 2px 4px;
                line-height: 1;
            }
            .quiz-card-mapel {
                font-size: 0.8rem;
                color: var(--text-muted);
            }
            .quiz-card-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                align-items: center;
            }
            .quiz-card-meta .meta-item {
                font-size: 0.78rem;
                color: var(--text-muted);
                display: inline-flex;
                align-items: center;
                gap: 3px;
            }
            .quiz-card-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .quiz-card-actions .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.85rem;
            }
            .quiz-card-actions .btn-primary,
            .quiz-card-actions .btn[style*="success"] {
                flex: 1;
                justify-content: center;
            }

            .card-more-wrap { position: relative; }
            .card-more-btn {
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: var(--radius-sm);
                border: 1px solid var(--border);
                background: var(--surface);
                cursor: pointer;
                font-size: 1.1rem;
                color: var(--text-muted);
                flex-shrink: 0;
            }
            .card-more-btn:active { background: var(--bg); }
            .card-more-menu {
                display: none;
                position: absolute;
                right: 0;
                bottom: calc(100% + 6px);
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                box-shadow: var(--shadow);
                z-index: 50;
                min-width: 160px;
                overflow: hidden;
            }
            .card-more-menu.open { display: block; }
            .card-more-menu a,
            .card-more-menu button {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                width: 100%;
                padding: 0.6rem 0.85rem;
                font-size: 0.82rem;
                font-family: inherit;
                color: var(--text-main);
                background: none;
                border: none;
                text-decoration: none;
                cursor: pointer;
                text-align: left;
            }
            .card-more-menu a:hover,
            .card-more-menu button:hover {
                background: var(--bg);
            }
            .card-more-menu .menu-danger {
                color: var(--danger);
                border-top: 1px solid var(--border);
            }

            .page-pagination { margin-top: 1rem; margin-bottom: 1.5rem; }
        }

        /* ── Desktop: hide mobile cards ── */
        @media (min-width: 768px) {
            .mobile-cards { display: none !important; }
        }

        @media (min-width: 768px) {
            body { display: flex; }
            .sidebar {
                position: static;
                transform: none;
                transition: none;
            }
            .content { flex-grow: 1; }
        }

        .topic-chip {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: .8rem;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            cursor: pointer;
            user-select: none;
            transition: all .15s ease;
        }
        .topic-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .topic-chip.selected {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
    </style>
</head>
<body>

    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php if (empty($hideSidebar)): ?>
    <aside class="sidebar" id="sidebar">
        <div class="brand">DigitLKS</div>
        <nav>
            <a href="<?= $adminBase ?>index.php" class="nav-link <?= $currentURL == 'index.php' ? 'active' : '' ?>">📋 Dashboard</a>
            <a href="<?= $adminBase ?>upload.php" class="nav-link <?= $currentURL == 'upload.php' ? 'active' : '' ?>">➕ Buat Soal Baru</a>
            <a href="<?= $adminBase ?>laporan.php" class="nav-link <?= $currentURL == 'laporan.php' ? 'active' : '' ?>">📊 Laporan Siswa</a>
            <a href="<?= $adminBase ?>siswa.php" class="nav-link <?= $currentURL == 'siswa.php' ? 'active' : '' ?>">👨‍🎓 Data Siswa</a>
            <a href="<?= $adminBase ?>ai_generator.php" class="nav-link <?= $currentURL == 'ai_generator.php' ? 'active' : '' ?>">🤖 AI Generator Soal</a>
            <a href="<?= $adminBase ?>prompt_generator_quickstart.php" class="nav-link <?= $currentURL == 'prompt_generator_quickstart.php' || $currentURL == 'prompt_generator.php' ? 'active' : '' ?>" target="_blank">📝 Prompt Manual (AI)</a>
            <a href="<?= $adminBase ?>settings.php" class="nav-link <?= $currentURL == 'settings.php' ? 'active' : '' ?>">⚙️ Pengaturan</a>
            <?php if (\Core\Auth::can($adminRole, 'kepala_sekolah')): ?>
            <a href="<?= $adminBase ?>users.php" class="nav-link <?= $currentURL == 'users.php' ? 'active' : '' ?>">👥 Kelola User</a>
            <?php endif; ?>
            <a href="<?= $adminBase ?>logout.php" class="nav-link" style="color: var(--danger);">🚪 Logout</a>
        </nav>
        <div class="user-info">
            <div style="margin-bottom:0.25rem"><?= htmlspecialchars($_SESSION['admin_nama'] ?? $adminUser) ?></div>
            <div style="font-size:0.75rem; opacity:0.7">@<?= htmlspecialchars($adminUser) ?> — <span style="text-transform:capitalize"><?= htmlspecialchars($adminRole) ?></span></div>
        </div>
    </aside>
    <?php endif; ?>

    <main class="content">

    <?php if ($flashMsg): ?>
    <div style="padding:0.875rem 1.25rem; border-radius:var(--radius-sm); margin-bottom:1.25rem; font-size:0.9rem;
        <?= $flashMsg['type'] === 'success' ? 'background:#F0FDF4;color:#065F46;border:1px solid #6EE7B7;' : 'background:#FEF2F2;color:#991B1B;border:1px solid #FCA5A5;' ?>">
        <?= htmlspecialchars($flashMsg['msg']) ?>
    </div>
    <?php endif; ?>

    <script>
    (function() {
        var toggle = document.getElementById('menuToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', closeSidebar);

        // Tutup sidebar setelah klik nav-link (mobile)
        sidebar.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });
    })();
    </script>
