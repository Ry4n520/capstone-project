/**
 * Attendance Page JavaScript
 * 
 * Handles semester collapsing, attendance code modal, and percentage calculations
 */

// Calculate and display percentage
function calculatePercentage(attended, total) {
    if (total === 0) return 0;
    return ((attended / total) * 100).toFixed(1);
}

// Apply color class based on percentage
function applyColorClass(element, percentage) {
    if (percentage >= 80) {
        element.classList.add('high');
        element.classList.remove('low');
    } else {
        element.classList.add('low');
        element.classList.remove('high');
    }
}

// Calculate and display all percentages
function calculateAllPercentages() {
    // Calculate subject percentages
    const subjectPercentages = document.querySelectorAll('.subject-percentage');
    subjectPercentages.forEach(element => {
        const attended = parseInt(element.getAttribute('data-attended'));
        const total = parseInt(element.getAttribute('data-total'));
        const percentage = calculatePercentage(attended, total);
        
        element.textContent = percentage + '%';
        applyColorClass(element, parseFloat(percentage));
    });
    
    // Calculate semester percentages
    const semesterPercentages = document.querySelectorAll('.semester-percentage');
    semesterPercentages.forEach(element => {
        const attended = parseInt(element.getAttribute('data-attended'));
        const total = parseInt(element.getAttribute('data-total'));
        const percentage = calculatePercentage(attended, total);
        
        element.textContent = percentage + '%';
        applyColorClass(element, parseFloat(percentage));
    });
    
    // Calculate overall percentage
    let totalAttended = 0;
    let totalClasses = 0;
    
    semesterPercentages.forEach(element => {
        totalAttended += parseInt(element.getAttribute('data-attended'));
        totalClasses += parseInt(element.getAttribute('data-total'));
    });
    
    const overallPercentage = calculatePercentage(totalAttended, totalClasses);
    const overallElement = document.getElementById('overall-percentage');
    if (overallElement) {
        overallElement.textContent = overallPercentage + '%';
        applyColorClass(overallElement, parseFloat(overallPercentage));
    }
}

// Toggle semester section
function toggleSemester(header) {
    const content = header.nextElementSibling;
    const isActive = header.classList.contains('active');
    
    // Toggle active class on header
    if (isActive) {
        header.classList.remove('active');
        content.classList.remove('show');
    } else {
        header.classList.add('active');
        content.classList.add('show');
    }
}

// Open attendance code modal
function openAttendanceModal() {
    const modal = document.getElementById('attendance-modal');
    if (modal) {
        modal.classList.add('show');
        // Focus on first code input
        setTimeout(() => {
            document.getElementById('code1').focus();
        }, 100);
    }
}

// Close attendance code modal
function closeAttendanceModal() {
    const modal = document.getElementById('attendance-modal');
    if (modal) {
        modal.classList.remove('show');
        // Clear all code inputs
        document.getElementById('code1').value = '';
        document.getElementById('code2').value = '';
        document.getElementById('code3').value = '';
    }
}

// Move to next input field
function moveToNext(current, nextId) {
    if (current.value.length === 1) {
        const nextInput = document.getElementById(nextId);
        if (nextInput) {
            nextInput.focus();
        }
    }
}

// Submit attendance code
function submitAttendanceCode() {
    const code1 = document.getElementById('code1').value;
    const code2 = document.getElementById('code2').value;
    const code3 = document.getElementById('code3').value;
    
    const fullCode = code1 + code2 + code3;
    
    if (fullCode.length === 3) {
        console.log('Attendance code submitted:', fullCode);
        // Here you would typically send this to the server
        alert('Attendance code submitted: ' + fullCode);
        closeAttendanceModal();
    } else {
        alert('Please enter all 3 digits of the code');
    }
}

// Initialize - expand first semester by default and calculate percentages
document.addEventListener('DOMContentLoaded', function() {
    // Calculate all percentages first
    calculateAllPercentages();
    
    // Expand first semester
    const firstSemester = document.querySelector('.semester-header');
    if (firstSemester) {
        toggleSemester(firstSemester);
    }
    
    // Handle Enter key on code inputs
    const codeInputs = document.querySelectorAll('.code-input');
    codeInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitAttendanceCode();
            }
        });
    });
});
