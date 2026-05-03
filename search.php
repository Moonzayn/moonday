<?php
require_once 'includes/header.php';

$userId = getUserId();
$query = trim($_GET['q'] ?? '');

$tasks = [];
if (!empty($query)) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon 
        FROM tasks t 
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN categories c ON t.category_id = c.id 
        WHERE (t.title LIKE ? OR t.description LIKE ?)
        ORDER BY t.created_at DESC
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    $tasks = $stmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔍 Pencarian</h1>
        <p class="page-subtitle">
            <?php if ($query): ?>
                Hasil pencarian untuk "<strong><?= htmlspecialchars($query) ?></strong>" - <?= count($tasks) ?> ditemukan
            <?php else: ?>
                Cari tugas berdasarkan judul atau deskripsi
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET">
            <div class="search-box" style="max-width: 100%;">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Ketik untuk mencari..." value="<?= htmlspecialchars($query) ?>" autofocus style="font-size: 16px; padding: 14px 16px 14px 44px;">
            </div>
        </form>
    </div>
</div>

<?php if (!empty($query)): ?>
    <?php if (empty($tasks)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>Tidak ada hasil</h3>
                <p>Coba kata kunci yang berbeda</p>
            </div>
        </div>
    <?php else: ?>
        <div class="task-list">
            <?php foreach ($tasks as $task): ?>
            <div class="task-item <?= $task['status'] === 'completed' ? 'completed' : '' ?>">
                <div class="task-content">
                    <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                    <div style="font-size: 12px; color: var(--gray-400); margin-bottom: 4px;">oleh <?= htmlspecialchars($task['owner_name'] ?? $task['owner_username'] ?? 'Unknown') ?></div>
                    <?php if ($task['description']): ?>
                    <p style="font-size: 13px; color: var(--gray-500); margin: 4px 0;"><?= htmlspecialchars(substr($task['description'], 0, 120)) ?></p>
                    <?php endif; ?>
                    <div class="task-meta">
                        <span class="priority-badge priority-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                        <span class="status-badge status-<?= $task['status'] ?>">
                            <?php
                            $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
                            echo $statusLabels[$task['status']];
                            ?>
                        </span>
                        <?php if ($task['category_name']): ?>
                        <span class="category-tag"><?= $task['category_icon'] ?> <?= htmlspecialchars($task['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="task-actions">
                    <a href="edit_task.php?id=<?= $task['id'] ?>" class="btn btn-ghost btn-icon">✏️</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>