<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();

$userId = getUserId();
$taskId = $_GET['id'] ?? 0;
$newStatus = $_GET['status'] ?? '';
$redirect = $_GET['redirect'] ?? 'tasks';

$validStatuses = ['todo', 'in_progress', 'completed', 'archived'];

if (in_array($newStatus, $validStatuses)) {
    $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
    
    $sql = "UPDATE tasks SET status = ?, completed_at = " . ($completedAt ? '?' : 'NULL') . " WHERE id = ?";
    $params = [$newStatus];
    if ($completedAt) $params[] = $completedAt;
    $params[] = $taskId;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
    logActivity($pdo, $userId, $taskId, $newStatus === 'completed' ? 'completed' : 'updated', 'Mengubah status ke ' . $statusLabels[$newStatus]);
}

header("Location: {$redirect}.php");
exit();
?>