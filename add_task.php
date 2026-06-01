<?php
// === Process POST before any output ===
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();
$userId = getUserId();

$errors = [];
$title = $description = $priority = $status = $dueDate = '';
$categoryId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = $_POST['priority'] ?? 'medium';
$status = $_POST['status'] ?? 'todo';
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

if (empty($title)) {
    $errors[] = 'Judul tugas wajib diisi.';
}

$validPriorities = ['low', 'medium', 'high', 'urgent'];
if (!in_array($priority, $validPriorities)) {
    $priority = 'medium';
}

$validStatuses = ['todo', 'in_progress'];
if (!in_array($status, $validStatuses)) {
    $status = 'todo';
}

if ($categoryId !== null) {
    $checkCat = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $checkCat->execute([$categoryId]);
    if (!$checkCat->fetch()) {
        $categoryId = null;
    }
}

if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
    $dueDate = null;
}

if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $categoryId, $title, $description, $priority, $status, $dueDate
        ]);

        $taskId = $pdo->lastInsertId();

        try {
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, task_id, action, description) VALUES (?, ?, 'created', 'Membuat tugas baru')");
            $logStmt->execute([$userId, $taskId]);
        } catch (Exception $e) {}

        try {
            require_once 'includes/mail_helper.php';
            $taskData = ['id' => $taskId, 'user_id' => $userId, 'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => $status, 'due_date' => $dueDate, 'category_name' => ''];
            if ($categoryId) {
                $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                $catStmt->execute([$categoryId]);
                $cat = $catStmt->fetch();
                if ($cat) $taskData['category_name'] = $cat['name'];
            }
            sendTaskNotification($pdo, $taskData, getUserName());
        } catch (Exception $e) {}

        header('Location: tasks.php?msg=created');
        exit();
    } catch (PDOException $e) {
        $errors[] = 'Gagal menyimpan tugas: ' . $e->getMessage();
    }
}
}

// === Now safe to render ===
require_once 'includes/header.php';

// Get categories
$catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$catStmt->execute();
$categories = $catStmt->fetchAll();
?>

<div class="page-header">
<div>
    <h1 class="page-title">➕ Tugas Baru</h1>
    <p class="page-subtitle">Tambahkan tugas baru ke daftar kamu</p>
</div>
<a href="tasks.php" class="btn btn-outline">← Kembali</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card">
<div class="card-body">
    <form method="POST" action="add_task.php">
        <div class="form-group">
            <label>Judul Tugas *</label>
            <input type="text" name="title" class="form-control" placeholder="Apa yang perlu dikerjakan?" value="<?= htmlspecialchars($title) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="description" class="form-control" placeholder="Tambahkan detail tugas (opsional)"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Prioritas</label>
                <select name="priority" class="form-control">
                    <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>🟢 Rendah</option>
                    <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>🔵 Sedang</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>🟡 Tinggi</option>
                    <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>🔴 Urgent</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="todo" <?= $status === 'todo' ? 'selected' : '' ?>>📝 To Do</option>
                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>🔄 In Progress</option>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Kategori</label>
                <select name="category_id" class="form-control">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                        <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Deadline</label>
                <input disabled type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($dueDate ?? '') ?>">
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:8px;">
            <button type="submit" class="btn btn-primary btn-lg">
                ✅ Simpan Tugas
            </button>
            <a href="tasks.php" class="btn btn-outline btn-lg">Batal</a>
        </div>
    </form>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>