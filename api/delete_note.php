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
$noteId = $input['noteId'] ?? 0;

if (!$noteId) { echo json_encode(['error' => 'Invalid note ID']); exit; }

try {
    // Verify ownership
    $check = $pdo->prepare("SELECT user_id FROM task_notes WHERE id = ?");
    $check->execute([$noteId]);
    $note = $check->fetch();

    if (!$note) { echo json_encode(['error' => 'Note not found']); exit; }
    if ($note['user_id'] != $_SESSION['user_id']) { echo json_encode(['error' => 'Not authorized']); exit; }

    $pdo->prepare("DELETE FROM task_notes WHERE id = ?")->execute([$noteId]);
    echo json_encode(['success' => true, 'message' => 'Komentar berhasil dihapus']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
