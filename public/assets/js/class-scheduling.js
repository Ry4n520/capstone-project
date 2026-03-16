/**
 * Class Scheduling – Admin page JS
 */

const csConfig = window.csConfig || { todayIso: '' };

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

let currentWeekStart    = null;   // Y-m-d ISO string
let currentTimetableData = null;
let currentConflicts    = new Set();
let activeFilter        = 'all';
let pendingRejectId     = null;

/* ── HELPERS ──────────────────────────────────────────────────── */

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = (text == null) ? '' : String(text);
    return div.innerHTML;
}

function formatTime(t) {
    if (!t) { return ''; }
    const [h, m] = String(t).split(':');
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const dh   = hour % 12 || 12;
    return `${dh}:${m} ${ampm}`;
}

function isoToShort(iso) {
    if (!iso) { return ''; }
    return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function isoToLong(iso) {
    if (!iso) { return ''; }
    return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function toIso(d) {
    return d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0');
}

function addDays(isoStr, n) {
    const d = new Date(isoStr + 'T00:00:00');
    d.setDate(d.getDate() + n);
    return toIso(d);
}

function getMondayIso() {
    const today = new Date();
    const day   = today.getDay();
    const diff  = day === 0 ? -6 : 1 - day;
    today.setDate(today.getDate() + diff);
    today.setHours(0, 0, 0, 0);
    return toIso(today);
}

/* ── CONFLICT DETECTION ───────────────────────────────────────── */

function detectConflicts(timetableObj) {
    const ids = new Set();
    DAYS.forEach(day => {
        const classes = (timetableObj && timetableObj[day]) || [];
        for (let i = 0; i < classes.length; i++) {
            for (let j = i + 1; j < classes.length; j++) {
                const a = classes[i];
                const b = classes[j];
                if (a.room_id && b.room_id && a.room_id === b.room_id) {
                    if (a.start_time < b.end_time && b.start_time < a.end_time) {
                        ids.add(a.timetable_id);
                        ids.add(b.timetable_id);
                    }
                }
            }
        }
    });
    return ids;
}

/* ── STATS ────────────────────────────────────────────────────── */

function updateStats(timetableObj, conflicts) {
    let total = 0;
    let released = 0;
    DAYS.forEach(day => {
        const classes = (timetableObj && timetableObj[day]) || [];
        total += classes.length;
        released += classes.filter(c => c.status === 'released').length;
    });
    document.getElementById('statTotal').textContent     = total;
    document.getElementById('statReleased').textContent  = released;
    document.getElementById('statConflicts').textContent = conflicts.size;
}

function updateRequestStat(count) {
    document.getElementById('statRequests').textContent = count;
    const badge = document.getElementById('requestsBadge');
    if (count > 0) {
        badge.textContent    = count + ' Pending';
        badge.style.display  = '';
    } else {
        badge.style.display  = 'none';
    }
}

/* ── TIMETABLE RENDERING ──────────────────────────────────────── */

function statusBadge(status, isConflict) {
    if (isConflict) {
        return '<span class="cs-badge cs-badge-conflict">⚠ Conflict</span>';
    }
    if (status === 'released')  { return '<span class="cs-badge cs-badge-released">Released</span>'; }
    if (status === 'cancelled') { return '<span class="cs-badge cs-badge-cancelled">Cancelled</span>'; }
    return '<span class="cs-badge cs-badge-pending">Pending</span>';
}

function kebabMenu(course) {
    const tid = course.timetable_id;
    return `
        <div class="cs-dropdown-wrapper">
            <button type="button" class="cs-kebab-btn" onclick="toggleDropdown(this)" title="Row actions" aria-haspopup="true" aria-expanded="false">⋮</button>
            <div class="cs-dropdown-menu">
                <div class="cs-dropdown-item" onclick="cancelEntry(${tid},'cancel')">🔁 Cancel This Week</div>
                <div class="cs-dropdown-item" onclick="cancelEntry(${tid},'cancel_rest')">🚫 Cancel Rest of Semester</div>
                <div class="cs-dropdown-divider"></div>
                <div class="cs-dropdown-item danger" onclick="deleteEntry(${tid})">🗑 Delete</div>
            </div>
        </div>`;
}

function renderTimetable(data, conflicts) {
    const tt = data.timetable || {};

    // Count whether any day has data
    const hasDays = DAYS.some(d => (tt[d] || []).length > 0);
    if (!hasDays) {
        return '<p class="cs-empty-state">No classes scheduled for this week.</p>';
    }

    // Single table — day separator rows span all columns so columns stay aligned
    let rows = '';

    DAYS.forEach(day => {
        const all     = (tt[day] || []);
        const classes = activeFilter === 'all'
            ? all
            : all.filter(c => c.building && c.building.toLowerCase().includes(activeFilter.toLowerCase()));

        if (all.length === 0) { return; }

        const dayIso  = (all[0].actual_date) || addDays(data.week_start, DAYS.indexOf(day));
        const isToday = dayIso === csConfig.todayIso;

        // Day separator row
        rows += `
        <tr class="cs-day-sep-row${isToday ? ' cs-today-sep' : ''}" data-day="${escapeHtml(day)}">
            <td colspan="7">
                <span class="cs-day-name">${escapeHtml(day)}</span>
                <span class="cs-day-date">${escapeHtml(isoToShort(dayIso))}</span>
                ${isToday ? '<span class="cs-today-badge">Today</span>' : ''}
                <span class="cs-day-count">${all.length} class${all.length !== 1 ? 'es' : ''}</span>
            </td>
        </tr>`;

        if (classes.length === 0) {
            rows += `<tr><td colspan="7" style="padding:0.55rem 1.1rem;color:#a0aec0;font-size:0.85rem">No classes match the current filter.</td></tr>`;
        } else {
            classes.forEach(c => {
                const isConflict  = conflicts.has(c.timetable_id);
                const conflictMsg = isConflict
                    ? `<div class="cs-conflict-indicator">⚠️ Room conflict — ${escapeHtml(c.venue)} overlapping at this time</div>`
                    : '';

                rows += `
                <tr class="${isConflict ? 'cs-conflict-row' : ''}"
                    id="tt-row-${c.timetable_id}"
                    data-section-id="${c.section_id}"
                    data-room-id="${c.room_id}">
                    <td>
                        <div class="cs-course-name">${escapeHtml(c.course_name)}</div>
                        <div class="cs-course-code">${escapeHtml(c.section_code)}</div>
                        ${conflictMsg}
                    </td>
                    <td>
                        <div class="cs-venue-name">${escapeHtml(c.venue)}</div>
                        <div class="cs-venue-building">${escapeHtml(c.building || '')}</div>
                    </td>
                    <td><div class="cs-time-val">${escapeHtml(formatTime(c.start_time))} – ${escapeHtml(formatTime(c.end_time))}</div></td>
                    <td>${escapeHtml(c.lecturer || '')}</td>
                    <td>${c.enrolled_count != null ? escapeHtml(String(c.enrolled_count)) : '—'}</td>
                    <td>${statusBadge(c.status, isConflict)}</td>
                    <td class="cs-action-cell">
                        <div class="cs-row-actions">
                            ${isConflict ? '<button class="cs-resolve-btn" title="Room conflict detected">🔧</button>' : ''}
                            ${kebabMenu(c)}
                        </div>
                    </td>
                </tr>`;
            });
        }
    });

    return `
    <div class="cs-table-scroll">
    <table class="cs-tt-table">
        <colgroup>
            <col><col><col><col><col><col><col>
        </colgroup>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Venue</th>
                <th>Time</th>
                <th>Lecturer</th>
                <th>Enrolled</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
    </table>
    </div>`;
}

/* ── LOAD TIMETABLE ───────────────────────────────────────────── */

async function loadTimetable(weekStart) {
    const container = document.getElementById('timetableContainer');
    container.innerHTML = '<p class="cs-empty-state">Loading…</p>';
    document.getElementById('weekDisplay').textContent = 'Loading…';

    try {
        const resp = await fetch(`api/get-timetable.php?week_start=${encodeURIComponent(weekStart)}`, {
            credentials: 'same-origin'
        });
        if (!resp.ok) { throw new Error('Server returned ' + resp.status); }
        const data = await resp.json();
        if (!data.success) { throw new Error(data.error || 'Failed to load timetable'); }

        currentWeekStart    = weekStart;
        currentTimetableData = data;
        currentConflicts    = detectConflicts(data.timetable);

        document.getElementById('weekDisplay').textContent =
            `${isoToShort(data.week_start)} – ${isoToLong(data.week_end)}`;

        container.innerHTML = renderTimetable(data, currentConflicts);
        updateStats(data.timetable, currentConflicts);

    } catch (err) {
        console.error(err);
        container.innerHTML = `<p class="cs-empty-state" style="color:#e53e3e">Error loading timetable: ${escapeHtml(err.message)}</p>`;
        document.getElementById('weekDisplay').textContent = 'Error';
    }
}

/* ── WEEK NAVIGATION ──────────────────────────────────────────── */

function prevWeek() {
    if (currentWeekStart) { loadTimetable(addDays(currentWeekStart, -7)); }
}

function nextWeek() {
    if (currentWeekStart) { loadTimetable(addDays(currentWeekStart, 7)); }
}

/* ── GENERATE NEXT WEEK ───────────────────────────────────────── */

async function generateNextWeek() {
    if (!currentWeekStart) { return; }
    const btn = document.getElementById('generateBtn');
    btn.disabled    = true;
    btn.textContent = 'Generating…';

    try {
        const resp = await fetch('api/generate-next-week.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ source_week_start: currentWeekStart })
        });
        const data = await resp.json();

        if (data.already_exists) {
            const nws = addDays(currentWeekStart, 7);
            alert(`The week of ${isoToShort(nws)} already has timetable entries.`);
        } else if (data.success) {
            const nws = addDays(currentWeekStart, 7);
            alert(`Generated ${data.generated} timetable entries for the week of ${isoToShort(nws)}.`);
            loadTimetable(nws);
        } else {
            alert('Failed to generate: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = '⚡ Generate Next Week';
    }
}

/* ── FILTER CHIPS ─────────────────────────────────────────────── */

function initFilters() {
    document.querySelectorAll('.cs-filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.cs-filter-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            activeFilter = chip.dataset.filter;
            if (currentTimetableData) {
                document.getElementById('timetableContainer').innerHTML =
                    renderTimetable(currentTimetableData, currentConflicts);
            }
        });
    });
}

/* ── DROPDOWN KEBAB ───────────────────────────────────────────── */

function closeDropdown(menu) {
    if (!menu) { return; }

    menu.classList.remove('open');

    const wrapper = menu.closest('.cs-dropdown-wrapper');
    if (!wrapper) { return; }

    wrapper.classList.remove('menu-open');
    const btn = wrapper.querySelector('.cs-kebab-btn');
    if (btn) {
        btn.setAttribute('aria-expanded', 'false');
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.cs-dropdown-menu.open').forEach(closeDropdown);
}

function toggleDropdown(btn) {
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('open');

    closeAllDropdowns();

    if (isOpen) {
        return;
    }

    menu.classList.toggle('open');

    const wrapper = btn.closest('.cs-dropdown-wrapper');
    if (wrapper) {
        wrapper.classList.add('menu-open');
    }
    btn.setAttribute('aria-expanded', 'true');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.cs-dropdown-wrapper')) {
        closeAllDropdowns();
    }
});

window.addEventListener('resize', closeAllDropdowns);
window.addEventListener('scroll', closeAllDropdowns, true);

/* ── ROW ACTIONS ──────────────────────────────────────────────── */

async function cancelEntry(timetableId, action) {
    const label = action === 'cancel_rest' ? 'Cancel rest of semester for this section' : 'Cancel this week\'s class';
    if (!confirm(label + '? This cannot be undone.')) { return; }

    try {
        const resp = await fetch('api/update-timetable-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ timetable_id: timetableId, action })
        });
        const data = await resp.json();
        if (data.success) {
            loadTimetable(currentWeekStart);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function deleteEntry(timetableId) {
    if (!confirm('Permanently delete this timetable entry?')) { return; }

    try {
        const resp = await fetch('api/update-timetable-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ timetable_id: timetableId, action: 'delete' })
        });
        const data = await resp.json();
        if (data.success) {
            loadTimetable(currentWeekStart);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

/* ── CHANGE REQUESTS ──────────────────────────────────────────── */

function renderRequests(requests) {
    const container = document.getElementById('requestsContainer');
    updateRequestStat(requests.length);

    if (!requests || requests.length === 0) {
        container.innerHTML = '<p class="cs-empty-state cs-empty-card">No pending change requests.</p>';
        return;
    }

    let html = '';

    requests.forEach(req => {
        const currentDay = req.current_day_of_week || req.day_of_week;
        const currentRoom = req.current_room_name
            ? `${req.current_room_name}${req.current_building ? ' (' + req.current_building + ')' : ''}`
            : '—';
        const requestedRoom = req.requested_room_name
            ? `${req.requested_room_name}${req.requested_building ? ' (' + req.requested_building + ')' : ''}`
            : '—';
        const currentTime = (req.current_start_time && req.current_end_time)
            ? `${formatTime(req.current_start_time)} – ${formatTime(req.current_end_time)}`
            : '—';
        const requestedTime = `${formatTime(req.start_time)} – ${formatTime(req.end_time)}`;
        const weekLabel = req.week_start_date ? isoToShort(req.week_start_date) + ' week' : '—';

        const submittedAt = req.requested_at
            ? new Date(String(req.requested_at).replace(' ', 'T')).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
              })
            : '';

        html += `
        <div class="cs-request-card" id="req-${req.request_id}">
            <div class="cs-request-head" onclick="toggleRequest('req-${req.request_id}')">
                <div class="cs-req-icon">🔁</div>
                <div class="cs-req-info">
                    <div class="cs-req-title">${escapeHtml(req.course_name)} (${escapeHtml(req.section_code)}) — ${escapeHtml(req.lecturer_name)}</div>
                    <div class="cs-req-meta">Submitted ${escapeHtml(submittedAt)} &middot; ${escapeHtml(req.day_of_week)} class &middot; ${escapeHtml(weekLabel)}</div>
                </div>
                <div class="cs-req-right">
                    <span class="cs-scope-tag">Week of ${escapeHtml(isoToShort(req.week_start_date))}</span>
                    <span class="cs-badge cs-badge-pending">Pending</span>
                    <span class="cs-chevron">▼</span>
                </div>
            </div>
            <div class="cs-request-body">
                <div class="cs-diff-grid">
                    <div class="cs-diff-box">
                        <div class="cs-diff-label">Current</div>
                        <div class="cs-diff-row"><strong>Day</strong>&nbsp; ${escapeHtml(currentDay)}</div>
                        <div class="cs-diff-row"><strong>Time</strong>&nbsp; ${escapeHtml(currentTime)}</div>
                        <div class="cs-diff-row"><strong>Room</strong>&nbsp; ${escapeHtml(currentRoom)}</div>
                    </div>
                    <div class="cs-diff-arrow">→</div>
                    <div class="cs-diff-box cs-diff-new">
                        <div class="cs-diff-label">Requested</div>
                        <div class="cs-diff-row"><strong>Day</strong>&nbsp; ${escapeHtml(req.day_of_week)}</div>
                        <div class="cs-diff-row"><strong>Time</strong>&nbsp; ${escapeHtml(requestedTime)}</div>
                        <div class="cs-diff-row"><strong>Room</strong>&nbsp; ${escapeHtml(requestedRoom)}</div>
                    </div>
                </div>
                <div class="cs-request-actions">
                    <button class="cs-btn-approve" onclick="approveRequest(${req.request_id}, this)">✓ Approve</button>
                    <button class="cs-btn-reject"  onclick="openRejectModal(${req.request_id}, '${escapeHtml(req.course_name)} (${escapeHtml(req.section_code)})')">✕ Reject</button>
                    <button class="cs-btn-view-row" onclick="highlightRow(${Number(req.section_id)}, '${escapeHtml(req.day_of_week)}')">👁 View in Timetable</button>
                </div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

async function loadChangeRequests() {
    try {
        const resp = await fetch('api/get-schedule-requests.php', { credentials: 'same-origin' });
        if (!resp.ok) { throw new Error('Server returned ' + resp.status); }
        const data = await resp.json();
        if (!data.success) { throw new Error(data.error || 'Failed to load requests'); }
        renderRequests(data.requests || []);
    } catch (err) {
        document.getElementById('requestsContainer').innerHTML =
            `<p class="cs-empty-state cs-empty-card" style="color:#e53e3e">Error loading requests: ${escapeHtml(err.message)}</p>`;
    }
}

function toggleRequest(id) {
    const card = document.getElementById(id);
    if (card) { card.classList.toggle('expanded'); }
}

async function approveRequest(requestId, btn) {
    if (!confirm('Approve this schedule change request?')) { return; }

    btn.disabled = true;
    const rejectBtn = btn.nextElementSibling;
    if (rejectBtn) { rejectBtn.disabled = true; }

    try {
        const resp = await fetch('api/approve-schedule-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ request_id: requestId, action: 'approve' })
        });
        const data = await resp.json();

        if (data.success) {
            const actionsDiv = document.querySelector(`#req-${requestId} .cs-request-actions`);
            if (actionsDiv) {
                actionsDiv.innerHTML = '<span class="cs-inline-result success">✓ Approved — timetable updated</span>';
            }
            loadTimetable(currentWeekStart);
            setTimeout(() => loadChangeRequests(), 900);
        } else {
            alert('Could not approve: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            if (rejectBtn) { rejectBtn.disabled = false; }
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        if (rejectBtn) { rejectBtn.disabled = false; }
    }
}

function openRejectModal(requestId, courseName) {
    pendingRejectId = requestId;
    document.getElementById('rejectModalDesc').textContent = 'Rejecting: ' + courseName;
    document.getElementById('rejectReasonInput').value     = '';
    document.getElementById('rejectReasonError').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    pendingRejectId = null;
    document.getElementById('rejectModal').classList.add('hidden');
}

async function confirmReject() {
    const reason = document.getElementById('rejectReasonInput').value.trim();
    if (!reason) {
        document.getElementById('rejectReasonError').classList.remove('hidden');
        return;
    }
    document.getElementById('rejectReasonError').classList.add('hidden');

    const idToReject = pendingRejectId;
    closeRejectModal();

    try {
        const resp = await fetch('api/approve-schedule-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ request_id: idToReject, action: 'reject', rejection_reason: reason })
        });
        const data = await resp.json();

        if (data.success) {
            const actionsDiv = document.querySelector(`#req-${idToReject} .cs-request-actions`);
            if (actionsDiv) {
                actionsDiv.innerHTML = '<span class="cs-inline-result error">✕ Rejected</span>';
            }
            setTimeout(() => loadChangeRequests(), 900);
        } else {
            alert('Could not reject: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

/* ── HIGHLIGHT ROW ────────────────────────────────────────────── */

function highlightRow(sectionId, dayOfWeek) {
    const row = document.querySelector(
        `tbody tr[data-section-id="${sectionId}"]`
    );

    document.getElementById('timetableCard').scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (row) {
        setTimeout(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.transition = 'background 0.3s';
            row.style.background = 'rgba(102,126,234,0.15)';
            setTimeout(() => { row.style.background = ''; }, 2500);
        }, 400);
    }
}

/* ── INIT ─────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('prevWeekBtn').addEventListener('click', prevWeek);
    document.getElementById('nextWeekBtn').addEventListener('click', nextWeek);
    document.getElementById('generateBtn').addEventListener('click', generateNextWeek);

    initFilters();

    const monday = getMondayIso();
    await loadTimetable(monday);
    await loadChangeRequests();
});
