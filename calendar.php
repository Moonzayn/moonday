<?php
require_once 'includes/header.php';

$userId = getUserId();

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM tasks");
$stTotal->execute(); $statTotal = $stTotal->fetchColumn();

$stDue = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date IS NOT NULL AND status NOT IN ('completed', 'archived')");
$stDue->execute(); $statDue = $stDue->fetchColumn();

$stOver = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','archived')");
$stOver->execute(); $statOverdue = $stOver->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📅 Kalender</h1>
        <p class="page-subtitle">Lihat tugas berdasarkan tanggal</p>
    </div>
    <a href="add_task.php" class="btn btn-primary">➕ Tugas Baru</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card purple">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= $statTotal ?></div>
        <div class="stat-label">Total Tugas</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📅</div>
        <div class="stat-value"><?= $statDue ?></div>
        <div class="stat-label">Dengan Deadline</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= $statOverdue ?></div>
        <div class="stat-label">Terlambat</div>
    </div>
    <div class="stat-card orange" id="holidayStat" style="cursor:pointer;display:none" onclick="document.getElementById('holidayPanel').classList.toggle('open')">
        <div class="stat-icon">🎉</div>
        <div class="stat-value" id="holidayCount">0</div>
        <div class="stat-label">Libur Nasional</div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:20px">
        <div class="cal-nav">
            <button class="btn btn-ghost" onclick="navigateMonth(-1)">‹</button>
            <h2 class="cal-title" id="calTitle"></h2>
            <button class="btn btn-ghost" onclick="navigateMonth(1)">›</button>
            <button class="btn btn-outline btn-sm" onclick="goToday()" style="margin-left:12px">Hari Ini</button>
        </div>
        <table class="cal-table">
            <thead>
                <tr>
                    <th>Min</th><th>Sen</th><th>Sel</th><th>Rab</th><th>Kam</th><th>Jum</th><th>Sab</th>
                </tr>
            </thead>
            <tbody id="calBody"></tbody>
        </table>
    </div>
</div>

<div class="card" id="dayDetail" style="display:none;margin-top:24px">
    <div class="card-header">
        <h2 class="card-title" id="dayDetailTitle">📋 Tugas</h2>
        <button class="btn btn-ghost btn-sm" onclick="closeDayDetail()">✕</button>
    </div>
    <div class="card-body" id="dayDetailBody" style="padding:16px"></div>
</div>

<div class="holiday-panel" id="holidayPanel">
    <div class="holiday-panel-header">
        <span>🎉 Libur Nasional <span id="holidayMonth"></span></span>
        <button class="btn btn-ghost btn-sm" onclick="this.parentElement.parentElement.classList.remove('open')">✕</button>
    </div>
    <div class="holiday-panel-body" id="holidayList"></div>
</div>

<style>
.cal-nav{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.cal-nav h2{flex:1;font-size:20px;font-weight:700;color:var(--gray-900);text-align:center}

.cal-table{width:100%;border-collapse:collapse;table-layout:fixed}
.cal-table th{padding:10px 4px;font-size:12px;font-weight:600;color:var(--gray-500);text-align:center;text-transform:uppercase;border-bottom:2px solid var(--gray-100)}
.cal-table td{padding:4px;vertical-align:top;height:100px;border:1px solid var(--gray-100);background:white;transition:var(--transition);position:relative}

.cal-date{display:inline-flex;width:28px;height:28px;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:var(--gray-700);border-radius:50%;margin-bottom:2px}
.cal-today .cal-date{background:var(--primary);color:white;box-shadow:0 2px 8px rgba(99,102,241,.3)}
.cal-other .cal-date{color:var(--gray-300)}
.cal-weekend{background:var(--gray-50)}

.cal-cell{cursor:pointer;transition:var(--transition)}
.cal-cell:hover{background:var(--primary-bg)!important}
.cal-cell.selected{background:var(--primary-bg)!important;box-shadow:inset 0 0 0 2px var(--primary)}

.cal-dots{display:flex;flex-wrap:wrap;gap:3px;padding:0 2px}
.cal-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.cal-dot.todo{background:var(--gray-400)}
.cal-dot.in_progress{background:var(--info)}
.cal-dot.completed{background:var(--success)}
.cal-dot.overdue{background:var(--danger)}
.cal-more{font-size:10px;color:var(--gray-400);padding:0 2px;font-weight:500;cursor:pointer}

.day-task-item{display:flex;align-items:center;gap:10px;padding:12px;border-radius:var(--radius);border:1px solid var(--gray-100);margin-bottom:8px;transition:var(--transition);cursor:pointer}
.day-task-item:hover{box-shadow:var(--shadow-md);border-color:var(--gray-200)}
.day-task-item .dt-status{width:4px;height:36px;border-radius:2px;flex-shrink:0}
.day-task-item .dt-content{flex:1;min-width:0}
.day-task-item .dt-title{font-size:14px;font-weight:600;color:var(--gray-800)}
.day-task-item .dt-meta{font-size:11px;color:var(--gray-500);margin-top:2px;display:flex;gap:8px;align-items:center}

.cal-holiday{background:#fef2f2!important}
.cal-holiday .cal-date{background:#fee2e2;color:#991b1b}
.cal-today.cal-holiday .cal-date{background:var(--primary);color:white;box-shadow:0 2px 8px rgba(99,102,241,.3)}
.cal-holiday-label{font-size:9px;color:#ef4444;font-weight:600;padding:0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}

.holiday-panel{position:fixed;top:0;right:-380px;width:360px;height:100vh;background:white;box-shadow:-4px 0 24px rgba(0,0,0,.1);z-index:200;transition:right .35s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column}
.holiday-panel.open{right:0}
.holiday-panel-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--gray-100);font-size:16px;font-weight:700;color:var(--gray-900)}
.holiday-panel-body{flex:1;overflow-y:auto;padding:16px 24px}
.holiday-item{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid var(--gray-100)}
.holiday-item:last-child{border-bottom:none}
.holiday-date{font-size:12px;font-weight:700;color:var(--danger);white-space:nowrap;min-width:60px;padding-top:2px}
.holiday-info{flex:1}
.holiday-name{font-size:14px;font-weight:600;color:var(--gray-800)}
.holiday-desc{font-size:12px;color:var(--gray-500);margin-top:2px;line-height:1.4}
.holiday-type{display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;margin-top:4px}
.holiday-type.nasional{background:#fee2e2;color:#991b1b}
.holiday-type.cuti{background:#fef3c7;color:#92400e}

.day-holiday-banner{display:flex;align-items:flex-start;gap:10px;padding:14px 18px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius);margin-bottom:16px}
.day-holiday-banner .h-icon{font-size:24px;flex-shrink:0}
.day-holiday-banner .h-text{flex:1}
.day-holiday-banner .h-name{font-size:14px;font-weight:700;color:#991b1b}
.day-holiday-banner .h-desc{font-size:12px;color:#b91c1c;margin-top:2px}

.holiday-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.3);z-index:199;display:none}
.holiday-overlay.open{display:block}

@media(max-width:768px){
    .cal-table td{height:60px;padding:2px}
    .cal-date{width:22px;height:22px;font-size:11px}
    .cal-dots{gap:2px}
    .cal-dot{width:4px;height:4px}
    .cal-nav h2{font-size:16px}
    .holiday-panel{width:100%;right:-100%}
}
</style>

<div class="holiday-overlay" id="holidayOverlay" onclick="document.getElementById('holidayPanel').classList.remove('open');this.classList.remove('open')"></div>

<!-- Announcement Management -->
<div class="card" style="margin-top:24px">
    <div class="card-header">
        <h2 class="card-title">📢 Pengumuman</h2>
        <button class="btn btn-outline btn-sm" onclick="showAnnouncementForm()">➕ Tambah</button>
    </div>
    <div class="card-body" id="announcementListBody" style="padding:16px">
        <div class="empty-state" style="padding:20px"><p>Memuat...</p></div>
    </div>
</div>

<!-- Announcement Form Modal -->
<div class="modal-overlay" id="announcementModal" style="display:none" onclick="if(event.target===this)closeAnnouncementForm()">
    <div class="modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:18px;font-weight:700" id="announcementModalTitle">📢 Pengumuman Baru</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeAnnouncementForm()">✕</button>
        </div>
        <form id="announcementForm" onsubmit="saveAnnouncement(event)">
            <input type="hidden" name="id" id="announcementId" value="0">
            <div class="form-group">
                <label>Judul Pengumuman *</label>
                <input type="text" id="annTitle" class="form-control" placeholder="Mis: Libur Lebaran" required>
            </div>
            <div class="form-group">
                <label>Isi Pengumuman</label>
                <textarea id="annContent" class="form-control" placeholder="Detail pengumuman..." rows="3"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" id="annStart" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" id="annEnd" class="form-control" required>
                </div>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" id="annActive" checked style="width:18px;height:18px;cursor:pointer">
                <label for="annActive" style="margin:0;cursor:pointer">Aktif</label>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <button type="submit" class="btn btn-primary" id="annSaveBtn">💾 Simpan</button>
                <button type="button" class="btn btn-outline" onclick="closeAnnouncementForm()">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentMonth = <?= date('n') ?>;
let currentYear = <?= date('Y') ?>;
const today = '<?= date('Y-m-d') ?>';
var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
let taskCache = {};
let holidayCache = {};

document.addEventListener('DOMContentLoaded', () => { renderCalendar(); loadAnnouncements(); });

function navigateMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    renderCalendar();
}

function goToday() {
    currentMonth = <?= date('n') ?>;
    currentYear = <?= date('Y') ?>;
    renderCalendar();
}

async function renderCalendar() {
    const title = document.getElementById('calTitle');
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    title.textContent = months[currentMonth - 1] + ' ' + currentYear;

    await Promise.all([loadTasks(), loadHolidays()]);

    const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
    const daysInPrev = new Date(currentYear, currentMonth - 1, 0).getDate();

    const tbody = document.getElementById('calBody');
    tbody.innerHTML = '';

    let row = document.createElement('tr');
    let date = 1;
    let nextMonth = 1;

    // Previous month days
    for (let i = 0; i < firstDay; i++) {
        const cell = document.createElement('td');
        const prevDate = daysInPrev - firstDay + i + 1;
        const prevMonth = currentMonth === 1 ? 12 : currentMonth - 1;
        const prevYear = currentMonth === 1 ? currentYear - 1 : currentYear;
        const dateStr = sprintf(prevYear, prevMonth, prevDate);
        cell.className = 'cal-other';
        cell.innerHTML = `<div class="cal-date">${prevDate}</div>`;
        const dots = renderDots(taskCache[dateStr] || []);
        if (dots) cell.innerHTML += dots;
        cell.addEventListener('click', () => showDayDetail(dateStr));
        cell.title = dateStr;
        row.appendChild(cell);
    }

    // Current month days
    while (date <= daysInMonth) {
        if (row.children.length === 7) {
            tbody.appendChild(row);
            row = document.createElement('tr');
        }
        const cell = document.createElement('td');
        const dateStr = sprintf(currentYear, currentMonth, date);
        const dayOfWeek = new Date(currentYear, currentMonth - 1, date).getDay();
        const isToday = dateStr === today;
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        const isHoliday = holidayCache[dateStr];

        let cls = 'cal-cell';
        if (isToday) cls += ' cal-today';
        if (isHoliday) cls += ' cal-holiday';
        else if (isWeekend) cls += ' cal-weekend';

        cell.className = cls;
        let inner = `<div class="cal-date">${date}</div>`;
        if (isHoliday) inner += `<div class="cal-holiday-label">${isHoliday.keterangan.substring(0, 18)}</div>`;
        const dots = renderDots(taskCache[dateStr] || []);
        if (dots) inner += dots;
        cell.innerHTML = inner;
        cell.addEventListener('click', () => showDayDetail(dateStr));
        cell.title = dateStr;
        row.appendChild(cell);
        date++;
    }

    // Next month days
    const remaining = 7 - row.children.length;
    if (remaining < 7) {
        for (let i = 0; i < remaining; i++) {
            const cell = document.createElement('td');
            const nextMonthVal = currentMonth === 12 ? 1 : currentMonth + 1;
            const nextYear = currentMonth === 12 ? currentYear + 1 : currentYear;
            const dateStr = sprintf(nextYear, nextMonthVal, nextMonth);
            cell.className = 'cal-other';
            cell.innerHTML = `<div class="cal-date">${nextMonth}</div>`;
            const dots = renderDots(taskCache[dateStr] || []);
            if (dots) cell.innerHTML += dots;
            cell.addEventListener('click', () => showDayDetail(dateStr));
            cell.title = dateStr;
            row.appendChild(cell);
            nextMonth++;
        }
        tbody.appendChild(row);
    }
}

function sprintf(year, month, day) {
    const m = String(month).padStart(2, '0');
    const d = String(day).padStart(2, '0');
    return `${year}-${m}-${d}`;
}

function renderDots(tasks) {
    if (!tasks || tasks.length === 0) return '';
    const max = tasks.length > 5 ? 5 : tasks.length;
    let html = '<div class="cal-dots">';
    for (let i = 0; i < max; i++) {
        const t = tasks[i];
        let cls = t.status;
        if (t.due_date < today && t.status !== 'completed') cls = 'overdue';
        html += `<span class="cal-dot ${cls}"></span>`;
    }
    if (tasks.length > 5) html += `<span class="cal-more">+${tasks.length - 5}</span>`;
    html += '</div>';
    return html;
}

async function loadTasks() {
    const params = new URLSearchParams({ month: currentMonth, year: currentYear });
    const r = await fetch(basePath + '/api/get_calendar_tasks.php?' + params.toString());
    const data = await r.json();
    if (data.success) {
        taskCache = data.tasks || {};
    }
}

async function loadHolidays() {
    try {
        const r = await fetch(basePath + '/api/get_holidays.php?month=' + currentMonth + '&year=' + currentYear);
        if (!r.ok) return;
        const data = await r.json();
        holidayCache = {};
        data.forEach(h => { holidayCache[h.tanggal] = h; });

        // Update holiday stat
        const hCount = data.length;
        const stat = document.getElementById('holidayStat');
        if (hCount > 0) {
            stat.style.display = 'block';
            document.getElementById('holidayCount').textContent = hCount;
        } else {
            stat.style.display = 'none';
        }

        // Update holiday panel
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        document.getElementById('holidayMonth').textContent = months[currentMonth - 1] + ' ' + currentYear;

        const list = document.getElementById('holidayList');
        list.innerHTML = '';
        data.forEach(h => {
            const d = new Date(h.tanggal + 'T00:00:00');
            const dayName = d.toLocaleDateString('id-ID', { weekday: 'long' });
            const dateParts = h.tanggal.split('-');
            const formattedDate = `${dateParts[2]} ${months[parseInt(dateParts[1]) - 1].substring(0, 3)} ${dateParts[0]}`;
            const type = h.is_cuti ? 'cuti' : 'nasional';
            const typeLabel = h.is_cuti ? 'Cuti Bersama' : 'Libur Nasional';
            const desc = h.keterangan.length > 60 ? h.keterangan : (`Hari libur nasional memperingati ${h.keterangan.toLowerCase()}`);

            list.innerHTML += `<div class="holiday-item">
                <div class="holiday-date">${formattedDate}</div>
                <div class="holiday-info">
                    <div class="holiday-name">${h.keterangan}</div>
                    <div class="holiday-desc">${dayName} — ${desc}</div>
                    <span class="holiday-type ${type}">${typeLabel}</span>
                </div>
            </div>`;
        });
    } catch (e) {}
}

async function showDayDetail(dateStr) {
    if (!taskCache[dateStr]) { await loadTasks(); }
    const tasks = taskCache[dateStr] || [];
    const detail = document.getElementById('dayDetail');
    const title = document.getElementById('dayDetailTitle');
    const body = document.getElementById('dayDetailBody');

    const d = new Date(dateStr + 'T00:00:00');
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const locale = 'id-ID';
    const formatted = d.toLocaleDateString(locale, opts);
    const count = tasks.length;
    const holiday = holidayCache[dateStr];

    let header = `📋 ${formatted} — ${count} tugas`;
    if (holiday) header = `🎉 ${formatted} — ${holiday.keterangan}`;
    title.textContent = header;

    let html = '';

    // Holiday banner
    if (holiday) {
        const typeLabel = holiday.is_cuti ? 'Cuti Bersama' : 'Libur Nasional';
        html += `<div class="day-holiday-banner">
            <div class="h-icon">🎉</div>
            <div class="h-text">
                <div class="h-name">${holiday.keterangan}</div>
                <div class="h-desc">${typeLabel} — ${d.toLocaleDateString('id-ID', { weekday: 'long' })}</div>
            </div>
        </div>`;
    }

    // Tasks
    if (count === 0 && !holiday) {
        html += '<div class="empty-state" style="padding:30px"><div class="empty-icon">🎉</div><h3>Tidak ada tugas</h3><p>Belum ada tugas dengan deadline tanggal ini</p><a href="add_task.php" class="btn btn-primary">➕ Tambah Tugas</a></div>';
    } else if (count > 0) {
        html += '<div class="task-list">';
        const priorityColors = { low: '#10b981', medium: '#3b82f6', high: '#f59e0b', urgent: '#ef4444' };
        const statusLabels = { todo: 'To Do', in_progress: 'In Progress', completed: 'Selesai' };

        tasks.forEach(t => {
            const isOverdue = t.due_date < today && t.status !== 'completed';
            html += `<div class="day-task-item" onclick="window.location.href='task_detail.php?id=${t.id}'">
                <div class="dt-status" style="background:${priorityColors[t.priority] || '#94a3b8'}"></div>
                <div class="dt-content">
                    <div class="dt-title">${escHtml(t.title)}</div>
                    <div class="dt-meta">
                        <span class="status-badge status-${t.status}">${statusLabels[t.status] || t.status}</span>
                        <span class="priority-badge priority-${t.priority}">${t.priority}</span>
                        ${t.category_name ? `<span class="category-tag">${t.category_icon} ${escHtml(t.category_name)}</span>` : ''}
                        ${isOverdue ? '<span style="color:var(--danger);font-weight:600">⚠️ Terlambat</span>' : ''}
                    </div>
                </div>
                <a href="edit_task.php?id=${t.id}" class="btn btn-ghost btn-icon" data-tooltip="Edit">✏️</a>
            </div>`;
        });
        html += '</div>';
    }

    body.innerHTML = html;

    detail.style.display = 'block';
    detail.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Highlight selected cell
    document.querySelectorAll('.cal-cell').forEach(c => c.classList.remove('selected'));
    const cells = document.querySelectorAll('.cal-cell');
    for (const cell of cells) {
        const dateDiv = cell.querySelector('.cal-date');
        if (dateDiv && cell.title === dateStr) {
            cell.classList.add('selected');
            break;
        }
    }
}

function closeDayDetail() {
    document.getElementById('dayDetail').style.display = 'none';
    document.querySelectorAll('.cal-cell').forEach(c => c.classList.remove('selected'));
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ---- Announcement Management ----
async function loadAnnouncements() {
    try {
        const r = await fetch(basePath + '/api/get_announcements.php?mode=all');
        const data = await r.json();
        const body = document.getElementById('announcementListBody');
        if (!data.success || !data.data.length) {
            body.innerHTML = '<div class="empty-state" style="padding:20px"><div class="empty-icon">📢</div><h3>Belum ada pengumuman</h3><p>Buat pengumuman yang akan muncul di semua halaman</p></div>';
            return;
        }
        let html = '';
        data.data.forEach(function(a) {
            const now = new Date();
            const start = new Date(a.start_date + 'T00:00:00');
            const end = new Date(a.end_date + 'T00:00:00');
            const isActive = a.is_active == 1 && start <= now && end >= now;
            const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            var sp = a.start_date.split('-'), ep = a.end_date.split('-');
            var dateRange = parseInt(sp[2]) + ' ' + months[parseInt(sp[1])-1] + ' — ' + parseInt(ep[2]) + ' ' + months[parseInt(ep[1])-1];

            html += '<div class="ann-item" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--gray-100);border-radius:var(--radius);margin-bottom:8px;transition:var(--transition)">';
            html += '<div style="font-size:24px;flex-shrink:0">📢</div>';
            html += '<div style="flex:1;min-width:0">';
            html += '<div style="font-weight:600;font-size:14px;color:var(--gray-800)">' + escHtml(a.title) + '</div>';
            if (a.content) html += '<div style="font-size:12px;color:var(--gray-500);margin-top:2px">' + escHtml(a.content.substring(0, 80)) + '</div>';
            html += '<div style="font-size:11px;color:var(--gray-400);margin-top:4px">📅 ' + dateRange + '</div>';
            html += '</div>';
            html += '<span class="status-badge ' + (isActive ? 'status-completed' : 'status-todo') + '" style="font-size:10px">' + (isActive ? 'Aktif' : 'Tidak Aktif') + '</span>';
            html += '<button class="btn btn-ghost btn-icon" onclick="editAnnouncement(' + a.id + ',\'' + escHtml(a.title) + '\',\'' + escHtml(a.content || '') + '\',\'' + a.start_date + '\',\'' + a.end_date + '\',' + a.is_active + ')" data-tooltip="Edit">✏️</button>';
            html += '<button class="btn btn-ghost btn-icon" onclick="deleteAnnouncement(' + a.id + ')" data-tooltip="Hapus" style="color:var(--danger)">🗑️</button>';
            html += '</div>';
        });
        body.innerHTML = html;
    } catch (e) {
        document.getElementById('announcementListBody').innerHTML = '<div class="empty-state" style="padding:20px"><p>Gagal memuat pengumuman</p></div>';
    }
}

function showAnnouncementForm() {
    document.getElementById('announcementModalTitle').textContent = '📢 Pengumuman Baru';
    document.getElementById('announcementId').value = '0';
    document.getElementById('annTitle').value = '';
    document.getElementById('annContent').value = '';
    document.getElementById('annStart').value = toDateInput(new Date());
    document.getElementById('annEnd').value = toDateInput(new Date(Date.now() + 7*24*60*60*1000));
    document.getElementById('annActive').checked = true;
    document.getElementById('annSaveBtn').textContent = '💾 Simpan';
    document.getElementById('announcementModal').style.display = 'flex';
}

function editAnnouncement(id, title, content, startDate, endDate, isActive) {
    document.getElementById('announcementModalTitle').textContent = '✏️ Edit Pengumuman';
    document.getElementById('announcementId').value = id;
    document.getElementById('annTitle').value = title;
    document.getElementById('annContent').value = content;
    document.getElementById('annStart').value = startDate;
    document.getElementById('annEnd').value = endDate;
    document.getElementById('annActive').checked = isActive == 1;
    document.getElementById('annSaveBtn').textContent = '💾 Update';
    document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementForm() {
    document.getElementById('announcementModal').style.display = 'none';
}

async function saveAnnouncement(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('announcementId').value);
    const title = document.getElementById('annTitle').value.trim();
    const content = document.getElementById('annContent').value.trim();
    const startDate = document.getElementById('annStart').value;
    const endDate = document.getElementById('annEnd').value;
    const isActive = document.getElementById('annActive').checked ? 1 : 0;

    if (!title) { alert('Judul wajib diisi'); return; }
    if (!startDate || !endDate) { alert('Tanggal mulai dan selesai wajib diisi'); return; }

    const btn = document.getElementById('annSaveBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Menyimpan...';

    try {
        const r = await fetch(basePath + '/api/save_announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, title, content, start_date: startDate, end_date: endDate, is_active: isActive })
        });
        const data = await r.json();
        if (data.success) {
            closeAnnouncementForm();
            loadAnnouncements();
            showAlert('✅ Pengumuman berhasil disimpan!', 'success');
        } else {
            alert(data.error || 'Gagal menyimpan');
        }
    } catch (err) {
        alert('Gagal menyimpan pengumuman');
    }
    btn.disabled = false;
    btn.textContent = id > 0 ? '💾 Update' : '💾 Simpan';
}

async function deleteAnnouncement(id) {
    if (!confirm('Yakin ingin menghapus pengumuman ini?')) return;
    try {
        const r = await fetch(basePath + '/api/delete_announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await r.json();
        if (data.success) {
            loadAnnouncements();
            showAlert('🗑️ Pengumuman berhasil dihapus!', 'success');
        } else {
            alert(data.error || 'Gagal menghapus');
        }
    } catch (err) {
        alert('Gagal menghapus pengumuman');
    }
}

function toDateInput(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function showAlert(msg, type) {
    var c = document.getElementById('alertContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'alertContainer';
        document.querySelector('.page-header').after(c);
    }
    c.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    setTimeout(function() { c.innerHTML = ''; }, 4000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
