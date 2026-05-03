<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$todoCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'todo'");
$todoCount->execute();
$todoBadge = $todoCount->fetchColumn();

$inProgressCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'");
$inProgressCount->execute();
$inProgressBadge = $inProgressCount->fetchColumn();

$overdueCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed', 'archived')");
$overdueCount->execute();
$overdueBadge = $overdueCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moonday - Manajemen Tugas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <div class="logo-box">✦</div>
                <span>Moonday</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Menu</div>
                <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="tasks.php" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span> Semua Tugas
                    <?php if($todoBadge > 0): ?><span class="badge"><?= $todoBadge ?></span><?php endif; ?>
                </a>
                <a href="add_task.php" class="nav-item <?= $currentPage === 'add_task' ? 'active' : '' ?>">
                    <span class="nav-icon">➕</span> Tugas Baru
                </a>
                <a href="tasks.php?status=in_progress" class="nav-item <?= ($currentPage === 'tasks' && ($_GET['status'] ?? '') === 'in_progress') ? 'active' : '' ?>">
                    <span class="nav-icon">🔄</span> Sedang Dikerjakan
                    <?php if($inProgressBadge > 0): ?><span class="badge" style="background: var(--info)"><?= $inProgressBadge ?></span><?php endif; ?>
                </a>
                <a href="tasks.php?filter=overdue" class="nav-item <?= ($currentPage === 'tasks' && ($_GET['filter'] ?? '') === 'overdue') ? 'active' : '' ?>">
                    <span class="nav-icon">⚠️</span> Terlambat
                    <?php if($overdueBadge > 0): ?><span class="badge" style="background: var(--danger)"><?= $overdueBadge ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Kelola</div>
                <a href="categories.php" class="nav-item <?= $currentPage === 'categories' ? 'active' : '' ?>">
                    <span class="nav-icon">🏷️</span> Kategori
                </a>
                <a href="analytics.php" class="nav-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                    <span class="nav-icon">📈</span> Analitik
                </a>
                <a href="ai_chat.php" class="nav-item <?= $currentPage === 'ai_chat' ? 'active' : '' ?>">
                    <span class="nav-icon">🤖</span> AI Assistant
                    <span class="badge" style="background: linear-gradient(135deg, #8b5cf6, #6366f1);">AI</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Akun</div>
                <a href="profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span> Profil
                </a>
                <a href="email_settings.php" class="nav-item <?= $currentPage === 'email_settings' ? 'active' : '' ?>">
                    <span class="nav-icon">📧</span> Email
                </a>
                <a href="logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Keluar
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="profile.php" class="user-card">
                <div class="avatar" style="background: <?= getAvatarColor() ?>">
                    <?= getInitials(getUserName()) ?>
                </div>
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars(getUserName()) ?></div>
                    <div class="role"><?= htmlspecialchars(getUserEmail()) ?></div>
                </div>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <button class="mobile-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>