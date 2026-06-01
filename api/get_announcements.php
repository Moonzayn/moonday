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

$mode = $_GET['mode'] ?? 'active';

try {
    if ($mode === 'all') {
        $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE() ORDER BY created_at DESC");
        $stmt->execute();
    }
    $announcements = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $announcements], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
