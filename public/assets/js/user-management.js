/**
 * User Management interactions
 *
 * Handles row selection, table search, and action button feedback.
 */

(function () {
    const table = document.getElementById('usersTable');
    const searchInput = document.getElementById('userSearchInput');
    const visibleUserCount = document.getElementById('visibleUserCount');
    const feedback = document.getElementById('actionFeedback');
    const actionButtons = document.querySelectorAll('.action-btn');

    let selectedRow = null;

    function showFeedback(message, type) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove('hidden', 'info', 'warn');
        feedback.classList.add(type || 'info');
    }

    function getSelectedUser() {
        if (!selectedRow) return null;

        const cells = selectedRow.querySelectorAll('td');
        if (cells.length < 4) return null;

        return {
            id: selectedRow.getAttribute('data-user-id') || '',
            name: (cells[1] && cells[1].textContent.trim()) || 'Unknown User'
        };
    }

    function handleRowSelection() {
        if (!table) return;

        const rows = table.querySelectorAll('tbody .user-row');
        rows.forEach((row) => {
            row.addEventListener('click', function () {
                rows.forEach((r) => r.classList.remove('selected'));
                this.classList.add('selected');
                selectedRow = this;

                const selectedUser = getSelectedUser();
                if (selectedUser) {
                    showFeedback('Selected user: ' + selectedUser.name + ' (ID: ' + selectedUser.id + ')', 'info');
                }
            });
        });
    }

    function updateVisibleCount() {
        if (!table || !visibleUserCount) return;

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
        if (!actionButtons.length) return;

        actionButtons.forEach((button) => {
            button.addEventListener('click', function () {
                const action = this.getAttribute('data-action');
                const selectedUser = getSelectedUser();

                if (action === 'add') {
                    showFeedback('Add New Users button clicked. Connect this to a create-user form next.', 'info');
                    return;
                }

                if (!selectedUser) {
                    showFeedback('Select a user from the table before using this action.', 'warn');
                    return;
                }

                if (action === 'edit') {
                    showFeedback('Edit User Information clicked for ' + selectedUser.name + '.', 'info');
                    return;
                }

                if (action === 'delete') {
                    showFeedback('Delete Users clicked for ' + selectedUser.name + '.', 'warn');
                    return;
                }

                if (action === 'roles') {
                    showFeedback('Manage User Roles clicked for ' + selectedUser.name + '.', 'info');
                    return;
                }

                if (action === 'profile') {
                    showFeedback('View Profile clicked for ' + selectedUser.name + '.', 'info');
                }
            });
        });
    }

    handleRowSelection();
    setupSearch();
    setupActionButtons();
})();
