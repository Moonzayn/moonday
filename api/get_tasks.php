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
$userId = $_SESSION['user_id'];

try {
    $statusFilter   = $_GET['status'] ?? '';
    $priorityFilter = $_GET['priority'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';
    $searchQuery    = $_GET['search'] ?? '';
    $sortBy         = $_GET['sort'] ?? 'newest';
    $specialFilter  = $_GET['filter'] ?? '';

    $where = [];
    $params = [];

    if ($statusFilter) { $where[] = "t.status = ?"; $params[] = $statusFilter; }
    if ($priorityFilter) { $where[] = "t.priority = ?"; $params[] = $priorityFilter; }
    if ($categoryFilter) { $where[] = "t.category_id = ?"; $params[] = (int)$categoryFilter; }
    if ($searchQuery) { $where[] = "(t.title LIKE ? OR t.description LIKE ?)"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; }
    if ($specialFilter === 'overdue') { $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('completed', 'archived')"; }
    if ($specialFilter === 'today') { $where[] = "t.due_date = CURDATE()"; }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $orderMap = [
        'newest'   => 't.created_at DESC',
        'oldest'   => 't.created_at ASC',
        'due_date' => 't.due_date ASC',
        'priority' => "FIELD(t.priority, 'urgent', 'high', 'medium', 'low')",
        'title'    => 't.title ASC',
    ];
    $orderBy = $orderMap[$sortBy] ?? 't.created_at DESC';
    $separator = $whereClause !== '' ? ' ' : '';

    $sql = "SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon, n.notes_json FROM tasks t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id LEFT JOIN (SELECT task_id, JSON_ARRAYAGG(JSON_OBJECT('id', tn.id, 'note', tn.note, 'user_id', tn.user_id, 'full_name', u2.full_name, 'username', u2.username, 'created_at', tn.created_at)) as notes_json FROM task_notes tn LEFT JOIN users u2 ON tn.user_id = u2.id GROUP BY task_id) n ON t.id = n.task_id {$whereClause}{$separator}ORDER BY $orderBy";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    $today = date('Y-m-d');
    $html = '';

    if (empty($tasks)) {
        $html = '<div class="card"><div class="empty-state"><div class="empty-icon">📭</div><h3>Tidak ada tugas ditemukan</h3><p>Coba ubah filter atau tambah tugas baru</p><a href="add_task.php" class="btn btn-primary">➕ Tambah Tugas</a></div></div>';
    } else {
        foreach ($tasks as $task) {
            $isOverdue = $task['due_date'] && $task['due_date'] < $today && !in_array($task['status'], ['completed', 'archived']);
            $isToday = $task['due_date'] === $today;
            $tid = (int)$task['id'];
            $cc = $task['status'] === 'completed' ? ' completed' : '';
            $ch = $task['status'] === 'completed' ? ' checked' : '';
            $ts = $task['status'] === 'completed' ? 'todo' : 'completed';
            $owner = htmlspecialchars($task['owner_name'] ?? $task['owner_username'] ?? 'Unknown');
            $pri = htmlspecialchars($task['priority']);
            $st = htmlspecialchars($task['status']);
            $title = htmlspecialchars($task['title']);

            $notesHtml = '';
            if (!empty($task['notes_json'])) {
                $notes = json_decode($task['notes_json'], true);
                if (is_array($notes)) {
                    foreach ($notes as $note) {
                        $noteUser = htmlspecialchars($note['full_name'] ?? $note['username'] ?? 'Unknown');
                        $noteText = htmlspecialchars($note['note']);
                        $noteTime = timeAgoSafe($note['created_at']);
                        $notesHtml .= '<div class="note-item"><div class="note-text">' . $noteText . '</div><div class="note-meta">' . $noteUser . ' &middot; ' . $noteTime . '</div></div>';
                    }
                }
            }

            $noteCount = !empty($task['notes_json']) ? count(json_decode($task['notes_json'], true)) : 0;
            $notesVisibleClass = $noteCount > 0 ? '' : ' display-none';

            $html .= '<div class="task-item' . $cc . '" id="task-' . $tid . '">';
            $html .= '<a href="#" class="task-checkbox' . $ch . '" onclick="event.preventDefault();toggleTaskStatus(' . $tid . ',\'' . $ts . '\')"></a>';
            $html .= '<div class="task-content">';
            $html .= '<a href="task_detail.php?id=' . $tid . '" class="task-title" style="text-decoration:none;color:inherit;cursor:pointer;">' . $title . '</a>';
            $html .= '<div style="font-size:12px;color:var(--gray-400);margin-bottom:4px;">oleh ' . $owner . '</div>';

            if ($task['description']) {
                $desc = htmlspecialchars(mb_substr($task['description'], 0, 100));
                if (mb_strlen($task['description']) > 100) $desc .= '...';
                $html .= '<p style="font-size:13px;color:var(--gray-500);margin:4px 0;">' . $desc . '</p>';
            }

            if ($noteCount > 0) {
                $html .= '<div style="margin-top:6px;"><button class="btn btn-ghost btn-sm" onclick="toggleNotes(' . $tid . ')" id="notesBtn-' . $tid . '">💬 <span id="notesCount-' . $tid . '">' . $noteCount . '</span> komentar</button></div>';
                $html .= '<div class="task-notes hidden" id="notes-' . $tid . '">' . $notesHtml . '</div>';
            } else {
                $html .= '<div class="task-notes hidden" id="notes-' . $tid . '"></div>';
            }

            $html .= '<div class="note-input-row">';
            $html .= '<input type="text" class="note-input" id="noteInput-' . $tid . '" placeholder="Tambah catatan..." onkeypress="if(event.key===\'Enter\')addNote(' . $tid . ')">';
            $html .= '<button class="btn btn-ghost btn-icon note-btn" onclick="addNote(' . $tid . ')">📝</button>';
            $html .= '</div>';

            $html .= '<div class="task-meta">';
            $html .= '<span class="priority-badge priority-' . $pri . '">' . ucfirst($task['priority']) . '</span>';

            $sl = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
            $html .= '<span class="status-badge status-' . $st . '">' . ($sl[$task['status']] ?? $task['status']) . '</span>';

            if ($task['category_name']) {
                $html .= '<span class="category-tag">' . $task['category_icon'] . ' ' . htmlspecialchars($task['category_name']) . '</span>';
            }

            if ($task['due_date']) {
                $dc = $isOverdue ? ' due-overdue' : ($isToday ? ' due-today' : '');
                $dl = $isOverdue ? '(Terlambat!)' : ($isToday ? '(Hari ini)' : '');
                $html .= '<span class="task-meta-item' . $dc . '">📅 ' . date('d M Y', strtotime($task['due_date'])) . ' ' . $dl . '</span>';
            }

            $html .= '</div></div><div class="task-actions">';
            if ($task['status'] === 'todo') {
                $html .= '<a href="#" onclick="event.preventDefault();updateTaskStatus(' . $tid . ',\'in_progress\')" class="btn btn-ghost btn-icon" data-tooltip="Kerjakan" style="color:var(--info);">🔄</a>';
            } elseif ($task['status'] === 'in_progress') {
                $html .= '<a href="#" onclick="event.preventDefault();updateTaskStatus(' . $tid . ',\'todo\')" class="btn btn-ghost btn-icon" data-tooltip="Kembalikan ke To Do" style="color:var(--warning);">↩️</a>';
                $html .= '<a href="#" onclick="event.preventDefault();updateTaskStatus(' . $tid . ',\'completed\')" class="btn btn-ghost btn-icon" data-tooltip="Selesai" style="color:var(--success);">✅</a>';
            }
            $html .= '<a href="edit_task.php?id=' . $tid . '" class="btn btn-ghost btn-icon" data-tooltip="Edit">✏️</a>';
            $html .= '<a href="#" onclick="event.preventDefault();deleteTask(' . $tid . ')" class="btn btn-ghost btn-icon" data-tooltip="Hapus" style="color:var(--danger);">🗑️</a>';
            $html .= '</div></div>';
        }
    }

    $pt = $specialFilter === 'overdue' ? '⚠️ Tugas Terlambat' :
          ($statusFilter === 'completed' ? '✅ Tugas Selesai' :
          ($statusFilter === 'in_progress' ? '🔄 Sedang Dikerjakan' :
          ($statusFilter === 'todo' ? '📝 To Do' : '📋 Semua Tugas')));

    echo json_encode([
        'html' => $html, 'count' => count($tasks), 'title' => $pt,
        'statusFilter' => $statusFilter, 'categoryFilter' => $categoryFilter,
        'searchQuery' => $searchQuery, 'sortBy' => $sortBy, 'specialFilter' => $specialFilter,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function timeAgoSafe($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    // Asumsikan waktu yang disimpan di DB sudah dalam format WIB (karena kita set timezone saat insert)
    $ago = new DateTime($datetime, $tz);
    
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
