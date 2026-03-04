/**
 * Facility Booking Page JavaScript
 * 
 * Handles view switching and filter tabs
 */

function showFacilities(category) {
    document.getElementById('categoryView').classList.add('hidden');
    document.getElementById('facilityView').classList.remove('hidden');
    document.getElementById('bookingsView').classList.add('hidden');
    
    // Update section title based on category
    const title = document.querySelector('#facilityView .section-title');
    if (category === 'classroom') title.textContent = 'Classrooms';
    else if (category === 'meeting') title.textContent = 'Meeting Rooms';
    else if (category === 'sport') title.textContent = 'Sport Facilities';
}

function showMyBookings() {
    document.getElementById('categoryView').classList.add('hidden');
    document.getElementById('facilityView').classList.add('hidden');
    document.getElementById('bookingsView').classList.remove('hidden');
}

function showCategories() {
    document.getElementById('categoryView').classList.remove('hidden');
    document.getElementById('facilityView').classList.add('hidden');
    document.getElementById('bookingsView').classList.add('hidden');
}

// Filter tabs functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
