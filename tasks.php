<?php
require_once 'includes/header.php';

$catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$catStmt->execute();
$categories = $catStmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title" id="pageTitle">📋 Semua Tugas</h1>
        <p class="page-subtitle" id="taskCount">Memuat...</p>
    </div>
    <a href="add_task.php" class="btn btn-primary">➕ Tugas Baru</a>
</div>

<div id="alertContainer"></div>

<!-- Filters -->
<div class="filters-bar">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari tugas..." style="width:100%;" onkeyup="debounceSearch()">
    </div>
    
    <a href="#" onclick="event.preventDefault();setFilter('status','','');setActiveStatusBtn('')" class="filter-btn active" id="btnAll">Semua</a>
    <a href="#" onclick="event.preventDefault();setFilter('status','todo','');setActiveStatusBtn('btnTodo')" class="filter-btn" id="btnTodo">📝 To Do</a>
    <a href="#" onclick="event.preventDefault();setFilter('status','in_progress','');setActiveStatusBtn('btnProgress')" class="filter-btn" id="btnProgress">🔄 Progress</a>
    <a href="#" onclick="event.preventDefault();setFilter('status','completed','');setActiveStatusBtn('btnCompleted')" class="filter-btn" id="btnCompleted">✅ Selesai</a>

    <select class="form-control" id="categorySelect" onchange="setFilter('category',this.value,'')" style="width:auto;padding:8px 40px 8px 12px;font-size:13px;">
        <option value="">🏷️ Semua Kategori</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    
    <select class="form-control" id="sortSelect" onchange="setFilter('sort',this.value,'')" style="width:auto;padding:8px 40px 8px 12px;font-size:13px;">
        <option value="newest">Terbaru</option>
        <option value="oldest">Terlama</option>
        <option value="due_date">Deadline</option>
        <option value="priority">Prioritas</option>
    </select>
</div>

<!-- Task List Container -->
<div id="taskContainer"></div>

<?php require_once 'includes/footer.php'; ?>
<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
#taskContainer .task-item { animation: fadeIn 0.3s ease; }
.loading-spinner { display: flex; justify-content: center; padding: 60px; }
.loading-spinner::after { content: ''; width: 32px; height: 32px; border: 3px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.task-notes { display: flex; flex-direction: column; gap: 6px; margin: 8px 0; }
.note-item { background: var(--gray-50); border-radius: 8px; padding: 8px 12px; border-left: 3px solid var(--primary); }
.note-text { font-size: 13px; color: var(--gray-700); white-space: pre-wrap; word-break: break-word; }
.note-meta { font-size: 11px; color: var(--gray-400); margin-top: 4px; }
.note-input-row { display: flex; gap: 6px; margin-top: 8px; align-items: center; }
.note-input { flex: 1; padding: 6px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px; background: var(--bg-secondary); color: var(--text-primary); outline: none; transition: border-color 0.2s; }
.note-input:focus { border-color: var(--primary); }
.note-btn { padding: 4px 8px; min-width: auto; font-size: 16px; }
</style>
<script>
const state = {
    status: '',
    priority: '',
    category: '',
    search: '',
    sort: 'newest',
    filter: '',
};

const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
let searchTimer = null;

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.search = document.getElementById('searchInput').value;
        loadTasks();
    }, 400);
}

function setFilter(key, value, filter) {
    state[key] = value;
    if (filter !== undefined && filter !== '') state.filter = filter;
    loadTasks();
}

function setActiveStatusBtn(id) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    const btns = { '': 'btnAll', 'todo': 'btnTodo', 'in_progress': 'btnProgress', 'completed': 'btnCompleted' };
    if (btns[state.status]) document.getElementById(btns[state.status])?.classList.add('active');
}

function loadTasks() {
    const scrollY = window.scrollY;
    const container = document.getElementById('taskContainer');
    container.innerHTML = '<div class="loading-spinner"></div>';

    const params = new URLSearchParams();
    if (state.status) params.set('status', state.status);
    if (state.priority) params.set('priority', state.priority);
    if (state.category) params.set('category', state.category);
    if (state.search) params.set('search', state.search);
    if (state.sort) params.set('sort', state.sort);
    if (state.filter) params.set('filter', state.filter);

    fetch(basePath + '/api/get_tasks.php?' + params.toString())
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON: ' + text.substring(0, 100));
            }
            if (data.error) {
                container.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }

            container.innerHTML = data.html;
            document.getElementById('pageTitle').textContent = data.title;
            document.getElementById('taskCount').textContent = data.count + ' tugas ditemukan';

            setActiveStatusBtn(state.status);
            document.getElementById('categorySelect').value = state.category;
            document.getElementById('sortSelect').value = data.sortBy || state.sort;
            document.getElementById('searchInput').value = data.searchQuery || state.search;

            const url = new URL(window.location);
            if (data.statusFilter) url.searchParams.set('status', data.statusFilter);
            else url.searchParams.delete('status');
            if (data.categoryFilter) url.searchParams.set('category', data.categoryFilter);
            else url.searchParams.delete('category');
            if (data.searchQuery) url.searchParams.set('search', data.searchQuery);
            else url.searchParams.delete('search');
            url.searchParams.set('sort', data.sortBy || state.sort);
            history.replaceState(null, '', url);

            window.scrollTo(0, scrollY);
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Gagal memuat tugas: ' + err.message + '</div>';
        });
}

function toggleTaskStatus(id, newStatus) {
    fetch(basePath + '/api/update_task_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadTasks();
        } else {
            showAlert(data.error || 'Gagal update status', 'danger');
        }
    });
}

function updateTaskStatus(id, newStatus) {
    toggleTaskStatus(id, newStatus);
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
            showAlert('🗑️ Tugas berhasil dihapus!', 'success');
            loadTasks();
        } else {
            showAlert(data.error || 'Gagal hapus tugas', 'danger');
        }
    });
}

function addNote(taskId) {
    const input = document.getElementById('noteInput-' + taskId);
    const note = input.value.trim();
    if (!note) return;

    const btn = input.nextElementSibling;
    btn.disabled = true;
    input.disabled = true;

    fetch(basePath + '/api/add_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: taskId, note })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        input.disabled = false;
        if (data.success) {
            input.value = '';
            loadTasks();
        } else {
            showAlert(data.error || 'Gagal menambah catatan', 'danger');
        }
    })
    .catch(() => { btn.disabled = false; input.disabled = false; showAlert('Gagal menambah catatan', 'danger'); });
}

function showAlert(msg, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    setTimeout(() => { container.innerHTML = ''; }, 3000);
}

// Restore state from URL
(function() {
    const params = new URLSearchParams(window.location.search);
    state.status   = params.get('status') || '';
    state.category = params.get('category') || '';
    state.search   = params.get('search') || '';
    state.sort     = params.get('sort') || 'newest';
    state.filter   = params.get('filter') || '';

    if (state.search) document.getElementById('searchInput').value = state.search;
    if (state.category) document.getElementById('categorySelect').value = state.category;
    document.getElementById('sortSelect').value = state.sort;

    loadTasks();
})();
</script>
