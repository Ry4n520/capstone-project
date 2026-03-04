/**
 * Header JavaScript
 * 
 * Handles navigation interactions and notification popup
 */

// Toggle notification popup
function toggleNotifications() {
    console.log('Toggle notifications called');
    const modal = document.getElementById('notification-modal');
    const backdrop = document.getElementById('notification-backdrop');
    
    if (modal && backdrop) {
        modal.classList.toggle('show');
        backdrop.classList.toggle('show');
        console.log('Modal show state:', modal.classList.contains('show'));
    }
}

// Close notification popup
function closeNotifications() {
    console.log('Close notifications called');
    const modal = document.getElementById('notification-modal');
    const backdrop = document.getElementById('notification-backdrop');
    
    if (modal && backdrop) {
        modal.classList.remove('show');
        backdrop.classList.remove('show');
    }
}

// Set up event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Smart Campus navigation loaded');
    
    // Get notification elements
    const backdrop = document.getElementById('notification-backdrop');
    
    // Close notification when clicking on backdrop
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeNotifications();
        });
    }
    
    // Close notification when clicking outside
    document.addEventListener('click', function(e) {
        const notificationModal = document.getElementById('notification-modal');
        const notificationBtn = document.getElementById('notification-btn');
        
        if (notificationModal && notificationBtn && notificationModal.classList.contains('show')) {
            // Check if click is outside both the modal and button
            if (!notificationModal.contains(e.target) && !notificationBtn.contains(e.target)) {
                closeNotifications();
            }
        }
    });
});

