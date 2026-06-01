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

$month = (int)($_GET['month'] ?? date('m'));
$year = (int)($_GET['year'] ?? date('Y'));

$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.status, t.priority, t.due_date,
               c.name as category_name, c.color as category_color, c.icon as category_icon,
               u.full_name as owner_name
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE (t.due_date BETWEEN ? AND ?)
        ORDER BY t.due_date ASC, FIELD(t.priority, 'urgent', 'high', 'medium', 'low')
    ");
    $stmt->execute([$startDate, $endDate]);
    $tasks = $stmt->fetchAll();

    $grouped = [];
    foreach ($tasks as $task) {
        $date = $task['due_date'];
        if (!isset($grouped[$date])) $grouped[$date] = [];
        $grouped[$date][] = $task;
    }

    echo json_encode([
        'success' => true,
        'tasks' => $grouped,
        'month' => $month,
        'year' => $year,
        'total' => count($tasks)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
