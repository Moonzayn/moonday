<?php
require_once 'includes/header.php';

$userId = getUserId();

// Overall stats
$totalTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks");
$totalTasks->execute();
$total = $totalTasks->fetchColumn();

$completedTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'completed'");
$completedTasks->execute();
$completed = $completedTasks->fetchColumn();

$completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

// Tasks by status
$statusStmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM tasks
    GROUP BY status
");
$statusStmt->execute();
$statusData = [];
while ($row = $statusStmt->fetch()) {
    $statusData[$row['status']] = $row['count'];
}

// Tasks by priority
$priorityStmt = $pdo->prepare("
    SELECT priority, COUNT(*) as count 
    FROM tasks
    GROUP BY priority
");
$priorityStmt->execute();
$priorityData = [];
while ($row = $priorityStmt->fetch()) {
    $priorityData[$row['priority']] = $row['count'];
}

// Tasks by category
$catStmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, COUNT(t.id) as total,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM categories c
    LEFT JOIN tasks t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY total DESC
");
$catStmt->execute();
$categoryData = $catStmt->fetchAll();

// Tasks completed per day (last 7 days)
$dailyStmt = $pdo->prepare("
    SELECT DATE(completed_at) as date, COUNT(*) as count 
    FROM tasks 
    WHERE status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(completed_at)
    ORDER BY date ASC
");
$dailyStmt->execute();
$dailyData = [];
while ($row = $dailyStmt->fetch()) {
    $dailyData[$row['date']] = $row['count'];
}

// Fill in missing days
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last7Days[$date] = $dailyData[$date] ?? 0;
}
$maxDaily = max(array_values($last7Days) ?: [1]);

// Tasks created per day (last 7 days)
$createdStmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM tasks 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$createdStmt->execute();
$createdData = [];
while ($row = $createdStmt->fetch()) {
    $createdData[$row['date']] = $row['count'];
}

// Average completion time
$avgStmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours
    FROM tasks 
    WHERE status = 'completed' AND completed_at IS NOT NULL
");
$avgStmt->execute();
$avgHours = round($avgStmt->fetchColumn() ?? 0, 1);

// Overdue count
$overdueStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed', 'archived')");
$overdueStmt->execute();
$overdue = $overdueStmt->fetchColumn();

// Productivity score (simple algorithm)
$productivityScore = min(100, round(($completionRate * 0.4) + (($total > 0 ? (1 - $overdue / max($total, 1)) : 1) * 30) + (min($completed, 10) * 3)));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📈 Analitik</h1>
        <p class="page-subtitle">Pantau produktivitas dan progress kamu</p>
    </div>
</div>

<!-- Top Stats -->
<div class="stats-grid">
    <div class="stat-card purple">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= $productivityScore ?></div>
        <div class="stat-label">Skor Produktivitas</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?= $completionRate ?>%</div>
        <div class="stat-label">Tingkat Penyelesaian</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value"><?= $avgHours ?>h</div>
        <div class="stat-label">Rata-rata Waktu Selesai</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">🔥</div>
        <div class="stat-value"><?= $completed ?></div>
        <div class="stat-label">Total Diselesaikan</div>
    </div>
</div>

<div class="grid-2">
    <!-- Completion Chart (Last 7 Days) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📅 Tugas Selesai (7 Hari Terakhir)</h2>
        </div>
        <div class="card-body">
            <div class="chart-bar-container">
                <?php foreach ($last7Days as $date => $count): ?>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar-value"><?= $count ?></div>
                    <div class="chart-bar" style="
                        height: <?= $maxDaily > 0 ? ($count / $maxDaily * 200) : 4 ?>px;
                        background: linear-gradient(180deg, #6366f1, #8b5cf6);
                    "></div>
                    <div class="chart-bar-label"><?= date('D', strtotime($date)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Status Distribution -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📊 Distribusi Status</h2>
        </div>
        <div class="card-body">
            <?php
            $statusColors = ['todo' => '#94a3b8', 'in_progress' => '#3b82f6', 'completed' => '#10b981', 'archived' => '#6b7280'];
            $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
            $statusIcons = ['todo' => '📝', 'in_progress' => '🔄', 'completed' => '✅', 'archived' => '📦'];
            ?>
            <?php foreach (['todo', 'in_progress', 'completed', 'archived'] as $status): ?>
            <?php $count = $statusData[$status] ?? 0; $percent = $total > 0 ? round(($count / $total) * 100) : 0; ?>
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 13px; font-weight: 600; color: var(--gray-700);">
                        <?= $statusIcons[$status] ?> <?= $statusLabels[$status] ?>
                    </span>
                    <span style="font-size: 13px; color: var(--gray-500);"><?= $count ?> (<?= $percent ?>%)</span>
                </div>
                <div style="height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?= $percent ?>%; background: <?= $statusColors[$status] ?>; border-radius: 4px; transition: width 1s ease;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 24px;">
    <!-- Priority Distribution -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🎯 Distribusi Prioritas</h2>
        </div>
        <div class="card-body">
            <?php
            $priorityColors = ['low' => '#10b981', 'medium' => '#3b82f6', 'high' => '#f59e0b', 'urgent' => '#ef4444'];
            $priorityLabels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Urgent'];
            $priorityIcons = ['low' => '🟢', 'medium' => '🔵', 'high' => '🟡', 'urgent' => '🔴'];
            ?>
            <div class="chart-bar-container" style="height: 200px;">
                <?php
                $maxPriority = max(array_values($priorityData) ?: [1]);
                foreach (['low', 'medium', 'high', 'urgent'] as $p): 
                    $count = $priorityData[$p] ?? 0;
                ?>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar-value"><?= $count ?></div>
                    <div class="chart-bar" style="
                        height: <?= $maxPriority > 0 ? ($count / $maxPriority * 160) : 4 ?>px;
                        background: <?= $priorityColors[$p] ?>;
                    "></div>
                    <div class="chart-bar-label"><?= $priorityIcons[$p] ?> <?= $priorityLabels[$p] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Category Performance -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏷️ Performa Kategori</h2>
        </div>
        <div class="card-body">
            <?php if (empty($categoryData)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 20px;">Belum ada data</p>
            <?php else: ?>
                <?php foreach ($categoryData as $cat): ?>
                <?php $catPercent = $cat['total'] > 0 ? round(($cat['completed'] / $cat['total']) * 100) : 0; ?>
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 13px; font-weight: 600; color: var(--gray-700);">
                            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                        </span>
                        <span style="font-size: 13px; color: var(--gray-500);">
                            <?= $cat['completed'] ?>/<?= $cat['total'] ?> (<?= $catPercent ?>%)
                        </span>
                    </div>
                    <div style="height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $catPercent ?>%; background: <?= $cat['color'] ?>; border-radius: 4px; transition: width 1s ease;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Summary Card -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">📋 Ringkasan</h2>
    </div>
    <div class="card-body">
        <div class="stats-grid">
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 40px; margin-bottom: 8px;">📋</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--gray-900);"><?= $total ?></div>
                <div style="font-size: 13px; color: var(--gray-500);">Total Tugas</div>
            </div>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 40px; margin-bottom: 8px;">✅</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--success);"><?= $completed ?></div>
                <div style="font-size: 13px; color: var(--gray-500);">Diselesaikan</div>
            </div>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 40px; margin-bottom: 8px;">⚠️</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--danger);"><?= $overdue ?></div>
                <div style="font-size: 13px; color: var(--gray-500);">Terlambat</div>
            </div>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 40px; margin-bottom: 8px;">⏱️</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--info);"><?= $avgHours ?>h</div>
                <div style="font-size: 13px; color: var(--gray-500);">Rata-rata Waktu</div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>