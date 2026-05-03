<?php
// Force WIB timezone
date_default_timezone_set('Asia/Jakarta');

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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) { echo json_encode(['error' => 'Task tidak ditemukan']); exit; }

    $userId = $_SESSION['user_id'];
    try { $pdo->prepare("INSERT INTO activity_log (user_id, task_id, action, description) VALUES (?,?,?,?)")->execute([$userId, $taskId, 'deleted', 'Menghapus tugas: ' . $task['title']]); } catch (Throwable $e) {}

    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
