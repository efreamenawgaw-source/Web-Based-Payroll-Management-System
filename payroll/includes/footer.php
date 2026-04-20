        </main><!-- end .page-content -->
    </div><!-- end .main-content -->
</div><!-- end .layout -->

<script>
/* ============================================================
   Sidebar — open / close / overlay
   ============================================================ */
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.body.style.overflow = 'hidden'; // prevent background scroll
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Close sidebar when a nav link is clicked (mobile UX)
document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            closeSidebar();
        }
    });
});

// Close sidebar on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

// Re-open sidebar on resize if desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }
});

/* ============================================================
   Auto-close alerts after 5 seconds
   ============================================================ */
document.querySelectorAll('.alert').forEach(function(alert) {
    setTimeout(function() {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s';
        setTimeout(function() { alert.remove(); }, 500);
    }, 5000);
});

/* ============================================================
   Modal helpers
   ============================================================ */
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal when clicking the overlay background
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
            m.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});

/* ============================================================
   Notifications — real-time polling
   ============================================================ */
const NOTIF_API = '<?= $depth ?>api/notifications.php';

function loadNotifications() {
    fetch(NOTIF_API)
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notifCount');
            const list  = document.getElementById('notifList');
            if (!badge || !list) return;

            // Update badge
            if (data.count > 0) {
                badge.textContent = data.count > 9 ? '9+' : data.count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }

            // Render list
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = `
                    <div style="padding:32px;text-align:center;color:var(--gray-400);">
                        <i class="fas fa-bell-slash" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:8px;"></i>
                        <p style="margin:0;font-size:0.85rem;">No notifications</p>
                    </div>`;
                return;
            }

            const typeColors = {
                success: 'var(--success)', info: 'var(--info)',
                warning: 'var(--warning)', danger: 'var(--danger)'
            };
            const typeIcons = {
                success: 'fa-check-circle', info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle', danger: 'fa-times-circle'
            };

            list.innerHTML = data.notifications.map(n => `
                <div onclick="openNotif(${n.notif_id}, '${n.link || ''}')"
                     style="padding:12px 16px;border-bottom:1px solid var(--gray-200);
                            cursor:pointer;display:flex;gap:12px;align-items:flex-start;
                            background:${n.is_read == 1 ? 'var(--white)' : 'var(--bg-light)'};
                            transition:background 0.2s;"
                     onmouseover="this.style.background='var(--bg-light)'"
                     onmouseout="this.style.background='${n.is_read == 1 ? 'var(--white)' : 'var(--bg-light)'}'">
                    <div style="width:34px;height:34px;border-radius:50%;flex-shrink:0;
                                background:${typeColors[n.type] || 'var(--info)'}20;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="fas ${typeIcons[n.type] || 'fa-info-circle'}"
                           style="color:${typeColors[n.type] || 'var(--info)'};font-size:0.9rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-weight:${n.is_read == 1 ? '500' : '700'};
                                  font-size:0.85rem;margin:0 0 2px;color:var(--gray-800);">
                            ${n.title}
                        </p>
                        <p style="font-size:0.78rem;color:var(--gray-600);margin:0 0 3px;
                                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            ${n.message}
                        </p>
                        <p style="font-size:0.7rem;color:var(--gray-400);margin:0;">${n.time_ago}</p>
                    </div>
                    ${n.is_read == 0 ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:6px;"></div>' : ''}
                </div>
            `).join('');
        })
        .catch(() => {});
}

function markRead(id, el) {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('notif_id', id);
    fetch(NOTIF_API, { method: 'POST', body: fd })
        .then(() => loadNotifications());
}

function openNotif(id, link) {
    // Mark as read
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('notif_id', id);
    fetch(NOTIF_API, { method: 'POST', body: fd }).then(() => {
        loadNotifications();
        // Navigate if link provided
        if (link && link.trim() !== '') {
            // Build full URL from the link path
            const base = window.location.pathname.split('/pages/')[0];
            window.location.href = base + link;
        }
    });
}

function markAllRead() {
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fetch(NOTIF_API, { method: 'POST', body: fd })
        .then(() => loadNotifications());
}

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    const isOpen = panel.style.display === 'flex';
    panel.style.display = isOpen ? 'none' : 'flex';
    if (!isOpen) loadNotifications();
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    const panel = document.getElementById('notifPanel');
    const btn   = document.getElementById('notifBtn');
    if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.style.display = 'none';
    }
});

// Poll every 30 seconds
loadNotifications();
setInterval(loadNotifications, 30000);
</script>
</body>
</html>
