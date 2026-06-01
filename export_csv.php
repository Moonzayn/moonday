<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'antzynmy_task_manager');
define('DB_USER', 'antzynmy_root');
define('DB_PASS', '@Antzyn19');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die("Connection failed"); }

$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

$where = [];
$params = [];
if ($statusFilter) { $where[] = "t.status = ?"; $params[] = $statusFilter; }
if ($categoryFilter) { $where[] = "t.category_id = ?"; $params[] = (int)$categoryFilter; }
$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$orderMap = ['newest' => 't.created_at DESC', 'oldest' => 't.created_at ASC', 'due_date' => 't.due_date ASC', 'priority' => "FIELD(t.priority, 'urgent', 'high', 'medium', 'low')"];
$orderBy = $orderMap[$sortBy] ?? 't.created_at DESC';
$separator = $whereClause !== '' ? ' ' : '';

$sql = "SELECT t.*, u.full_name as owner_name, c.name as category_name FROM tasks t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id {$whereClause}{$separator}ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Preload notes for all tasks
$taskIds = array_map(fn($t) => (int)$t['id'], $tasks);
$notesMap = [];
if (!empty($taskIds)) {
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $noteStmt = $pdo->prepare("SELECT task_id, note, full_name as author FROM task_notes tn LEFT JOIN users u ON tn.user_id = u.id WHERE task_id IN ($placeholders) ORDER BY tn.created_at ASC");
    $noteStmt->execute($taskIds);
    while ($row = $noteStmt->fetch()) {
        $notesMap[(int)$row['task_id']][] = ($row['author'] ?? 'User') . ': ' . $row['note'];
    }
}

// Export
$filename = 'moonday_tasks_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['No', 'Judul', 'Deskripsi', 'Catatan', 'Status', 'Kategori', 'Pemilik', 'Dibuat', 'Selesai']);

$statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
$no = 1;
foreach ($tasks as $t) {
    $tid = (int)$t['id'];
    $notes = isset($notesMap[$tid]) ? implode("\n", $notesMap[$tid]) : '';
    fputcsv($output, [
        $no++,
        $t['title'],
        $t['description'] ?? '',
        $notes,
        $statusLabels[$t['status']] ?? $t['status'],
        $t['category_name'] ?? '-',
        $t['owner_name'] ?? $t['owner_username'] ?? '-',
        date('d/m/Y H:i', strtotime($t['created_at'])),
        $t['completed_at'] ? date('d/m/Y H:i', strtotime($t['completed_at'])) : '-'
    ]);
}

fclose($output);
exit;
