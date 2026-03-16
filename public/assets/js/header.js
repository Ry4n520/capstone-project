/**
 * Header JavaScript
 * 
 * Handles navigation interactions and announcement notifications
 */

// Load announcements on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
    // Refresh every 60 seconds
    setInterval(loadAnnouncements, 60000 );
});

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Load announcements for dropdown
 */
function loadAnnouncements() {
    fetch('api/get-announcements.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAnnouncementBadge(data.new_count);
                displayAnnouncementsDropdown(data.announcements);
            }
        })
        .catch(error => console.error('Error loading announcements:', error));
}

/**
 * Update announcement badge count
 */
function updateAnnouncementBadge(count) {
    const badge = document.getElementById('announcement-badge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

/**
 * Display announcements in dropdown
 */
function displayAnnouncementsDropdown(announcements) {
    const container = document.getElementById('announcements-list');
    if (!container) return;
    
    if (!Array.isArray(announcements) || announcements.length === 0) {
        container.innerHTML = '<div class="no-announcements">No recent announcements</div>';
        return;
    }
    
    container.innerHTML = announcements.map(ann => `
        <div class="announcement-item" onclick="window.location.href='announcements.php?id=${ann.announcement_id}'">
            <div class="announcement-icon">📢</div>
            <div class="announcement-content">
                <div class="announcement-title">${escapeHtml(ann.title)}</div>
                <div class="announcement-text">${truncateText(escapeHtml(ann.content), 80)}</div>
                <div class="announcement-time">${escapeHtml(ann.time_ago)}</div>
            </div>
        </div>
    `).join('');
}

/**
 * Toggle announcements dropdown
 */
function toggleAnnouncements(event) {
    event.preventDefault();
    event.stopPropagation();
    const dropdown = document.getElementById('announcements-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

/**
 * Truncate text to specified length
 */
function truncateText(text, length) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const container = document.querySelector('.nav-item.announcements-container');
    const dropdown = document.getElementById('announcements-dropdown');
    
    if (container && dropdown && !container.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});

