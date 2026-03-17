/**
 * Announcements Page JavaScript
 */

const announcementsConfig = window.ANNOUNCEMENTS_CONFIG || {
    isAdmin: false
};

const isAdminUser = Boolean(announcementsConfig.isAdmin);

let currentFilter = 'all';
let allAnnouncements = [];
let editingAnnouncementId = null;
let feedbackTimeoutId = null;

document.addEventListener('DOMContentLoaded', function() {
    setupAnnouncementInteractions();

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

function setupAnnouncementInteractions() {
    document.addEventListener('click', function(event) {
        const menuItem = event.target.closest('.announcement-admin-item');
        if (menuItem) {
            event.preventDefault();
            event.stopPropagation();

            const action = menuItem.getAttribute('data-action');
            const id = Number(menuItem.getAttribute('data-id'));
            closeAllAnnouncementAdminMenus();

            if (id > 0) {
                handleAnnouncementAdminAction(action, id);
            }
            return;
        }

        if (!event.target.closest('.announcement-admin-controls')) {
            closeAllAnnouncementAdminMenus();
        }
    });
}

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

    container.innerHTML = announcements.map(ann => {
        const adminControls = isAdminUser
            ? `
                <div class="announcement-admin-controls">
                    <button
                        type="button"
                        class="announcement-admin-menu-btn"
                        onclick="toggleAnnouncementAdminMenu(event, ${Number(ann.announcement_id)})"
                        title="Announcement actions"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >⋮</button>
                    <div class="announcement-admin-menu" id="announcement-admin-menu-${Number(ann.announcement_id)}">
                        <button type="button" class="announcement-admin-item" data-action="view" data-id="${Number(ann.announcement_id)}">View Details</button>
                        <button type="button" class="announcement-admin-item" data-action="edit" data-id="${Number(ann.announcement_id)}">Edit Announcement</button>
                        <button type="button" class="announcement-admin-item danger" data-action="delete" data-id="${Number(ann.announcement_id)}">Delete Announcement</button>
                    </div>
                </div>
            `
            : '';

        const targetLabel = getAnnouncementTargetLabel(ann);
        const targetMeta = isAdminUser
            ? `<span class="announcement-author">${escapeHtml(targetLabel)}</span>`
            : '';

        return `
        <div class="announcement-card" data-id="${ann.announcement_id}">
            <div class="announcement-card-header">
                <h3 class="announcement-card-title">${escapeHtml(ann.title)}</h3>
                <div class="announcement-card-header-right">
                    <span class="announcement-card-time">${escapeHtml(ann.time_ago)}</span>
                    ${adminControls}
                </div>
            </div>
            <div class="announcement-card-content">
                ${escapeHtml(truncateText(ann.content, 200))}
            </div>
            <div class="announcement-card-footer">
                <span class="announcement-author">Posted by ${escapeHtml(ann.created_by)}</span>
                <div class="announcement-card-footer-right">
                    ${targetMeta}
                    <span class="read-more">Read more →</span>
                </div>
            </div>
        </div>
    `;
    }).join('');

    container.querySelectorAll('.announcement-card').forEach(card => {
        card.addEventListener('click', function(event) {
            if (event.target.closest('.announcement-admin-controls')) {
                return;
            }

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
    const targetLabel = getAnnouncementTargetLabel(announcement);
    const dateText = isAdminUser
        ? `${announcement.formatted_date} • ${targetLabel}`
        : announcement.formatted_date;

    document.getElementById('modal-title').textContent = announcement.title;
    document.getElementById('modal-content').textContent = announcement.content;
    document.getElementById('modal-date').textContent = dateText;

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

function getAnnouncementTargetLabel(announcement) {
    const roleName = String(announcement && announcement.target_role_name ? announcement.target_role_name : '').toLowerCase();
    if (!roleName) {
        return 'Visible to: All Users';
    }

    if (roleName === 'staff') {
        return 'Visible to: Lecturers';
    }

    if (roleName === 'student') {
        return 'Visible to: Students';
    }

    if (roleName === 'admin') {
        return 'Visible to: Admins';
    }

    return 'Visible to: ' + roleName;
}

function showActionFeedback(message, isError) {
    const feedback = document.getElementById('announcement-action-feedback');
    if (!feedback) {
        return;
    }

    if (feedbackTimeoutId) {
        window.clearTimeout(feedbackTimeoutId);
    }

    feedback.textContent = message;
    feedback.classList.remove('hidden', 'is-error');
    if (isError) {
        feedback.classList.add('is-error');
    }

    feedbackTimeoutId = window.setTimeout(() => {
        feedback.classList.add('hidden');
    }, 3600);
}

function closeAllAnnouncementAdminMenus(exceptMenu) {
    document.querySelectorAll('.announcement-admin-menu.open').forEach(menu => {
        if (menu !== exceptMenu) {
            menu.classList.remove('open');

            const controls = menu.closest('.announcement-admin-controls');
            const btn = controls ? controls.querySelector('.announcement-admin-menu-btn') : null;
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        }
    });
}

function toggleAnnouncementAdminMenu(event, announcementId) {
    if (!isAdminUser) {
        return;
    }

    event.stopPropagation();
    const menu = document.getElementById(`announcement-admin-menu-${announcementId}`);
    if (!menu) {
        return;
    }

    const shouldOpen = !menu.classList.contains('open');
    closeAllAnnouncementAdminMenus(menu);
    menu.classList.toggle('open', shouldOpen);

    const controls = menu.closest('.announcement-admin-controls');
    const btn = controls ? controls.querySelector('.announcement-admin-menu-btn') : null;
    if (btn) {
        btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }
}

function handleAnnouncementAdminAction(action, announcementId) {
    if (!isAdminUser) {
        return;
    }

    const announcement = findAnnouncementById(announcementId);
    if (!announcement) {
        showActionFeedback('Announcement not found. Refresh and try again.', true);
        return;
    }

    if (action === 'view') {
        openAnnouncementModal(announcement);
        return;
    }

    if (action === 'edit') {
        openAnnouncementFormModal('edit', announcementId);
        return;
    }

    if (action === 'delete') {
        deleteAnnouncement(announcementId);
    }
}

function openAnnouncementFormModal(mode, announcementId) {
    if (!isAdminUser) {
        return;
    }

    const modal = document.getElementById('announcement-form-modal');
    const modalTitle = document.getElementById('announcement-form-title');
    const submitBtn = document.getElementById('announcement-submit-btn');
    const titleInput = document.getElementById('announcement-title-input');
    const contentInput = document.getElementById('announcement-content-input');
    const targetInput = document.getElementById('announcement-target-input');

    if (!modal || !modalTitle || !submitBtn || !titleInput || !contentInput || !targetInput) {
        return;
    }

    const isEditMode = mode === 'edit';
    editingAnnouncementId = null;

    if (isEditMode) {
        const announcement = findAnnouncementById(announcementId);
        if (!announcement) {
            showActionFeedback('Announcement not found for editing.', true);
            return;
        }

        editingAnnouncementId = Number(announcement.announcement_id);
        modalTitle.textContent = 'Edit Announcement';
        submitBtn.textContent = 'Save Changes';
        titleInput.value = announcement.title || '';
        contentInput.value = announcement.content || '';
        targetInput.value = announcement.target_role_name || 'all';
    } else {
        modalTitle.textContent = 'Post Announcement';
        submitBtn.textContent = 'Post Announcement';
        titleInput.value = '';
        contentInput.value = '';
        targetInput.value = 'all';
    }

    modal.classList.remove('hidden');
    window.setTimeout(() => {
        titleInput.focus();
    }, 0);
}

function closeAnnouncementFormModal() {
    const modal = document.getElementById('announcement-form-modal');
    const titleInput = document.getElementById('announcement-title-input');
    const contentInput = document.getElementById('announcement-content-input');
    const targetInput = document.getElementById('announcement-target-input');

    if (modal) {
        modal.classList.add('hidden');
    }

    if (titleInput) {
        titleInput.value = '';
    }

    if (contentInput) {
        contentInput.value = '';
    }

    if (targetInput) {
        targetInput.value = 'all';
    }

    editingAnnouncementId = null;
}

async function submitAnnouncementForm() {
    if (!isAdminUser) {
        return;
    }

    const titleInput = document.getElementById('announcement-title-input');
    const contentInput = document.getElementById('announcement-content-input');
    const targetInput = document.getElementById('announcement-target-input');
    const submitBtn = document.getElementById('announcement-submit-btn');

    if (!titleInput || !contentInput || !targetInput || !submitBtn) {
        return;
    }

    const title = titleInput.value.trim();
    const content = contentInput.value.trim();
    const targetRole = targetInput.value.trim() || 'all';

    if (!title || !content) {
        showActionFeedback('Title and content are required.', true);
        return;
    }

    const payload = {
        action: editingAnnouncementId ? 'update' : 'create',
        title: title,
        content: content,
        target_role: targetRole
    };

    if (editingAnnouncementId) {
        payload.announcement_id = editingAnnouncementId;
    }

    const originalLabel = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = editingAnnouncementId ? 'Saving...' : 'Posting...';

    try {
        const response = await fetch('api/announcements-admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save announcement.');
        }

        closeAnnouncementFormModal();
        showActionFeedback(data.message || 'Announcement saved successfully.', false);
        await loadAllAnnouncements();
    } catch (error) {
        showActionFeedback(error.message || 'Failed to save announcement.', true);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalLabel;
    }
}

async function deleteAnnouncement(announcementId) {
    if (!isAdminUser) {
        return;
    }

    try {
        const response = await fetch('api/announcements-admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete',
                announcement_id: Number(announcementId)
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete announcement.');
        }

        const url = new URL(window.location);
        if (url.searchParams.get('id') == announcementId) {
            closeAnnouncementModal();
        }

        showActionFeedback(data.message || 'Announcement deleted successfully.', false);
        await loadAllAnnouncements();
    } catch (error) {
        showActionFeedback(error.message || 'Failed to delete announcement.', true);
    }
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
