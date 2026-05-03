    </main>
</div>

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
</script>
</body>
</html>