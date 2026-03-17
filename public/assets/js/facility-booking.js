/**
 * Facility Booking Page JavaScript
 * 
 * Handles dynamic facility loading and booking modal interactions.
 */

const bookingConfig = window.FACILITY_BOOKING_CONFIG || {
    today: new Date().toISOString().slice(0, 10),
    todayLabel: new Date().toDateString(),
    tomorrow: new Date().toISOString().slice(0, 10),
    maxDate: new Date().toISOString().slice(0, 10),
    isAdmin: false
};

const isAdminUser = Boolean(bookingConfig.isAdmin);

let selectedFacilityId = null;
let selectedFacilityName = '';
let selectedDate = null;
let selectedTimeSlot = null;
let bookingMode = 'today';
let activeCategoryType = null;
let editingBookingId = null;
let adminFacilityFormMode = 'create';
let editingFacilityId = null;
let editingFacilityOriginalType = null;

const facilityTypeTitles = {
    classroom: 'Classrooms',
    meeting_room: 'Meeting Rooms',
    sport_facility: 'Sport Facilities'
};

const facilitiesById = {};
const facilitiesSelectedSlots = {}; // Track selected slots per facility

function isFacilityAvailableForBooking(facility) {
    return Boolean(facility && (facility.is_available === true || Number(facility.is_available) === 1));
}

function showFacilities(category) {
    activeCategoryType = normalizeFacilityType(category);

    document.getElementById('categoryView').classList.add('hidden');
    document.getElementById('facilityView').classList.remove('hidden');
    document.getElementById('bookingsView').classList.add('hidden');

    const title = document.querySelector('#facilityView .section-title');
    title.textContent = facilityTypeTitles[activeCategoryType] || 'Facilities';

    loadFacilitiesForCategory(activeCategoryType);
}

function showMyBookings() {
    document.getElementById('categoryView').classList.add('hidden');
    document.getElementById('facilityView').classList.add('hidden');
    document.getElementById('bookingsView').classList.remove('hidden');

    const defaultFilter = isAdminUser ? 'all' : 'upcoming';
    setActiveFilter(defaultFilter);
    applyBookingsFilter(defaultFilter);
}

function showCategories() {
    document.getElementById('categoryView').classList.remove('hidden');
    document.getElementById('facilityView').classList.add('hidden');
    document.getElementById('bookingsView').classList.add('hidden');
}

function normalizeFacilityType(type) {
    if (type === 'meeting') return 'meeting_room';
    if (type === 'sport') return 'sport_facility';
    return type;
}

async function loadFacilitiesForCategory(categoryType) {
    const facilityGrid = document.getElementById('facilityGrid');
    facilityGrid.innerHTML = '<div class="facility-empty-state">Loading facilities...</div>';

    try {
        const response = await fetch(`api/get-facilities.php?type=${encodeURIComponent(categoryType)}`);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to load facilities.');
        }

        const facilities = data.facilities || [];
        displayFacilities(facilities);
        return facilities;
    } catch (error) {
        facilityGrid.innerHTML = `<div class="facility-empty-state">${error.message}</div>`;
        return [];
    }
}

// API returns facilities pre-sorted by available_count (desc), so fully booked cards are last.
function displayFacilities(facilities) {
    const facilityGrid = document.getElementById('facilityGrid');
    facilityGrid.innerHTML = '';

    if (!facilities.length) {
        facilityGrid.innerHTML = '<div class="facility-empty-state">No facilities found for this category.</div>';
        return;
    }

    facilities.forEach(facility => {
        facilitiesById[facility.facility_id] = facility;
        facilitiesSelectedSlots[facility.facility_id] = null;

        const availableCount = Number(facility.available_count || 0);
        const facilityIsAvailable = isFacilityAvailableForBooking(facility);
        const isFullyBookedToday = facilityIsAvailable && availableCount === 0;

        let slotsHtml = '<span class="time-slot booked">Unavailable for booking</span>';

        if (facilityIsAvailable) {
            const allSlots = facility.all_slots || facility.available_slots || [];
            slotsHtml = allSlots
                .map(slot => {
                    const booked = !slot.available ? 'booked' : '';
                    return `<span class="time-slot ${booked}" data-start="${slot.start}" data-end="${slot.end}" data-label="${escapeHtml(slot.label)}" 
                        ${!slot.available ? 'title="Already booked"' : 'onclick="selectFacilityTimeSlot(event, ' + Number(facility.facility_id) + ')"'}
                        >${slot.label}</span>`;
                })
                .join('');

            if (!slotsHtml) {
                slotsHtml = '<span class="time-slot booked">All booked</span>';
            }
        }

        const availabilityBadgeClass = !facilityIsAvailable
            ? 'status-unavailable'
            : (isFullyBookedToday ? 'status-occupied' : 'status-available');
        const availabilityText = !facilityIsAvailable
            ? 'Unavailable'
            : (isFullyBookedToday ? 'Fully Booked' : `${availableCount} Slots Available`);

        const card = document.createElement('div');
        card.className = `facility-card ${!facilityIsAvailable ? 'facility-unavailable-card' : ''} ${isFullyBookedToday ? 'fully-booked-card' : ''}`.trim();
        card.setAttribute('data-facility-id', facility.facility_id);

        const adminControls = isAdminUser
            ? `
                <div class="facility-admin-controls">
                    <button
                        type="button"
                        class="facility-admin-menu-btn"
                        onclick="toggleFacilityAdminMenu(event, ${Number(facility.facility_id)})"
                        title="Facility actions"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >⋮</button>
                    <div class="facility-admin-menu" id="facility-admin-menu-${Number(facility.facility_id)}">
                        <button type="button" class="facility-admin-item" data-facility-action="edit" data-facility-id="${Number(facility.facility_id)}">Edit Facility</button>
                        <button type="button" class="facility-admin-item danger" data-facility-action="delete" data-facility-id="${Number(facility.facility_id)}">Delete Facility</button>
                        <button type="button" class="facility-admin-item" data-facility-action="toggle-availability" data-facility-id="${Number(facility.facility_id)}">${facilityIsAvailable ? 'Set Unavailable' : 'Set Available'}</button>
                    </div>
                </div>
            `
            : '';

        card.innerHTML = `
            <div class="facility-header">
                <div class="facility-name">${escapeHtml(facility.facility_name)}</div>
                <div class="facility-header-right">
                    <span class="status-badge ${availabilityBadgeClass}">
                        ${availabilityText}
                    </span>
                    ${adminControls}
                </div>
            </div>
            <div class="facility-info">
                <span>👥 Max ${Number(facility.capacity || 0)} people</span>
                <span>📍 ${escapeHtml(facility.location || 'Campus')}</span>
            </div>
            <div class="time-slots">
                <div class="time-slots-label">${facilityIsAvailable ? 'Available Slots Today:' : 'Booking Status:'}</div>
                <div class="slots" data-facility-slots="${facility.facility_id}">${slotsHtml}</div>
            </div>
            <div class="booking-buttons">
                <button
                    class="book-btn book-now-btn"
                    onclick="handleBookNowClick(${Number(facility.facility_id)})"
                    ${(!facilityIsAvailable || isFullyBookedToday) ? 'disabled' : ''}
                >
                    Book Now
                </button>
                <button
                    class="book-btn book-advance-btn"
                    onclick="openBookingModal(${Number(facility.facility_id)}, 'advance')"
                    ${!facilityIsAvailable ? 'disabled' : ''}
                >
                    Book in Advance
                </button>
            </div>
        `;

        facilityGrid.appendChild(card);
    });
}

function openAdminFacilityModal() {
    if (!isAdminUser) {
        return;
    }

    const modal = document.getElementById('adminFacilityModal');
    if (modal) {
        resetAdminFacilityForm();
        modal.classList.remove('hidden');

        const nameInput = document.getElementById('admin-facility-name');
        if (nameInput) {
            window.setTimeout(() => {
                nameInput.focus();
            }, 0);
        }
    }
}

function closeAdminFacilityModal() {
    const modal = document.getElementById('adminFacilityModal');
    if (modal) {
        modal.classList.add('hidden');
    }

    resetAdminFacilityForm();
}

function resetAdminFacilityForm() {
    const form = document.getElementById('adminFacilityForm');
    const titleEl = document.getElementById('adminFacilityModalTitle');
    if (form) {
        form.reset();
    }

    adminFacilityFormMode = 'create';
    editingFacilityId = null;
    editingFacilityOriginalType = null;

    if (titleEl) {
        titleEl.textContent = 'Add New Facility';
    }

    const categorySelect = document.getElementById('admin-facility-category');
    if (categorySelect) {
        categorySelect.value = activeCategoryType || 'classroom';
    }

    const submitButton = document.getElementById('admin-facility-submit-btn');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Post';
    }
}

function openEditFacilityModal(facilityId) {
    if (!isAdminUser) {
        return;
    }

    const facility = facilitiesById[facilityId];
    if (!facility) {
        showErrorPopup('Facility data could not be loaded. Please refresh and try again.', 'Facility Not Found');
        return;
    }

    const modal = document.getElementById('adminFacilityModal');
    const titleEl = document.getElementById('adminFacilityModalTitle');
    const nameInput = document.getElementById('admin-facility-name');
    const categorySelect = document.getElementById('admin-facility-category');
    const locationInput = document.getElementById('admin-facility-location');
    const capacityInput = document.getElementById('admin-facility-capacity');
    const submitButton = document.getElementById('admin-facility-submit-btn');

    if (!modal || !titleEl || !nameInput || !categorySelect || !locationInput || !capacityInput || !submitButton) {
        return;
    }

    resetAdminFacilityForm();

    adminFacilityFormMode = 'edit';
    editingFacilityId = Number(facilityId);
    editingFacilityOriginalType = normalizeFacilityType(facility.facility_type);

    titleEl.textContent = 'Edit Facility';
    nameInput.value = facility.facility_name || '';
    categorySelect.value = normalizeFacilityType(facility.facility_type) || 'classroom';
    locationInput.value = facility.location || '';
    capacityInput.value = Number(facility.capacity || 0) || '';
    submitButton.textContent = 'Save Changes';

    modal.classList.remove('hidden');

    window.setTimeout(() => {
        nameInput.focus();
        nameInput.select();
    }, 0);
}

function updateCategoryCount(categoryType, facilities) {
    const normalizedType = normalizeFacilityType(categoryType);
    const countEl = document.querySelector(`[data-category-count-type="${normalizedType}"]`);
    if (!countEl) {
        return;
    }

    const availableCount = (facilities || []).filter(facility => Number(facility.available_count || 0) > 0).length;
    const label = countEl.getAttribute('data-category-count-label') || 'available';

    countEl.setAttribute('data-category-count-value', String(availableCount));
    countEl.textContent = `${availableCount} ${label}`;
}

async function refreshCategoryCount(categoryType) {
    const normalizedType = normalizeFacilityType(categoryType);
    if (!normalizedType) {
        return;
    }

    try {
        const response = await fetch(`api/get-facilities.php?type=${encodeURIComponent(normalizedType)}`);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to refresh category count.');
        }

        updateCategoryCount(normalizedType, data.facilities || []);
    } catch (error) {
        console.error('Facility count refresh failed:', error);
    }
}

async function refreshFacilityManagementState(categoryTypes) {
    const normalizedTypes = Array.from(new Set((categoryTypes || [])
        .map(type => normalizeFacilityType(type))
        .filter(Boolean)));

    if (activeCategoryType) {
        const activeFacilities = await loadFacilitiesForCategory(activeCategoryType);
        updateCategoryCount(activeCategoryType, activeFacilities);
    }

    for (const type of normalizedTypes) {
        if (type !== activeCategoryType) {
            await refreshCategoryCount(type);
        }
    }
}

async function submitAdminFacilityForm(event) {
    if (event) {
        event.preventDefault();
    }

    if (!isAdminUser) {
        return false;
    }

    const nameInput = document.getElementById('admin-facility-name');
    const categorySelect = document.getElementById('admin-facility-category');
    const locationInput = document.getElementById('admin-facility-location');
    const capacityInput = document.getElementById('admin-facility-capacity');
    const submitButton = document.getElementById('admin-facility-submit-btn');

    if (!nameInput || !categorySelect || !locationInput || !capacityInput || !submitButton) {
        return false;
    }

    const facilityName = nameInput.value.trim();
    const facilityType = normalizeFacilityType(categorySelect.value);
    const location = locationInput.value.trim();
    const capacity = Number.parseInt(capacityInput.value, 10);
    const isEditMode = adminFacilityFormMode === 'edit' && Number(editingFacilityId) > 0;

    if (!facilityName || !facilityType || !location || !capacityInput.value.trim()) {
        await showInfoPopup('Please complete facility name, category, location, and capacity.', 'Missing Details');
        return false;
    }

    if (!Number.isInteger(capacity) || capacity < 1) {
        await showInfoPopup('Capacity must be a whole number greater than 0.', 'Invalid Capacity');
        return false;
    }

    if (facilityName.length > 120 || location.length > 120) {
        await showInfoPopup('Facility name and location must be 120 characters or fewer.', 'Value Too Long');
        return false;
    }

    submitButton.disabled = true;
    submitButton.textContent = isEditMode ? 'Saving...' : 'Posting...';

    try {
        const response = await fetch(isEditMode ? 'api/update-facility.php' : 'api/create-facility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...(isEditMode ? { facility_id: Number(editingFacilityId) } : {}),
                facility_name: facilityName,
                facility_type: facilityType,
                location: location,
                capacity: capacity
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Failed to create facility.');
        }

        const originalFacilityType = editingFacilityOriginalType;
        closeAdminFacilityModal();
        await refreshFacilityManagementState([facilityType, originalFacilityType]);

        await showActionPopup({
            title: isEditMode ? 'Facility Updated' : 'Facility Added',
            message: data.message || (isEditMode ? 'Facility updated successfully.' : 'Facility created successfully.'),
            confirmText: 'OK',
            variant: 'success'
        });
    } catch (error) {
        await showErrorPopup(
            (isEditMode ? 'Failed to update facility: ' : 'Failed to create facility: ') + error.message,
            isEditMode ? 'Update Facility Failed' : 'Create Facility Failed'
        );
    } finally {
        if (submitButton.isConnected) {
            submitButton.disabled = false;
            submitButton.textContent = adminFacilityFormMode === 'edit' ? 'Save Changes' : 'Post';
        }
    }

    return false;
}

async function deleteFacility(facilityId) {
    if (!isAdminUser) {
        return;
    }

    const facility = facilitiesById[facilityId];
    if (!facility) {
        await showErrorPopup('Facility data could not be loaded. Please refresh and try again.', 'Facility Not Found');
        return;
    }

    const confirmed = await showConfirmPopup(`Delete ${facility.facility_name}? This only works when there are no booking records for the facility.`, {
        title: 'Delete Facility',
        confirmText: 'Delete Facility',
        variant: 'warning',
        isDanger: true
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('api/delete-facility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                facility_id: Number(facilityId)
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Failed to delete facility.');
        }

        delete facilitiesById[facilityId];
        delete facilitiesSelectedSlots[facilityId];
        await refreshFacilityManagementState([facility.facility_type]);

        await showActionPopup({
            title: 'Facility Deleted',
            message: data.message || 'Facility deleted successfully.',
            confirmText: 'OK',
            variant: 'success'
        });
    } catch (error) {
        await showErrorPopup('Failed to delete facility: ' + error.message, 'Delete Facility Failed');
    }
}

async function toggleFacilityAvailability(facilityId) {
    if (!isAdminUser) {
        return;
    }

    const facility = facilitiesById[facilityId];
    if (!facility) {
        await showErrorPopup('Facility data could not be loaded. Please refresh and try again.', 'Facility Not Found');
        return;
    }

    const nextAvailability = !isFacilityAvailableForBooking(facility);
    const actionLabel = nextAvailability ? 'Set Available' : 'Set Unavailable';
    const confirmed = await showConfirmPopup(`${actionLabel} for ${facility.facility_name}?`, {
        title: actionLabel,
        confirmText: actionLabel,
        variant: 'warning'
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('api/toggle-facility-availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                facility_id: Number(facilityId),
                is_available: nextAvailability
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Failed to update facility availability.');
        }

        facilitiesById[facilityId] = {
            ...facility,
            is_available: nextAvailability,
            available_count: nextAvailability ? facility.available_count : 0,
            available_slots_count: nextAvailability ? facility.available_slots_count : 0
        };
        await refreshFacilityManagementState([facility.facility_type]);

        await showActionPopup({
            title: nextAvailability ? 'Facility Available' : 'Facility Unavailable',
            message: data.message || (nextAvailability ? 'Facility is now available for booking.' : 'Facility has been set as unavailable.'),
            confirmText: 'OK',
            variant: 'success'
        });
    } catch (error) {
        await showErrorPopup('Failed to update facility availability: ' + error.message, 'Availability Update Failed');
    }
}

function closeAllFacilityAdminMenus(exceptMenu = null) {
    document.querySelectorAll('.facility-admin-menu.open').forEach(menu => {
        if (menu !== exceptMenu) {
            menu.classList.remove('open');

            const controls = menu.closest('.facility-admin-controls');
            const btn = controls ? controls.querySelector('.facility-admin-menu-btn') : null;
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        }
    });
}

function toggleFacilityAdminMenu(event, facilityId) {
    if (!isAdminUser) {
        return;
    }

    event.stopPropagation();
    const menu = document.getElementById(`facility-admin-menu-${facilityId}`);
    if (!menu) {
        return;
    }

    const shouldOpen = !menu.classList.contains('open');
    closeAllFacilityAdminMenus(menu);

    menu.classList.toggle('open', shouldOpen);

    const controls = menu.closest('.facility-admin-controls');
    const btn = controls ? controls.querySelector('.facility-admin-menu-btn') : null;
    if (btn) {
        btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }
}

function selectFacilityTimeSlot(event, facilityId) {
    event.stopPropagation();
    const slot = event.target;

    if (slot.classList.contains('booked') || slot.disabled) {
        return;
    }

    // Check if this slot is already selected
    const isAlreadySelected = slot.classList.contains('selected');

    // Get all slots for this facility and remove selection
    const slotsContainer = document.querySelector(`[data-facility-slots="${facilityId}"]`);
    if (slotsContainer) {
        slotsContainer.querySelectorAll('.time-slot:not(.booked)').forEach(s => {
            s.classList.remove('selected');
        });
    }

    // If it was already selected, deselect it; otherwise select it
    if (isAlreadySelected) {
        facilitiesSelectedSlots[facilityId] = null;
    } else {
        // Mark this slot as selected
        slot.classList.add('selected');

        // Store the selected slot
        facilitiesSelectedSlots[facilityId] = {
            start: slot.getAttribute('data-start'),
            end: slot.getAttribute('data-end'),
            label: slot.getAttribute('data-label')
        };
    }
}

function handleBookNowClick(facilityId) {
    const facility = facilitiesById[facilityId];
    if (!isFacilityAvailableForBooking(facility)) {
        showErrorPopup('This facility is currently unavailable for booking.', 'Facility Unavailable');
        return;
    }

    const selectedSlot = facilitiesSelectedSlots[facilityId];

    if (selectedSlot) {
        // If a slot is selected, book directly
        directlyBookFacility(facilityId, selectedSlot, event.target);
    } else {
        // Otherwise, open the modal so they can select a slot
        openBookingModal(facilityId, 'today');
    }
}

async function directlyBookFacility(facilityId, slot, confirmBtn) {
    const facility = facilitiesById[facilityId];

    if (!facility) {
        await showErrorPopup('Unable to load selected facility. Please refresh and try again.', 'Facility Not Available');
        return;
    }

    if (!isFacilityAvailableForBooking(facility)) {
        await showErrorPopup('This facility is currently unavailable for booking.', 'Facility Unavailable');
        return;
    }

    const facilityName = facility.facility_name;
    const originalText = confirmBtn.textContent;
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Booking...';

    console.log('Booking payload:', {
        facility_id: facilityId,
        booking_date: bookingConfig.today,
        start_time: slot.start,
        end_time: slot.end
    });

    try {
        const response = await fetch('api/create-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                facility_id: facilityId,
                booking_date: bookingConfig.today,
                start_time: slot.start,
                end_time: slot.end
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            console.error('Booking API Error:', { response_status: response.status, response_data: data });
            throw new Error(data.message || data.error || 'Booking failed.');
        }

        showSuccessPopup(data.message || 'Booking created successfully!', {
            facility: facilityName,
            date: bookingConfig.todayLabel,
            time: slot.label
        });
    } catch (error) {
        await showErrorPopup(`Booking failed: ${error.message}`, 'Booking Failed');
        confirmBtn.disabled = false;
        confirmBtn.textContent = originalText;
    }
}

function openBookingModal(facilityId, mode) {
    const facility = facilitiesById[facilityId];

    if (!facility) {
        showErrorPopup('Unable to load selected facility. Please refresh and try again.', 'Facility Not Available');
        return;
    }

    if (!isFacilityAvailableForBooking(facility)) {
        showErrorPopup('This facility is currently unavailable for booking.', 'Facility Unavailable');
        return;
    }

    selectedFacilityId = facilityId;
    selectedFacilityName = facility.facility_name;
    bookingMode = mode;
    selectedDate = mode === 'today' ? bookingConfig.today : null;
    
    // Check if a slot is already selected on the card
    const preSelectedSlot = facilitiesSelectedSlots[facilityId];
    selectedTimeSlot = preSelectedSlot || null;

    document.getElementById('modal-facility-name').textContent = selectedFacilityName;
    document.getElementById('summary-facility').textContent = selectedFacilityName;
    document.getElementById('summary-time').textContent = '-';

    const modeIndicator = document.getElementById('booking-mode-indicator');
    const todaySection = document.getElementById('today-date-display');
    const dateSelectionSection = document.getElementById('date-selection-section');
    const datePicker = document.getElementById('booking-date-picker');

    if (mode === 'today') {
        todaySection.classList.remove('hidden');
        dateSelectionSection.classList.add('hidden');
        modeIndicator.textContent = 'Booking for Today';
        document.getElementById('summary-date').textContent = bookingConfig.today;
    } else {
        todaySection.classList.add('hidden');
        dateSelectionSection.classList.remove('hidden');
        modeIndicator.textContent = 'Book in Advance';
        datePicker.min = bookingConfig.tomorrow;
        datePicker.max = bookingConfig.maxDate;
        datePicker.value = bookingConfig.tomorrow;
        selectedDate = datePicker.value;
        document.getElementById('summary-date').textContent = selectedDate || '-';
    }

    document.getElementById('bookingModal').classList.remove('hidden');
    loadAvailableSlots();
}

async function loadAvailableSlots() {
    const datePicker = document.getElementById('booking-date-picker');
    const date = bookingMode === 'today' ? bookingConfig.today : datePicker.value;
    const container = document.getElementById('time-slots-container');

    // Preserve the pre-selected slot if it exists
    const preSelectedSlot = selectedTimeSlot;
    selectedTimeSlot = null;
    document.getElementById('summary-time').textContent = '-';

    if (!selectedFacilityId || !date) {
        container.innerHTML = '<div class="facility-empty-state">Select a date to view available slots.</div>';
        return;
    }

    selectedDate = date;
    document.getElementById('summary-date').textContent = selectedDate;
    container.innerHTML = '<div class="facility-empty-state">Loading slots...</div>';

    try {
        const response = await fetch(
            `api/get-available-slots.php?facility_id=${encodeURIComponent(selectedFacilityId)}&date=${encodeURIComponent(date)}`
        );
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Unable to load time slots.');
        }

        container.innerHTML = '';

        if (!data.slots || data.slots.length === 0) {
            container.innerHTML = '<div class="facility-empty-state">No available slots for this date.</div>';
            return;
        }

        (data.slots || []).forEach(slot => {
            const slotBtn = document.createElement('button');
            slotBtn.type = 'button';
            slotBtn.className = slot.available ? 'time-slot' : 'time-slot booked';
            slotBtn.textContent = slot.label;

            if (!slot.available) {
                // BOOKED SLOT: Make it completely disabled and unclickable
                slotBtn.disabled = true;
                slotBtn.title = 'Already booked';
                slotBtn.style.pointerEvents = 'none';
                slotBtn.style.cursor = 'not-allowed';
            } else {
                // AVAILABLE SLOT: Make it clickable
                slotBtn.addEventListener('click', () => selectTimeSlot(slot, slotBtn));

                // If this slot matches the pre-selected slot, mark it as selected
                if (preSelectedSlot && slot.start === preSelectedSlot.start && slot.end === preSelectedSlot.end) {
                    slotBtn.classList.add('selected');
                    selectedTimeSlot = slot;
                    document.getElementById('summary-time').textContent = slot.label;
                }
            }

            container.appendChild(slotBtn);
        });
    } catch (error) {
        container.innerHTML = `<div class="facility-empty-state">Error: ${error.message}</div>`;
    }
}

function selectTimeSlot(slot, element) {
    selectedTimeSlot = slot;

    document.querySelectorAll('#time-slots-container .time-slot').forEach(slotElement => {
        slotElement.classList.remove('selected');
    });

    element.classList.add('selected');
    document.getElementById('summary-time').textContent = slot.label;
    document.getElementById('summary-date').textContent = selectedDate;
}

async function submitBooking() {
    if (!selectedFacilityId || !selectedDate || !selectedTimeSlot) {
        await showInfoPopup('Please select a time slot before confirming.', 'Select Time Slot');
        return;
    }

    const selectedFacility = facilitiesById[selectedFacilityId];
    if (!isFacilityAvailableForBooking(selectedFacility)) {
        await showErrorPopup('This facility is currently unavailable for booking.', 'Facility Unavailable');
        return;
    }

    const confirmButton = document.getElementById('confirmBookingBtn');
    confirmButton.disabled = true;
    confirmButton.textContent = 'Confirming...';

    try {
        const response = await fetch('api/create-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                facility_id: selectedFacilityId,
                booking_date: selectedDate,
                start_time: selectedTimeSlot.start,
                end_time: selectedTimeSlot.end
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            console.error('Booking API Error:', { response_status: response.status, response_data: data });
            throw new Error(data.message || data.error || 'Booking failed.');
        }

        showSuccessPopup(data.message || 'Booking created successfully!', {
            facility: selectedFacilityName,
            date: selectedDate,
            time: selectedTimeSlot.label
        });
    } catch (error) {
        await showErrorPopup(`Booking failed: ${error.message}`, 'Booking Failed');
    } finally {
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirm Booking';
    }
}

function closeBookingModal() {
    document.getElementById('bookingModal').classList.add('hidden');

    selectedFacilityId = null;
    selectedFacilityName = '';
    selectedDate = null;
    selectedTimeSlot = null;

    document.getElementById('modal-facility-name').textContent = '';
    document.getElementById('summary-facility').textContent = '-';
    document.getElementById('summary-date').textContent = '-';
    document.getElementById('summary-time').textContent = '-';
    document.getElementById('time-slots-container').innerHTML = '';
}

/**
 * Cancel Booking
 * Cancels a booking (only future bookings can be cancelled)
 */
async function cancelBooking(bookingId) {
    const confirmed = await showConfirmPopup('Are you sure you want to cancel this booking?', {
        title: 'Cancel Booking',
        confirmText: 'Cancel Booking',
        variant: 'warning',
        isDanger: true
    });

    if (!confirmed) {
        return;
    }
    
    try {
        const response = await fetch('api/cancel-booking.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ booking_id: bookingId })
        });
        
        const data = await response.json();
        
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to cancel booking');
        }

        await showActionPopup({
            title: 'Booking Cancelled',
            message: data.message || 'Booking cancelled successfully.',
            confirmText: 'OK',
            variant: 'success'
        });
        location.reload();
    } catch (error) {
        await showErrorPopup('Failed to cancel: ' + error.message, 'Cancel Failed');
    }
}

function openEditBookingModal(bookingId) {
    if (!isAdminUser) {
        return;
    }

    const row = document.querySelector(`tr[data-booking-id="${bookingId}"]`);
    if (!row) {
        showErrorPopup('Booking row not found. Please refresh and try again.', 'Booking Not Found');
        return;
    }

    editingBookingId = Number(bookingId);

    const facilityNameEl = document.getElementById('editBookingFacilityName');
    const dateInput = document.getElementById('edit-booking-date');
    const startInput = document.getElementById('edit-booking-start');
    const endInput = document.getElementById('edit-booking-end');
    const modal = document.getElementById('editBookingModal');

    if (!facilityNameEl || !dateInput || !startInput || !endInput || !modal) {
        return;
    }

    facilityNameEl.textContent = row.dataset.facilityName || '-';
    dateInput.value = row.dataset.bookingDate || '';
    startInput.value = (row.dataset.startTime || '').slice(0, 5);
    endInput.value = (row.dataset.endTime || '').slice(0, 5);

    modal.classList.remove('hidden');
}

function closeEditBookingModal() {
    const modal = document.getElementById('editBookingModal');
    if (modal) {
        modal.classList.add('hidden');
    }

    editingBookingId = null;

    const facilityNameEl = document.getElementById('editBookingFacilityName');
    const dateInput = document.getElementById('edit-booking-date');
    const startInput = document.getElementById('edit-booking-start');
    const endInput = document.getElementById('edit-booking-end');

    if (facilityNameEl) {
        facilityNameEl.textContent = '-';
    }
    if (dateInput) {
        dateInput.value = '';
    }
    if (startInput) {
        startInput.value = '';
    }
    if (endInput) {
        endInput.value = '';
    }
}

async function submitBookingEdit() {
    if (!isAdminUser || !editingBookingId) {
        return;
    }

    const dateInput = document.getElementById('edit-booking-date');
    const startInput = document.getElementById('edit-booking-start');
    const endInput = document.getElementById('edit-booking-end');

    if (!dateInput || !startInput || !endInput) {
        return;
    }

    const bookingDate = dateInput.value;
    const startTimeRaw = startInput.value;
    const endTimeRaw = endInput.value;

    if (!bookingDate || !startTimeRaw || !endTimeRaw) {
        await showInfoPopup('Please provide booking date, start time, and end time.', 'Missing Details');
        return;
    }

    if (startTimeRaw >= endTimeRaw) {
        await showInfoPopup('Start time must be before end time.', 'Invalid Time Range');
        return;
    }

    const startTime = `${startTimeRaw}:00`;
    const endTime = `${endTimeRaw}:00`;

    try {
        const response = await fetch('api/update-booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: editingBookingId,
                booking_date: bookingDate,
                start_time: startTime,
                end_time: endTime
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Failed to update booking.');
        }

        await showActionPopup({
            title: 'Booking Updated',
            message: data.message || 'Booking updated successfully.',
            confirmText: 'OK',
            variant: 'success'
        });
        closeEditBookingModal();
        window.location.reload();
    } catch (error) {
        await showErrorPopup('Failed to update booking: ' + error.message, 'Update Failed');
    }
}

async function deleteBooking(bookingId) {
    if (!isAdminUser) {
        return;
    }

    const confirmed = await showConfirmPopup('Delete this booking permanently?', {
        title: 'Delete Booking',
        confirmText: 'Delete Booking',
        variant: 'warning',
        isDanger: true
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('api/delete-booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: Number(bookingId) })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Failed to delete booking.');
        }

        await showActionPopup({
            title: 'Booking Deleted',
            message: data.message || 'Booking deleted successfully.',
            confirmText: 'OK',
            variant: 'success'
        });
        window.location.reload();
    } catch (error) {
        await showErrorPopup('Failed to delete booking: ' + error.message, 'Delete Failed');
    }
}

/**
 * Validate date and load slots
 * Checks if selected date is a public holiday before loading slots
 */
async function validateAndLoadSlots() {
    const dateInput = document.getElementById('booking-date-picker');
    const selectedDate = dateInput.value;
    
    if (!selectedDate) {
        document.getElementById('time-slots-container').innerHTML = 
            '<div class="facility-empty-state">Select a date to view available slots.</div>';
        return;
    }
    
    // Check if date is a public holiday
    try {
        const response = await fetch(`api/check-holiday.php?date=${encodeURIComponent(selectedDate)}`);
        const data = await response.json();
        
        if (data.is_holiday) {
            await showInfoPopup(`Cannot book on ${data.holiday_name}. Please select another date.`, 'Public Holiday');
            dateInput.value = '';
            document.getElementById('time-slots-container').innerHTML = 
                '<div class="facility-empty-state">Select a date to view available slots.</div>';
            return;
        }
        
        // If not a holiday, load available slots
        loadAvailableSlots();
    } catch (error) {
        console.error('Holiday check error:', error);
        // If holiday check fails, still try to load slots (API will validate)
        loadAvailableSlots();
    }
}

function setActiveFilter(filterType) {
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === filterType);
    });
}

function applyBookingsFilter(filterType) {
    const today = bookingConfig.today;
    const rows = document.querySelectorAll('.bookings-table tbody tr');

    rows.forEach(row => {
        if (row.classList.contains('bookings-empty-row')) {
            row.style.display = '';
            return;
        }

        const bookingDate = row.getAttribute('data-booking-date');
        if (!bookingDate) {
            row.style.display = '';
            return;
        }

        let shouldShow = true;

        if (filterType === 'upcoming') {
            shouldShow = bookingDate >= today;
        } else if (filterType === 'past') {
            shouldShow = bookingDate < today;
        }

        row.style.display = shouldShow ? '' : 'none';
    });
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showActionPopup(options) {
    const popup = document.getElementById('actionPopup');
    const popupContent = document.getElementById('actionPopupContent');
    const titleEl = document.getElementById('actionPopupTitle');
    const messageEl = document.getElementById('actionPopupMessage');
    const actionsEl = document.getElementById('actionPopupActions');
    const closeBtn = document.getElementById('actionPopupClose');

    const config = options || {};
    const allowCancel = Boolean(config.showCancel);

    if (!popup || !popupContent || !titleEl || !messageEl || !actionsEl || !closeBtn) {
        return Promise.resolve(!allowCancel);
    }

    titleEl.textContent = config.title || 'Notice';
    messageEl.textContent = config.message || '';

    popupContent.classList.remove('is-error', 'is-success', 'is-warning');
    if (config.variant === 'error') {
        popupContent.classList.add('is-error');
    } else if (config.variant === 'success') {
        popupContent.classList.add('is-success');
    } else if (config.variant === 'warning') {
        popupContent.classList.add('is-warning');
    }

    actionsEl.innerHTML = '';

    let cancelBtn = null;
    if (allowCancel) {
        cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'action-popup-btn';
        cancelBtn.textContent = config.cancelText || 'Cancel';
        actionsEl.appendChild(cancelBtn);
    }

    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.className = 'action-popup-btn ' + (config.isDanger ? 'danger' : 'primary');
    confirmBtn.textContent = config.confirmText || 'OK';
    actionsEl.appendChild(confirmBtn);

    popup.classList.remove('hidden');
    popup.setAttribute('aria-hidden', 'false');

    return new Promise((resolve) => {
        let finished = false;

        function cleanup() {
            document.removeEventListener('keydown', onKeyDown);
            popup.removeEventListener('click', onPopupClick);
            closeBtn.removeEventListener('click', onCancel);
            if (cancelBtn) {
                cancelBtn.removeEventListener('click', onCancel);
            }
            confirmBtn.removeEventListener('click', onConfirm);
        }

        function finish(value) {
            if (finished) {
                return;
            }

            finished = true;
            cleanup();
            popup.classList.add('hidden');
            popup.setAttribute('aria-hidden', 'true');
            resolve(value);
        }

        function onCancel() {
            finish(false);
        }

        function onConfirm() {
            finish(true);
        }

        function onPopupClick(event) {
            if (event.target && event.target.hasAttribute('data-action-popup-close')) {
                onCancel();
            }
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCancel();
            }
        }

        document.addEventListener('keydown', onKeyDown);
        popup.addEventListener('click', onPopupClick);
        closeBtn.addEventListener('click', onCancel);
        if (cancelBtn) {
            cancelBtn.addEventListener('click', onCancel);
        }
        confirmBtn.addEventListener('click', onConfirm);

        window.setTimeout(() => {
            confirmBtn.focus();
        }, 0);
    });
}

function showInfoPopup(message, title) {
    return showActionPopup({
        title: title || 'Notice',
        message: message,
        confirmText: 'OK'
    });
}

function showErrorPopup(message, title) {
    return showActionPopup({
        title: title || 'Error',
        message: message,
        confirmText: 'Close',
        variant: 'error'
    });
}

function showConfirmPopup(message, options) {
    const opts = options || {};
    return showActionPopup({
        title: opts.title || 'Please Confirm',
        message: message,
        confirmText: opts.confirmText || 'Confirm',
        cancelText: opts.cancelText || 'Cancel',
        showCancel: true,
        variant: opts.variant || 'warning',
        isDanger: Boolean(opts.isDanger)
    });
}

function showSuccessPopup(message, details) {
    const popup = document.getElementById('successPopup');
    const titleEl = document.getElementById('successPopupTitle');
    const messageEl = document.getElementById('successPopupMessage');
    const detailsEl = document.getElementById('successPopupDetails');

    // Set message
    messageEl.textContent = message;

    // Build details HTML
    let detailsHtml = '';
    if (details) {
        if (details.facility) {
            detailsHtml += `<p><strong>Facility:</strong> ${escapeHtml(details.facility)}</p>`;
        }
        if (details.date) {
            detailsHtml += `<p><strong>Date:</strong> ${escapeHtml(details.date)}</p>`;
        }
        if (details.time) {
            detailsHtml += `<p><strong>Time:</strong> ${escapeHtml(details.time)}</p>`;
        }
    }
    detailsEl.innerHTML = detailsHtml;

    popup.classList.remove('hidden');
}

function closeSuccessPopup() {
    const popup = document.getElementById('successPopup');
    popup.classList.add('hidden');
}

function handleSuccessPopupClose() {
    closeSuccessPopup();
    window.location.reload();
}

function setupAdminBookingActions() {
    if (!isAdminUser) {
        return;
    }

    const bookingsTable = document.getElementById('bookingsTable');
    if (!bookingsTable) {
        return;
    }

    bookingsTable.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-booking-action]');
        if (!button || !bookingsTable.contains(button) || button.disabled) {
            return;
        }

        const action = button.getAttribute('data-booking-action');
        const bookingId = Number(button.getAttribute('data-booking-id'));

        if (!bookingId) {
            return;
        }

        if (action === 'cancel') {
            cancelBooking(bookingId);
            return;
        }

        if (action === 'edit') {
            openEditBookingModal(bookingId);
            return;
        }

        if (action === 'delete') {
            deleteBooking(bookingId);
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const datePicker = document.getElementById('booking-date-picker');
    if (datePicker) {
        datePicker.addEventListener('change', validateAndLoadSlots);
    }

    const adminFacilityForm = document.getElementById('adminFacilityForm');
    if (adminFacilityForm) {
        adminFacilityForm.addEventListener('submit', submitAdminFacilityForm);
    }

    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const filterType = this.dataset.filter || 'all';
            setActiveFilter(filterType);
            applyBookingsFilter(filterType);
        });
    });

    const defaultFilter = isAdminUser ? 'all' : 'upcoming';
    setActiveFilter(defaultFilter);
    applyBookingsFilter(defaultFilter);
    setupAdminBookingActions();

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.facility-admin-controls')) {
            closeAllFacilityAdminMenus();
        }
    });

    document.addEventListener('click', function (event) {
        const actionButton = event.target.closest('.facility-admin-item');
        if (actionButton) {
            event.preventDefault();
            closeAllFacilityAdminMenus();

            const action = actionButton.getAttribute('data-facility-action');
            const facilityId = Number(actionButton.getAttribute('data-facility-id'));

            if (!action || !facilityId) {
                return;
            }

            if (action === 'edit') {
                openEditFacilityModal(facilityId);
                return;
            }

            if (action === 'delete') {
                deleteFacility(facilityId);
                return;
            }

            if (action === 'toggle-availability') {
                toggleFacilityAvailability(facilityId);
            }
        }
    });
});
