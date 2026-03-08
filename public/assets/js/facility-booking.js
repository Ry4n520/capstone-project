/**
 * Facility Booking Page JavaScript
 * 
 * Handles dynamic facility loading and booking modal interactions.
 */

const bookingConfig = window.FACILITY_BOOKING_CONFIG || {
    today: new Date().toISOString().slice(0, 10),
    todayLabel: new Date().toDateString(),
    tomorrow: new Date().toISOString().slice(0, 10),
    maxDate: new Date().toISOString().slice(0, 10)
};

let selectedFacilityId = null;
let selectedFacilityName = '';
let selectedDate = null;
let selectedTimeSlot = null;
let bookingMode = 'today';
let activeCategoryType = null;

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

    setActiveFilter('upcoming');
    applyBookingsFilter('upcoming');
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
        const slotsHtml = (facility.available_slots || [])
            .map(slot => `<span class="time-slot" data-start="${slot.start}" data-end="${slot.end}" data-label="${escapeHtml(slot.label)}" onclick="selectFacilityTimeSlot(event, ${Number(facility.facility_id)})">${slot.label}</span>`)
            .join('');

        const card = document.createElement('div');
        card.className = `facility-card ${isFullyBookedToday ? 'fully-booked-card' : ''}`;
        card.setAttribute('data-facility-id', facility.facility_id);
        card.innerHTML = `
            <div class="facility-header">
                <div class="facility-name">${escapeHtml(facility.facility_name)}</div>
                <span class="status-badge ${isFullyBookedToday ? 'status-occupied' : 'status-available'}">
                    ${isFullyBookedToday ? 'Fully Booked' : `${availableCount} Slots Available`}
                </span>
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
            throw new Error(data.error || 'Unable to load time slots.');
        }

        container.innerHTML = '';

        (data.slots || []).forEach(slot => {
            const slotBtn = document.createElement('button');
            slotBtn.type = 'button';
            slotBtn.className = slot.available ? 'time-slot' : 'time-slot booked';
            slotBtn.textContent = slot.label;

            if (!slot.available) {
                slotBtn.disabled = true;
            } else {
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
        container.innerHTML = `<div class="facility-empty-state">${error.message}</div>`;
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
        datePicker.addEventListener('change', loadAvailableSlots);
    }

    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const filterType = this.dataset.filter || 'all';
            setActiveFilter(filterType);
            applyBookingsFilter(filterType);
        });
    });

    setActiveFilter('upcoming');
    applyBookingsFilter('upcoming');
});
