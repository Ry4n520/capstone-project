const notificationsConfig = window.NOTIFICATIONS_CONFIG || { isAdmin: false };
const isAdmin = Boolean(notificationsConfig.isAdmin);

let allNotifications = [];

function escapeHtml(text) {
    if (text == null) {
        return '';
    }

    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function truncateText(text, maxLength) {
    if (!text) {
        return '';
    }

    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
}

function closeAllAnnouncementMenus() {
    document.querySelectorAll('.notification-menu.open').forEach((menu) => {
        menu.classList.remove('open');
    });
}

function toggleAnnouncementMenu(event, announcementId) {
    if (!isAdmin) {
        return;
    }

    event.stopPropagation();
    const menu = document.getElementById(`announcement-menu-${announcementId}`);
    if (!menu) {
        return;
    }

    const shouldOpen = !menu.classList.contains('open');
    closeAllAnnouncementMenus();
    menu.classList.toggle('open', shouldOpen);
}

function openAddAnnouncementModal() {
    if (!isAdmin) {
        return;
    }

    const modal = document.getElementById('addAnnouncementModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeAddAnnouncementModal() {
    const modal = document.getElementById('addAnnouncementModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function submitAnnouncementPlaceholder() {
    closeAddAnnouncementModal();
    alert('Post announcement is not connected yet. This is a UI placeholder.');
}

function handleAnnouncementMenuAction(event) {
    const target = event.target;
    if (!target.classList.contains('notification-menu-item')) {
        return;
    }

    event.preventDefault();
    closeAllAnnouncementMenus();

    const action = target.getAttribute('data-action') || 'action';
    alert(action + ' action is not connected yet.');
}

function renderNotifications(announcements) {
    const container = document.getElementById('notifications-container');
    if (!container) {
        return;
    }

    if (!Array.isArray(announcements) || announcements.length === 0) {
        container.innerHTML = '<div class="notifications-empty-state">No notifications to display.</div>';
        return;
    }

    container.innerHTML = announcements.map((ann) => {
        const adminMenu = isAdmin
            ? `
                <div class="notification-actions">
                    <button type="button" class="notification-menu-btn" onclick="toggleAnnouncementMenu(event, ${Number(ann.announcement_id)})" title="Announcement actions">⋮</button>
                    <div class="notification-menu" id="announcement-menu-${Number(ann.announcement_id)}">
                        <button type="button" class="notification-menu-item" data-action="Edit">Edit</button>
                        <button type="button" class="notification-menu-item" data-action="Delete">Delete</button>
                    </div>
                </div>
            `
            : '';

        return `
            <div class="notification-card" data-id="${Number(ann.announcement_id)}">
                <div class="notification-card-header">
                    <h3 class="notification-title">${escapeHtml(ann.title)}</h3>
                    <span class="notification-time">${escapeHtml(ann.time_ago || '')}</span>
                </div>
                <div class="notification-content">${escapeHtml(truncateText(ann.content, 220))}</div>
                <div class="notification-footer">
                    <span class="notification-author">Posted by ${escapeHtml(ann.created_by || 'System')}</span>
                    ${adminMenu}
                </div>
            </div>
        `;
    }).join('');
}

async function loadNotifications() {
    const container = document.getElementById('notifications-container');
    if (!container) {
        return;
    }

    container.innerHTML = '<div class="notifications-empty-state">Loading notifications...</div>';

    try {
        const response = await fetch('api/get-all-announcements.php?filter=all', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Unable to load notifications.');
        }

        allNotifications = data.announcements || [];
        renderNotifications(allNotifications);
    } catch (error) {
        container.innerHTML = `<div class="notifications-empty-state">${escapeHtml(error.message || 'Unable to load notifications.')}</div>`;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    loadNotifications();

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.notification-actions')) {
            closeAllAnnouncementMenus();
        }
    });

    document.addEventListener('click', handleAnnouncementMenuAction);
});
