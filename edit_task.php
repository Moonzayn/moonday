<?php
require_once 'includes/header.php';

$userId = getUserId();
$taskId = $_GET['id'] ?? 0;

// Get task
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: tasks.php');
    exit();
}

// Get categories
$catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$catStmt->execute();
$categories = $catStmt->fetchAll();

// Get notes
$notesStmt = $pdo->prepare("SELECT * FROM task_notes WHERE task_id = ? ORDER BY created_at DESC");
$notesStmt->execute([$taskId]);
$notes = $notesStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_note'])) {
        // Add note
        $note = trim($_POST['note'] ?? '');
        if (!empty($note)) {
            $noteStmt = $pdo->prepare("INSERT INTO task_notes (task_id, user_id, note) VALUES (?, ?, ?)");
            $noteStmt->execute([$taskId, $userId, $note]);
            logActivity($pdo, $userId, $taskId, 'updated', 'Menambah catatan');
            header("Location: edit_task.php?id=$taskId&msg=note_added");
            exit();
        }
    } else {
        // Update task
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'todo';
        $categoryId = $_POST['category_id'] ?: null;
        $dueDate = $_POST['due_date'] ?: null;

        if (empty($title)) $errors[] = 'Judul tugas wajib diisi.';

        if (empty($errors)) {
            $completedAt = null;
            if ($status === 'completed' && $task['status'] !== 'completed') {
                $completedAt = date('Y-m-d H:i:s');
            }

            $sql = "UPDATE tasks SET title = ?, description = ?, priority = ?, status = ?, category_id = ?, due_date = ?";
            $params = [$title, $description, $priority, $status, $categoryId, $dueDate];

            if ($completedAt) {
                $sql .= ", completed_at = ?";
                $params[] = $completedAt;
            } elseif ($status !== 'completed') {
                $sql .= ", completed_at = NULL";
            }

            $sql .= " WHERE id = ?";
            $params[] = $taskId;

            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);

            logActivity($pdo, $userId, $taskId, 'updated', 'Memperbarui tugas');

            header('Location: tasks.php?msg=updated');
            exit();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">✏️ Edit Tugas</h1>
        <p class="page-subtitle">Perbarui detail tugas kamu</p>
    </div>
    <a href="tasks.php" class="btn btn-outline">← Kembali</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">⚠️ <?= implode('<br>', $errors) ?></div>
<?php endif; ?>

<?php if (($_GET['msg'] ?? '') === 'note_added'): ?>
    <div class="alert alert-success">📝 Catatan berhasil ditambahkan!</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Detail Tugas</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Judul Tugas *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" class="form-control"><?= htmlspecialchars($task['description']) ?></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Prioritas</label>
                        <select name="priority" class="form-control">
                            <option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>🟢 Rendah</option>
                            <option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>🔵 Sedang</option>
                            <option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>🟡 Tinggi</option>
                            <option value="urgent" <?= $task['priority'] === 'urgent' ? 'selected' : '' ?>>🔴 Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>📝 To Do</option>
                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>🔄 In Progress</option>
                            <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>✅ Selesai</option>
                            <option value="archived" <?= $task['status'] === 'archived' ? 'selected' : '' ?>>📦 Arsip</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Tanpa Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $task['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Deadline</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $task['due_date'] ?>">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="delete_task.php?id=<?= $task['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus?')">🗑️ Hapus</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes Section -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h2 class="card-title">📝 Catatan</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <textarea name="note" class="form-control" placeholder="Tambah catatan..." rows="3" required></textarea>
                    </div>
                    <button type="submit" name="add_note" class="btn btn-outline btn-sm">➕ Tambah Catatan</button>
                </form>

                <?php if (!empty($notes)): ?>
                <div style="margin-top: 16px;">
                    <?php foreach ($notes as $note): ?>
                    <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; margin-bottom: 8px;">
                        <p style="font-size: 14px; color: var(--gray-700);"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                        <small style="color: var(--gray-400);"><?= timeAgo($note['created_at']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Task Info -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">ℹ️ Informasi</h2>
            </div>
            <div class="card-body">
                <div style="font-size: 13px; color: var(--gray-600);">
                    <p style="margin-bottom: 8px;"><strong>Dibuat:</strong> <?= date('d M Y H:i', strtotime($task['created_at'])) ?></p>
                    <p style="margin-bottom: 8px;"><strong>Diperbarui:</strong> <?= date('d M Y H:i', strtotime($task['updated_at'])) ?></p>
                    <?php if ($task['completed_at']): ?>
                    <p style="margin-bottom: 8px;"><strong>Diselesaikan:</strong> <?= date('d M Y H:i', strtotime($task['completed_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>