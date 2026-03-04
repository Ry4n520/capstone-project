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
    
    // Add smooth hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.cursor = 'default';
        });
    });
});
