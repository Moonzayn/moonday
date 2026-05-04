<?php
if (ob_get_level()) while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

define('DB_HOST', 'localhost');
define('DB_NAME', 'antzynmy_task_manager');
define('DB_USER', 'antzynmy_root');
define('DB_PASS', '@Antzyn19');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (PDOException $e) { echo json_encode(['error' => 'DB failed']); exit; }

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit; }
$currentUserId = $_SESSION['user_id'];

$taskId = $_GET['id'] ?? '';
if (!is_numeric($taskId)) { echo json_encode(['error' => 'Invalid ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon FROM tasks t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) { echo json_encode(['error' => 'Task not found']); exit; }

    // Get notes
    $noteStmt = $pdo->prepare("SELECT tn.*, u.full_name, u.username FROM task_notes tn LEFT JOIN users u ON tn.user_id = u.id WHERE tn.task_id = ? ORDER BY tn.created_at DESC");
    $noteStmt->execute([$taskId]);
    $notes = $noteStmt->fetchAll();

    // Get activity log
    $logStmt = $pdo->prepare("SELECT al.*, u.full_name, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.id WHERE al.task_id = ? ORDER BY al.created_at DESC LIMIT 20");
    $logStmt->execute([$taskId]);
    $logs = $logStmt->fetchAll();

    echo json_encode([
        'task' => $task,
        'notes' => $notes,
        'logs' => $logs,
        'currentUserId' => $currentUserId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
