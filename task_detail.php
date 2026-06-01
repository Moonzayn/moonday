<?php
// === Task lookup before any output ===
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($taskId <= 0) { header('Location: tasks.php'); exit; }

$stmt = $pdo->prepare("SELECT t.*, u.full_name as owner_name, u.username as owner_username, c.name as category_name, c.color as category_color, c.icon as category_icon FROM tasks t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) { header('Location: tasks.php'); exit; }

// === Now safe to render ===
require_once 'includes/header.php';
?>

<div class="page-header">
    <div>
        <a href="tasks.php" style="font-size:14px;color:var(--gray-500);text-decoration:none;margin-bottom:8px;display:inline-block;">&larr; Kembali ke Daftar Tugas</a>
        <h1 class="page-title" style="margin-top:4px;" id="taskTitleDisplay"><?= htmlspecialchars($task['title']) ?></h1>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="edit_task.php?id=<?= $taskId ?>" class="btn btn-outline">&#9998;&#65039; Edit</a>
        <button class="btn btn-outline" id="deleteBtn" style="color:var(--danger);border-color:var(--danger);">&#128465;&#65039; Hapus</button>
    </div>
</div>

<div id="alertContainer"></div>

<div class="grid-2" style="grid-template-columns:2fr 1fr;gap:24px;">
    <div>
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">&#128203; Informasi Tugas</h2>
            </div>
            <div class="card-body" id="taskInfo">Memuat...</div>
        </div>

        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">&#128221; Deskripsi</h2>
            </div>
            <div class="card-body">
                <div id="taskDesc" style="white-space:pre-wrap;color:var(--gray-700);line-height:1.6;"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">&#128172; Komentar (<span id="noteCount">0</span>)</h2>
                <button class="btn btn-ghost btn-sm" id="toggleNotesBtn">Sembunyikan</button>
            </div>
            <div class="card-body" id="notesSection">
                <div id="notesList"></div>
                <div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
                    <input type="text" id="noteInput" placeholder="Tulis komentar..." onkeypress="if(event.key==='Enter')window.addNote()">
                    <button class="btn btn-primary" id="sendNoteBtn">Kirim</button>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">&#9889;&#65039; Aksi Cepat</h2>
            </div>
            <div class="card-body" id="quickActions"></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">&#128218; Riwayat Aktivitas</h2>
            </div>
            <div class="card-body" id="activityLog" style="font-size:13px;color:var(--gray-600);">Memuat...</div>
        </div>
    </div>
</div>

<style>
.note-item{background:var(--gray-50);border-radius:8px;padding:12px 16px;border-left:3px solid var(--primary);margin-bottom:8px}
.note-text{font-size:14px;color:var(--gray-700);white-space:pre-wrap;word-break:break-word}
.note-meta{font-size:11px;color:var(--gray-400);margin-top:6px}
#noteInput{flex:1;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:var(--bg-secondary);color:var(--text-primary);outline:none}
#noteInput:focus{border-color:var(--primary)}
.notes-hidden #notesList,.notes-hidden #noteInput,.notes-hidden #sendNoteBtn{display:none}
.btn-sm{padding:4px 10px;font-size:12px}
.info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100)}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--gray-500);font-size:13px}
.info-value{font-size:13px;font-weight:500}
.log-item{padding:6px 0;border-bottom:1px solid var(--gray-100)}
.log-item:last-child{border-bottom:none}
.log-time{font-size:11px;color:var(--gray-400)}
.log-text{font-size:13px;color:var(--gray-600)}
</style>

<script>
(function() {
    var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    var taskId = <?= (int)$taskId ?>;
    var currentUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    var notesVisible = true;

    function esc(str) { var d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
    function nl2br(str) { return (str || '').replace(/\n/g, '<br>'); }
    function timeAgo(dt) {
        var now = new Date(), ago = new Date(dt), diff = Math.floor((now - ago) / 1000);
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
        if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' hari lalu';
        if (diff < 31536000) return Math.floor(diff / 2592000) + ' bulan lalu';
        return Math.floor(diff / 31536000) + ' tahun lalu';
    }
    function formatDateID(d) {
        var date = new Date(d);
        var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
    }
    function showAlert(msg, type) {
        var c = document.getElementById('alertContainer');
        c.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
        setTimeout(function() { c.innerHTML = ''; }, 3000);
    }

    function toggleNotesDisplay() {
        var section = document.getElementById('notesSection');
        var btn = document.getElementById('toggleNotesBtn');
        notesVisible = !notesVisible;
        if (notesVisible) {
            section.classList.remove('notes-hidden');
            btn.textContent = 'Sembunyikan';
        } else {
            section.classList.add('notes-hidden');
            btn.textContent = 'Tampilkan';
        }
    }
    document.getElementById('toggleNotesBtn').onclick = toggleNotesDisplay;

    function addNote() {
        var input = document.getElementById('noteInput');
        var btn = document.getElementById('sendNoteBtn');
        var note = input.value.trim();
        if (!note) return;
        input.disabled = true; btn.disabled = true;
        fetch(basePath + '/api/add_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, note: note })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            input.disabled = false; btn.disabled = false;
            if (data.success) { input.value = ''; loadDetail(); }
            else showAlert(data.error || 'Gagal', 'danger');
        })
        .catch(function() { input.disabled = false; btn.disabled = false; });
    }
    document.getElementById('sendNoteBtn').onclick = addNote;
    window.addNote = addNote;

    function deleteNote(noteId) {
        if (!confirm('Hapus komentar ini?')) return;
        fetch(basePath + '/api/delete_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ noteId: noteId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) loadDetail();
            else showAlert(data.error || 'Gagal', 'danger');
        });
    }
    window.deleteNote = deleteNote;

    function updateStatus(newStatus) {
        fetch(basePath + '/api/update_task_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, status: newStatus })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showAlert(data.message, 'success'); loadDetail(); }
            else showAlert(data.error || 'Gagal', 'danger');
        });
    }
    window.updateStatus = updateStatus;

    function deleteTaskFn() {
        if (!confirm('Yakin ingin menghapus tugas ini?')) return;
        fetch(basePath + '/api/delete_task.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) window.location.href = basePath + '/tasks.php';
            else showAlert(data.error || 'Gagal', 'danger');
        });
    }
    document.getElementById('deleteBtn').onclick = deleteTaskFn;

    function loadDetail() {
        fetch(basePath + '/api/get_task_detail.php?id=' + taskId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) { showAlert(data.error, 'danger'); return; }
                var task = data.task;
                document.title = task.title + ' - Moonday';

                // Info
                var sL = { todo: '&#128221; To Do', in_progress: '&#128260; In Progress', completed: '&#9989; Selesai', archived: '&#128230; Arsip' };
                var pL = { low: '&#128994; Rendah', medium: '&#128308; Sedang', high: '&#128992; Tinggi', urgent: '&#128308; Urgent' };
                var info = '';
                info += '<div class="info-row"><span class="info-label">Status</span><span class="info-value">' + (sL[task.status] || task.status) + '</span></div>';
                info += '<div class="info-row"><span class="info-label">Prioritas</span><span class="info-value">' + (pL[task.priority] || task.priority) + '</span></div>';
                info += '<div class="info-row"><span class="info-label">Pembuat</span><span class="info-value">' + esc(task.owner_name || task.owner_username) + '</span></div>';
                info += '<div class="info-row"><span class="info-label">Kategori</span><span class="info-value">' + (task.category_icon ? task.category_icon + ' ' : '') + esc(task.category_name || '-') + '</span></div>';
                info += '<div class="info-row"><span class="info-label">Deadline</span><span class="info-value">' + (task.due_date ? formatDateID(task.due_date) : '-') + '</span></div>';
                info += '<div class="info-row"><span class="info-label">Dibuat</span><span class="info-value">' + formatDateID(task.created_at) + '</span></div>';
                document.getElementById('taskInfo').innerHTML = info;

                // Desc
                document.getElementById('taskDesc').innerHTML = task.description ? nl2br(esc(task.description)) : '<span style="color:var(--gray-400);">(Tidak ada deskripsi)</span>';

                // Quick Actions
                var qa = '';
                if (task.status === 'todo') qa = '<button class="btn btn-info" style="width:100%;margin-bottom:8px;" onclick="updateStatus(\'in_progress\')">&#128260; Kerjakan</button>';
                else if (task.status === 'in_progress') {
                    qa = '<button class="btn btn-warning" style="width:100%;margin-bottom:8px;" onclick="updateStatus(\'todo\')">&#8617;&#65039; Kembalikan ke To Do</button>';
                    qa += '<button class="btn btn-success" style="width:100%;margin-bottom:8px;" onclick="updateStatus(\'completed\')">&#9989; Selesai</button>';
                } else if (task.status === 'completed') {
                    qa = '<button class="btn btn-outline" style="width:100%;margin-bottom:8px;" onclick="updateStatus(\'todo\')">&#128260; Buka Kembali</button>';
                }
                document.getElementById('quickActions').innerHTML = qa;

                // Notes
                var nHtml = '';
                if (data.notes.length > 0) {
                    data.notes.forEach(function(note) {
                        var user = esc(note.full_name || note.username || 'Unknown');
                        var text = esc(note.note);
                        var time = timeAgo(note.created_at);
                        var canDel = note.user_id == currentUserId;
                        nHtml += '<div class="note-item" id="note-' + note.id + '">';
                        nHtml += '<div class="note-text">' + text + '</div>';
                        nHtml += '<div class="note-meta">' + user + ' &middot; ' + time;
                        if (canDel) nHtml += ' <span style="float:right;cursor:pointer;" onclick="deleteNote(' + note.id + ')">&#128465;&#65039;</span>';
                        nHtml += '</div></div>';
                    });
                } else {
                    nHtml = '<div style="text-align:center;padding:24px;color:var(--gray-400);font-size:14px;">Belum ada komentar</div>';
                }
                document.getElementById('notesList').innerHTML = nHtml;
                document.getElementById('noteCount').textContent = data.notes.length;

                // Activity Log
                var logEl = document.getElementById('activityLog');
                if (data.logs.length > 0) {
                    var lHtml = '';
                    data.logs.forEach(function(log) {
                        var user = esc(log.full_name || log.username || 'Unknown');
                        var desc = esc(log.description || log.action);
                        lHtml += '<div class="log-item"><div class="log-text"><strong>' + user + '</strong> ' + desc + '</div><div class="log-time">' + timeAgo(log.created_at) + '</div></div>';
                    });
                    logEl.innerHTML = lHtml;
                } else {
                    logEl.innerHTML = '<div style="color:var(--gray-400);font-size:13px;">Belum ada aktivitas</div>';
                }
            })
            .catch(function(err) {
                document.getElementById('taskInfo').innerHTML = 'Gagal memuat: ' + err.message;
            });
    }

    loadDetail();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
