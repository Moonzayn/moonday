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

// Fetch holidays for current month (server-side, no CORS)
$holidays = [];
$holidayApiUrl = "https://api-hari-libur.vercel.app/api?year=" . date('Y') . "&month=" . date('n');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $holidayApiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$holidayResponse = curl_exec($ch);
$holidayHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($holidayHttpCode === 200 && $holidayResponse) {
    $raw = json_decode($holidayResponse, true) ?: [];
    $holidays = [];
    if (isset($raw['data'])) {
        foreach ($raw['data'] as $item) {
            $holidays[] = [
                'tanggal' => $item['date'],
                'keterangan' => $item['description'],
                'is_cuti' => false,
            ];
        }
    }
}
$holidayCount = count($holidays);
$todayStr = date('Y-m-d');
$upcomingHolidays = array_filter($holidays, function($h) use ($todayStr) {
    return $h['tanggal'] >= $todayStr;
});
usort($upcomingHolidays, function($a, $b) { return strcmp($a['tanggal'], $b['tanggal']); });
$upcomingHolidays = array_slice($upcomingHolidays, 0, 5);
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
                <a href="calendar.php" class="nav-item <?= $currentPage === 'calendar' ? 'active' : '' ?>">
                    <span class="nav-icon">📅</span> Kalender
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

<?php if ($holidayCount > 0 && !empty($upcomingHolidays)): ?>
<div class="holiday-alert" id="holidayAlert">
    <div class="holiday-alert-content">
        <span class="holiday-alert-icon">🎉</span>
        <div class="holiday-alert-text">
            <strong><?= $holidayCount ?> tanggal merah</strong> bulan ini —
            <?php foreach ($upcomingHolidays as $i => $h):
                $d = new DateTime($h['tanggal']);
                $dayName = $d->format('l');
                $dayNames = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
                $dayLabel = $dayNames[$dayName] ?? $dayName;
                $dateParts = explode('-', $h['tanggal']);
                $formatted = (int)$dateParts[2] . ' ' . ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int)$dateParts[1] - 1];
            ?>
                <span class="holiday-chip"><?= $dayLabel ?>, <?= $formatted ?> — <?= htmlspecialchars($h['keterangan']) ?></span>
                <?php if ($i < count($upcomingHolidays) - 1): ?><span class="holiday-sep">•</span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <button class="holiday-alert-close" onclick="dismissHolidayAlert()" title="Tutup">✕</button>
</div>
<style>
.holiday-alert{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;background:linear-gradient(135deg,#fef2f2,#fff1f2);border:1px solid #fecaca;border-radius:var(--radius);margin-bottom:24px;animation:slideDown .35s ease}
.holiday-alert-content{display:flex;align-items:center;gap:12px;flex:1;min-width:0}
.holiday-alert-icon{font-size:20px;flex-shrink:0}
.holiday-alert-text{font-size:13px;color:#991b1b;line-height:1.6;display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.holiday-alert-text strong{color:#7f1d1d}
.holiday-chip{display:inline-block;padding:2px 8px;background:white;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:500;color:#991b1b;white-space:nowrap}
.holiday-sep{color:#fca5a5;font-size:10px;margin:0 2px}
.holiday-alert-close{width:28px;height:28px;border:none;background:rgba(0,0,0,.05);border-radius:50%;cursor:pointer;font-size:12px;color:#991b1b;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition)}
.holiday-alert-close:hover{background:rgba(0,0,0,.1)}
@media(max-width:768px){.holiday-alert-text{font-size:12px}.holiday-chip{white-space:normal}}
</style>
<?php endif; ?>