<?php
/**
 * Smart Campus Management System - Class Scheduling (Admin)
 *
 * Admin-only page for managing weekly timetables and reviewing
 * schedule change requests from lecturers.
 */

include 'includes/check_session.php';

if ($user_role !== 'admin') {
    header('Location: homepage.php');
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date   = $current_datetime->format('l, F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Class Scheduling</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/class-scheduling.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container">

        <!-- Page Heading -->
        <div class="welcome-section">
            <h1>Class Scheduling</h1>
            <p>Manage weekly timetables and review lecturer change requests</p>
        </div>

        <!-- Stats Bar -->
        <div class="cs-stats-grid">
            <div class="card cs-stat-card">
                <div class="cs-stat-icon cs-icon-blue">📅</div>
                <div>
                    <div class="cs-stat-num" id="statTotal">—</div>
                    <div class="cs-stat-label">Total Classes</div>
                </div>
            </div>
            <div class="card cs-stat-card">
                <div class="cs-stat-icon cs-icon-green">✅</div>
                <div>
                    <div class="cs-stat-num cs-num-green" id="statReleased">—</div>
                    <div class="cs-stat-label">Released</div>
                </div>
            </div>
            <div class="card cs-stat-card">
                <div class="cs-stat-icon cs-icon-amber">⚠️</div>
                <div>
                    <div class="cs-stat-num cs-num-amber" id="statConflicts">—</div>
                    <div class="cs-stat-label">Conflicts</div>
                </div>
            </div>
            <div class="card cs-stat-card">
                <div class="cs-stat-icon cs-icon-purple">📩</div>
                <div>
                    <div class="cs-stat-num cs-num-purple" id="statRequests">—</div>
                    <div class="cs-stat-label">Change Requests</div>
                </div>
            </div>
        </div>

        <!-- Toolbar: week navigation + generate button -->
        <div class="cs-toolbar">
            <div class="cs-week-nav">
                <button id="prevWeekBtn" class="cs-nav-btn">← Previous Week</button>
                <span id="weekDisplay" class="cs-week-label">Loading…</span>
                <button id="nextWeekBtn" class="cs-nav-btn">Next Week →</button>
            </div>
            <div class="cs-toolbar-actions">
                <button id="addTimetableBtn" class="cs-btn-generate cs-btn-add">＋ Add Timetable Class</button>
                <button id="generateBtn" class="cs-btn-generate">⚡ Generate Next Week</button>
            </div>
        </div>

        <!-- Building filter chips -->
        <div class="cs-filter-row">
            <button class="cs-filter-chip active" data-filter="all">All</button>
            <button class="cs-filter-chip" data-filter="Block A">Block A</button>
            <button class="cs-filter-chip" data-filter="Block B">Block B</button>
            <button class="cs-filter-chip" data-filter="Block C">Block C</button>
            <button class="cs-filter-chip" data-filter="Block D">Block D</button>
            <button class="cs-filter-chip" data-filter="Sports">Sports Complex</button>
        </div>

        <!-- Timetable card -->
        <div class="card cs-timetable-card" id="timetableCard">
            <div id="timetableContainer">
                <p class="cs-empty-state">Loading timetable…</p>
            </div>
        </div>

        <!-- Change Requests section heading -->
        <div class="cs-section-label">
            Change Requests
            <span class="cs-requests-badge" id="requestsBadge" style="display:none"></span>
        </div>

        <!-- Request cards list -->
        <div class="cs-requests-list" id="requestsContainer">
            <p class="cs-empty-state cs-empty-card">Loading requests…</p>
        </div>

    </div><!-- /.container -->

    <!-- Add Timetable Modal -->
    <div id="addTimetableModal" class="cs-modal-overlay hidden">
        <div class="cs-modal-backdrop" onclick="closeAddTimetableModal()"></div>
        <div class="cs-modal-dialog cs-modal-wide">
            <div class="cs-modal-header">
                <h3>Add New Timetable Class</h3>
                <button class="cs-modal-close" onclick="closeAddTimetableModal()" aria-label="Close">&times;</button>
            </div>
            <div class="cs-modal-body">
                <p class="cs-modal-desc">Choose course, section, lecturer, time, and venue. Released entries will be visible to students.</p>

                <div class="cs-form-grid">
                    <div>
                        <label class="cs-field-label" for="addCourseSelect">Course <span style="color:#e53e3e">*</span></label>
                        <select id="addCourseSelect" class="cs-select"></select>
                    </div>
                    <div>
                        <label class="cs-field-label" for="addSectionSelect">Class / Section <span style="color:#e53e3e">*</span></label>
                        <select id="addSectionSelect" class="cs-select"></select>
                    </div>
                    <div>
                        <label class="cs-field-label" for="addLecturerInput">Lecturer</label>
                        <input id="addLecturerInput" class="cs-input" type="text" readonly>
                    </div>
                    <div>
                        <label class="cs-field-label" for="addRoomSelect">Classroom <span style="color:#e53e3e">*</span></label>
                        <select id="addRoomSelect" class="cs-select"></select>
                    </div>
                    <div>
                        <label class="cs-field-label" for="addWeekStartInput">Week Start Date (Monday) <span style="color:#e53e3e">*</span></label>
                        <input id="addWeekStartInput" class="cs-input" type="date">
                    </div>
                    <div>
                        <label class="cs-field-label" for="addDaySelect">Day <span style="color:#e53e3e">*</span></label>
                        <select id="addDaySelect" class="cs-select">
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>
                    <div>
                        <label class="cs-field-label" for="addStartTimeInput">Start Time <span style="color:#e53e3e">*</span></label>
                        <input id="addStartTimeInput" class="cs-input" type="time" step="300">
                    </div>
                    <div>
                        <label class="cs-field-label" for="addEndTimeInput">End Time <span style="color:#e53e3e">*</span></label>
                        <input id="addEndTimeInput" class="cs-input" type="time" step="300">
                    </div>
                </div>

                <label class="cs-inline-check">
                    <input id="addReleaseNowCheck" type="checkbox" checked>
                    Release immediately (students can view this class)
                </label>

                <p class="cs-input-error hidden" id="addTimetableError"></p>
            </div>
            <div class="cs-modal-footer">
                <button class="cs-btn-cancel" onclick="closeAddTimetableModal()">Cancel</button>
                <button id="addTimetableSubmitBtn" class="cs-btn-confirm-add" onclick="submitAddTimetable()">Add to Timetable</button>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="cs-modal-overlay hidden">
        <div class="cs-modal-backdrop" onclick="closeRejectModal()"></div>
        <div class="cs-modal-dialog">
            <div class="cs-modal-header">
                <h3>Reject Request</h3>
                <button class="cs-modal-close" onclick="closeRejectModal()" aria-label="Close">&times;</button>
            </div>
            <div class="cs-modal-body">
                <p class="cs-modal-desc" id="rejectModalDesc"></p>
                <label class="cs-field-label" for="rejectReasonInput">
                    Reason for rejection <span style="color:#e53e3e">*</span>
                </label>
                <textarea
                    id="rejectReasonInput"
                    class="cs-textarea"
                    placeholder="Briefly explain why this request is being rejected…"
                    rows="4"
                ></textarea>
                <p class="cs-input-error hidden" id="rejectReasonError">Please provide a reason before rejecting.</p>
            </div>
            <div class="cs-modal-footer">
                <button class="cs-btn-cancel" onclick="closeRejectModal()">Cancel</button>
                <button class="cs-btn-confirm-reject" onclick="confirmReject()">Confirm Reject</button>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        window.csConfig = {
            todayIso: '<?php echo htmlspecialchars($current_datetime->format('Y-m-d'), ENT_QUOTES); ?>'
        };
    </script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/class-scheduling.js?v=<?php echo time(); ?>"></script>
</body>
</html>
