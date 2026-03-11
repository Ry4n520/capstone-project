/**
 * Homepage JavaScript - Real-time Dashboard Features
 * 
 * Handles real-time clock updates and dynamic dashboard behavior
 */

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
    
    // Check for ongoing classes
    function checkOngoingClasses() {
        const now = new Date();
        const currentTime = now.getHours() * 60 + now.getMinutes(); // Convert to minutes
        
        const classItems = document.querySelectorAll('.class-item');
        
        // Only process if there are actual class items
        if (classItems.length === 0) {
            return;
        }
        
        classItems.forEach(item => {
            const timeText = item.querySelector('.class-time');
            if (timeText) {
                const timeRange = timeText.textContent;
                const match = timeRange.match(/(\d+):(\d+)\s*(AM|PM)\s*-\s*(\d+):(\d+)\s*(AM|PM)/);
                
                if (match) {
                    let startHour = parseInt(match[1]);
                    let startMin = parseInt(match[2]);
                    let endHour = parseInt(match[4]);
                    let endMin = parseInt(match[5]);
                    
                    // Convert to 24-hour format
                    if (match[3] === 'PM' && startHour !== 12) startHour += 12;
                    if (match[3] === 'AM' && startHour === 12) startHour = 0;
                    if (match[6] === 'PM' && endHour !== 12) endHour += 12;
                    if (match[6] === 'AM' && endHour === 12) endHour = 0;
                    
                    const startTime = startHour * 60 + startMin;
                    const endTime = endHour * 60 + endMin;
                    
                    // Check if current time is within class time
                    if (currentTime >= startTime && currentTime < endTime) {
                        item.classList.add('ongoing');
                        // Add or update the ongoing status badge
                        let statusBadge = item.querySelector('.class-status');
                        if (!statusBadge) {
                            statusBadge = document.createElement('span');
                            statusBadge.className = 'class-status status-ongoing';
                            statusBadge.textContent = 'Ongoing';
                            item.appendChild(statusBadge);
                        }
                    } else {
                        item.classList.remove('ongoing');
                        const statusBadge = item.querySelector('.class-status');
                        if (statusBadge) {
                            statusBadge.remove();
                        }
                    }
                }
            }
        });
    }
    
    // Check ongoing classes immediately and every minute
    checkOngoingClasses();
    setInterval(checkOngoingClasses, 60000);
    
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
            displayTodaysClasses(data.todays_classes || []);
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
    // Find the card with "Today's Classes" header
    const cards = document.querySelectorAll('.card-grid.row-2 .card');
    if (cards.length === 0) return;
    
    const classesCard = cards[0];
    
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
    const classesHtml = classes.map(cls => `
        <div class="class-item ${cls.class_status}">
            <div>
                <div class="class-time">${formatTime(cls.start_time)} - ${formatTime(cls.end_time)}</div>
                <div class="class-name">${cls.course_name}</div>
                <div class="class-lecturer">${cls.lecturer_name}</div>
            </div>
            <div style="text-align: right;">
                ${cls.class_status === 'ongoing' ? '<span class="class-status status-ongoing">Ongoing</span>' : ''}
                <div class="class-room">${cls.room_name}${cls.building ? ' - ' + cls.building : ''}</div>
            </div>
        </div>
    `).join('');
    
    const classesContainer = document.createElement('div');
    classesContainer.className = 'classes-list';
    classesContainer.innerHTML = classesHtml;
    classesCard.appendChild(classesContainer);
}

/**
 * Display announcements
 */
function displayAnnouncements(announcements) {
    const cards = document.querySelectorAll('.card-grid.row-2 .card');
    if (cards.length < 2) return;
    
    const announcementCard = cards[1];
    
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
    // Find the card in row-1 which should be bookings
    const bookingsCard = document.querySelector('.card-grid.row-1 .card');
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
