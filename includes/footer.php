    </main>
</div>

<div id="holidayToast" class="float-toast float-toast-holiday" style="display:none">
    <div class="float-toast-inner">
        <div class="float-toast-icon">🎉</div>
        <div class="float-toast-body">
            <div class="float-toast-title" id="holidayToastTitle"></div>
            <div class="float-toast-list" id="holidayToastList"></div>
        </div>
        <button class="float-toast-close" onclick="dismissFloat('holidayToast','moonday_holiday_dismissed')">✕</button>
    </div>
</div>

<div id="announceToastContainer" style="position:fixed;top:16px;left:16px;z-index:9998;display:flex;flex-direction:column;gap:8px;max-width:380px;width:100%;pointer-events:none"></div>

<style>
.float-toast{position:fixed;z-index:9999;max-width:420px;width:100%;animation:floatIn .45s cubic-bezier(.175,.885,.32,1.275);box-shadow:0 12px 40px rgba(0,0,0,.15)}
.float-toast-holiday{top:16px;right:16px}
@keyframes floatIn{0%{opacity:0;transform:translateX(40px) scale(.95)}100%{opacity:1;transform:translateX(0) scale(1)}}
@keyframes floatOut{0%{opacity:1;transform:translateX(0)}100%{opacity:0;transform:translateX(40px)}}
.float-toast-inner{display:flex;align-items:flex-start;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.12)}
.float-toast-holiday .float-toast-inner{border-left:4px solid #ef4444}
.float-toast-icon{font-size:28px;flex-shrink:0;line-height:1}
.float-toast-body{flex:1;min-width:0}
.float-toast-title{font-size:14px;font-weight:700;color:#991b1b;margin-bottom:6px}
.float-toast-list{font-size:13px;color:var(--gray-700);line-height:1.6}
.float-toast-item{display:flex;align-items:center;gap:6px;padding:2px 0}
.float-toast-dot{width:6px;height:6px;border-radius:50%;background:#ef4444;flex-shrink:0}
.float-toast-close{width:28px;height:28px;border:none;background:#f1f5f9;border-radius:50%;cursor:pointer;font-size:12px;color:var(--gray-500);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;pointer-events:auto}
.float-toast-close:hover{background:#e2e8f0;color:var(--gray-700)}

.ann-toast{animation:annIn .45s cubic-bezier(.175,.885,.32,1.275);pointer-events:auto;width:100%}
@keyframes annIn{0%{opacity:0;transform:translateX(-40px) scale(.95)}100%{opacity:1;transform:translateX(0) scale(1)}}
@keyframes annOut{0%{opacity:1;transform:translateX(0)}100%{opacity:0;transform:translateX(-40px)}}
.ann-toast .float-toast-inner{border-left:4px solid #6366f1}
.ann-toast .float-toast-title{color:#4338ca}
</style>

<script>
// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuBtn) {
        sidebar.classList.remove('open');
    }
});

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(function(alert) {
    setTimeout(function() {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(function() { alert.remove(); }, 300);
    }, 4000);
});

// Dismiss holiday alert (from header)
function dismissHolidayAlert() {
    const el = document.getElementById('holidayAlert');
    if (el) {
        el.style.transition = 'all .3s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-10px)';
        setTimeout(function() { el.remove(); }, 300);
    }
}

// --- Shared helpers ---
var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
var _months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
var _days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

function fmtDate(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    var p = dateStr.split('-');
    return _days[d.getDay()] + ', ' + parseInt(p[2]) + ' ' + _months[parseInt(p[1])-1];
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function toDateStr(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function dismissFloat(elId, storageKey) {
    var el = document.getElementById(elId);
    if (el) {
        el.style.animation = 'floatOut .35s ease forwards';
        setTimeout(function() { el.style.display = 'none'; }, 350);
    }
    if (storageKey) { try { localStorage.setItem(storageKey, '1'); } catch(e) {} }
}

// --- Holiday toast ---
(function() {
    if (localStorage.getItem('moonday_holiday_dismissed') === '1') return;
    var now = new Date();
    fetch(basePath + '/api/get_holidays.php?month=' + (now.getMonth()+1) + '&year=' + now.getFullYear())
        .then(function(r) { if (!r.ok) throw new Error(); return r.json(); })
        .then(function(data) {
            if (!data || !data.length) return;
            var upcoming = data.filter(function(h) { return h.tanggal >= toDateStr(now); });
            if (!upcoming.length) return;

            var toast = document.getElementById('holidayToast');
            document.getElementById('holidayToastTitle').textContent = '🎉 ' + data.length + ' tanggal merah bulan ini';
            var html = '';
            upcoming.slice(0, 5).forEach(function(h) {
                html += '<div class="float-toast-item"><span class="float-toast-dot"></span><span><strong>' + fmtDate(h.tanggal) + '</strong> — ' + escHtml(h.keterangan) + '</span></div>';
            });
            document.getElementById('holidayToastList').innerHTML = html;
            toast.style.display = 'block';

            setTimeout(function() {
                if (toast.style.display !== 'none') dismissFloat('holidayToast');
            }, 12000);
        })
        .catch(function() {});
})();

// --- Announcement toasts ---
(function() {
    var dismissed;
    try { dismissed = JSON.parse(localStorage.getItem('moonday_announce_dismissed') || '[]'); } catch(e) { dismissed = []; }

    fetch(basePath + '/api/get_announcements.php?mode=active')
        .then(function(r) { if (!r.ok) throw new Error(); return r.json(); })
        .then(function(data) {
            if (!data.success || !data.data.length) return;

            var container = document.getElementById('announceToastContainer');
            data.data.forEach(function(a) {
                if (dismissed.indexOf(a.id) !== -1) return;

                var el = document.createElement('div');
                el.className = 'ann-toast';
                el.id = 'annToast-' + a.id;
                el.innerHTML = '<div class="float-toast-inner"><div class="float-toast-icon">📢</div><div class="float-toast-body"><div class="float-toast-title">' + escHtml(a.title) + '</div><div class="float-toast-list">' + (a.content ? escHtml(a.content) : '') + '</div></div><button class="float-toast-close" onclick="dismissAnnounce(' + a.id + ')">✕</button></div>';
                container.appendChild(el);

                // Auto dismiss after 15s (tidak simpan ke localStorage)
                setTimeout(function() { dismissAnnounce(a.id, false); }, 15000);
            });
        })
        .catch(function() {});
})();

function dismissAnnounce(id, persist) {
    if (persist === undefined) persist = true;
    var el = document.getElementById('annToast-' + id);
    if (el) {
        el.style.animation = 'annOut .35s ease forwards';
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
    }
    if (persist) {
        try {
            var arr = JSON.parse(localStorage.getItem('moonday_announce_dismissed') || '[]');
            if (arr.indexOf(id) === -1) arr.push(id);
            localStorage.setItem('moonday_announce_dismissed', JSON.stringify(arr));
        } catch(e) {}
    }
}
</script>
</body>
</html>