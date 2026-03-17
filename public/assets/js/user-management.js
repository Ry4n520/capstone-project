/**
 * User Management interactions
 *
 * Handles row selection, table search, and real admin actions.
 */

(function () {
    const table = document.getElementById('usersTable');
    const searchInput = document.getElementById('userSearchInput');
    const visibleUserCount = document.getElementById('visibleUserCount');
    const feedback = document.getElementById('actionFeedback');
    const actionButtons = document.querySelectorAll('.action-btn');
    const modal = document.getElementById('userActionModal');
    const modalTitle = document.getElementById('umModalTitle');
    const modalBody = document.getElementById('umModalBody');
    const modalActions = document.getElementById('umModalActions');
    const modalCloseBtn = document.getElementById('umModalCloseBtn');

    let selectedRow = null;
    const allowedRoles = ['admin', 'staff', 'student'];

    function showFeedback(message, type) {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.remove('hidden', 'info', 'warn');
        feedback.classList.add(type || 'info');
    }

    function roleDisplay(role) {
        return role === 'staff' ? 'Lecturer' : role.charAt(0).toUpperCase() + role.slice(1);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getSelectedUser() {
        if (!selectedRow) {
            return null;
        }

        const ds = selectedRow.dataset;

        return {
            id: Number(ds.userId || 0),
            name: ds.name || 'Unknown User',
            email: ds.email || '',
            role: ds.role || 'student',
            gender: ds.gender || '',
            phone: ds.phone || '',
            dateJoined: ds.dateJoined || ''
        };
    }

    function closeModalUi() {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('um-modal-open');
    }

    function clearInlineError(container) {
        const errorBox = container ? container.querySelector('[data-modal-error]') : null;
        if (!errorBox) {
            return;
        }

        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    }

    function showInlineError(container, message) {
        const errorBox = container ? container.querySelector('[data-modal-error]') : null;
        if (!errorBox) {
            return;
        }

        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function openModal(options) {
        if (!modal || !modalTitle || !modalBody || !modalActions || !modalCloseBtn) {
            return Promise.reject(new Error('Modal UI is unavailable.'));
        }

        const config = options || {};
        const primaryText = config.primaryText || 'Confirm';
        const secondaryText = Object.prototype.hasOwnProperty.call(config, 'secondaryText')
            ? config.secondaryText
            : 'Cancel';

        modalTitle.textContent = config.title || 'Action';
        modalBody.innerHTML = config.bodyHtml || '';
        modalActions.innerHTML = '';

        const primaryButton = document.createElement('button');
        primaryButton.type = 'button';
        primaryButton.className = 'um-btn ' + (config.primaryClass || 'primary');
        primaryButton.textContent = primaryText;

        let secondaryButton = null;
        if (secondaryText !== null) {
            secondaryButton = document.createElement('button');
            secondaryButton.type = 'button';
            secondaryButton.className = 'um-btn';
            secondaryButton.textContent = secondaryText;
            modalActions.appendChild(secondaryButton);
        }

        modalActions.appendChild(primaryButton);

        modal.classList.remove('hidden');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('um-modal-open');

        return new Promise((resolve) => {
            const focusTarget = config.focusSelector
                ? modalBody.querySelector(config.focusSelector)
                : modalBody.querySelector('input, select, textarea, button');

            let finished = false;

            function finalize(result) {
                if (finished) {
                    return;
                }

                finished = true;
                document.removeEventListener('keydown', onKeydown);
                modal.removeEventListener('click', onModalClick);
                modalCloseBtn.removeEventListener('click', onCancel);
                primaryButton.removeEventListener('click', onConfirm);
                if (secondaryButton) {
                    secondaryButton.removeEventListener('click', onCancel);
                }

                closeModalUi();
                resolve(result || { confirmed: false, value: null });
            }

            function onCancel() {
                finalize({ confirmed: false, value: null });
            }

            function onModalClick(event) {
                if (event.target && event.target.hasAttribute('data-modal-close')) {
                    onCancel();
                }
            }

            function onKeydown(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    onCancel();
                }
            }

            async function onConfirm() {
                primaryButton.disabled = true;
                if (secondaryButton) {
                    secondaryButton.disabled = true;
                }

                try {
                    let value = true;
                    if (typeof config.onConfirm === 'function') {
                        value = await config.onConfirm(modalBody);
                    }

                    if (value === false) {
                        primaryButton.disabled = false;
                        if (secondaryButton) {
                            secondaryButton.disabled = false;
                        }
                        return;
                    }

                    finalize({ confirmed: true, value: value });
                } catch (error) {
                    showInlineError(modalBody, error.message || 'Please try again.');
                    primaryButton.disabled = false;
                    if (secondaryButton) {
                        secondaryButton.disabled = false;
                    }
                }
            }

            document.addEventListener('keydown', onKeydown);
            modal.addEventListener('click', onModalClick);
            modalCloseBtn.addEventListener('click', onCancel);
            primaryButton.addEventListener('click', onConfirm);
            if (secondaryButton) {
                secondaryButton.addEventListener('click', onCancel);
            }

            window.setTimeout(() => {
                if (focusTarget) {
                    focusTarget.focus();
                } else {
                    primaryButton.focus();
                }
            }, 0);
        });
    }

    function buildUserFormHtml(options) {
        const opts = options || {};
        const initial = opts.initial || {};
        const includeRole = Boolean(opts.includeRole);
        const includePassword = Boolean(opts.includePassword);
        const roleValue = String(initial.role || 'student').toLowerCase();

        const roleOptions = allowedRoles.map((role) => {
            const selected = roleValue === role ? ' selected' : '';
            return '<option value="' + escapeHtml(role) + '"' + selected + '>' + escapeHtml(roleDisplay(role)) + '</option>';
        }).join('');

        return [
            '<div class="um-form-grid">',
            '  <div class="um-form-error hidden" data-modal-error></div>',
            '  <div class="um-form-row">',
            '    <label for="um-name">Full Name</label>',
            '    <input id="um-name" name="name" type="text" value="' + escapeHtml(initial.name || '') + '" required>',
            '  </div>',
            '  <div class="um-form-row">',
            '    <label for="um-email">Email</label>',
            '    <input id="um-email" name="email" type="email" value="' + escapeHtml(initial.email || '') + '" required>',
            '  </div>',
            includeRole
                ? '  <div class="um-form-row"><label for="um-role">Role</label><select id="um-role" name="role">' + roleOptions + '</select></div>'
                : '',
            includePassword
                ? '  <div class="um-form-row"><label for="um-password">Temporary Password</label><input id="um-password" name="password" type="text" value="password123" required><span class="um-form-hint">User can change this after first login.</span></div>'
                : '',
            '  <div class="um-form-row">',
            '    <label for="um-gender">Gender (Optional)</label>',
            '    <input id="um-gender" name="gender" type="text" value="' + escapeHtml(initial.gender || '') + '">',
            '  </div>',
            '  <div class="um-form-row">',
            '    <label for="um-phone">Phone (Optional)</label>',
            '    <input id="um-phone" name="phone" type="text" value="' + escapeHtml(initial.phone || '') + '">',
            '  </div>',
            '</div>'
        ].join('');
    }

    async function promptUserForm(options) {
        const opts = options || {};
        const result = await openModal({
            title: opts.title || 'User Form',
            bodyHtml: buildUserFormHtml(opts),
            primaryText: opts.submitLabel || 'Save',
            secondaryText: 'Cancel',
            primaryClass: 'primary',
            focusSelector: '#um-name',
            onConfirm: function (container) {
                clearInlineError(container);

                const nameInput = container.querySelector('#um-name');
                const emailInput = container.querySelector('#um-email');
                const roleInput = container.querySelector('#um-role');
                const passwordInput = container.querySelector('#um-password');
                const genderInput = container.querySelector('#um-gender');
                const phoneInput = container.querySelector('#um-phone');

                const payload = {
                    name: nameInput ? nameInput.value.trim() : '',
                    email: emailInput ? emailInput.value.trim() : '',
                    gender: genderInput ? genderInput.value.trim() : '',
                    phone: phoneInput ? phoneInput.value.trim() : ''
                };

                if (payload.name === '') {
                    showInlineError(container, 'Full name is required.');
                    return false;
                }

                if (payload.email === '') {
                    showInlineError(container, 'Email is required.');
                    return false;
                }

                if (roleInput) {
                    const roleValue = roleInput.value.trim().toLowerCase();
                    if (!allowedRoles.includes(roleValue)) {
                        showInlineError(container, 'Role must be admin, staff, or student.');
                        return false;
                    }
                    payload.role = roleValue;
                }

                if (passwordInput) {
                    const passwordValue = passwordInput.value.trim();
                    if (passwordValue === '') {
                        showInlineError(container, 'Temporary password is required.');
                        return false;
                    }
                    payload.password = passwordValue;
                }

                return payload;
            }
        });

        return result.confirmed ? result.value : null;
    }

    async function promptRoleUpdate(selectedUser) {
        const currentRole = String(selectedUser.role || 'student').toLowerCase();
        const roleOptions = allowedRoles.map((role) => {
            const selected = currentRole === role ? ' selected' : '';
            return '<option value="' + escapeHtml(role) + '"' + selected + '>' + escapeHtml(roleDisplay(role)) + '</option>';
        }).join('');

        const result = await openModal({
            title: 'Update User Role',
            bodyHtml: [
                '<div class="um-form-grid">',
                '  <div class="um-form-error hidden" data-modal-error></div>',
                '  <div class="um-form-row">',
                '    <label for="um-role-only">Role for ' + escapeHtml(selectedUser.name) + '</label>',
                '    <select id="um-role-only" name="role">' + roleOptions + '</select>',
                '  </div>',
                '</div>'
            ].join(''),
            primaryText: 'Update Role',
            secondaryText: 'Cancel',
            primaryClass: 'primary',
            focusSelector: '#um-role-only',
            onConfirm: function (container) {
                clearInlineError(container);

                const roleInput = container.querySelector('#um-role-only');
                const roleValue = roleInput ? roleInput.value.trim().toLowerCase() : '';

                if (!allowedRoles.includes(roleValue)) {
                    showInlineError(container, 'Role must be admin, staff, or student.');
                    return false;
                }

                return roleValue;
            }
        });

        return result.confirmed ? result.value : null;
    }

    async function confirmDeleteUser(selectedUser) {
        const result = await openModal({
            title: 'Delete User',
            bodyHtml: '<p class="um-confirm-text">Delete user <strong>' +
                escapeHtml(selectedUser.name) + '</strong> (' + escapeHtml(selectedUser.email) +
                ')? This cannot be undone.</p>',
            primaryText: 'Delete User',
            secondaryText: 'Cancel',
            primaryClass: 'danger'
        });

        return result.confirmed;
    }

    function buildProfileHtml(profile, selectedUser) {
        const data = profile || {};
        const profileName = data.name || selectedUser.name;
        const profileEmail = data.email || selectedUser.email || '-';
        const profileRole = roleDisplay(String(data.role_name || selectedUser.role || 'student'));
        const profileGender = data.gender || '-';
        const profilePhone = data.phone || '-';
        const profileJoined = data.date_joined ? String(data.date_joined) : (selectedUser.dateJoined || '-');

        return [
            '<ul class="um-profile-list">',
            '  <li class="um-profile-item"><span>Name</span><strong>' + escapeHtml(profileName) + '</strong></li>',
            '  <li class="um-profile-item"><span>Email</span><strong>' + escapeHtml(profileEmail) + '</strong></li>',
            '  <li class="um-profile-item"><span>Role</span><strong>' + escapeHtml(profileRole) + '</strong></li>',
            '  <li class="um-profile-item"><span>Gender</span><strong>' + escapeHtml(profileGender) + '</strong></li>',
            '  <li class="um-profile-item"><span>Phone</span><strong>' + escapeHtml(profilePhone) + '</strong></li>',
            '  <li class="um-profile-item"><span>Joined</span><strong>' + escapeHtml(profileJoined) + '</strong></li>',
            '</ul>'
        ].join('');
    }

    async function showProfileModal(profile, selectedUser) {
        await openModal({
            title: 'User Profile',
            bodyHtml: buildProfileHtml(profile, selectedUser),
            primaryText: 'Close',
            secondaryText: null,
            primaryClass: 'primary'
        });
    }

    async function callApi(action, payload, method) {
        const verb = method || 'POST';
        let url = 'api/user-management.php';
        const options = {
            method: verb,
            credentials: 'same-origin',
            headers: {}
        };

        if (verb === 'GET') {
            const params = new URLSearchParams({ action });
            Object.keys(payload || {}).forEach((key) => {
                params.append(key, String(payload[key]));
            });
            url += '?' + params.toString();
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(Object.assign({ action: action }, payload || {}));
        }

        const response = await fetch(url, options);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Request failed.');
        }

        return data;
    }

    function refreshPageSoon() {
        setTimeout(() => {
            window.location.reload();
        }, 650);
    }

    async function addUser() {
        const payload = await promptUserForm({
            title: 'Add New User',
            submitLabel: 'Create User',
            includeRole: true,
            includePassword: true,
            initial: {
                role: 'student'
            }
        });

        if (!payload) {
            return;
        }

        showFeedback('Creating user...', 'info');

        const data = await callApi('create', payload);

        showFeedback(data.message || 'User created successfully.', 'info');
        refreshPageSoon();
    }

    async function editUser(selectedUser) {
        const payload = await promptUserForm({
            title: 'Edit User Information',
            submitLabel: 'Save Changes',
            includeRole: false,
            includePassword: false,
            initial: {
                name: selectedUser.name,
                email: selectedUser.email,
                gender: selectedUser.gender,
                phone: selectedUser.phone
            }
        });

        if (!payload) {
            return;
        }

        showFeedback('Updating user details...', 'info');

        const data = await callApi('update', {
            user_id: selectedUser.id,
            name: payload.name,
            email: payload.email,
            gender: payload.gender,
            phone: payload.phone
        });

        showFeedback(data.message || 'User updated successfully.', 'info');
        refreshPageSoon();
    }

    async function deleteUser(selectedUser) {
        const confirmed = await confirmDeleteUser(selectedUser);
        if (!confirmed) {
            return;
        }

        showFeedback('Deleting user...', 'warn');

        const data = await callApi('delete', {
            user_id: selectedUser.id
        });

        showFeedback(data.message || 'User deleted successfully.', 'info');
        refreshPageSoon();
    }

    async function updateUserRole(selectedUser) {
        const role = await promptRoleUpdate(selectedUser);
        if (!role) {
            return;
        }

        showFeedback('Updating role...', 'info');

        const data = await callApi('update_role', {
            user_id: selectedUser.id,
            role: role
        });

        showFeedback(data.message || 'User role updated successfully.', 'info');
        refreshPageSoon();
    }

    async function viewProfile(selectedUser) {
        showFeedback('Loading user profile...', 'info');

        const data = await callApi('get_profile', {
            user_id: selectedUser.id
        }, 'GET');

        await showProfileModal(data.profile || {}, selectedUser);
        showFeedback('Profile loaded for ' + selectedUser.name + '.', 'info');
    }

    function handleRowSelection() {
        if (!table) {
            return;
        }

        const rows = table.querySelectorAll('tbody .user-row');
        rows.forEach((row) => {
            row.addEventListener('click', function () {
                rows.forEach((r) => r.classList.remove('selected'));
                this.classList.add('selected');
                selectedRow = this;

                const selectedUser = getSelectedUser();
                if (selectedUser) {
                    showFeedback(
                        'Selected user: ' + selectedUser.name +
                        ' (' + selectedUser.email + ') - ' + roleDisplay(selectedUser.role),
                        'info'
                    );
                }
            });
        });
    }

    function updateVisibleCount() {
        if (!table || !visibleUserCount) {
            return;
        }

        const rows = table.querySelectorAll('tbody .user-row');
        let visible = 0;

        rows.forEach((row) => {
            if (row.style.display !== 'none') {
                visible++;
            }
        });

        visibleUserCount.textContent = 'Showing ' + visible + ' user' + (visible === 1 ? '' : 's');
    }

    function setupSearch() {
        if (!table || !searchInput) {
            if (visibleUserCount && table) {
                updateVisibleCount();
            }
            return;
        }

        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody .user-row');

            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                const matches = query === '' || text.indexOf(query) !== -1;
                row.style.display = matches ? '' : 'none';

                if (!matches && selectedRow === row) {
                    selectedRow.classList.remove('selected');
                    selectedRow = null;
                }
            });

            updateVisibleCount();
        });

        updateVisibleCount();
    }

    function setupActionButtons() {
        if (!actionButtons.length) {
            return;
        }

        actionButtons.forEach((button) => {
            button.addEventListener('click', async function () {
                const action = this.getAttribute('data-action');
                const selectedUser = getSelectedUser();

                try {
                    if (action === 'add') {
                        await addUser();
                        return;
                    }

                    if (!selectedUser || !selectedUser.id) {
                        showFeedback('Select a user from the table before using this action.', 'warn');
                        return;
                    }

                    if (action === 'edit') {
                        await editUser(selectedUser);
                        return;
                    }

                    if (action === 'delete') {
                        await deleteUser(selectedUser);
                        return;
                    }

                    if (action === 'roles') {
                        await updateUserRole(selectedUser);
                        return;
                    }

                    if (action === 'profile') {
                        await viewProfile(selectedUser);
                    }
                } catch (error) {
                    showFeedback(error.message || 'Action failed.', 'warn');
                }
            });
        });
    }

    handleRowSelection();
    setupSearch();
    setupActionButtons();
})();
