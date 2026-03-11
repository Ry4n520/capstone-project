/**
 * Announcements Page JavaScript
 */

let currentFilter = 'all';
let allAnnouncements = [];

document.addEventListener('DOMContentLoaded', function() {
    loadAllAnnouncements().then(() => {
        // Check if redirected to specific announcement
        const urlParams = new URLSearchParams(window.location.search);
        const announcementId = urlParams.get('id');
        if (announcementId) {
            const announcement = findAnnouncementById(announcementId);
            if (announcement) {
                openAnnouncementModal(announcement);
            }
        }
    });
});

function loadAllAnnouncements() {
    const container = document.getElementById('announcements-container');
    container.innerHTML = '<div class="loading-state">Loading announcements...</div>';

    return fetch(`api/get-all-announcements.php?filter=${currentFilter}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAllAnnouncements(data.announcements);
            } else {
                container.innerHTML = '<div class="error-state">Error loading announcements</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="error-state">Error loading announcements</div>';
        });
}

function displayAllAnnouncements(announcements) {
    const container = document.getElementById('announcements-container');
    allAnnouncements = announcements;

    if (announcements.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No announcements found</p></div>';
        return;
    }

    container.innerHTML = announcements.map(ann => `
        <div class="announcement-card" data-id="${ann.announcement_id}">
            <div class="announcement-card-header">
                <h3 class="announcement-card-title">${escapeHtml(ann.title)}</h3>
                <span class="announcement-card-time">${escapeHtml(ann.time_ago)}</span>
            </div>
            <div class="announcement-card-content">
                ${escapeHtml(truncateText(ann.content, 200))}
            </div>
            <div class="announcement-card-footer">
                <span class="announcement-author">Posted by ${escapeHtml(ann.created_by)}</span>
                <span class="read-more">Read more →</span>
            </div>
        </div>
    `).join('');

    container.querySelectorAll('.announcement-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const announcement = findAnnouncementById(id);
            if (announcement) {
                openAnnouncementModal(announcement);
                const url = new URL(window.location);
                url.searchParams.set('id', id);
                window.history.replaceState({}, '', url);
            }
        });
    });
}

function filterAnnouncements(filter, event) {
    currentFilter = filter;

    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    if (event && event.target) {
        event.target.classList.add('active');
    }

    // Reload announcements with filter
    loadAllAnnouncements();
}

function openAnnouncementModal(announcement) {
    document.getElementById('modal-title').textContent = announcement.title;
    document.getElementById('modal-content').textContent = announcement.content;
    document.getElementById('modal-date').textContent = announcement.formatted_date;

    document.getElementById('announcement-modal').classList.remove('hidden');
}

function closeAnnouncementModal() {
    document.getElementById('announcement-modal').classList.add('hidden');

    // Remove ID from URL if present
    const url = new URL(window.location);
    url.searchParams.delete('id');
    window.history.replaceState({}, '', url);
}

function findAnnouncementById(id) {
    return allAnnouncements.find(ann => ann.announcement_id == id);
}

function truncateText(text, length) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
