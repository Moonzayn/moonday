<?php
require_once 'includes/header.php';

$userId = getUserId();

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
$stTotal->execute([$userId]); $statTotal = $stTotal->fetchColumn();

$stDone = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'");
$stDone->execute([$userId]); $statDone = $stDone->fetchColumn();

$stOver = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND due_date < CURDATE() AND status NOT IN ('completed','archived')");
$stOver->execute([$userId]); $statOverdue = $stOver->fetchColumn();

$chatHistory = $_SESSION['chat_history'] ?? [];
$chatHistoryJson = json_encode($chatHistory, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$userName = htmlspecialchars(explode(' ', getUserName())[0]);
?>

<style>
.chat-page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.chat-page-header h1{font-size:28px;font-weight:800;color:var(--gray-900)}
.chat-page-header p{font-size:14px;color:var(--gray-500);margin-top:2px}

.chat-container{display:flex;flex-direction:column;height:calc(100vh - 160px);min-height:500px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-lg);border:1px solid var(--gray-200);background:var(--gray-50)}

.chat-header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:white;border-bottom:1px solid var(--gray-100);flex-shrink:0}
.chat-header-left{display:flex;align-items:center;gap:12px}
.chat-ai-avatar{width:44px;height:44px;background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 12px rgba(99,102,241,.3);animation:glow 3s ease-in-out infinite}
@keyframes glow{0%,100%{box-shadow:0 4px 12px rgba(99,102,241,.3)}50%{box-shadow:0 4px 24px rgba(99,102,241,.5)}}
.ai-name{font-size:15px;font-weight:700;color:var(--gray-900)}
.ai-status{font-size:12px;color:var(--gray-500);display:flex;align-items:center;gap:6px}
.sdot{width:8px;height:8px;border-radius:50%;background:var(--success);animation:dp 2s ease-in-out infinite;transition:all .3s}
.sdot.think{background:var(--warning);animation:db .5s ease-in-out infinite}
@keyframes dp{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
@keyframes db{0%,100%{opacity:1}50%{opacity:.2}}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{padding:4px 10px;background:var(--gray-100);border-radius:12px;font-size:11px;color:var(--gray-600);font-weight:500;white-space:nowrap}
.chip.red{background:var(--danger-bg);color:#991b1b}

.chat-messages{flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:8px;scroll-behavior:smooth}

.bwrap{display:flex;flex-direction:column;max-width:78%;animation:bpop .35s cubic-bezier(.175,.885,.32,1.275)}
.bwrap.user{align-self:flex-end;align-items:flex-end}
.bwrap.assistant{align-self:flex-start;align-items:flex-start}
@keyframes bpop{0%{opacity:0;transform:translateY(16px) scale(.95)}100%{opacity:1;transform:translateY(0) scale(1)}}

.blabel{font-size:11px;font-weight:600;margin-bottom:4px;padding:0 4px}
.bwrap.user .blabel{color:var(--primary)}
.bwrap.assistant .blabel{color:var(--gray-500)}

.bubble{padding:14px 18px;border-radius:18px;font-size:14px;line-height:1.75;word-wrap:break-word}
.bubble.user{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border-bottom-right-radius:6px;box-shadow:0 2px 12px rgba(99,102,241,.25)}
.bubble.assistant{background:white;color:var(--gray-800);border-bottom-left-radius:6px;box-shadow:var(--shadow-sm);border:1px solid var(--gray-100)}

.btime{font-size:10px;margin-top:4px;padding:0 4px;color:var(--gray-400)}
.bwrap.user .btime{text-align:right}

.bubble.assistant .aitxt{white-space:pre-wrap}
.bubble.assistant .aitxt strong{color:var(--gray-900)}

/* Typing */
.typi{align-self:flex-start;display:flex;align-items:center;gap:10px;padding:14px 18px;background:white;border-radius:18px;border-bottom-left-radius:6px;box-shadow:var(--shadow-sm);border:1px solid var(--gray-100);animation:bpop .3s ease}
.tdots{display:flex;gap:5px}
.tdot{width:8px;height:8px;background:var(--gray-400);border-radius:50%;animation:tdb 1.4s ease-in-out infinite}
.tdot:nth-child(2){animation-delay:.16s}
.tdot:nth-child(3){animation-delay:.32s}
@keyframes tdb{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-10px)}}
.tlabel{font-size:12px;color:var(--gray-400);font-style:italic}

/* Welcome */
.chat-welcome{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px}
.wicon{width:88px;height:88px;background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7);border-radius:28px;font-size:40px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;box-shadow:0 12px 32px rgba(99,102,241,.3);animation:fl 3s ease-in-out infinite}
@keyframes fl{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.chat-welcome h2{font-size:22px;font-weight:700;color:var(--gray-900);margin-bottom:8px}
.chat-welcome p{color:var(--gray-500);font-size:14px;margin-bottom:28px;max-width:420px}

.sugs{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;max-width:520px;width:100%}
.sbtn{padding:12px 16px;background:white;border:1.5px solid var(--gray-200);border-radius:14px;font-size:13px;color:var(--gray-600);cursor:pointer;transition:all .25s ease;font-family:'Inter',sans-serif;text-align:left;line-height:1.4;display:flex;align-items:center;gap:8px}
.sbtn:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-bg);transform:translateY(-3px);box-shadow:0 4px 12px rgba(99,102,241,.15)}

/* Input */
.chat-input-area{padding:16px 24px 20px;background:white;border-top:1px solid var(--gray-100);flex-shrink:0}
.irow{display:flex;gap:12px;align-items:flex-end}
.ifield{flex:1;position:relative}
.cinput{width:100%;padding:14px 50px 14px 18px;border:2px solid var(--gray-200);border-radius:16px;font-size:14px;font-family:'Inter',sans-serif;resize:none;min-height:52px;max-height:140px;transition:all .25s ease;background:var(--gray-50);line-height:1.5;color:var(--gray-800)}
.cinput:focus{outline:none;border-color:var(--primary);background:white;box-shadow:0 0 0 4px rgba(99,102,241,.1)}
.cinput:disabled{opacity:.6;cursor:not-allowed}
.ccnt{position:absolute;right:14px;bottom:8px;font-size:10px;color:var(--gray-400);pointer-events:none}

/* Send Button — NO SPIN, just disabled */
.sendbtn{
    width:52px;height:52px;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    border:none;border-radius:16px;color:white;font-size:20px;
    cursor:pointer;transition:all .2s ease;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;box-shadow:0 4px 12px rgba(99,102,241,.35);
}
.sendbtn:hover:not(:disabled){
    transform:translateY(-2px) scale(1.05);
    box-shadow:0 6px 20px rgba(99,102,241,.45);
}
.sendbtn:active:not(:disabled){transform:scale(.95)}
.sendbtn:disabled{
    opacity:.45;
    cursor:not-allowed;
    transform:none !important;
    box-shadow:0 2px 8px rgba(99,102,241,.15);
}

.ihint{font-size:11px;color:var(--gray-400);margin-top:8px;text-align:center}

/* Toast */
.tbox{position:fixed;top:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast{background:white;border:1px solid #fca5a5;border-left:4px solid var(--danger);border-radius:var(--radius);padding:14px 20px;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:10px;font-size:13px;color:#991b1b;animation:tsin .4s ease;max-width:400px}
@keyframes tsin{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
.toast.out{animation:tsout .3s ease forwards}
@keyframes tsout{to{opacity:0;transform:translateX(40px)}}

@media(max-width:768px){
    .chat-container{height:calc(100vh - 120px);min-height:400px}
    .chat-messages{padding:16px}
    .bwrap{max-width:90%}
    .sugs{grid-template-columns:1fr}
    .chips{display:none}
}
</style>

<div class="chat-page-header">
    <div>
        <h1>🤖 AI Assistant</h1>
        <p>Chat cerdas yang memahami semua tugas kamu</p>
    </div>
    <button onclick="clearChat()" class="btn btn-outline btn-sm">🗑️ Hapus Chat</button>
</div>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-left">
            <div class="chat-ai-avatar">🤖</div>
            <div>
                <div class="ai-name">moonday AI</div>
                <div class="ai-status">
                    <span class="sdot" id="sDot"></span>
                    <span id="sTxt">Online</span>
                </div>
            </div>
        </div>
        <div class="chips">
            <span class="chip">📋 <?= $statTotal ?> tugas</span>
            <span class="chip">✅ <?= $statDone ?> selesai</span>
            <?php if($statOverdue > 0): ?>
            <span class="chip red">⚠️ <?= $statOverdue ?> terlambat</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-messages" id="msgBox"></div>

    <div class="chat-input-area">
        <div class="irow">
            <div class="ifield">
                <textarea id="inp" class="cinput" placeholder="Tanya tentang tugas kamu..." rows="1" maxlength="2000"></textarea>
                <span class="ccnt" id="cnt"></span>
            </div>
            <button id="sBtn" class="sendbtn" onclick="send()" title="Kirim">➤</button>
        </div>
        <div class="ihint">Enter = kirim · Shift+Enter = baris baru</div>
    </div>
</div>

<div class="tbox" id="tBox"></div>

<script>
let hist = <?= $chatHistoryJson ?>;
let busy = false;
const ME = "<?= $userName ?>";
const API = 'ai_chat_api.php';

const SUGS = [
    {i:"📋",t:"Ringkasan semua tugas saya"},
    {i:"⚠️",t:"Tugas apa yang terlambat?"},
    {i:"📅",t:"Apa yang harus dikerjakan hari ini?"},
    {i:"🔥",t:"Tugas prioritas tinggi yang belum selesai?"},
    {i:"📊",t:"Bagaimana produktivitas saya minggu ini?"},
    {i:"💡",t:"Saran untuk meningkatkan produktivitas"},
    {i:"🏷️",t:"Kategori mana paling banyak pending?"},
    {i:"📈",t:"Analisis lengkap progress saya"},
];

document.addEventListener('DOMContentLoaded', () => { render(); initInp(); });

function render() {
    const b = document.getElementById('msgBox');
    if (!hist.length) { b.innerHTML = welcome(); return; }
    b.innerHTML = '';
    hist.forEach(m => b.appendChild(mkBubble(m)));
    scroll();
}

function welcome() {
    const cards = SUGS.map(s =>
        `<button class="sbtn" onclick="quick('${s.t.replace(/'/g, "\\'")}')">
            <span style="font-size:18px;flex-shrink:0">${s.i}</span><span>${s.t}</span>
        </button>`
    ).join('');
    return `<div class="chat-welcome" id="wel">
        <div class="wicon">✨</div>
        <h2>Halo, ${ME}! 👋</h2>
        <p>Saya moonday AI — asisten yang memahami semua tugas, deadline, dan produktivitas kamu.</p>
        <div class="sugs">${cards}</div>
    </div>`;
}

function mkBubble(m) {
    const w = document.createElement('div'); w.className = `bwrap ${m.role}`;
    const l = document.createElement('div'); l.className = 'blabel';
    l.textContent = m.role === 'user' ? 'Kamu' : '🤖 moonday AI';
    const b = document.createElement('div'); b.className = `bubble ${m.role}`;
    if (m.role === 'assistant') {
        const x = document.createElement('div'); x.className = 'aitxt';
        x.innerHTML = fmt(m.content); b.appendChild(x);
    } else { b.textContent = m.content; }
    const t = document.createElement('div'); t.className = 'btime'; t.textContent = m.time || '';
    w.appendChild(l); w.appendChild(b); w.appendChild(t); return w;
}

function fmt(t) {
    let h = esc(t);
    h = h.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    h = h.replace(/__(.*?)__/g, '<strong>$1</strong>');
    h = h.replace(/`(.*?)`/g, '<code style="background:var(--gray-100);padding:1px 5px;border-radius:4px;font-size:13px">$1</code>');
    h = h.replace(/\n/g, '<br>');
    return h;
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// Typing
function showTyp() {
    hideTyp();
    const e = document.createElement('div'); e.className = 'typi'; e.id = 'typEl';
    e.innerHTML = '<div class="tdots"><div class="tdot"></div><div class="tdot"></div><div class="tdot"></div></div><span class="tlabel">AI sedang berpikir...</span>';
    document.getElementById('msgBox').appendChild(e); scroll();
}
function hideTyp() { const e = document.getElementById('typEl'); if (e) e.remove(); }

// Typewriter
function typewr(el, text, spd = 8) {
    return new Promise(res => {
        const html = fmt(text);
        const tgt = el.querySelector('.aitxt');
        if (!tgt) { el.innerHTML = html; res(); return; }
        tgt.innerHTML = '';
        let i = 0; const step = 3;
        function tick() {
            if (i < html.length) {
                let end = Math.min(i + step, html.length);
                const partial = html.substring(0, end);
                const opens = (partial.match(/</g) || []).length;
                const closes = (partial.match(/>/g) || []).length;
                if (opens > closes) { const nx = html.indexOf('>', end); if (nx !== -1) end = nx + 1; }
                tgt.innerHTML = html.substring(0, end); i = end; scroll();
                requestAnimationFrame(() => setTimeout(tick, spd));
            } else { tgt.innerHTML = html; res(); }
        }
        tick();
    });
}

// Send — no spinning, just disabled
async function send() {
    const inp = document.getElementById('inp');
    const txt = inp.value.trim();
    if (!txt || busy) return;

    busy = true;
    setUI(true);

    const wel = document.getElementById('wel'); if (wel) wel.remove();

    const um = { role: 'user', content: txt, time: now() };
    hist.push(um);
    document.getElementById('msgBox').appendChild(mkBubble(um));

    inp.value = ''; inp.style.height = 'auto'; updCnt(); scroll();
    showTyp();

    try {
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('message', txt);

        const r = await fetch(API, { method: 'POST', body: fd });
        const raw = await r.text();
        hideTyp();

        let data;
        try { data = JSON.parse(raw); }
        catch (pe) {
            const jm = raw.match(/\{[\s\S]*\}$/);
            if (jm) { try { data = JSON.parse(jm[0]); } catch (e2) { data = null; } }
            if (!data) { toast('Server mengembalikan respons tidak valid'); hist.pop(); busy = false; setUI(false); inp.focus(); return; }
        }

        if (data.error) {
            toast(data.error);
            hist.pop();
        } else if (data.reply) {
            const am = { role: 'assistant', content: data.reply, time: data.time || now() };
            hist.push(am);
            const ab = mkBubble(am);
            document.getElementById('msgBox').appendChild(ab);
            await typewr(ab.querySelector('.bubble'), data.reply, 8);
        } else {
            toast('Respons tidak dikenali');
            hist.pop();
        }
    } catch (err) {
        hideTyp();
        toast('Gagal terhubung ke server. Periksa koneksi.');
        hist.pop();
    }

    busy = false;
    setUI(false);
    inp.focus();
    scroll();
}

function quick(t) { document.getElementById('inp').value = t; send(); }

async function clearChat() {
    if (!confirm('Hapus semua riwayat chat?')) return;
    try { const fd = new FormData(); fd.append('action', 'clear'); await fetch(API, { method: 'POST', body: fd }); } catch (e) {}
    hist = []; render();
}

// UI: hanya disabled/enabled, tanpa animasi spin
function setUI(loading) {
    const btn = document.getElementById('sBtn');
    const inp = document.getElementById('inp');
    const dot = document.getElementById('sDot');
    const stxt = document.getElementById('sTxt');

    btn.disabled = loading;
    inp.disabled = loading;

    if (loading) {
        dot.classList.add('think');
        stxt.textContent = 'Berpikir...';
    } else {
        dot.classList.remove('think');
        stxt.textContent = 'Online';
    }
}

function scroll() {
    const e = document.getElementById('msgBox');
    requestAnimationFrame(() => e.scrollTo({ top: e.scrollHeight, behavior: 'smooth' }));
}

function now() { return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); }

function updCnt() {
    const i = document.getElementById('inp'), c = document.getElementById('cnt'), l = i.value.length;
    c.textContent = l > 0 ? l + '/2000' : '';
    c.style.color = l > 1800 ? 'var(--danger)' : 'var(--gray-400)';
}

function toast(m) {
    const b = document.getElementById('tBox'), e = document.createElement('div');
    e.className = 'toast'; e.innerHTML = `⚠️ <span>${esc(m)}</span>`;
    b.appendChild(e);
    setTimeout(() => { e.classList.add('out'); setTimeout(() => e.remove(), 300); }, 6000);
}

function initInp() {
    const i = document.getElementById('inp');
    i.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 140) + 'px';
        updCnt();
    });
    i.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    i.focus();
}
</script>

<?php require_once 'includes/footer.php'; ?>