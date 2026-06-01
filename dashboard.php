<?php
require_once 'includes/header.php';

$userId = getUserId();

// Stats
$stats = [];
$queries = [
    'total' => "SELECT COUNT(*) FROM tasks",
    'todo' => "SELECT COUNT(*) FROM tasks WHERE status = 'todo'",
    'in_progress' => "SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'",
    'completed' => "SELECT COUNT(*) FROM tasks WHERE status = 'completed'",
    'overdue' => "SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed', 'archived')"
];

foreach ($queries as $key => $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stats[$key] = $stmt->fetchColumn();
}

$completionRate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100) : 0;

// Today's tasks
$todayStmt = $pdo->prepare("
    SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon 
    FROM tasks t 
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN categories c ON t.category_id = c.id 
    WHERE t.due_date = CURDATE() AND t.status != 'archived'
    ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at DESC
");
$todayStmt->execute();
$todayTasks = $todayStmt->fetchAll();

// Upcoming tasks (next 7 days)
$upcomingStmt = $pdo->prepare("
    SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon 
    FROM tasks t 
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN categories c ON t.category_id = c.id 
    WHERE t.due_date > CURDATE() AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status != 'archived'
    ORDER BY t.due_date ASC, FIELD(t.priority, 'urgent', 'high', 'medium', 'low')
    LIMIT 5
");
$upcomingStmt->execute();
$upcomingTasks = $upcomingStmt->fetchAll();

// Recent activity
$activityStmt = $pdo->prepare("
    SELECT al.*, t.title as task_title, u.full_name as user_name
    FROM activity_log al 
    LEFT JOIN tasks t ON al.task_id = t.id 
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC 
    LIMIT 8
");
$activityStmt->execute();
$activities = $activityStmt->fetchAll();

// Tasks by category
$catStatsStmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, COUNT(t.id) as task_count,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM categories c
    LEFT JOIN tasks t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY task_count DESC
");
$catStatsStmt->execute();
$catStats = $catStatsStmt->fetchAll();
?>

<!-- Dashboard Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Selamat datang, <?= htmlspecialchars(explode(' ', getUserName())[0]) ?>! 👋</h1>
        <p class="page-subtitle"><?= date('l, d F Y') ?></p>
    </div>
    <a href="add_task.php" class="btn btn-primary">
        ➕ Tugas Baru
    </a>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card purple">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Tugas</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🔄</div>
        <div class="stat-value"><?= $stats['in_progress'] ?></div>
        <div class="stat-label">Sedang Dikerjakan</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $stats['completed'] ?></div>
        <div class="stat-label">Selesai</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= $stats['overdue'] ?></div>
        <div class="stat-label">Terlambat</div>
    </div>
</div>

<div class="grid-2">
    <!-- Today's Tasks -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📅 Tugas Hari Ini</h2>
            <span style="font-size: 13px; color: var(--gray-500)"><?= count($todayTasks) ?> tugas</span>
        </div>
        <div class="card-body" style="padding: 16px;">
            <?php if (empty($todayTasks)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <div class="empty-icon">🎉</div>
                    <h3>Tidak ada tugas hari ini</h3>
                    <p>Nikmati harimu atau <a href="add_task.php" class="link">tambah tugas baru</a></p>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($todayTasks as $task): ?>
                    <div class="task-item <?= $task['status'] === 'completed' ? 'completed' : '' ?>">
                        <a href="update_status.php?id=<?= $task['id'] ?>&status=<?= $task['status'] === 'completed' ? 'todo' : 'completed' ?>&redirect=dashboard" 
                           class="task-checkbox <?= $task['status'] === 'completed' ? 'checked' : '' ?>"
                           data-tooltip="<?= $task['status'] === 'completed' ? 'Tandai belum selesai' : 'Tandai selesai' ?>">
                        </a>
                        <div class="task-content">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div style="font-size: 11px; color: var(--gray-400);">oleh <?= htmlspecialchars($task['owner_name'] ?? $task['owner_username'] ?? 'Unknown') ?></div>
                            <div class="task-meta">
                                <span class="priority-badge priority-<?= $task['priority'] ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                                <?php if ($task['category_name']): ?>
                                <span class="category-tag">
                                    <?= $task['category_icon'] ?> <?= htmlspecialchars($task['category_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-actions">
                            <a href="edit_task.php?id=<?= $task['id'] ?>" class="btn btn-ghost btn-icon" data-tooltip="Edit">✏️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Completion Progress -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h2 class="card-title">🎯 Progress Keseluruhan</h2>
            </div>
            <div class="card-body">
                <div class="progress-ring-container">
                    <div class="progress-ring">
                        <svg width="180" height="180">
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                            <circle cx="90" cy="90" r="75" fill="none" stroke="url(#gradient)" stroke-width="12" 
                                    stroke-dasharray="<?= 2 * M_PI * 75 ?>" 
                                    stroke-dashoffset="<?= 2 * M_PI * 75 * (1 - $completionRate / 100) ?>"
                                    stroke-linecap="round"
                                    style="transition: stroke-dashoffset 1s ease;"/>
                            <defs>
                                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#6366f1"/>
                                    <stop offset="100%" style="stop-color:#8b5cf6"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="progress-text">
                            <span class="progress-value"><?= $completionRate ?>%</span>
                            <span class="progress-label">Selesai</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Overview -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h2 class="card-title">🏷️ Kategori</h2>
                <a href="categories.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body" style="padding: 16px;">
                <?php foreach ($catStats as $cat): ?>
                <div class="category-item" style="margin-bottom: 8px;">
                    <div class="category-color" style="background: <?= $cat['color'] ?>"></div>
                    <span style="font-size: 16px;"><?= $cat['icon'] ?></span>
                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="category-count"><?= $cat['completed_count'] ?>/<?= $cat['task_count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Tasks & Activity -->
<div class="grid-2" style="margin-top: 24px;">
    <!-- Upcoming -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📆 Tugas Mendatang</h2>
            <a href="tasks.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body" style="padding: 16px;">
            <?php if (empty($upcomingTasks)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <p>Belum ada tugas mendatang</p>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($upcomingTasks as $task): ?>
                    <div class="task-item">
                        <div class="task-content">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div style="font-size: 11px; color: var(--gray-400);">oleh <?= htmlspecialchars($task['owner_name'] ?? $task['owner_username'] ?? 'Unknown') ?></div>
                            <div class="task-meta">
                                <span class="task-meta-item">📅 <?= date('d M', strtotime($task['due_date'])) ?></span>
                                <span class="priority-badge priority-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                                <?php if ($task['category_name']): ?>
                                <span class="category-tag"><?= $task['category_icon'] ?> <?= htmlspecialchars($task['category_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🕐 Aktivitas Terbaru</h2>
        </div>
        <div class="card-body">
            <div class="activity-feed">
                <?php if (empty($activities)): ?>
                    <p style="color: var(--gray-500); text-align: center; padding: 20px;">Belum ada aktivitas</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-dot <?= $activity['action'] ?>"></div>
                        <div>
                            <div class="activity-text">
                                <strong><?= htmlspecialchars($activity['user_name'] ?? 'User') ?></strong> <?= htmlspecialchars($activity['description']) ?>
                                <?php if ($activity['task_title']): ?>
                                    - <strong><?= htmlspecialchars($activity['task_title']) ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time"><?= timeAgo($activity['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>