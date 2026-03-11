/**
 * Lecturer attendance management.
 */

let currentSessionId = null;
let attendancePollInterval = null;
let expiryCountdownInterval = null;
const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function setClassesLoading(message) {
    const container = document.getElementById('lecturer-classes-container');
    if (!container) {
        return;
    }
    container.innerHTML = `<div class="loading-state">${escapeHtml(message)}</div>`;
}

function renderClasses(classes) {
    const container = document.getElementById('lecturer-classes-container');
    if (!container) {
        return;
    }

    if (!classes || classes.length === 0) {
        container.innerHTML = '<div class="empty-state">No classes scheduled for this week.</div>';
        return;
    }

    const classesByDay = new Map();

    classes.forEach((classItem) => {
        const day = classItem.day_of_week || 'Other';
        if (!classesByDay.has(day)) {
            classesByDay.set(day, []);
        }
        classesByDay.get(day).push(classItem);
    });

    container.innerHTML = weekDays
        .filter((day) => classesByDay.has(day))
        .map((day) => {
            const dayClasses = classesByDay.get(day) || [];
            const firstClass = dayClasses[0] || {};
            const formattedDate = firstClass.class_date
                ? new Date(`${firstClass.class_date}T00:00:00`).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                })
                : '';
            const dayBadge = firstClass.is_today ? '<span class="lecturer-week-heading-badge">Today</span>' : '';

            return `
                <div class="lecturer-week-group">
                    <div class="lecturer-week-heading${firstClass.is_today ? ' is-today' : ''}">
                        <div class="lecturer-week-heading-main">${escapeHtml(day)}</div>
                        <div class="lecturer-week-heading-side">
                            <span class="lecturer-week-heading-date">${escapeHtml(formattedDate)}</span>
                            ${dayBadge}
                        </div>
                    </div>
                    ${dayClasses.map((classItem) => {
                        const room = classItem.building
                            ? `${classItem.room_name} (${classItem.building})`
                            : classItem.room_name;
                        const actionText = classItem.can_start_attendance
                            ? (classItem.has_active_session ? 'Refresh Code' : 'Start Attendance')
                            : 'Available on Class Day';
                        const buttonAttributes = classItem.can_start_attendance
                            ? `onclick="startClass(${classItem.timetable_id})"`
                            : 'type="button" disabled';

                        return `
                            <div class="lecturer-class-row">
                                <div class="class-main">
                                    <div class="class-title">${escapeHtml(classItem.course_name)} - ${escapeHtml(classItem.section_code || 'Section')}</div>
                                    <div class="class-meta">${escapeHtml(classItem.start_time)} - ${escapeHtml(classItem.end_time)} | ${escapeHtml(room)}</div>
                                </div>
                                <button class="btn-start-attendance${classItem.can_start_attendance ? '' : ' is-disabled'}" ${buttonAttributes}>${escapeHtml(actionText)}</button>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        })
        .join('');
}

async function loadLecturerClasses() {
    setClassesLoading('Loading your classes...');

    try {
        const response = await fetch('api/get-lecturer-classes.php', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to load classes');
        }

        renderClasses(data.classes || []);
    } catch (error) {
        setClassesLoading(error.message || 'Unable to load classes right now.');
    }
}

function setActiveSessionVisible(isVisible) {
    const card = document.getElementById('active-session-card');
    if (!card) {
        return;
    }

    if (isVisible) {
        card.classList.remove('hidden');
    } else {
        card.classList.add('hidden');
    }
}

function updateCodeDisplay(code, expiresAt) {
    const codeEl = document.getElementById('attendance-code');
    const expiryEl = document.getElementById('code-expiry');

    if (codeEl) {
        codeEl.textContent = code || '---';
    }

    if (expiryEl) {
        expiryEl.textContent = 'Expires in: --:--';
    }

    if (expiryCountdownInterval) {
        clearInterval(expiryCountdownInterval);
        expiryCountdownInterval = null;
    }

    if (!expiresAt) {
        return;
    }

    const expiresAtDate = new Date(String(expiresAt).replace(' ', 'T'));

    const tick = () => {
        const now = new Date();
        const diffMs = expiresAtDate.getTime() - now.getTime();

        if (diffMs <= 0) {
            if (expiryEl) {
                expiryEl.textContent = 'Code expired. Generate a new code.';
            }
            clearInterval(expiryCountdownInterval);
            expiryCountdownInterval = null;
            return;
        }

        const diffSeconds = Math.floor(diffMs / 1000);
        const minutes = String(Math.floor(diffSeconds / 60)).padStart(2, '0');
        const seconds = String(diffSeconds % 60).padStart(2, '0');

        if (expiryEl) {
            expiryEl.textContent = `Expires in: ${minutes}:${seconds}`;
        }
    };

    tick();
    expiryCountdownInterval = setInterval(tick, 1000);
}

function renderAttendanceList(students) {
    const listEl = document.getElementById('attendance-list');
    if (!listEl) {
        return;
    }

    if (!students || students.length === 0) {
        listEl.innerHTML = '<div class="empty-state">No students have signed in yet</div>';
        return;
    }

    listEl.innerHTML = students.map((student) => `
        <div class="attendance-item">
            <div class="attendance-item-main">
                <div class="attendance-item-name">${escapeHtml(student.name)}</div>
                <div class="attendance-item-email">${escapeHtml(student.email)}</div>
            </div>
            <div class="attendance-item-time">${escapeHtml(student.marked_time || '')}</div>
        </div>
    `).join('');
}

async function loadClassAttendance() {
    if (!currentSessionId) {
        return;
    }

    try {
        const response = await fetch(`api/get-class-attendance.php?session_id=${encodeURIComponent(currentSessionId)}`, {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to load class attendance');
        }

        const presentCountEl = document.getElementById('present-count');
        const totalStudentsEl = document.getElementById('total-students');

        if (presentCountEl) {
            presentCountEl.textContent = String(data.present_count || 0);
        }

        if (totalStudentsEl) {
            totalStudentsEl.textContent = String(data.total_students || 0);
        }

        renderAttendanceList(data.present_students || []);
    } catch (error) {
        const listEl = document.getElementById('attendance-list');
        if (listEl) {
            listEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message || 'Unable to load attendance')}</div>`;
        }
    }
}

function startAttendancePolling() {
    if (attendancePollInterval) {
        clearInterval(attendancePollInterval);
    }

    loadClassAttendance();
    attendancePollInterval = setInterval(loadClassAttendance, 5000);
}

async function startClass(timetableId) {
    try {
        const response = await fetch('api/start-class.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ timetable_id: timetableId })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to start attendance');
        }

        currentSessionId = data.session_id;
        setActiveSessionVisible(true);
        updateCodeDisplay(data.attendance_code, data.expires_at);
        startAttendancePolling();
        await loadLecturerClasses();
    } catch (error) {
        alert(error.message || 'Unable to start class attendance.');
    }
}

async function endClass() {
    if (!currentSessionId) {
        return;
    }

    const shouldEnd = window.confirm('End this attendance session? Students will no longer be able to submit the code.');
    if (!shouldEnd) {
        return;
    }

    try {
        const response = await fetch('api/end-class.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ session_id: currentSessionId })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to end attendance session');
        }

        currentSessionId = null;
        setActiveSessionVisible(false);

        if (attendancePollInterval) {
            clearInterval(attendancePollInterval);
            attendancePollInterval = null;
        }

        if (expiryCountdownInterval) {
            clearInterval(expiryCountdownInterval);
            expiryCountdownInterval = null;
        }

        await loadLecturerClasses();
    } catch (error) {
        alert(error.message || 'Unable to end class attendance.');
    }
}

window.startClass = startClass;
window.endClass = endClass;

document.addEventListener('DOMContentLoaded', () => {
    loadLecturerClasses();
});
