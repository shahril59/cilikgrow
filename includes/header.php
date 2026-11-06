<?php
/**
 * Shared layout - top part (sidebar + top bar).
 * Set $page_title and $active before including. Pages must call require_login()/require_roles() first.
 */
require_once __DIR__ . '/auth.php';

$role         = $_SESSION['role'] ?? '';
$display_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User');
$page_title   = $page_title ?? 'CilikGrow';
$active       = $active ?? '';
$staff        = ['Management', 'Teacher'];

$menu = [
    ['dashboard',     'dashboard.php',     'bi-speedometer2',          'Dashboard',           ['Management', 'Teacher', 'Parent']],
    ['students',      'students.php',      'bi-people-fill',           'Student Profiles',    $staff],
    ['evaluations',   'evaluations.php',   'bi-journal-check',         'Progress Evaluations', $staff],
    ['attendance',    'attendance.php',    'bi-calendar-check-fill',   'Attendance Tracking', $staff],
    ['report',        'report.php',        'bi-file-earmark-pdf-fill', $role === 'Parent' ? 'Child Progress Report' : 'Student Reports', ['Management', 'Teacher', 'Parent']],
    ['class_summary', 'class_summary.php', 'bi-bar-chart-fill',        'Class Summary',       $staff],
    ['my_attendance', 'my_attendance.php', 'bi-calendar2-week-fill',   'Child Attendance',    ['Parent']],
    ['users',         'users.php',         'bi-person-gear',           'User Management',     ['Management']],
    ['change_password', 'change_password.php', 'bi-key-fill',          'Change Password',     ['Management', 'Teacher', 'Parent']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CilikGrow - <?= e($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { --bs-offcanvas-width: 260px; }
        .sidebar-inner { background: #0d6efd; color: #fff; padding: 1rem; overflow-y: auto; }
        .sidebar .nav-link-item { color: rgba(255,255,255,.85); text-decoration: none; padding: 11px 16px; display: block; border-radius: 6px; margin: 3px 0; transition: all .2s; }
        .sidebar .nav-link-item:hover, .sidebar .nav-link-item.active { background: rgba(255,255,255,.18); color: #fff; font-weight: 600; }
        .topbar { background: #fff; padding: 12px 24px; box-shadow: 0 2px 4px rgba(0,0,0,.04); }
        .main-content { padding: 24px; }
        .stat-card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,.03); transition: transform .2s; }
        .stat-card:hover { transform: translateY(-3px); }
        @media (min-width: 992px) {
            .sidebar { position: fixed !important; top: 0; left: 0; bottom: 0; width: 260px !important; height: 100vh !important; z-index: 100; }
            .main-wrap { margin-left: 260px; }
        }
        @media print {
            .sidebar, .topbar, .no-print { display: none !important; }
            .main-wrap { margin-left: 0 !important; }
            .main-content { padding: 0; }
            body { background: #fff; }
        }
    </style>
</head>
<body>

<aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebarMenu" aria-label="Main menu">
    <div class="sidebar-inner d-flex flex-column h-100">
        <div class="d-flex align-items-center justify-content-between px-2 py-2 mb-3">
            <div class="d-flex align-items-center">
                <img src="img/logo.png" alt="CilikGrow logo" style="width:35px;height:35px;object-fit:contain;" class="me-2" onerror="this.style.display='none'">
                <h4 class="fw-bold text-white mb-0">CilikGrow</h4>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>
        <nav class="flex-column mb-auto">
            <?php foreach ($menu as [$key, $href, $icon, $label, $roles]): ?>
                <?php if (in_array($role, $roles, true)): ?>
                    <a href="<?= e($href); ?>" class="nav-link-item <?= $active === $key ? 'active' : ''; ?>">
                        <i class="bi <?= e($icon); ?> me-2"></i><?= e($label); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <hr class="text-white-50">
        <div class="px-2 pb-1 text-white-50 small">
            <div>Logged in as:</div>
            <div class="fw-bold text-white"><?= e($display_name); ?></div>
            <span class="badge bg-light text-primary mt-1"><?= e($role); ?></span>
        </div>
    </div>
</aside>

<div class="main-wrap">
    <div class="topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Open menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h5 class="fw-bold mb-0 text-dark"><?= e($page_title); ?></h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border p-2 d-none d-md-inline"><i class="bi bi-calendar-event me-1"></i> <?= date('l, d F Y'); ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm fw-semibold"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
    </div>
    <main class="main-content">
        <?= flash_render(); ?>
