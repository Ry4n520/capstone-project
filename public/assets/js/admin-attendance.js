/**
 * Admin attendance dashboard behavior.
 */

(function () {
    const adminData = window.adminAttendanceData || {};

    const sections = Array.isArray(adminData.sections) ? adminData.sections : [];
    const sectionSessions = adminData.sectionSessions || {};
    const sessionStudents = adminData.sessionStudents || {};
    const students = Array.isArray(adminData.students) ? adminData.students : [];
    const studentBreakdown = adminData.studentBreakdown || {};
    const weeks = Array.isArray(adminData.weeks) ? adminData.weeks : [];
    const weekSessions = adminData.weekSessions || {};
    const atRiskReport = Array.isArray(adminData.atRiskReport) ? adminData.atRiskReport : [];

    const state = {
        selectedSectionId: null,
        selectedStudentId: null,
        weekFilter: 'all',
        currentWeekIndex: 0
    };

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function asNumber(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatDate(dateValue) {
        if (!dateValue) {
            return '-';
        }

        const parsed = new Date(`${dateValue}T00:00:00`);
        if (Number.isNaN(parsed.getTime())) {
            return String(dateValue);
        }

        return parsed.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatShortDate(dateValue) {
        if (!dateValue) {
            return '-';
        }

        const parsed = new Date(`${dateValue}T00:00:00`);
        if (Number.isNaN(parsed.getTime())) {
            return String(dateValue);
        }

        return parsed.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }

    function formatWeekLabel(weekStart, weekEnd) {
        if (!weekStart || !weekEnd) {
            return 'Unknown Week';
        }

        const start = new Date(`${weekStart}T00:00:00`);
        const end = new Date(`${weekEnd}T00:00:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
            return `${weekStart} - ${weekEnd}`;
        }

        return `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
    }

    function getRateClass(rate) {
        const value = asNumber(rate);
        if (value >= 80) {
            return 'high';
        }
        if (value === 0) {
            return 'neutral';
        }
        return 'low';
    }

    function getSectionSessions(sectionId) {
        return Array.isArray(sectionSessions[String(sectionId)]) ? sectionSessions[String(sectionId)] : [];
    }

    function getSessionStudents(sessionId) {
        return Array.isArray(sessionStudents[String(sessionId)]) ? sessionStudents[String(sessionId)] : [];
    }

    function getStudentBreakdown(userId) {
        return Array.isArray(studentBreakdown[String(userId)]) ? studentBreakdown[String(userId)] : [];
    }

    function activateTab(tabName) {
        document.querySelectorAll('.admin-tab-btn').forEach((button) => {
            button.classList.toggle('active', button.dataset.tab === tabName);
        });

        document.querySelectorAll('.admin-tab-panel').forEach((panel) => {
            panel.classList.add('hidden');
        });

        const target = document.getElementById(`admin-tab-${tabName}`);
        if (target) {
            target.classList.remove('hidden');
        }
    }

    function renderSessionStudentRows(sessionId) {
        const rows = getSessionStudents(sessionId);
        if (rows.length === 0) {
            return '<div class="empty-state">No enrolled students found for this session.</div>';
        }

        const content = rows.map((row) => {
            const status = row.status || 'not_marked';
            const statusLabel = status === 'not_marked' ? 'Not marked' : status.charAt(0).toUpperCase() + status.slice(1);
            const statusClass = {
                present: 'is-present',
                late: 'is-late',
                absent: 'is-absent',
                not_marked: 'is-not-marked'
            }[status] || 'is-not-marked';

            return `
                <tr>
                    <td>${escapeHtml(row.student_name)}</td>
                    <td><span class="admin-status-badge ${statusClass}">${escapeHtml(statusLabel)}</span></td>
                    <td>${escapeHtml(row.marked_time || '--')}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="admin-session-student-list">
                <table class="admin-subtable">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${content}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderCourseTable() {
        const body = document.getElementById('admin-course-tbody');
        if (!body) {
            return;
        }

        if (!state.selectedSectionId) {
            body.innerHTML = '<tr><td colspan="7" class="empty-state">Select a course section to view attendance records.</td></tr>';
            return;
        }

        const sessions = getSectionSessions(state.selectedSectionId);
        if (sessions.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="empty-state">No class sessions found for this section.</td></tr>';
            return;
        }

        body.innerHTML = sessions.map((session) => {
            const sessionId = asNumber(session.session_id);
            const rate = asNumber(session.attendance_rate).toFixed(1);
            const notTakenBadge = session.not_taken
                ? '<span class="admin-inline-badge">Not taken</span>'
                : '';

            return `
                <tr class="admin-session-row" data-session-id="${sessionId}">
                    <td>${escapeHtml(formatDate(session.session_date))}</td>
                    <td>${escapeHtml(session.day_name || '-')}</td>
                    <td>${asNumber(session.present_count)}</td>
                    <td>${asNumber(session.late_count)}</td>
                    <td>${asNumber(session.absent_count)}</td>
                    <td>${asNumber(session.total_enrolled)}</td>
                    <td>
                        <span class="admin-rate-badge ${getRateClass(rate)}">${rate}%</span>
                        ${notTakenBadge}
                    </td>
                </tr>
                <tr class="admin-session-detail-row hidden" data-detail-for="${sessionId}">
                    <td colspan="7">
                        ${renderSessionStudentRows(sessionId)}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function bindCourseRowToggle() {
        const body = document.getElementById('admin-course-tbody');
        if (!body) {
            return;
        }

        body.addEventListener('click', (event) => {
            const row = event.target.closest('.admin-session-row');
            if (!row) {
                return;
            }

            const sessionId = row.getAttribute('data-session-id');
            const detailRow = body.querySelector(`.admin-session-detail-row[data-detail-for="${sessionId}"]`);
            if (!detailRow) {
                return;
            }

            detailRow.classList.toggle('hidden');
            row.classList.toggle('expanded');
        });
    }

    function renderStudentList(query) {
        const resultContainer = document.getElementById('admin-student-results');
        if (!resultContainer) {
            return;
        }

        const normalized = String(query || '').trim().toLowerCase();
        const filtered = students.filter((student) => {
            const name = String(student.name || '').toLowerCase();
            const email = String(student.email || '').toLowerCase();
            return name.includes(normalized) || email.includes(normalized);
        });

        if (filtered.length === 0) {
            resultContainer.innerHTML = '<div class="empty-state">No students match your search.</div>';
            state.selectedStudentId = null;
            renderStudentBreakdown();
            return;
        }

        resultContainer.innerHTML = filtered.slice(0, 30).map((student) => {
            const userId = asNumber(student.user_id);
            const activeClass = userId === state.selectedStudentId ? 'active' : '';
            return `
                <button type="button" class="admin-student-result ${activeClass}" data-student-id="${userId}">
                    <span class="admin-student-name">${escapeHtml(student.name)}</span>
                    <span class="admin-student-email">${escapeHtml(student.email)}</span>
                </button>
            `;
        }).join('');

        const hasCurrentSelection = filtered.some((student) => asNumber(student.user_id) === state.selectedStudentId);
        if (!hasCurrentSelection) {
            state.selectedStudentId = asNumber(filtered[0].user_id);
        }
    }

    function renderStudentBreakdown() {
        const container = document.getElementById('admin-student-breakdown');
        if (!container) {
            return;
        }

        if (!state.selectedStudentId) {
            container.classList.add('empty-state');
            container.innerHTML = 'Select a student to view attendance details.';
            return;
        }

        const selectedStudent = students.find((student) => asNumber(student.user_id) === state.selectedStudentId);
        const rows = getStudentBreakdown(state.selectedStudentId);

        if (rows.length === 0) {
            container.classList.add('empty-state');
            container.innerHTML = 'No enrollment attendance breakdown is available for this student.';
            return;
        }

        container.classList.remove('empty-state');
        container.innerHTML = `
            <div class="admin-student-breakdown-header">
                ${escapeHtml(selectedStudent ? selectedStudent.name : 'Student')} (${escapeHtml(selectedStudent ? selectedStudent.email : '')})
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Attended</th>
                            <th>Total</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => {
                            const rate = asNumber(row.attendance_rate).toFixed(1);
                            return `
                                <tr>
                                    <td>${escapeHtml(row.course_name)}</td>
                                    <td>${escapeHtml(row.section_code)}</td>
                                    <td>${asNumber(row.attended_count)}</td>
                                    <td>${asNumber(row.total_sessions)}</td>
                                    <td><span class="admin-rate-badge ${getRateClass(rate)}">${rate}%</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function getWeekForTodayIndex() {
        if (weeks.length === 0) {
            return 0;
        }

        const todayIso = new Date().toISOString().slice(0, 10);
        const index = weeks.findIndex((week) => {
            const start = String(week.week_start_date || '');
            const end = String(week.week_end_date || '');
            return todayIso >= start && todayIso <= end;
        });

        if (index >= 0) {
            return index;
        }

        return Math.max(0, weeks.length - 1);
    }

    function updateWeekHeader() {
        const label = document.getElementById('admin-week-display');
        const prev = document.getElementById('admin-prev-week');
        const next = document.getElementById('admin-next-week');

        if (!label || !prev || !next) {
            return;
        }

        if (weeks.length === 0) {
            label.textContent = 'No weeks available';
            prev.disabled = true;
            next.disabled = true;
            return;
        }

        const currentWeek = weeks[state.currentWeekIndex];
        label.textContent = formatWeekLabel(currentWeek.week_start_date, currentWeek.week_end_date);
        prev.disabled = state.currentWeekIndex <= 0;
        next.disabled = state.currentWeekIndex >= weeks.length - 1;
    }

    function applyWeekFilter(sessions) {
        if (state.weekFilter === 'not-taken') {
            return sessions.filter((session) => Boolean(session.not_taken));
        }

        if (state.weekFilter === 'low') {
            return sessions.filter((session) => asNumber(session.attendance_rate) < 70);
        }

        return sessions;
    }

    function renderWeekTable() {
        const body = document.getElementById('admin-week-tbody');
        if (!body) {
            return;
        }

        if (weeks.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="empty-state">No class sessions are available for weekly view.</td></tr>';
            return;
        }

        const currentWeek = weeks[state.currentWeekIndex];
        const weekKey = String(currentWeek.week_start_date || '');
        const sessions = Array.isArray(weekSessions[weekKey]) ? weekSessions[weekKey] : [];
        const filteredSessions = applyWeekFilter(sessions);

        if (filteredSessions.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="empty-state">No sessions match the selected filter in this week.</td></tr>';
            return;
        }

        body.innerHTML = filteredSessions.map((session) => {
            const rate = asNumber(session.attendance_rate).toFixed(1);
            const notTakenBadge = session.not_taken ? '<span class="admin-inline-badge">Not taken</span>' : '';

            return `
                <tr>
                    <td>${escapeHtml(session.course_name)}</td>
                    <td>${escapeHtml(session.section_code)}</td>
                    <td>${escapeHtml(session.lecturer_name)}</td>
                    <td>${escapeHtml(formatDate(session.session_date))}</td>
                    <td>${asNumber(session.present_count)}</td>
                    <td>${asNumber(session.absent_count)}</td>
                    <td>
                        <span class="admin-rate-badge ${getRateClass(rate)}">${rate}%</span>
                        ${notTakenBadge}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function showReportOutput(html) {
        const output = document.getElementById('admin-report-output');
        const actions = document.getElementById('admin-report-actions');

        if (!output || !actions) {
            return;
        }

        output.classList.remove('empty-state');
        output.innerHTML = html;
        actions.classList.remove('hidden');
    }

    function showEmptyReport(message) {
        const output = document.getElementById('admin-report-output');
        const actions = document.getElementById('admin-report-actions');

        if (!output || !actions) {
            return;
        }

        output.classList.add('empty-state');
        output.textContent = message;
        actions.classList.add('hidden');
    }

    function generateSectionReport() {
        const reportSectionSelect = document.getElementById('admin-report-section-select');
        if (!reportSectionSelect) {
            return;
        }

        const sectionId = reportSectionSelect.value;
        const section = sections.find((item) => String(item.section_id) === String(sectionId));
        const sessions = getSectionSessions(sectionId)
            .slice()
            .sort((a, b) => String(a.session_date).localeCompare(String(b.session_date)));

        if (!section || sessions.length === 0) {
            showEmptyReport('No class sessions found for this section report.');
            return;
        }

        let totalPresent = 0;
        let totalLate = 0;
        let totalAbsent = 0;
        let totalPossible = 0;

        const rows = sessions.map((session) => {
            totalPresent += asNumber(session.present_count);
            totalLate += asNumber(session.late_count);
            totalAbsent += asNumber(session.absent_count);
            totalPossible += asNumber(session.total_enrolled);

            return `
                <tr>
                    <td>${escapeHtml(formatWeekLabel(session.week_start_date, session.week_end_date))}</td>
                    <td>${escapeHtml(formatDate(session.session_date))}</td>
                    <td>${asNumber(session.present_count)}</td>
                    <td>${asNumber(session.late_count)}</td>
                    <td>${asNumber(session.absent_count)}</td>
                    <td>${asNumber(session.attendance_rate).toFixed(1)}%</td>
                </tr>
            `;
        }).join('');

        const overallRate = totalPossible > 0
            ? (((totalPresent + totalLate) / totalPossible) * 100).toFixed(1)
            : '0.0';

        const generatedDate = new Date().toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });

        showReportOutput(`
            <div class="admin-print-header">
                <h3>Section Attendance Report</h3>
                <div><strong>Course:</strong> ${escapeHtml(section.course_name)}</div>
                <div><strong>Section:</strong> ${escapeHtml(section.section_code)}</div>
                <div><strong>Lecturer:</strong> ${escapeHtml(section.lecturer_name)}</div>
                <div><strong>Generated:</strong> ${escapeHtml(generatedDate)}</div>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table admin-report-table">
                    <thead>
                        <tr>
                            <th>Week</th>
                            <th>Date</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                        <tr class="admin-report-summary-row">
                            <td colspan="2"><strong>Totals</strong></td>
                            <td><strong>${totalPresent}</strong></td>
                            <td><strong>${totalLate}</strong></td>
                            <td><strong>${totalAbsent}</strong></td>
                            <td><strong>${overallRate}%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `);
    }

    function generateRiskReport() {
        if (atRiskReport.length === 0) {
            showEmptyReport('No at-risk students found below 80% attendance.');
            return;
        }

        const rows = atRiskReport.slice().sort((a, b) => asNumber(a.attendance_rate) - asNumber(b.attendance_rate));
        const generatedDate = new Date().toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });

        showReportOutput(`
            <div class="admin-print-header">
                <h3>At-Risk Students Attendance Report</h3>
                <div><strong>Generated:</strong> ${escapeHtml(generatedDate)}</div>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table admin-report-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Attended</th>
                            <th>Total</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => {
                            const rate = asNumber(row.attendance_rate).toFixed(1);
                            return `
                                <tr>
                                    <td>${escapeHtml(row.student_name)}</td>
                                    <td>${escapeHtml(row.course_name)}</td>
                                    <td>${escapeHtml(row.section_code)}</td>
                                    <td>${asNumber(row.attended_count)}</td>
                                    <td>${asNumber(row.total_sessions)}</td>
                                    <td><span class="admin-rate-badge low">${rate}%</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `);
    }

    function bindReportControls() {
        const toggle = document.getElementById('admin-report-toggle');
        const panel = document.getElementById('admin-report-panel');
        const reportTypeInputs = document.querySelectorAll('input[name="admin-report-type"]');
        const sectionControls = document.getElementById('admin-section-report-controls');
        const riskControls = document.getElementById('admin-risk-report-controls');
        const sectionButton = document.getElementById('admin-generate-section-report');
        const riskButton = document.getElementById('admin-generate-risk-report');
        const printButton = document.getElementById('admin-report-print-btn');

        if (!toggle || !panel || !sectionControls || !riskControls || !sectionButton || !riskButton || !printButton) {
            return;
        }

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.classList.toggle('hidden', expanded);
        });

        reportTypeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const isSection = input.value === 'section' && input.checked;
                sectionControls.classList.toggle('hidden', !isSection);
                riskControls.classList.toggle('hidden', isSection);
            });
        });

        sectionButton.addEventListener('click', generateSectionReport);
        riskButton.addEventListener('click', generateRiskReport);
        printButton.addEventListener('click', () => {
            window.print();
        });
    }

    function populateSectionSelects() {
        const courseSelect = document.getElementById('admin-section-select');
        const reportSelect = document.getElementById('admin-report-section-select');

        if (!courseSelect || !reportSelect) {
            return;
        }

        if (sections.length === 0) {
            const noDataOption = '<option value="">No sections available</option>';
            courseSelect.innerHTML = noDataOption;
            reportSelect.innerHTML = noDataOption;
            return;
        }

        const optionsHtml = sections.map((section) => {
            const optionLabel = `${section.course_name} (${section.section_code}) - ${section.lecturer_name}`;
            return `<option value="${asNumber(section.section_id)}">${escapeHtml(optionLabel)}</option>`;
        }).join('');

        courseSelect.innerHTML = optionsHtml;
        reportSelect.innerHTML = optionsHtml;

        state.selectedSectionId = asNumber(sections[0].section_id);
        courseSelect.value = String(state.selectedSectionId);
        reportSelect.value = String(state.selectedSectionId);

        courseSelect.addEventListener('change', () => {
            state.selectedSectionId = asNumber(courseSelect.value);
            renderCourseTable();
        });
    }

    function bindStudentControls() {
        const searchInput = document.getElementById('admin-student-search');
        const results = document.getElementById('admin-student-results');

        if (!searchInput || !results) {
            return;
        }

        searchInput.addEventListener('input', () => {
            renderStudentList(searchInput.value);
            renderStudentBreakdown();
        });

        results.addEventListener('click', (event) => {
            const button = event.target.closest('.admin-student-result');
            if (!button) {
                return;
            }

            state.selectedStudentId = asNumber(button.dataset.studentId);
            results.querySelectorAll('.admin-student-result').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            renderStudentBreakdown();
        });

        renderStudentList('');
        renderStudentBreakdown();
    }

    function bindWeekControls() {
        const prev = document.getElementById('admin-prev-week');
        const next = document.getElementById('admin-next-week');
        const chips = document.querySelectorAll('.admin-chip');

        if (!prev || !next) {
            return;
        }

        state.currentWeekIndex = getWeekForTodayIndex();

        prev.addEventListener('click', () => {
            if (state.currentWeekIndex > 0) {
                state.currentWeekIndex -= 1;
                updateWeekHeader();
                renderWeekTable();
            }
        });

        next.addEventListener('click', () => {
            if (state.currentWeekIndex < weeks.length - 1) {
                state.currentWeekIndex += 1;
                updateWeekHeader();
                renderWeekTable();
            }
        });

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                state.weekFilter = chip.dataset.filter || 'all';
                chips.forEach((item) => item.classList.remove('active'));
                chip.classList.add('active');
                renderWeekTable();
            });
        });

        updateWeekHeader();
        renderWeekTable();
    }

    function bindTabs() {
        const buttons = document.querySelectorAll('.admin-tab-btn');
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                activateTab(button.dataset.tab);
            });
        });

        activateTab('course');
    }

    function init() {
        bindTabs();
        populateSectionSelects();
        renderCourseTable();
        bindCourseRowToggle();
        bindStudentControls();
        bindWeekControls();
        bindReportControls();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
