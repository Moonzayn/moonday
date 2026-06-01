<?php
require_once 'includes/header.php';

$userId = getUserId();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';
        $icon = trim($_POST['icon'] ?? '📁');

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, color, icon) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $color, $icon]);
            $message = 'Kategori berhasil ditambahkan!';
        } else {
            $error = 'Nama kategori wajib diisi.';
        }
    }

    if (isset($_POST['delete_category'])) {
        $catId = $_POST['category_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$catId, $userId]);
        $message = 'Kategori berhasil dihapus!';
    }
}

// Get categories with task counts
$catStmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM categories c
    LEFT JOIN tasks t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$catStmt->execute();
$categories = $catStmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🏷️ Kategori</h1>
        <p class="page-subtitle">Kelola kategori tugas kamu</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success">✅ <?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Add Category -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">➕ Tambah Kategori</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="name" class="form-control" placeholder="Nama kategori" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Warna</label>
                        <input type="color" name="color" class="form-control" value="#6366f1" style="height: 46px; padding: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Ikon (Emoji)</label>
                        <input type="text" name="icon" class="form-control" value="📁" maxlength="4">
                    </div>
                </div>
                <button type="submit" name="add_category" class="btn btn-primary">
                    ➕ Tambah Kategori
                </button>
            </form>
        </div>
    </div>

    <!-- Category List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Daftar Kategori</h2>
            <span style="font-size: 13px; color: var(--gray-500);"><?= count($categories) ?> kategori</span>
        </div>
        <div class="card-body" style="padding: 16px;">
            <?php if (empty($categories)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <div class="empty-icon">🏷️</div>
                    <h3>Belum ada kategori</h3>
                    <p>Buat kategori pertama kamu</p>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <div class="category-item" style="margin-bottom: 8px;">
                    <div class="category-color" style="background: <?= $cat['color'] ?>; width: 16px; height: 40px; border-radius: 6px;"></div>
                    <span style="font-size: 20px;"><?= $cat['icon'] ?></span>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 14px; color: var(--gray-800);"><?= htmlspecialchars($cat['name']) ?></div>
                        <div style="font-size: 12px; color: var(--gray-500);">
                            <?= $cat['completed_tasks'] ?>/<?= $cat['total_tasks'] ?> selesai
                        </div>
                    </div>
                    <a href="tasks.php?category=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm" data-tooltip="Lihat Tugas">👁️</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus kategori ini?')">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <button type="submit" name="delete_category" class="btn btn-ghost btn-sm" style="color: var(--danger);" data-tooltip="Hapus">🗑️</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>