<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();

$userId = getUserId();
$taskId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if ($task) {
    logActivity($pdo, $userId, null, 'deleted', 'Menghapus tugas: ' . $task['title']);

    $deleteStmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $deleteStmt->execute([$taskId]);
}

header('Location: tasks.php?msg=deleted');
exit();
?>