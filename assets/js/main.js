/* ============================================================
   Care Home VMS – Main JavaScript
   ============================================================ */

'use strict';

/* ── Live Clock ──────────────────────────────────────────────── */
function updateClock() {
    const el = document.getElementById('live-clock');
    const dateEl = document.getElementById('live-date');
    if (!el) return;

    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    el.textContent = timeStr;
    if (dateEl) dateEl.textContent = dateStr;

    // Also update welcome banner clocks if present
    const bigTime = document.getElementById('big-time');
    const bigDate = document.getElementById('big-date');
    if (bigTime) bigTime.textContent = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    if (bigDate) bigDate.textContent = dateStr;
}
setInterval(updateClock, 1000);
updateClock();


/* ── Toast Notifications ─────────────────────────────────────── */
function showToast(message, type = 'success', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${icons[type] || '💬'}</span><span style="flex:1">${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;opacity:.7;margin-left:6px">✕</button>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 350);
    }, duration);
}

// Show any PHP flash toast passed via data attribute
document.addEventListener('DOMContentLoaded', () => {
    const flashEl = document.getElementById('php-flash');
    if (flashEl) {
        showToast(flashEl.dataset.message, flashEl.dataset.type || 'success');
    }
});


/* ── Alert Dismissal ─────────────────────────────────────────── */
document.addEventListener('click', e => {
    if (e.target.classList.contains('alert-close')) {
        e.target.closest('.alert')?.remove();
    }
});


/* ── Auto-dismiss alerts after 6s ───────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 6000);
    });
});


/* ── Live Table Search ───────────────────────────────────────── */
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr');
        let visible = 0;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const show = !q || text.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Show empty state if no results
        let emptyRow = table.querySelector('.empty-search-row');
        if (visible === 0 && q) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'empty-search-row';
                const cols = table.querySelectorAll('thead th').length;
                emptyRow.innerHTML = `<td colspan="${cols}" style="text-align:center;padding:40px;color:#96a5b0;font-weight:600">
                    🔍 No results for "<strong>${q}</strong>"</td>`;
                table.querySelector('tbody').appendChild(emptyRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }
    });
}


/* ── Autocomplete Visitor Search ─────────────────────────────── */
function initVisitorSearch(config) {
    const { inputId, listId, hiddenId, clearBtnId, endpoint } = config;
    const input     = document.getElementById(inputId);
    const list      = document.getElementById(listId);
    const hidden    = document.getElementById(hiddenId);
    const clearBtn  = document.getElementById(clearBtnId);
    if (!input || !list) return;

    let debounceTimer;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (hidden) hidden.value = '';

        if (q.length < 2) {
            list.classList.remove('open');
            list.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                list.innerHTML = '';

                if (!data.length) {
                    list.innerHTML = '<div class="autocomplete-item" style="color:#96a5b0">No visitors found. <strong>Register new?</strong></div>';
                    list.classList.add('open');
                    return;
                }

                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = `<strong>${item.full_name}</strong>
                        <small>${item.id_type}: ${item.id_number} ${item.contact_phone ? '· ' + item.contact_phone : ''}</small>`;
                    div.addEventListener('click', () => {
                        input.value = item.full_name;
                        if (hidden) hidden.value = item.id;
                        list.classList.remove('open');
                        list.innerHTML = '';
                        // Populate extra fields if available
                        fillField('visitor_phone', item.contact_phone);
                        fillField('visitor_id_type', item.id_type);
                        fillField('visitor_id_number', item.id_number);
                        if (clearBtn) clearBtn.classList.remove('hidden');
                    });
                    list.appendChild(div);
                });
                list.classList.add('open');
            } catch (err) {
                console.error('Visitor search error:', err);
            }
        }, 300);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            input.value = '';
            if (hidden) hidden.value = '';
            list.innerHTML = '';
            list.classList.remove('open');
            clearBtn.classList.add('hidden');
            clearFields(['visitor_phone','visitor_id_type','visitor_id_number']);
        });
    }

    // Close on outside click
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !list.contains(e.target)) {
            list.classList.remove('open');
        }
    });
}


/* ── Autocomplete Resident Search ────────────────────────────── */
function initResidentSearch(config) {
    const { inputId, listId, hiddenId, endpoint } = config;
    const input  = document.getElementById(inputId);
    const list   = document.getElementById(listId);
    const hidden = document.getElementById(hiddenId);
    if (!input || !list) return;

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (hidden) hidden.value = '';
        if (q.length < 1) { list.classList.remove('open'); list.innerHTML = ''; return; }

        timer = setTimeout(async () => {
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            list.innerHTML = '';
            if (!data.length) {
                list.innerHTML = '<div class="autocomplete-item" style="color:#96a5b0">No active residents found</div>';
            } else {
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = `<strong>${item.full_name}</strong>
                        <small>Room ${item.room_number} · ${item.gender || ''}</small>`;
                    div.addEventListener('click', () => {
                        input.value = item.full_name;
                        if (hidden) hidden.value = item.id;
                        list.classList.remove('open');
                    });
                    list.appendChild(div);
                });
            }
            list.classList.add('open');
        }, 250);
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !list.contains(e.target)) list.classList.remove('open');
    });
}


/* ── Helper: fill form field ─────────────────────────────────── */
function fillField(id, value) {
    const el = document.getElementById(id);
    if (!el || value === undefined || value === null) return;
    if (el.tagName === 'SELECT') {
        for (let opt of el.options) {
            if (opt.value === value || opt.text === value) { el.value = opt.value; break; }
        }
    } else {
        el.value = value || '';
    }
}

function clearFields(ids) {
    ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
}


/* ── Confirm Delete ──────────────────────────────────────────── */
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    const msg = btn.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) e.preventDefault();
});


/* ── Modal Management ────────────────────────────────────────── */
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close modal on overlay background click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
});


/* ── Password Visibility Toggle ──────────────────────────────── */
document.addEventListener('click', e => {
    const btn = e.target.closest('.toggle-eye');
    if (!btn) return;
    const input = btn.parentElement.querySelector('input');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁';
    }
});


/* ── Filter Table by Select ──────────────────────────────────── */
function initStatusFilter(selectId, tableId, colIndex) {
    const sel   = document.getElementById(selectId);
    const table = document.getElementById(tableId);
    if (!sel || !table) return;

    sel.addEventListener('change', () => {
        const val = sel.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            const cell = row.querySelectorAll('td')[colIndex];
            if (!cell) return;
            row.style.display = (!val || cell.textContent.toLowerCase().includes(val)) ? '' : 'none';
        });
    });
}


/* ── Print functionality ─────────────────────────────────────── */
function printSection(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>Print</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        </head><body style="padding:20px">${el.innerHTML}</body></html>`);
    w.document.close();
    w.print();
}


/* ── Auto-refresh dashboard stats every 60s ──────────────────── */
function startDashboardRefresh() {
    const container = document.getElementById('stat-cards');
    if (!container) return;
    setInterval(async () => {
        try {
            const res = await fetch('api/dashboard_stats.php');
            const d   = await res.json();
            Object.entries(d).forEach(([key, val]) => {
                const el = document.getElementById('stat-' + key);
                if (el) el.textContent = val;
            });
        } catch {}
    }, 60000);
}

document.addEventListener('DOMContentLoaded', startDashboardRefresh);
