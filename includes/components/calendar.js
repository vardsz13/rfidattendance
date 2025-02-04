function showDayDetails(date) {
        // Check if date is in future
        if (new Date(date) > new Date(new Date().setHours(23, 59, 59, 999))) {
            const modal = document.getElementById('dayDetailsModal');
            const modalDate = document.getElementById('modalDate');
            const modalContent = document.getElementById('modalContent');
    
            modalDate.textContent = new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
    
            modalContent.innerHTML = `
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-gray-600">Future date - No attendance data available</p>
                </div>`;
    
            modal.classList.remove('hidden');
            return;
        }
    fetch(`${BASE_URL}/ajax/attendance.php?action=get_daily_details&date=${date}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('dayDetailsModal');
            const modalDate = document.getElementById('modalDate');
            const modalContent = document.getElementById('modalContent');
            const selectedDate = new Date(date);

            // Format date header
            modalDate.textContent = selectedDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            let content = '';

            if (data.holiday) {
                content += `
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <h4 class="font-semibold text-purple-800">${data.holiday.title}</h4>
                        <p class="text-purple-600 mt-1">${data.holiday.description}</p>
                    </div>`;
            }

            if (data.attendance) {
                const {
                    total_present,
                    on_time,
                    late,
                    excused,
                    event,
                    medical,
                    absent,
                    total_users,
                    isToday
                } = data.attendance;

                if (isToday) {
                    content += `
                        <div class="mb-4 p-2 bg-blue-100 text-blue-800 rounded text-center">
                            Today's Attendance
                        </div>`;
                }

                content += `
                    <div class="grid gap-4">
                        <!-- Present Section -->
                        <div class="p-3 bg-green-50 rounded">
                            <h4 class="font-semibold text-green-800 mb-2">Present</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-green-600">${on_time}</div>
                                    <div class="text-sm text-green-600">On Time</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-yellow-600">${late}</div>
                                    <div class="text-sm text-yellow-600">Late</div>
                                </div>
                            </div>
                        </div>

                        <!-- Special Cases Section -->
                        <div class="p-3 bg-blue-50 rounded">
                            <h4 class="font-semibold text-blue-800 mb-2">Special Cases</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-purple-600">${excused}</div>
                                    <div class="text-sm text-purple-600">Excused</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-indigo-600">${event}</div>
                                    <div class="text-sm text-indigo-600">Event</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-pink-600">${medical}</div>
                                    <div class="text-sm text-pink-600">Medical</div>
                                </div>
                            </div>
                        </div>

                        <!-- Absent Section -->
                        <div class="p-3 bg-red-50 rounded">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-red-600">${absent}</div>
                                <div class="text-sm text-red-600">Absent</div>
                            </div>
                        </div>

                        <!-- Total Section -->
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-600">${total_users}</div>
                                <div class="text-sm text-gray-600">Total Students</div>
                            </div>
                        </div>
                    </div>`;

                // Add detailed list if admin and there are records
                if (data.details && data.details.length > 0) {
                    content += `
                        <div class="mt-4">
                            <h4 class="font-semibold text-gray-800 mb-2">Detailed Records</h4>
                            <div class="overflow-y-auto max-h-60">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500">Name</th>
                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500">Time</th>
                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">`;

                    data.details.forEach(record => {
                        const timeIn = new Date(record.time_in).toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        const statusClass = {
                            'on_time': 'bg-green-100 text-green-800',
                            'late': 'bg-yellow-100 text-yellow-800',
                            'excused': 'bg-purple-100 text-purple-800',
                            'event': 'bg-indigo-100 text-indigo-800',
                            'medical': 'bg-pink-100 text-pink-800'
                        }[record.status || record.override_status] || 'bg-gray-100 text-gray-800';

                        content += `
                            <tr>
                                <td class="px-2 py-1 text-sm">${record.name}</td>
                                <td class="px-2 py-1 text-sm">${timeIn}</td>
                                <td class="px-2 py-1">
                                    <span class="px-2 py-0.5 text-xs rounded-full ${statusClass}">
                                        ${record.override_status || record.status}
                                    </span>
                                </td>
                            </tr>`;
                    });

                    content += `
                                    </tbody>
                                </table>
                            </div>
                        </div>`;
                }

            } else {
                content += `
                    <div class="p-3 bg-gray-50 rounded text-center">
                        <p class="text-gray-600">No attendance records for this date</p>
                        ${data.attendance && data.attendance.isToday ? 
                            '<p class="text-blue-600 mt-1">Attendance tracking in progress</p>' : ''}
                    </div>`;
            }

            modalContent.innerHTML = content;
            modal.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error details:', error);
            const modal = document.getElementById('dayDetailsModal');
            const modalContent = document.getElementById('modalContent');
            
            let errorMessage = 'Error loading attendance data.';
            if (error.message) {
                errorMessage += ' Details: ' + error.message;
            }
            
            modalContent.innerHTML = `
                <div class="p-3 bg-red-100 text-red-700 rounded text-center">
                    ${errorMessage}
                    <button onclick="window.location.reload()" 
                            class="mt-2 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Retry
                    </button>
                </div>`;
            modal.classList.remove('hidden');
        });
}

function changeMonth(offset) {
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);
    
    let year = parseInt(params.get('year')) || new Date().getFullYear();
    let month = parseInt(params.get('month')) || new Date().getMonth() + 1;
    
    month += offset;
    
    if (month > 12) {
        month = 1;
        year++;
    } else if (month < 1) {
        month = 12;
        year--;
    }
    
    params.set('year', year);
    params.set('month', month);
    currentUrl.search = params.toString();
    window.location.href = currentUrl.toString();
}

function closeModal() {
    document.getElementById('dayDetailsModal').classList.add('hidden');
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking outside
    const modal = document.getElementById('dayDetailsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
});

// Export functions for accessibility
window.showDayDetails = showDayDetails;
window.changeMonth = changeMonth;
window.closeModal = closeModal;