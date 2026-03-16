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

const facilityTypeTitles = {
    classroom: 'Classrooms',
    meeting_room: 'Meeting Rooms',
    sport_facility: 'Sport Facilities'
};

const facilitiesById = {};
const facilitiesSelectedSlots = {}; // Track selected slots per facility

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

        displayFacilities(data.facilities || []);
    } catch (error) {
        facilityGrid.innerHTML = `<div class="facility-empty-state">${error.message}</div>`;
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
        const isFullyBookedToday = availableCount === 0;
        
        // Display ALL slots (both available and booked)
        // Use all_slots if available, otherwise fall back to slots
        const allSlots = facility.all_slots || facility.available_slots || [];
        const slotsHtml = allSlots
            .map(slot => {
                // Mark booked slots with 'booked' class
                const booked = !slot.available ? 'booked' : '';
                return `<span class="time-slot ${booked}" data-start="${slot.start}" data-end="${slot.end}" data-label="${escapeHtml(slot.label)}" 
                    ${!slot.available ? 'title="Already booked"' : 'onclick="selectFacilityTimeSlot(event, ' + Number(facility.facility_id) + ')"'}
                    >${slot.label}</span>`;
            })
            .join('');

        const card = document.createElement('div');
        card.className = `facility-card ${isFullyBookedToday ? 'fully-booked-card' : ''}`;
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
                        <button type="button" class="facility-admin-item">Edit Facility</button>
                        <button type="button" class="facility-admin-item">Delete Facility</button>
                        <button type="button" class="facility-admin-item">Set Unavailable</button>
                    </div>
                </div>
            `
            : '';

        card.innerHTML = `
            <div class="facility-header">
                <div class="facility-name">${escapeHtml(facility.facility_name)}</div>
                <div class="facility-header-right">
                    <span class="status-badge ${isFullyBookedToday ? 'status-occupied' : 'status-available'}">
                        ${isFullyBookedToday ? 'Fully Booked' : `${availableCount} Slots Available`}
                    </span>
                    ${adminControls}
                </div>
            </div>
            <div class="facility-info">
                <span>👥 Max ${Number(facility.capacity || 0)} people</span>
                <span>📍 ${escapeHtml(facility.location || 'Campus')}</span>
            </div>
            <div class="time-slots">
                <div class="time-slots-label">Available Slots Today:</div>
                <div class="slots" data-facility-slots="${facility.facility_id}">${slotsHtml || '<span class="time-slot booked">All booked</span>'}</div>
            </div>
            <div class="booking-buttons">
                <button
                    class="book-btn book-now-btn"
                    onclick="handleBookNowClick(${Number(facility.facility_id)})"
                    ${isFullyBookedToday ? 'disabled' : ''}
                >
                    Book Now
                </button>
                <button
                    class="book-btn book-advance-btn"
                    onclick="openBookingModal(${Number(facility.facility_id)}, 'advance')"
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
        modal.classList.remove('hidden');
    }
}

function closeAdminFacilityModal() {
    const modal = document.getElementById('adminFacilityModal');
    if (modal) {
        modal.classList.add('hidden');
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
        alert('Unable to load selected facility. Please refresh and try again.');
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
        alert(`Booking failed: ${error.message}`);
        confirmBtn.disabled = false;
        confirmBtn.textContent = originalText;
    }
}

function openBookingModal(facilityId, mode) {
    const facility = facilitiesById[facilityId];

    if (!facility) {
        alert('Unable to load selected facility. Please refresh and try again.');
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
        alert('Please select a time slot before confirming.');
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
        alert(`Booking failed: ${error.message}`);
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
    if (!confirm('Are you sure you want to cancel this booking?')) {
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
        
        alert('Booking cancelled successfully');
        location.reload();
    } catch (error) {
        alert('Failed to cancel: ' + error.message);
    }
}

function openEditBookingModal(bookingId) {
    if (!isAdminUser) {
        return;
    }

    const row = document.querySelector(`tr[data-booking-id="${bookingId}"]`);
    if (!row) {
        alert('Booking row not found. Please refresh and try again.');
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
        alert('Please provide booking date, start time, and end time.');
        return;
    }

    if (startTimeRaw >= endTimeRaw) {
        alert('Start time must be before end time.');
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

        alert('Booking updated successfully');
        closeEditBookingModal();
        window.location.reload();
    } catch (error) {
        alert('Failed to update booking: ' + error.message);
    }
}

async function deleteBooking(bookingId) {
    if (!isAdminUser) {
        return;
    }

    if (!confirm('Delete this booking permanently?')) {
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

        alert('Booking deleted successfully');
        window.location.reload();
    } catch (error) {
        alert('Failed to delete booking: ' + error.message);
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
            alert(`Cannot book on ${data.holiday_name}. Please select another date.`);
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

document.addEventListener('DOMContentLoaded', function () {
    const datePicker = document.getElementById('booking-date-picker');
    if (datePicker) {
        datePicker.addEventListener('change', validateAndLoadSlots);
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

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.facility-admin-controls')) {
            closeAllFacilityAdminMenus();
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('facility-admin-item')) {
            event.preventDefault();
            closeAllFacilityAdminMenus();
        }
    });
});
