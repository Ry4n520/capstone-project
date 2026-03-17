/**
 * Homepage JavaScript - Real-time Dashboard Features
 * 
 * Handles real-time clock updates and dynamic dashboard behavior
 */

const homepageConfig = window.homepageConfig || { role: 'student' };

document.addEventListener('DOMContentLoaded', function() {
    // Real-time clock update
    function updateClock() {
        const now = new Date();
        
        // Format time (12-hour format with AM/PM)
        let hours = now.getHours();
        const minutes = now.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        const timeString = `${hours}:${minutes.toString().padStart(2, '0')} ${ampm}`;
        
        // Format date
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
        const dayName = days[now.getDay()];
        const monthName = months[now.getMonth()];
        const day = now.getDate();
        const year = now.getFullYear();
        const dateString = `${dayName}, ${monthName} ${day.toString().padStart(2, '0')}, ${year}`;
        
        // Update clock elements
        const clockTime = document.getElementById('clock-time');
        const clockDate = document.getElementById('clock-date');
        const currentTime = document.getElementById('current-time');
        const currentDayDate = document.getElementById('current-day-date');
        
        if (clockTime) clockTime.textContent = timeString;
        if (clockDate) clockDate.textContent = dateString;
        if (currentTime) currentTime.textContent = timeString;
        if (currentDayDate) currentDayDate.textContent = dateString;
    }
    
    // Update clock immediately and every second
    updateClock();
    setInterval(updateClock, 1000);
    
    // Load dynamic homepage data
    loadHomepageData();
    // Refresh every 60 seconds
    setInterval(loadHomepageData, 60000);
    
    // Add smooth hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.cursor = 'default';
        });
    });
});

/**
 * Load homepage data from API
 * Gets today's classes, attendance, bookings, and announcements
 */
function loadHomepageData() {
    console.log('[Homepage] Starting data load...');
    fetch('api/get-homepage-data.php')
        .then(response => {
            console.log('[Homepage] Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('[Homepage] Raw response:', text.substring(0, 300));
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('[Homepage] JSON parse error:', e);
                throw new Error('Invalid JSON response');
            }
            
            console.log('[Homepage] Parsed result:', result);
            
            if (!result.success) {
                console.error('[Homepage] API returned error:', result);
                throw new Error(result.message || 'API error');
            }
            
            if (!result.data) {
                console.error('[Homepage] No data property in response');
                throw new Error('No data in response');
            }
            
            const data = result.data;
            
            // Log what we received
            console.log('[Homepage] Data received:', {
                attendance_rate: data.attendance_rate,
                todays_classes: data.todays_classes?.length,
                schedule_requests: data.schedule_requests?.length,
                upcoming_bookings: data.upcoming_bookings?.length,
                recent_announcements: data.recent_announcements?.length,
                active_bookings_count: data.active_bookings_count,
                upcoming_classes_count: data.upcoming_classes_count,
                debug: data._debug
            });
            
            // Update attendance rate
            if (data.attendance_rate !== undefined) {
                const attendanceEl = document.getElementById('attendance-rate');
                if (attendanceEl) {
                    attendanceEl.textContent = data.attendance_rate + '%';
                    console.log('[Homepage] Set attendance rate to:', data.attendance_rate + '%');
                }
            }
            
            // Update counts
            const activeBookingsEl = document.getElementById('active-bookings');
            const upcomingClassesEl = document.getElementById('upcoming-classes');
            
            if (activeBookingsEl) {
                activeBookingsEl.textContent = data.active_bookings_count || 0;
                console.log('[Homepage] Set active bookings to:', data.active_bookings_count);
            }
            
            if (upcomingClassesEl) {
                upcomingClassesEl.textContent = data.upcoming_classes_count || 0;
                console.log('[Homepage] Set upcoming classes to:', data.upcoming_classes_count);
            }
            
            // Display data
            if (homepageConfig.role === 'admin') {
                displayScheduleRequests(data.schedule_requests || []);
            } else {
                displayTodaysClasses(data.todays_classes || []);
            }
            displayAnnouncements(data.recent_announcements || []);
            displayUpcomingBookings(data.upcoming_bookings || []);
            
            console.log('[Homepage] Data display complete');
        })
        .catch(error => {
            console.error('[Homepage] Error:', error);
            alert('Error loading homepage data. Check console for details.');
        });
}

/**
 * Display today's classes
 */
function displayTodaysClasses(classes) {
    const classesCard = getPrimaryDashboardCard();
    if (!classesCard) return;
    
    // Clear previous content
    const emptyStates = classesCard.querySelectorAll('.empty-state');
    emptyStates.forEach(el => el.remove());
    
    const existingList = classesCard.querySelector('.classes-list');
    if (existingList) existingList.remove();
    
    // If no classes, show empty state
    if (classes.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.innerHTML = '<p>No classes scheduled for today</p>';
        classesCard.appendChild(emptyDiv);
        return;
    }
    
    // Create classes list container
    const classesHtml = classes.map(cls => {
        return `
        <div class="class-item">
            <div>
                <div class="class-time">${formatTime(cls.start_time)} - ${formatTime(cls.end_time)}</div>
                <div class="class-name">${cls.course_name}</div>
                <div class="class-lecturer">${cls.lecturer_name}</div>
            </div>
            <div style="text-align: right;">
                <div class="class-room">${cls.room_name}${cls.building ? ' - ' + cls.building : ''}</div>
            </div>
        </div>
    `;
    }).join('');
    
    const classesContainer = document.createElement('div');
    classesContainer.className = 'classes-list';
    classesContainer.innerHTML = classesHtml;
    classesCard.appendChild(classesContainer);
}

/**
 * Display admin request changes preview
 */
function displayScheduleRequests(requests) {
    const requestCard = getPrimaryDashboardCard();
    if (!requestCard) return;

    clearPrimaryCardContent(requestCard, ['classes-list', 'request-changes-list']);

    if (requests.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.innerHTML = '<p>No pending schedule change requests</p>';
        requestCard.appendChild(emptyDiv);
        return;
    }

    const requestsHtml = requests.map(request => {
        const currentTime = [formatTime(request.current_start_time), formatTime(request.current_end_time)]
            .filter(Boolean)
            .join(' - ') || 'No existing time';
        const requestedTime = [formatTime(request.start_time), formatTime(request.end_time)]
            .filter(Boolean)
            .join(' - ');
        const currentRoom = request.current_room_name
            ? `${escapeHtml(request.current_room_name)}${request.current_building ? ` (${escapeHtml(request.current_building)})` : ''}`
            : 'No existing room';
        const requestedRoom = `${escapeHtml(request.requested_room_name)}${request.requested_building ? ` (${escapeHtml(request.requested_building)})` : ''}`;

        return `
        <div class="request-change-item" onclick="window.location.href='timetable.php'">
            <div class="request-change-topline">
                <div>
                    <div class="request-change-title">${escapeHtml(request.course_name)} (${escapeHtml(request.section_code)})</div>
                    <div class="request-change-meta">Lecturer: ${escapeHtml(request.lecturer_name)}</div>
                </div>
                <div class="request-change-badge">Pending</div>
            </div>
            <div class="request-change-line"><span>Current</span>${escapeHtml(request.current_day_of_week || request.day_of_week)} • ${escapeHtml(currentTime)} • ${currentRoom}</div>
            <div class="request-change-line"><span>Requested</span>${escapeHtml(request.day_of_week)} • ${escapeHtml(requestedTime)} • ${requestedRoom}</div>
            <div class="request-change-meta">Week of ${escapeHtml(formatDate(request.week_start_date))}</div>
        </div>`;
    }).join('');

    const requestsContainer = document.createElement('div');
    requestsContainer.className = 'request-changes-list';
    requestsContainer.innerHTML = requestsHtml + '<a href="timetable.php" class="view-all-link">Review All Requests →</a>';
    requestCard.appendChild(requestsContainer);
}

/**
 * Display announcements
 */
function displayAnnouncements(announcements) {
    const announcementCard = document.getElementById('announcements-card');
    if (!announcementCard) return;
    
    const emptyStates = announcementCard.querySelectorAll('.empty-state');
    emptyStates.forEach(el => el.remove());
    
    const existingList = announcementCard.querySelector('.announcements-list');
    if (existingList) existingList.remove();
    
    if (announcements.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.innerHTML = '<p>No announcements at this time</p>';
        announcementCard.appendChild(emptyDiv);
        return;
    }
    
    const announcementsHtml = announcements.map(ann => `
        <div class="announcement-item" onclick="window.location.href='announcements.php'">
            <div class="announcement-title">${ann.title}</div>
            <div class="announcement-desc">${truncate(ann.content, 100)}</div>
            <div class="announcement-time">${ann.time_ago}</div>
        </div>
    `).join('');
    
    const announcementsContainer = document.createElement('div');
    announcementsContainer.className = 'announcements-list';
    announcementsContainer.innerHTML = announcementsHtml + `
        <a href="announcements.php" class="view-all-link">View All Announcements →</a>
    `;
    announcementCard.appendChild(announcementsContainer);
}

/**
 * Display upcoming bookings
 */
function displayUpcomingBookings(bookings) {
    const bookingsCard = document.getElementById('bookings-card');
    if (!bookingsCard) return;
    
    // Clear previous content
    const emptyStates = bookingsCard.querySelectorAll('.empty-state');
    emptyStates.forEach(el => el.remove());
    
    const existingGrid = bookingsCard.querySelector('.bookings-grid');
    if (existingGrid) existingGrid.remove();
    
    // If no bookings, show empty state
    if (bookings.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.innerHTML = '<p>No upcoming facility bookings</p>';
        bookingsCard.appendChild(emptyDiv);
        return;
    }
    
    // Create bookings grid
    const bookingsHtml = bookings.map(booking => `
        <div class="booking-item">
            <div class="booking-title">${booking.facility_name}</div>
            <div class="booking-time">${formatDate(booking.booking_date)} • ${formatTime(booking.start_time)}</div>
            <div class="booking-status ${booking.booking_status === 'confirmed' ? 'status-confirmed' : 'status-pending'}">
                ${booking.booking_status === 'confirmed' ? '✓ Confirmed' : '⏳ Pending'}
            </div>
        </div>
    `).join('');
    
    const bookingsContainer = document.createElement('div');
    bookingsContainer.className = 'bookings-grid';
    bookingsContainer.innerHTML = bookingsHtml;
    bookingsCard.appendChild(bookingsContainer);
}

/**
 * Format time from 24-hour format to 12-hour format
 */
function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

/**
 * Format date to readable format
 */
function formatDate(date) {
    if (!date) return '';
    return new Date(date + 'T00:00:00').toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric'
    });
}

/**
 * Truncate text to specified length
 */
function truncate(text, length) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function getPrimaryDashboardCard() {
    return document.getElementById('primary-dashboard-card');
}

function clearPrimaryCardContent(card, removableClasses) {
    const emptyStates = card.querySelectorAll('.empty-state');
    emptyStates.forEach(el => el.remove());

    removableClasses.forEach(className => {
        const element = card.querySelector(`.${className}`);
        if (element) {
            element.remove();
        }
    });
}
