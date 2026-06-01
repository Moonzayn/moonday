<?php
date_default_timezone_set('Asia/Jakarta');

if (ob_get_level()) while (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');

define('DB_HOST', 'localhost');
define('DB_NAME', 'antzynmy_task_manager');
define('DB_USER', 'antzynmy_root');
define('DB_PASS', '@Antzyn19');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (PDOException $e) { die('Gagal terhubung ke database'); }

$apiKey = 'z-rC9GjPu2fb68ytsWDBwod0niVF';
if (!isset($_GET['key']) || $_GET['key'] !== $apiKey) { die('Unauthorized'); }

try {
    $stmt = $pdo->prepare("
         SELECT t.id, t.title, t.description, t.priority, t.created_at,
               u.full_name AS owner_name, u.username AS owner_username
        FROM tasks t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.status IN ('todo', 'in_progress')
        ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at ASC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll();

    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $dayName = $hari[(int)$now->format('w')];
    $day = $now->format('j');
    $month = $bulan[(int)$now->format('n') - 1];
    $year = $now->format('Y');
    $time = $now->format('H:i');
    $formattedDate = "$dayName, $day $month $year – $time WIB (Asia/Jakarta)";

    $output = "Laporan Moonday – Tugas Aktif (To Do & In Progress)\r\n";
    $output .= "Tanggal: $formattedDate\r\n";
    $output .= "\r\n───\r\n\r\n";

    if (empty($tasks)) {
        $output .= "Tidak ada tugas yang sedang dikerjakan.\r\n";
    } else {
        $num = 1;
        foreach ($tasks as $task) {
            $title = $task['title'];
            $owner = html_entity_decode($task['owner_name'] ?? $task['owner_username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
            $priority = $task['priority'];

            $noteStmt = $pdo->prepare("
                SELECT tn.note, tn.created_at, u.full_name, u.username
                FROM task_notes tn
                LEFT JOIN users u ON tn.user_id = u.id
                WHERE tn.task_id = ?
                ORDER BY tn.created_at DESC
                LIMIT 1
            ");
            $noteStmt->execute([$task['id']]);
            $lastNote = $noteStmt->fetch();

            $output .= "$num. $title\r\n";
            if (!empty($task['description'])) {
                $desc = html_entity_decode($task['description'], ENT_QUOTES, 'UTF-8');
                $output .= "• Deskripsi: $desc\r\n";
            }
            $output .= "• Pembuat: $owner\r\n";
            $output .= "• Prioritas: " . strtoupper($priority) . "\r\n";

            if (!empty($lastNote)) {
                $noteUser = html_entity_decode($lastNote['full_name'] ?? $lastNote['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                $noteText = html_entity_decode($lastNote['note'], ENT_QUOTES, 'UTF-8');
                $noteTime = timeAgoSafe($lastNote['created_at']);
                $output .= "• Komentar Terbaru: $noteUser · $noteTime\r\n";
                $output .= "  $noteText\r\n";
            } else {
                $output .= "• Komentar Terbaru: Tidak ada komentar (Stale)\r\n";
            }

            $output .= "\r\n";
            $num++;
        }
    }

    echo $output;
    $pdo = null;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
    $pdo = null;
}

function timeAgoSafe($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
