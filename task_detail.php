<?php
require_once 'includes/header.php';

$taskId = $_GET['id'] ?? 0;
if (!is_numeric($taskId)) { header('Location: tasks.php'); exit; }

// Fetch task data server-side for initial render
$stmt = $pdo->prepare("SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon FROM tasks t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) { header('Location: tasks.php'); exit; }

$noteStmt = $pdo->prepare("SELECT tn.*, u.full_name, u.username FROM task_notes tn LEFT JOIN users u ON tn.user_id = u.id WHERE tn.task_id = ? ORDER BY tn.created_at DESC");
$noteStmt->execute([$taskId]);
$notes = $noteStmt->fetchAll();
?>

<div class="page-header">
    <div>
        <a href="tasks.php" style="font-size:14px;color:var(--gray-500);text-decoration:none;margin-bottom:8px;display:inline-block;">← Kembali ke Daftar Tugas</a>
        <h1 class="page-title" style="margin-top:4px;" id="taskTitleDisplay"><?= htmlspecialchars($task['title']) ?></h1>
        <p class="page-subtitle" id="taskMetaDisplay"></p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="edit_task.php?id=<?= $taskId ?>" class="btn btn-outline">✏️ Edit</a>
        <button class="btn btn-outline" onclick="deleteTask(<?= $taskId ?>)" style="color:var(--danger);border-color:var(--danger);">🗑️ Hapus</button>
    </div>
</div>

<div id="alertContainer"></div>

<div class="grid-2" style="grid-template-columns:2fr 1fr;gap:24px;">
    <!-- Left Column: Description & Notes -->
    <div>
        <!-- Status & Info -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">📋 Informasi Tugas</h2>
            </div>
            <div class="card-body" id="taskInfo"></div>
        </div>

        <!-- Description -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">📝 Deskripsi</h2>
            </div>
            <div class="card-body">
                <p id="taskDesc" style="white-space:pre-wrap;color:var(--gray-700);line-height:1.6;"><?= nl2br(htmlspecialchars($task['description'] ?: '(Tidak ada deskripsi)')) ?></p>
            </div>
        </div>

        <!-- Comments / Notes -->
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">💬 Komentar (<span id="noteCount"><?= count($notes) ?></span>)</h2>
                <button class="btn btn-ghost btn-sm" onclick="toggleNotesDisplay()" id="toggleNotesBtn">Sembunyikan</button>
            </div>
            <div class="card-body" id="notesSection">
                <div id="notesList">
                    <?php foreach ($notes as $note): ?>
                    <div class="note-item" id="note-<?= $note['id'] ?>">
                        <div class="note-text"><?= htmlspecialchars($note['note']) ?></div>
                        <div class="note-meta">
                            <?= htmlspecialchars($note['full_name'] ?? $note['username'] ?? 'Unknown') ?> &middot; <?= formatTimeAgo($note['created_at']) ?>
                            <?php if ($note['user_id'] == $_SESSION['user_id']): ?>
                            <span style="float:right;cursor:pointer;" onclick="deleteNote(<?= $note['id'] ?>, <?= $taskId ?>)" data-tooltip="Hapus komentar">🗑️</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($notes)): ?>
                    <div style="text-align:center;padding:24px;color:var(--gray-400);font-size:14px;">Belum ada komentar</div>
                    <?php endif; ?>
                </div>

                <div class="note-input-row" style="margin-top:16px;">
                    <input type="text" class="note-input" id="noteInput" placeholder="Tulis komentar..." onkeypress="if(event.key==='Enter')addNote(<?= $taskId ?>)">
                    <button class="btn btn-primary" onclick="addNote(<?= $taskId ?>)">Kirim</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">⚡ Aksi Cepat</h2>
            </div>
            <div class="card-body">
                <?php if ($task['status'] === 'todo'): ?>
                <button class="btn btn-info" style="width:100%;margin-bottom:8px;" onclick="updateStatus('in_progress')">🔄 Kerjakan</button>
                <?php elseif ($task['status'] === 'in_progress'): ?>
                <button class="btn btn-warning" style="width:100%;margin-bottom:8px;" onclick="updateStatus('todo')">↩️ Kembalikan ke To Do</button>
                <button class="btn btn-success" style="width:100%;margin-bottom:8px;" onclick="updateStatus('completed')">✅ Selesai</button>
                <?php elseif ($task['status'] === 'completed'): ?>
                <button class="btn btn-outline" style="width:100%;margin-bottom:8px;" onclick="updateStatus('todo')">🔄 Buka Kembali</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📜 Riwayat Aktivitas</h2>
            </div>
            <div class="card-body" id="activityLog" style="font-size:13px;color:var(--gray-600);">
                Loading...
            </div>
        </div>
    </div>
</div>

<style>
.note-item { background: var(--gray-50); border-radius: 8px; padding: 12px 16px; border-left: 3px solid var(--primary); margin-bottom: 8px; }
.note-text { font-size: 14px; color: var(--gray-700); white-space: pre-wrap; word-break: break-word; }
.note-meta { font-size: 11px; color: var(--gray-400); margin-top: 6px; }
.note-input-row { display: flex; gap: 8px; align-items: center; }
.note-input { flex: 1; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: var(--bg-secondary); color: var(--text-primary); outline: none; transition: border-color 0.2s; }
.note-input:focus { border-color: var(--primary); }
.notes-hidden #notesList, .notes-hidden .note-input-row { display: none; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--gray-100); }
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--gray-500); font-size: 13px; }
.info-value { font-size: 13px; font-weight: 500; }
.log-item { padding: 6px 0; border-bottom: 1px solid var(--gray-100); }
.log-item:last-child { border-bottom: none; }
.log-time { font-size: 11px; color: var(--gray-400); }
.log-text { font-size: 13px; color: var(--gray-600); }
</style>

<script>
const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
const taskId = <?= $taskId ?>;
let notesVisible = true;

function toggleNotesDisplay() {
    const section = document.getElementById('notesSection');
    const btn = document.getElementById('toggleNotesBtn');
    notesVisible = !notesVisible;
    if (notesVisible) {
        section.classList.remove('notes-hidden');
        btn.textContent = 'Sembunyikan';
    } else {
        section.classList.add('notes-hidden');
        btn.textContent = 'Tampilkan';
    }
}

function addNote(taskId) {
    const input = document.getElementById('noteInput');
    const note = input.value.trim();
    if (!note) return;

    input.disabled = true;
    input.nextElementSibling.disabled = true;

    fetch(basePath + '/api/add_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: taskId, note })
    })
    .then(r => r.json())
    .then(data => {
        input.disabled = false;
        input.nextElementSibling.disabled = false;
        if (data.success) {
            input.value = '';
            loadDetail();
        } else {
            showAlert(data.error || 'Gagal menambah komentar', 'danger');
        }
    })
    .catch(() => { input.disabled = false; input.nextElementSibling.disabled = false; });
}

function deleteNote(noteId, taskId) {
    if (!confirm('Hapus komentar ini?')) return;
    fetch(basePath + '/api/delete_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ noteId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('note-' + noteId);
            if (el) el.remove();
            loadDetail();
        } else {
            showAlert(data.error || 'Gagal hapus komentar', 'danger');
        }
    });
}

function updateStatus(newStatus) {
    fetch(basePath + '/api/update_task_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: taskId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadDetail();
        } else {
            showAlert(data.error || 'Gagal update status', 'danger');
        }
    });
}

function deleteTask(id) {
    if (!confirm('Yakin ingin menghapus tugas ini?')) return;
    fetch(basePath + '/api/delete_task.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = basePath + '/tasks.php';
        } else {
            showAlert(data.error || 'Gagal hapus tugas', 'danger');
        }
    });
}

function loadDetail() {
    fetch(basePath + '/api/get_task_detail.php?id=' + taskId)
        .then(r => r.json())
        .then(data => {
            if (data.error) { showAlert(data.error, 'danger'); return; }

            const task = data.task;
            document.getElementById('taskTitleDisplay').textContent = task.title;
            document.getElementById('taskDesc').innerHTML = task.description ? nl2br(escHtml(task.description)) : '<span style="color:var(--gray-400);">(Tidak ada deskripsi)</span>';

            const statusLabels = { todo: '📝 To Do', in_progress: '🔄 In Progress', completed: '✅ Selesai', archived: '📦 Arsip' };
            const priLabels = { low: '🟢 Rendah', medium: '🔵 Sedang', high: '🟡 Tinggi', urgent: '🔴 Urgent' };

            let infoHtml = '';
            infoHtml += '<div class="info-row"><span class="info-label">Status</span><span class="info-value">' + (statusLabels[task.status] || task.status) + '</span></div>';
            infoHtml += '<div class="info-row"><span class="info-label">Prioritas</span><span class="info-value">' + (priLabels[task.priority] || task.priority) + '</span></div>';
            infoHtml += '<div class="info-row"><span class="info-label">Pembuat</span><span class="info-value">' + escHtml(task.owner_name || task.owner_username) + '</span></div>';
            infoHtml += '<div class="info-row"><span class="info-label">Kategori</span><span class="info-value">' + (task.category_icon ? task.category_icon + ' ' : '') + escHtml(task.category_name || '-') + '</span></div>';
            infoHtml += '<div class="info-row"><span class="info-label">Deadline</span><span class="info-value">' + (task.due_date ? escHtml(formatDateID(task.due_date)) : '-') + '</span></div>';
            infoHtml += '<div class="info-row"><span class="info-label">Dibuat</span><span class="info-value">' + escHtml(formatDateID(task.created_at)) + '</span></div>';
            document.getElementById('taskInfo').innerHTML = infoHtml;

            // Notes
            const notesList = document.getElementById('notesList');
            let notesHtml = '';
            if (data.notes.length > 0) {
                data.notes.forEach(note => {
                    const user = escHtml(note.full_name || note.username || 'Unknown');
                    const text = escHtml(note.note);
                    const time = timeAgo(note.created_at);
                    const canDelete = note.user_id == data.currentUserId;
                    notesHtml += '<div class="note-item" id="note-' + note.id + '">';
                    notesHtml += '<div class="note-text">' + text + '</div>';
                    notesHtml += '<div class="note-meta">' + user + ' &middot; ' + time;
                    if (canDelete) notesHtml += ' <span style="float:right;cursor:pointer;" onclick="deleteNote(' + note.id + ',' + taskId + ')">🗑️</span>';
                    notesHtml += '</div></div>';
                });
            } else {
                notesHtml = '<div style="text-align:center;padding:24px;color:var(--gray-400);font-size:14px;">Belum ada komentar</div>';
            }
            notesList.innerHTML = notesHtml;
            document.getElementById('noteCount').textContent = data.notes.length;

            // Activity Log
            const logEl = document.getElementById('activityLog');
            if (data.logs.length > 0) {
                let logHtml = '';
                data.logs.forEach(log => {
                    const user = escHtml(log.full_name || log.username || 'Unknown');
                    const desc = escHtml(log.description || log.action);
                    const time = timeAgo(log.created_at);
                    logHtml += '<div class="log-item"><div class="log-text"><strong>' + user + '</strong> ' + desc + '</div><div class="log-time">' + time + '</div></div>';
                });
                logEl.innerHTML = logHtml;
            } else {
                logEl.innerHTML = '<div style="color:var(--gray-400);font-size:13px;">Belum ada aktivitas</div>';
            }
        });
}

function nl2br(str) { return str.replace(/\n/g, '<br>'); }
function escHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
function formatDateID(d) {
    const date = new Date(d);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
function timeAgo(datetime) {
    const now = new Date();
    const ago = new Date(datetime);
    const diff = Math.floor((now - ago) / 1000);
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' hari lalu';
    if (diff < 31536000) return Math.floor(diff / 2592000) + ' bulan lalu';
    return Math.floor(diff / 31536000) + ' tahun lalu';
}

function showAlert(msg, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    setTimeout(() => { container.innerHTML = ''; }, 3000);
}

// Load detail on page load
loadDetail();
</script>

<?php require_once 'includes/footer.php'; ?>
