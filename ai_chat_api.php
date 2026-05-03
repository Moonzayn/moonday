<?php
ob_start();
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cleanOutputBuffer(): void {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

function jsonResponse(array $data, int $code = 200): void {
    cleanOutputBuffer();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400, array $extra = []): void {
    jsonResponse(array_merge(['error' => $message], $extra), $code);
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI - NVIDIA NIM API
|--------------------------------------------------------------------------
*/
define('DB_HOST', 'localhost');
define('DB_NAME', 'antzynmy_task_manager');
define('DB_USER', 'antzynmy_root');
define('DB_PASS', '@Antzyn19');

define('AI_API_URL', 'https://integrate.api.nvidia.com/v1/chat/completions');
define('AI_API_KEY', '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');
define('AI_MODEL', 'openai/gpt-oss-120b');

$fallbackModels = [];

if (!isset($_SESSION['user_id'])) {
    jsonError('Unauthorized - silakan login ulang', 401);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    jsonError('Unauthorized - user tidak valid', 401);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    jsonError('Database connection failed', 500);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        handleSend($pdo, $userId, $fallbackModels);
        break;
    case 'create_task':
        handleCreateTask($pdo, $userId);
        break;
    case 'create_multiple_tasks':
        handleCreateMultipleTasks($pdo, $userId);
        break;
    case 'clear':
        $_SESSION['chat_history'] = [];
        unset($_SESSION['pending_ai_task_actions']);
        jsonResponse(['success' => true]);
        break;
    case 'history':
        jsonResponse(['success' => true, 'history' => $_SESSION['chat_history'] ?? []]);
        break;
    case 'categories':
        jsonResponse(['success' => true, 'categories' => getUserCategories($pdo, $userId)]);
        break;
    case 'debug':
        handleDebug($fallbackModels);
        break;
    default:
        jsonError('Invalid action');
}

function handleSend(PDO $pdo, int $userId, array $fallbackModels): void {
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        jsonError('Pesan tidak boleh kosong');
    }

    if (strLengthSafe($message) > 2000) {
        jsonError('Pesan terlalu panjang (maks 2000 karakter)');
    }

    if (!isset($_SESSION['chat_history']) || !is_array($_SESSION['chat_history'])) {
        $_SESSION['chat_history'] = [];
    }

    $_SESSION['chat_history'][] = [
        'role'    => 'user',
        'content' => $message,
        'time'    => date('H:i')
    ];

    $context = gatherContext($pdo, $userId);
    $systemPrompt = buildPrompt($context);
    $aiResult = callAI($systemPrompt, $_SESSION['chat_history'], $fallbackModels);

    if (!empty($aiResult['error'])) {
        array_pop($_SESSION['chat_history']);
        jsonError($aiResult['error'], 400, [
            'meta' => $aiResult['meta'] ?? null
        ]);
    }

    $rawReply    = trim((string)($aiResult['reply'] ?? ''));
    $taskActions = extractTaskActions($rawReply);
    $cleanReply  = cleanReplyText($rawReply);

    if ($cleanReply === '' && !empty($taskActions)) {
        $cleanReply = 'Baik, saya sudah siapkan tugasnya. Silakan konfirmasi untuk membuat task ke aplikasi ya ✅';
    }

    if ($cleanReply === '') {
        $cleanReply = 'Saya sudah memproses permintaanmu ✅';
    }

    $_SESSION['chat_history'][] = [
        'role'    => 'assistant',
        'content' => $cleanReply,
        'time'    => date('H:i')
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT created_at 
            FROM activity_log 
            WHERE user_id = ? AND action = 'ai_chat'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $lastLog = $stmt->fetchColumn();

        if (!$lastLog || (time() - strtotime((string)$lastLog . ' UTC')) > 300) {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, description)
                VALUES (?, 'ai_chat', 'Menggunakan AI Assistant')
            ");
            $stmt->execute([$userId]);
        }
    } catch (Throwable $e) {
    }

    jsonResponse([
        'success'      => true,
        'reply'        => $cleanReply,
        'time'         => date('H:i'),
        'task_actions' => $taskActions,
        'meta'         => $aiResult['meta'] ?? null
    ]);
}

function handleCreateTask(PDO $pdo, int $userId): void {
    $title        = trim((string)($_POST['title'] ?? ''));
    $description  = trim((string)($_POST['description'] ?? ''));
    $priority     = normalizePriority((string)($_POST['priority'] ?? 'medium'));
    $status       = normalizeStatus((string)($_POST['status'] ?? 'todo'));
    $categoryId   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $categoryName = trim((string)($_POST['category_name'] ?? ''));
    $dueDateInput = trim((string)($_POST['due_date'] ?? 'today'));
    $taskUserId   = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $userId;

    if ($title === '') {
        jsonError('Judul tugas wajib diisi');
    }

    if (!$categoryId && $categoryName !== '') {
        $categoryId = resolveCategoryIdByName($pdo, $categoryName);
    }

    if ($categoryId) {
        $check = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $check->execute([$categoryId]);
        if (!$check->fetch()) {
            $categoryId = null;
        }
    }

    $dueDate = parseDueDate($dueDateInput);
        if (!$dueDate) {
            $dueDate = date('Y-m-d');
        }

        try {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $taskUserId,
            $categoryId,
            $title,
            $description,
            $priority,
            $status,
            $dueDate
        ]);

        $taskId = (int)$pdo->lastInsertId();

        try {
            $log = $pdo->prepare("
                INSERT INTO activity_log (user_id, task_id, action, description)
                VALUES (?, ?, 'created', 'Tugas dibuat via AI Chat')
            ");
            $log->execute([$userId, $taskId]);
        } catch (Throwable $e) {
        }

        $categoryLabel = '';
        if ($categoryId) {
            $catStmt = $pdo->prepare("SELECT name, icon FROM categories WHERE id = ? AND user_id = ?");
            $catStmt->execute([$categoryId, $userId]);
            $cat = $catStmt->fetch();
            if ($cat) {
                $categoryLabel = trim(($cat['icon'] ?? '') . ' ' . ($cat['name'] ?? ''));
            }
        }

        jsonResponse([
            'success' => true,
            'task'    => [
                'id'          => $taskId,
                'title'       => $title,
                'description' => $description,
                'priority'    => $priority,
                'status'      => $status,
                'category_id' => $categoryId,
                'category'    => $categoryLabel,
                'due_date'    => $dueDate,
                'user_id'     => $taskUserId
            ]
        ]);
    } catch (PDOException $e) {
        jsonError('Gagal menyimpan tugas');
    }
}

function handleCreateMultipleTasks(PDO $pdo, int $userId): void {
    $tasksRaw = $_POST['tasks'] ?? '[]';
    $tasks = json_decode((string)$tasksRaw, true);

    if (!is_array($tasks) || empty($tasks)) {
        jsonError('Tidak ada tugas untuk dibuat');
    }

    $created = [];
    $failed  = [];

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            $failed[] = ['title' => '', 'error' => 'Format task tidak valid'];
            continue;
        }

        $title        = trim((string)($task['title'] ?? ''));
        $description  = trim((string)($task['description'] ?? ''));
        $priority     = normalizePriority((string)($task['priority'] ?? 'medium'));
        $status       = normalizeStatus((string)($task['status'] ?? 'todo'));
        $categoryId   = !empty($task['category_id']) ? (int)$task['category_id'] : null;
        $categoryName = trim((string)($task['category_name'] ?? ($task['category'] ?? '')));
        $dueDateInput = trim((string)($task['due_date'] ?? 'today'));

        if ($title === '') {
            $failed[] = ['title' => '', 'error' => 'Judul kosong'];
            continue;
        }

        if (!$categoryId && $categoryName !== '') {
            $categoryId = resolveCategoryIdByName($pdo, $categoryName);
        }

        if ($categoryId) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $check->execute([$categoryId]);
            if (!$check->fetch()) {
                $categoryId = null;
            }
        }

        $dueDate = parseDueDate($dueDateInput);
        if (!$dueDate) {
            $dueDate = date('Y-m-d');
        }

        $taskUserId = !empty($task['user_id']) ? (int)$task['user_id'] : $userId;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $taskUserId,
                $categoryId,
                $title,
                $description,
                $priority,
                $status,
                $dueDate
            ]);

            $taskId = (int)$pdo->lastInsertId();

            try {
                $log = $pdo->prepare("
                    INSERT INTO activity_log (user_id, task_id, action, description)
                    VALUES (?, ?, 'created', 'Tugas dibuat via AI Chat')
                ");
                $log->execute([$userId, $taskId]);
            } catch (Throwable $e) {
            }

            $created[] = [
                'id'       => $taskId,
                'title'    => $title,
                'due_date' => $dueDate
            ];
        } catch (Throwable $e) {
            $failed[] = ['title' => $title, 'error' => 'Gagal insert'];
        }
    }

    jsonResponse([
        'success'       => true,
        'created'       => $created,
        'failed'        => $failed,
        'created_count' => count($created),
        'failed_count'  => count($failed)
    ]);
}

function handleDebug(array $fallbackModels): void {
    $models = array_merge([AI_MODEL], $fallbackModels);
    $results = [];

    foreach ($models as $model) {
        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Balas dengan kata: OK']
            ],
            'max_tokens'  => 50,
            'temperature' => 0.2
        ];

        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        $curlNo   = curl_errno($ch);
        curl_close($ch);

        $results[] = [
            'model'       => $model,
            'http_code'   => $httpCode,
            'curl_errno'  => $curlNo,
            'curl_error'  => $curlErr,
            'raw_preview' => substr((string)$response, 0, 500),
            'json'        => json_decode((string)$response, true)
        ];
    }

    jsonResponse([
        'success' => true,
        'results' => $results
    ]);
}

function callAI(string $systemPrompt, array $history, array $fallbackModels): array {
    $messages = [['role' => 'system', 'content' => $systemPrompt]];

    $recent = array_slice($history, -20);
    foreach ($recent as $msg) {
        $messages[] = [
            'role'    => $msg['role'],
            'content' => $msg['content']
        ];
    }

    $models = array_unique(array_merge([AI_MODEL], $fallbackModels));
    $lastError = null;

    foreach ($models as $model) {
        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => 2500,
            'temperature' => 0.7,
        ];

        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        $curlNo    = curl_errno($ch);
        curl_close($ch);

        if ($curlNo !== 0) {
            $lastError = "Koneksi gagal (#{$curlNo}): {$curlErr}";
            continue;
        }

        if (empty($response)) {
            $lastError = "Respons kosong (HTTP {$httpCode})";
            continue;
        }

        $data = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $lastError = "Respons bukan JSON. Preview: " . substr((string)$response, 0, 200);
            continue;
        }

        if ($httpCode !== 200) {
            $msg = $data['error']['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
            if (is_array($msg)) $msg = json_encode($msg);
            $lastError = "API Error: {$msg}";
            continue;
        }

        $reply = $data['choices'][0]['message']['content']
                ?? $data['choices'][0]['text']
                ?? $data['output']
                ?? $data['result']
                ?? null;

        if ($reply !== null) {
            $reply = trim(is_string($reply) ? $reply : json_encode($reply));
            if ($reply !== '') {
                return [
                    'reply' => $reply,
                    'meta'  => ['model' => $model, 'http_code' => $httpCode]
                ];
            }
        }

        $lastError = 'Format respons tidak dikenali. Keys: ' . implode(', ', array_keys($data));
    }

    return ['error' => $lastError ?: 'Gagal mendapatkan respons AI', 'meta' => ['model' => AI_MODEL]];
}

function getUserCategories(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT id, name, icon, color
        FROM categories
        ORDER BY name
    ");
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function gatherContext(PDO $pdo, int $userId): array {
    $ctx = [];

    $stmt = $pdo->prepare("
        SELECT full_name, username, email, bio, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $ctx['user'] = $stmt->fetch() ?: [
        'full_name' => 'User',
        'username'  => '',
        'email'     => '',
        'bio'       => '',
        'created_at'=> ''
    ];

    $queries = [
        'total_tasks'   => "SELECT COUNT(*) FROM tasks",
        'todo'          => "SELECT COUNT(*) FROM tasks WHERE status='todo'",
        'in_progress'   => "SELECT COUNT(*) FROM tasks WHERE status='in_progress'",
        'completed'     => "SELECT COUNT(*) FROM tasks WHERE status='completed'",
        'archived'      => "SELECT COUNT(*) FROM tasks WHERE status='archived'",
        'overdue'       => "SELECT COUNT(*) FROM tasks WHERE due_date<CURDATE() AND status NOT IN('completed','archived')",
        'due_today'     => "SELECT COUNT(*) FROM tasks WHERE due_date=CURDATE() AND status NOT IN('completed','archived')",
        'due_this_week' => "SELECT COUNT(*) FROM tasks WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status NOT IN('completed','archived')",
        'high_priority' => "SELECT COUNT(*) FROM tasks WHERE priority IN('high','urgent') AND status NOT IN('completed','archived')",
        'no_deadline'   => "SELECT COUNT(*) FROM tasks WHERE due_date IS NULL AND status NOT IN('completed','archived')",
    ];

    $stats = [];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats[$key] = (int)$stmt->fetchColumn();
    }
    $stats['completion_rate'] = $stats['total_tasks'] > 0
        ? round(($stats['completed'] / $stats['total_tasks']) * 100, 1)
        : 0;
    $ctx['stats'] = $stats;

    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date,
               c.name AS category, c.id AS category_id, u.full_name AS owner_name
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.status != 'archived'
        ORDER BY FIELD(t.status,'in_progress','todo','completed'),
                 FIELD(t.priority,'urgent','high','medium','low'),
                 t.due_date ASC,
                 t.created_at DESC
        LIMIT 80
    ");
    $stmt->execute();
    $ctx['active_tasks'] = $stmt->fetchAll() ?: [];

    $stmt = $pdo->prepare("
        SELECT t.title, t.priority, t.due_date
        FROM tasks t
        WHERE t.due_date < CURDATE()
          AND t.status NOT IN ('completed','archived')
        ORDER BY t.due_date ASC
        LIMIT 20
    ");
    $stmt->execute();
    $ctx['overdue_tasks'] = $stmt->fetchAll() ?: [];

    $stmt = $pdo->prepare("
        SELECT t.title, t.priority, t.status
        FROM tasks t
        WHERE t.due_date = CURDATE()
          AND t.status != 'archived'
        ORDER BY FIELD(t.priority,'urgent','high','medium','low')
        LIMIT 20
    ");
    $stmt->execute();
    $ctx['today_tasks'] = $stmt->fetchAll() ?: [];

    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.icon, COUNT(t.id) AS total
        FROM categories c
        LEFT JOIN tasks t ON c.id = t.category_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $stmt->execute();
    $ctx['categories'] = $stmt->fetchAll() ?: [];

    return $ctx;
}

function buildPrompt(array $ctx): string {
    $u = $ctx['user'];
    $s = $ctx['stats'];
    $today = date('Y-m-d');

    $catList = '';
    foreach (($ctx['categories'] ?? []) as $c) {
        $catList .= "- ID: {$c['id']} | {$c['icon']} {$c['name']} ({$c['total']} tugas)\n";
    }
    if ($catList === '') {
        $catList = "- Tidak ada kategori\n";
    }

    $activeTasks = '';
    foreach (($ctx['active_tasks'] ?? []) as $t) {
        $cat = $t['category'] ?: '-';
        $due = $t['due_date'] ?: 'no deadline';
        $activeTasks .= "- [{$t['status']}][{$t['priority']}] \"{$t['title']}\" | kategori: {$cat} | due: {$due}\n";
    }
    if ($activeTasks === '') {
        $activeTasks = "- Belum ada tugas aktif\n";
    }

    $overdueTasks = '';
    foreach (($ctx['overdue_tasks'] ?? []) as $t) {
        $lateDays = max(1, (int)((time() - strtotime((string)$t['due_date'])) / 86400));
        $overdueTasks .= "- {$t['title']} | {$t['priority']} | telat {$lateDays} hari\n";
    }
    if ($overdueTasks === '') {
        $overdueTasks = "- Tidak ada tugas terlambat\n";
    }

    return <<<PROMPT
Kamu adalah Moonday AI, asisten manajemen tugas.
Bahasa: Indonesia.
Gaya: natural, singkat, helpful, ramah, jelas.

TANGGAL HARI INI: {$today}

DATA USER:
- Nama: {$u['full_name']}
- Username: @{$u['username']}
- Email: {$u['email']}

STATISTIK:
- Total: {$s['total_tasks']}
- To Do: {$s['todo']}
- In Progress: {$s['in_progress']}
- Completed: {$s['completed']}
- Overdue: {$s['overdue']}
- Due Today: {$s['due_today']}
- High Priority Pending: {$s['high_priority']}
- Completion Rate: {$s['completion_rate']}%

KATEGORI TERSEDIA:
{$catList}

TUGAS AKTIF:
{$activeTasks}

TUGAS TERLAMBAT:
{$overdueTasks}

ATURAN UTAMA:
1. Jawab dalam Bahasa Indonesia.
2. Jika user meminta ringkasan / summary / tugas belum selesai, jawab berdasarkan data.
3. Jika user meminta buat / tambah / input / catat tugas:
   - Pahami judul, deskripsi, prioritas, kategori, deadline.
   - Jika kategori jelas dan ada di daftar kategori, gunakan category_id yang benar.
   - Jika kategori tidak jelas / ambigu, TANYAKAN dulu dan JANGAN keluarkan JSON task_create.
   - Jika deadline tidak disebut, default "today".
   - Jika prioritas tidak disebut, default "medium".
4. Jika data sudah cukup untuk membuat task, akhiri jawabanmu dengan blok persis seperti ini:

```task_create
{
  "tasks": [
    {
      "title": "Task 1",
      "description": "",
      "priority": "medium",
      "category_id": 1,
      "due_date": "today",
      "status": "todo"
    },
    {
      "title": "Task 2",
      "description": "",
      "priority": "high",
      "category_id": 2,
      "due_date": "besok",
      "status": "todo"
    }
  ]
}
```

PENTING:
- Selalu tulis penjelasan SEBELUM blok JSON
- Gunakan category_id dari daftar di atas
- Gunakan "today" jika deadline tidak disebut
- JANGAN buat task jika info ambigu
PROMPT;
}

function extractTaskActions(string $reply): array {
    $actions = [];

    if (preg_match_all('/```task_create\s*\n?([\s\S]*?)```/i', $reply, $matches)) {
        foreach ($matches[1] as $jsonStr) {
            $data = json_decode(trim($jsonStr), true);
            if ($data) {
                if (isset($data['tasks']) && is_array($data['tasks'])) {
                    foreach ($data['tasks'] as $t) {
                        $actions[] = normalizeTaskAction($t);
                    }
                } else {
                    $actions[] = normalizeTaskAction($data);
                }
            }
        }
    }

    return $actions;
}

function normalizeTaskAction(array $task): array {
    return [
        'title'         => trim($task['title'] ?? ''),
        'description'   => trim($task['description'] ?? ''),
        'priority'      => $task['priority'] ?? 'medium',
        'status'        => $task['status'] ?? 'todo',
        'category_name' => $task['category'] ?? $task['category_name'] ?? '',
        'category_id'   => $task['category_id'] ?? null,
        'due_date'      => $task['due_date'] ?? $task['deadline'] ?? '',
        'user_id'       => $task['user_id'] ?? null,
    ];
}

function cleanReplyText(string $reply): string {
    $clean = preg_replace('/```task_create\s*\n?[\s\S]*?```/i', '', $reply);
    $clean = preg_replace('/\n{3,}/', "\n\n", trim($clean));
    return $clean;
}

function parseDueDate(string $input): ?string {
    if (empty($input)) return date('Y-m-d');

    $input = strtolower(trim($input));

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) return $input;

    $map = [
        'today'        => 'today',
        'hari ini'     => 'today',
        'tomorrow'     => '+1 day',
        'besok'        => '+1 day',
        'lusa'         => '+2 days',
        'minggu ini'   => 'next sunday',
        'this week'    => 'next sunday',
        'next week'    => '+1 week',
        'minggu depan' => '+1 week',
        'bulan depan'  => '+1 month',
        'next month'   => '+1 month',
    ];

    if (isset($map[$input])) {
        return date('Y-m-d', strtotime($map[$input]));
    }

    if (preg_match('/^(\d+)\s*(hari|day|days|minggu|week|weeks|bulan|month|months)$/i', $input, $m)) {
        $num  = (int)$m[1];
        $unit = strtolower($m[2]);
        $unitMap = [
            'hari'   => 'days',   'day'   => 'days',   'days'   => 'days',
            'minggu' => 'weeks',  'week'  => 'weeks',  'weeks'  => 'weeks',
            'bulan'  => 'months', 'month' => 'months', 'months' => 'months',
        ];
        if (isset($unitMap[$unit])) {
            return date('Y-m-d', strtotime("+{$num} {$unitMap[$unit]}"));
        }
    }

    $ts = strtotime($input);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }

    return date('Y-m-d');
}

function normalizePriority(string $priority): string {
    $valid = ['low', 'medium', 'high', 'urgent'];
    return in_array($priority, $valid, true) ? $priority : 'medium';
}

function normalizeStatus(string $status): string {
    $valid = ['todo', 'in_progress'];
    return in_array($status, $valid, true) ? $status : 'todo';
}

function resolveCategoryIdByName(PDO $pdo, string $name): ?int {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $result = $stmt->fetchColumn();
    return $result ? (int)$result : null;
}

function strLengthSafe(string $str): int {
    return mb_strlen($str, 'UTF-8');
}
