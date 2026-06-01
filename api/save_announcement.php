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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success' => false, 'error' => 'Invalid request']); exit; }

$id = (int)($input['id'] ?? 0);
$title = trim($input['title'] ?? '');
$content = trim($input['content'] ?? '');
$startDate = $input['start_date'] ?? date('Y-m-d');
$endDate = $input['end_date'] ?? date('Y-m-d');
$isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Judul wajib diisi']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $content, $startDate, $endDate, $isActive, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $startDate, $endDate, $isActive]);
        $id = $pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
