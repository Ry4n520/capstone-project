/**
 * Timetable page behavior.
 */

const timetableConfig = window.timetableConfig || {
    isLecturer: false,
    isAdmin: false
};

let availableWeeks = [];
let currentWeekIndex = -1;
let currentWeekData = null;
let timetableLookup = {};
let classrooms = [];
let classroomsPromise = null;
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function formatTime(timeStr) {
    if (!timeStr) {
        return '';
    }

    const [hours, minutes] = String(timeStr).split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function formatDateShort(dateValue) {
    if (!dateValue) {
        return '';
    }

    return new Date(`${dateValue}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateTimeValue) {
    if (!dateTimeValue) {
        return '';
    }

    return new Date(String(dateTimeValue).replace(' ', 'T')).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

function toIsoDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getCurrentMondayIso() {
    const now = new Date();
    const day = now.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    now.setDate(now.getDate() + diff);
    now.setHours(0, 0, 0, 0);
    return toIsoDate(now);
}

function getDateDetails(dateValue) {
    if (!dateValue) {
        return null;
    }

    const targetDate = new Date(`${dateValue}T00:00:00`);
    if (Number.isNaN(targetDate.getTime())) {
        return null;
    }

    const dayNumber = targetDate.getDay();
    if (dayNumber === 0 || dayNumber === 6) {
        return null;
    }

    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const monday = new Date(targetDate);
    monday.setDate(targetDate.getDate() - (dayNumber - 1));

    const weekEnd = new Date(monday);
    weekEnd.setDate(monday.getDate() + 6);

    return {
        classDate: dateValue,
        dayOfWeek: dayNames[dayNumber],
        weekStartDate: toIsoDate(monday),
        weekEndDate: toIsoDate(weekEnd)
    };
}

function applyRequestDate(dateValue) {
    const details = getDateDetails(dateValue);
    const dayInput = document.getElementById('requestDayOfWeek');
    const weekStartInput = document.getElementById('requestWeekStartDate');
    const weekEndInput = document.getElementById('requestWeekEndDate');

    if (!dayInput || !weekStartInput || !weekEndInput) {
        return null;
    }

    if (!details) {
        dayInput.value = '';
        weekStartInput.value = '';
        weekEndInput.value = '';
        return null;
    }

    dayInput.value = details.dayOfWeek;
    weekStartInput.value = details.weekStartDate;
    weekEndInput.value = details.weekEndDate;
    return details;
}

function updateButtonStates() {
    const prevBtn = document.getElementById('prevWeekBtn');
    const nextBtn = document.getElementById('nextWeekBtn');

    if (!prevBtn || !nextBtn) {
        return;
    }

    const noWeeks = availableWeeks.length === 0 || currentWeekIndex < 0;
    prevBtn.disabled = noWeeks || currentWeekIndex === 0;
    nextBtn.disabled = noWeeks || currentWeekIndex >= availableWeeks.length - 1;
}

async function fetchAvailableWeeks() {
    const response = await fetch('api/get-available-weeks.php', {
        credentials: 'same-origin'
    });

    if (!response.ok) {
        throw new Error('Failed to load available weeks.');
    }

    const data = await response.json();
    if (!data.success) {
        throw new Error(data.error || 'Failed to load available weeks.');
    }

    availableWeeks = Array.isArray(data.weeks) ? data.weeks : [];
}

function getInitialWeekIndex() {
    if (availableWeeks.length === 0) {
        return -1;
    }

    const currentMonday = getCurrentMondayIso();
    const exactMatch = availableWeeks.findIndex((week) => week.week_start_date === currentMonday || week.week_start === currentMonday);
    if (exactMatch >= 0) {
        return exactMatch;
    }

    return 0;
}

function renderWeekHeader(weekStart, weekEnd, isReleased) {
    const weekStartObj = new Date(`${weekStart}T00:00:00`);
    const weekEndObj = new Date(`${weekEnd}T00:00:00`);
    const weekText = `${weekStartObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEndObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
    const releaseText = isReleased ? 'Released' : 'Pending Release';

    document.getElementById('weekDisplay').textContent = `${weekText} (${releaseText})`;
}

function buildEmptyState(message) {
    const safeMessage = message || 'No classes scheduled for this week.';
    return `<p class="timetable-empty-state">${escapeHtml(safeMessage)}</p>`;
}

function buildRequestButton(course) {
    if (!timetableConfig.isLecturer) {
        return '';
    }

    return `
        <button
            type="button"
            class="btn-request-change"
            onclick="openScheduleRequestModal(${Number(course.timetable_id)})"
        >
            Request Change
        </button>
    `;
}

function buildTimetableHtml(data) {
    let html = '';
    timetableLookup = {};

    days.forEach((day) => {
        const dayCourses = Array.isArray(data.timetable[day]) ? data.timetable[day] : [];
        if (dayCourses.length === 0) {
            return;
        }

        const dayDate = dayCourses[0].actual_date || data.week_start;
        const formattedDate = new Date(`${dayDate}T00:00:00`).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit' });

        html += `<div class="timetable-day">
            <div class="day-label">${escapeHtml(day)} (${escapeHtml(formattedDate)})</div>
            <div class="timetable-row timetable-head">
                <span>Subject Name</span>
                <span>Venue</span>
                <span>Time</span>
                <span>Lecturer Name</span>
            </div>`;

        dayCourses.forEach((course) => {
            timetableLookup[String(course.timetable_id)] = course;
            const venue = course.building
                ? `${course.venue} (${course.building})`
                : course.venue;
            const statusBadge = (course.status && timetableConfig.isAdmin)
                ? `<span class="timetable-status ${course.status === 'released' ? 'released' : 'pending'}">${escapeHtml(course.status)}</span>`
                : '';

            html += `<div class="timetable-row">
                <div>
                    <div class="subject-title">${escapeHtml(course.course_name)}</div>
                    <div class="subject-code">${escapeHtml(course.section_code)}</div>
                    ${buildRequestButton(course)}
                </div>
                <span>${escapeHtml(venue)}</span>
                <span>${escapeHtml(formatTime(course.start_time))} - ${escapeHtml(formatTime(course.end_time))}</span>
                <span>${escapeHtml(course.lecturer)} ${statusBadge}</span>
            </div>`;
        });

        html += '</div>';
    });

    return html || buildEmptyState(data.message);
}

async function loadTimetableByIndex(index) {
    if (index < 0 || index >= availableWeeks.length) {
        return;
    }

    const week = availableWeeks[index];
    const weekStart = week.week_start_date || week.week_start;

    try {
        const response = await fetch(`api/get-timetable.php?week_start=${encodeURIComponent(weekStart)}`, {
            credentials: 'same-origin'
        });
        if (!response.ok) {
            throw new Error('Failed to load timetable.');
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to load timetable.');
        }

        currentWeekIndex = index;
        currentWeekData = data;
        renderWeekHeader(data.week_start, data.week_end, data.is_released);
        document.getElementById('timetableContainer').innerHTML = buildTimetableHtml(data);
        updateButtonStates();
    } catch (error) {
        console.error('Error loading timetable:', error);
        document.getElementById('timetableContainer').innerHTML = `<p class="timetable-error-state">Error loading timetable: ${escapeHtml(error.message)}</p>`;
        updateButtonStates();
    }
}

function setRequestMessage(message, isError) {
    const messageEl = document.getElementById('scheduleRequestMessage');
    if (!messageEl) {
        return;
    }

    messageEl.textContent = message || '';
    messageEl.classList.toggle('error', Boolean(isError));
    messageEl.classList.toggle('success', Boolean(message) && !isError);
}

function closeScheduleRequestModal() {
    const modal = document.getElementById('scheduleRequestModal');
    const form = document.getElementById('scheduleRequestForm');

    if (modal) {
        modal.classList.add('hidden');
    }

    if (form) {
        form.reset();
        form.dataset.timetableId = '';
    }

    setRequestMessage('', false);
}

function populateClassrooms(currentRoomId) {
    const select = document.getElementById('requestRoomId');
    if (!select) {
        return;
    }

    select.innerHTML = classrooms.map((room) => {
        const label = room.building
            ? `${room.room_name} (${room.building})`
            : room.room_name;
        return `<option value="${room.room_id}">${escapeHtml(label)}</option>`;
    }).join('');

    if (currentRoomId) {
        select.value = String(currentRoomId);
    }
}

async function ensureClassroomsLoaded() {
    if (classrooms.length > 0) {
        return classrooms;
    }

    if (!classroomsPromise) {
        classroomsPromise = fetch('api/get-classrooms.php', {
            credentials: 'same-origin'
        })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to load classrooms.');
                }

                classrooms = Array.isArray(data.classrooms) ? data.classrooms : [];
                return classrooms;
            })
            .finally(() => {
                classroomsPromise = null;
            });
    }

    return classroomsPromise;
}

async function openScheduleRequestModal(timetableId) {
    const course = timetableLookup[String(timetableId)];
    if (!course) {
        return;
    }

    try {
        await ensureClassroomsLoaded();
    } catch (error) {
        alert(error.message || 'Unable to load classrooms.');
        return;
    }

    const modal = document.getElementById('scheduleRequestModal');
    const form = document.getElementById('scheduleRequestForm');
    const summary = document.getElementById('scheduleRequestSummary');
    const sectionIdInput = document.getElementById('requestSectionId');
    const dayInput = document.getElementById('requestDayOfWeek');
    const weekStartInput = document.getElementById('requestWeekStartDate');
    const weekEndInput = document.getElementById('requestWeekEndDate');
    const classDateInput = document.getElementById('requestClassDate');
    const startInput = document.getElementById('requestStartTime');
    const endInput = document.getElementById('requestEndTime');

    if (!modal || !form || !summary || !sectionIdInput || !dayInput || !weekStartInput || !weekEndInput || !classDateInput || !startInput || !endInput) {
        return;
    }

    form.dataset.timetableId = String(timetableId);
    sectionIdInput.value = String(course.section_id || '');
    classDateInput.value = course.actual_date || '';
    applyRequestDate(classDateInput.value);
    startInput.value = String(course.start_time || '').slice(0, 5);
    endInput.value = String(course.end_time || '').slice(0, 5);
    populateClassrooms(course.room_id);

    const venue = course.building
        ? `${course.venue} (${course.building})`
        : course.venue;

    summary.innerHTML = `
        <div><strong>Class:</strong> ${escapeHtml(course.course_name)} (${escapeHtml(course.section_code)})</div>
        <div><strong>Week:</strong> ${escapeHtml(formatDateShort(course.week_start_date))} - ${escapeHtml(formatDateShort(course.week_end_date))}</div>
        <div><strong>Current Slot:</strong> ${escapeHtml(course.day_of_week)} | ${escapeHtml(formatTime(course.start_time))} - ${escapeHtml(formatTime(course.end_time))} | ${escapeHtml(venue)}</div>
    `;

    modal.classList.remove('hidden');
    setRequestMessage('', false);
}

async function submitScheduleRequest(event) {
    event.preventDefault();

    const submitBtn = document.getElementById('submitScheduleRequestBtn');
    const selectedDate = document.getElementById('requestClassDate').value;
    const dateDetails = applyRequestDate(selectedDate);

    if (!dateDetails) {
        setRequestMessage('Please select a valid class date from Monday to Friday.', true);
        return;
    }

    const payload = {
        section_id: Number(document.getElementById('requestSectionId').value || 0),
        room_id: Number(document.getElementById('requestRoomId').value || 0),
        class_date: dateDetails.classDate,
        day_of_week: dateDetails.dayOfWeek,
        week_start_date: dateDetails.weekStartDate,
        week_end_date: dateDetails.weekEndDate,
        start_time: document.getElementById('requestStartTime').value,
        end_time: document.getElementById('requestEndTime').value
    };

    if (!payload.section_id || !payload.room_id || !payload.start_time || !payload.end_time) {
        setRequestMessage('Please complete the time and classroom fields.', true);
        return;
    }

    if (payload.start_time >= payload.end_time) {
        setRequestMessage('End time must be later than start time.', true);
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
    }

    try {
        const response = await fetch('api/create-schedule-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to submit schedule request.');
        }

        setRequestMessage(data.message || 'Request submitted successfully.', false);
        setTimeout(() => {
            closeScheduleRequestModal();
        }, 900);
    } catch (error) {
        setRequestMessage(error.message || 'Unable to submit schedule request.', true);
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

function renderScheduleRequests(requests) {
    const container = document.getElementById('scheduleRequestsContainer');
    if (!container) {
        return;
    }

    if (!Array.isArray(requests) || requests.length === 0) {
        container.innerHTML = '<div class="schedule-empty-state">No pending schedule requests right now.</div>';
        return;
    }

    container.innerHTML = requests.map((request) => {
        const currentRoom = request.current_room_name
            ? `${request.current_room_name}${request.current_building ? ` (${request.current_building})` : ''}`
            : 'No existing room';
        const requestedRoom = `${request.requested_room_name}${request.requested_building ? ` (${request.requested_building})` : ''}`;
        const currentTime = request.current_start_time && request.current_end_time
            ? `${formatTime(request.current_start_time)} - ${formatTime(request.current_end_time)}`
            : 'No existing time';
        const requestedTime = `${formatTime(request.start_time)} - ${formatTime(request.end_time)}`;

        return `
            <div class="schedule-request-item">
                <div class="schedule-request-main">
                    <div class="schedule-request-title">${escapeHtml(request.course_name)} (${escapeHtml(request.section_code)})</div>
                    <div class="schedule-request-meta">Lecturer: ${escapeHtml(request.lecturer_name)}</div>
                    <div class="schedule-request-meta">Week: ${escapeHtml(formatDateShort(request.week_start_date))} - ${escapeHtml(formatDateShort(request.week_end_date))}</div>
                    <div class="schedule-request-change"><strong>Current:</strong> ${escapeHtml(request.day_of_week)} | ${escapeHtml(currentTime)} | ${escapeHtml(currentRoom)}</div>
                    <div class="schedule-request-change"><strong>Requested:</strong> ${escapeHtml(request.day_of_week)} | ${escapeHtml(requestedTime)} | ${escapeHtml(requestedRoom)}</div>
                    <div class="schedule-request-meta">Submitted: ${escapeHtml(formatDateTime(request.requested_at))}</div>
                </div>
                <div class="schedule-request-actions">
                    <button type="button" class="btn-approve-request" onclick="approveScheduleRequest(${Number(request.request_id)})">Approve</button>
                    <button type="button" class="btn-reject-request" onclick="rejectScheduleRequest(${Number(request.request_id)})">Deny</button>
                </div>
            </div>
        `;
    }).join('');
}

async function loadScheduleRequests() {
    if (!timetableConfig.isAdmin) {
        return;
    }

    const container = document.getElementById('scheduleRequestsContainer');
    if (!container) {
        return;
    }

    container.innerHTML = '<div class="schedule-empty-state">Loading schedule requests...</div>';

    try {
        const response = await fetch('api/get-schedule-requests.php', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to load schedule requests.');
        }

        renderScheduleRequests(data.requests || []);
    } catch (error) {
        container.innerHTML = `<div class="schedule-error-state">${escapeHtml(error.message || 'Unable to load schedule requests.')}</div>`;
    }
}

async function processScheduleRequest(requestId, action, rejectionReason) {
    const response = await fetch('api/approve-schedule-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            request_id: requestId,
            action,
            rejection_reason: rejectionReason || ''
        })
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.error || data.message || 'Failed to process schedule request.');
    }

    await loadScheduleRequests();
    if (currentWeekIndex >= 0) {
        await loadTimetableByIndex(currentWeekIndex);
    }
}

async function approveScheduleRequest(requestId) {
    try {
        await processScheduleRequest(requestId, 'approve', '');
    } catch (error) {
        alert(error.message || 'Unable to approve schedule request.');
    }
}

async function rejectScheduleRequest(requestId) {
    const rejectionReason = window.prompt('Optional reason for denial:', '');
    if (rejectionReason === null) {
        return;
    }

    try {
        await processScheduleRequest(requestId, 'reject', rejectionReason.trim());
    } catch (error) {
        alert(error.message || 'Unable to deny schedule request.');
    }
}

async function initializeTimetable() {
    try {
        await fetchAvailableWeeks();

        if (availableWeeks.length === 0) {
            document.getElementById('weekDisplay').textContent = 'No Available Weeks';
            document.getElementById('timetableContainer').innerHTML = buildEmptyState('No released timetable weeks are available yet.');
            updateButtonStates();
        } else {
            const initialIndex = getInitialWeekIndex();
            await loadTimetableByIndex(initialIndex);
        }

        if (timetableConfig.isAdmin) {
            await loadScheduleRequests();
        }

        if (timetableConfig.isLecturer) {
            ensureClassroomsLoaded().catch((error) => {
                console.error('Error loading classrooms:', error);
            });
        }
    } catch (error) {
        console.error('Initialization error:', error);
        document.getElementById('weekDisplay').textContent = 'Error';
        document.getElementById('timetableContainer').innerHTML = `<p class="timetable-error-state">${escapeHtml(error.message)}</p>`;
        updateButtonStates();
    }
}

window.openScheduleRequestModal = openScheduleRequestModal;
window.closeScheduleRequestModal = closeScheduleRequestModal;
window.submitScheduleRequest = submitScheduleRequest;
window.approveScheduleRequest = approveScheduleRequest;
window.rejectScheduleRequest = rejectScheduleRequest;

document.addEventListener('DOMContentLoaded', () => {
    const prevBtn = document.getElementById('prevWeekBtn');
    const nextBtn = document.getElementById('nextWeekBtn');
    const requestForm = document.getElementById('scheduleRequestForm');
    const classDateInput = document.getElementById('requestClassDate');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentWeekIndex > 0) {
                loadTimetableByIndex(currentWeekIndex - 1);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentWeekIndex < availableWeeks.length - 1) {
                loadTimetableByIndex(currentWeekIndex + 1);
            }
        });
    }

    if (requestForm) {
        requestForm.addEventListener('submit', submitScheduleRequest);
    }

    if (classDateInput) {
        classDateInput.addEventListener('change', () => {
            const details = applyRequestDate(classDateInput.value);
            if (!details) {
                setRequestMessage('Please select a weekday date (Monday to Friday).', true);
                return;
            }

            setRequestMessage('', false);
        });
    }

    initializeTimetable();
});