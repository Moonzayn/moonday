<?php
if (session_status() === PHP_SESSION_NONE) {
    // Session never expires: 1 year (31536000 seconds)
    ini_set('session.gc_maxlifetime', 31536000);
    session_set_cookie_params([
        'lifetime' => 31536000,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    // Isolasi session file biar ga kehapus GC dari aplikasi lain
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
        @chmod($sessionPath, 0777);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['full_name'] ?? 'Guest';
}

function getUserEmail() {
    return $_SESSION['email'] ?? '';
}

function getAvatarColor() {
    return $_SESSION['avatar_color'] ?? '#6366f1';
}

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (strlen($word) > 0) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: '?';
}

function logActivity($pdo, $userId, $taskId, $action, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, task_id, action, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $taskId, $action, $description]);
    } catch (Exception $e) {}
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function timeAgo($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    // Asumsikan data dari database disimpan dalam format UTC
    $ago = new DateTime($datetime, new DateTimeZone('UTC'));
    $ago->setTimeZone($tz);
    
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
?>