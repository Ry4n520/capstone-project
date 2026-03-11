/**
 * Student attendance page behavior.
 */

function calculatePercentage(attended, total) {
    if (total === 0) {
        return 0;
    }
    return ((attended / total) * 100).toFixed(1);
}

function applyColorClass(element, percentage) {
    if (percentage >= 80) {
        element.classList.add('high');
        element.classList.remove('low');
    } else {
        element.classList.add('low');
        element.classList.remove('high');
    }
}

function calculateAllPercentages() {
    const subjectPercentages = document.querySelectorAll('.subject-percentage');
    subjectPercentages.forEach((element) => {
        const attended = parseInt(element.getAttribute('data-attended') || '0', 10);
        const total = parseInt(element.getAttribute('data-total') || '0', 10);
        const percentage = calculatePercentage(attended, total);

        element.textContent = `${percentage}%`;
        applyColorClass(element, parseFloat(percentage));
    });

    const semesterPercentages = document.querySelectorAll('.semester-percentage');
    semesterPercentages.forEach((element) => {
        const attended = parseInt(element.getAttribute('data-attended') || '0', 10);
        const total = parseInt(element.getAttribute('data-total') || '0', 10);
        const percentage = calculatePercentage(attended, total);

        element.textContent = `${percentage}%`;
        applyColorClass(element, parseFloat(percentage));
    });

    let totalAttended = 0;
    let totalClasses = 0;

    semesterPercentages.forEach((element) => {
        totalAttended += parseInt(element.getAttribute('data-attended') || '0', 10);
        totalClasses += parseInt(element.getAttribute('data-total') || '0', 10);
    });

    const overallElement = document.getElementById('overall-percentage');
    if (!overallElement) {
        return;
    }

    if (totalClasses === 0) {
        overallElement.textContent = '0/0 - No classes yet';
        overallElement.classList.remove('high');
        overallElement.classList.remove('low');
        return;
    }

    const overallPercentage = calculatePercentage(totalAttended, totalClasses);
    overallElement.textContent = `${overallPercentage}%`;
    applyColorClass(overallElement, parseFloat(overallPercentage));
}

function toggleSemester(header) {
    const content = header.nextElementSibling;
    const isActive = header.classList.contains('active');

    if (isActive) {
        header.classList.remove('active');
        content.classList.remove('show');
    } else {
        header.classList.add('active');
        content.classList.add('show');
    }
}

function openAttendanceModal() {
    const modal = document.getElementById('attendance-modal');
    if (!modal) {
        return;
    }

    modal.classList.remove('hidden');
    modal.classList.add('show');

    setTimeout(() => {
        const firstInput = document.getElementById('code1');
        if (firstInput) {
            firstInput.focus();
        }
    }, 100);
}

function closeAttendanceModal() {
    const modal = document.getElementById('attendance-modal');
    if (!modal) {
        return;
    }

    modal.classList.remove('show');
    modal.classList.add('hidden');

    const code1 = document.getElementById('code1');
    const code2 = document.getElementById('code2');
    const code3 = document.getElementById('code3');

    if (code1) {
        code1.value = '';
    }
    if (code2) {
        code2.value = '';
    }
    if (code3) {
        code3.value = '';
    }

    setModalMessage('');
}

function setModalMessage(message, isError = false) {
    const modalBody = document.querySelector('#attendance-modal .modal-body');
    if (!modalBody) {
        return;
    }

    let messageEl = document.getElementById('attendance-code-message');
    if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.id = 'attendance-code-message';
        messageEl.className = 'attendance-code-message';
        modalBody.appendChild(messageEl);
    }

    messageEl.textContent = message;
    messageEl.classList.toggle('error', isError);
    messageEl.classList.toggle('success', !isError && message.length > 0);
}

function moveToNext(current, nextId) {
    current.value = current.value.replace(/[^0-9]/g, '');

    if (current.value.length === 1 && nextId) {
        const nextInput = document.getElementById(nextId);
        if (nextInput) {
            nextInput.focus();
        }
    }
}

async function submitAttendanceCode() {
    const code1 = (document.getElementById('code1') || {}).value || '';
    const code2 = (document.getElementById('code2') || {}).value || '';
    const code3 = (document.getElementById('code3') || {}).value || '';

    const fullCode = `${code1}${code2}${code3}`;

    if (!/^\d{3}$/.test(fullCode)) {
        setModalMessage('Please enter all 3 digits of the code.', true);
        return;
    }

    try {
        const response = await fetch('api/submit-attendance-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code: fullCode })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to submit attendance code');
        }

        const classLabel = data.course_name
            ? `${data.course_name}${data.section_code ? ` (${data.section_code})` : ''}`
            : 'this class';

        setModalMessage(`Attendance submitted for ${classLabel}.`, false);

        setTimeout(() => {
            closeAttendanceModal();
            window.location.reload();
        }, 900);
    } catch (error) {
        setModalMessage(error.message || 'Unable to submit attendance code.', true);
    }
}

window.toggleSemester = toggleSemester;
window.openAttendanceModal = openAttendanceModal;
window.closeAttendanceModal = closeAttendanceModal;
window.moveToNext = moveToNext;
window.submitAttendanceCode = submitAttendanceCode;

document.addEventListener('DOMContentLoaded', () => {
    calculateAllPercentages();

    const firstSemester = document.querySelector('.semester-header');
    if (firstSemester) {
        toggleSemester(firstSemester);
    }

    const inputs = document.querySelectorAll('.code-input');
    inputs.forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                submitAttendanceCode();
            }

            if (event.key === 'Backspace' && input.value.length === 0) {
                const prev = input.previousElementSibling;
                if (prev && prev.classList.contains('code-input')) {
                    prev.focus();
                }
            }
        });
    });
});
