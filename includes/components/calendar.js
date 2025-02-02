function showDayDetails(date) {
    $.ajax({
        url: BASE_URL + '/ajax/attendance.php',
        data: {
            action: 'get_daily_details',
            date: date
        },
        success: function(response) {
            const modal = $('#dayDetailsModal');
            const modalDate = $('#modalDate');
            const modalContent = $('#modalContent');
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            modalDate.text(selectedDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }));

            let content = '';
            
            // Show holiday information if it's a holiday
            if (response.holiday) {
                content += `
                    <div class="p-3 bg-blue-50 rounded">
                        <h4 class="font-semibold text-blue-800">${response.holiday.title}</h4>
                        ${response.holiday.description ? 
                            `<p class="text-blue-600 mt-1">${response.holiday.description}</p>` : 
                            ''}
                    </div>`;
            }

            // Handle dates before user creation
            if (response.beforeCreation) {
                content += `
                    <div class="p-3 bg-gray-50 rounded text-center">
                        <p class="text-gray-600">No attendance records - Account not yet created</p>
                    </div>`;
            }
            // Handle future dates
            else if (response.futureDate) {
                content += `
                    <div class="p-3 bg-gray-50 rounded text-center">
                        <p class="text-gray-600">Future date - No attendance data available</p>
                    </div>`;
            } 
            // Handle today and past dates
            else if (!response.holiday) {
                // Show attendance summary with all statuses
                if (response.attendance) {
                    const {
                        total_present,
                        on_time,
                        late,
                        still_in,
                        total_users,
                        isToday
                    } = response.attendance;

                    // Add today indicator if applicable
                    if (isToday) {
                        content += `
                            <div class="mb-4 p-2 bg-blue-100 text-blue-800 rounded text-center">
                                Today's Attendance
                            </div>`;
                    }

                    content += `
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <!-- Present Section -->
                            <div class="col-span-2 p-3 bg-green-50 rounded">
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
                                <div class="text-center mt-2">
                                    <div class="text-sm text-gray-600">Total Present: ${total_present}</div>
                                </div>
                            </div>

                            <!-- Current Status Section -->
                            <div class="col-span-2 p-3 bg-purple-50 rounded">
                                <h4 class="font-semibold text-purple-800 mb-2">Current Status</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-purple-600">${still_in}</div>
                                        <div class="text-sm text-purple-600">Currently In</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-blue-600">${total_present - still_in}</div>
                                        <div class="text-sm text-blue-600">Checked Out</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Absent Section -->
                            <div class="col-span-2 p-3 bg-red-50 rounded">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-red-600">
                                        ${total_users - total_present}
                                    </div>
                                    <div class="text-sm text-red-600">Absent</div>
                                </div>
                            </div>

                            <!-- Total Section -->
                            <div class="col-span-2 p-3 bg-gray-50 rounded">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-600">${total_users}</div>
                                    <div class="text-sm text-gray-600">Total Users</div>
                                </div>
                            </div>
                        </div>`;

                    // If it's admin view and there are attendance records, show detailed list
                    if (response.detailedRecords && response.detailedRecords.length > 0) {
                        content += `
                            <div class="mt-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Detailed Records</h4>
                                <div class="space-y-2">
                                    ${response.detailedRecords.map(record => `
                                        <div class="p-2 border rounded">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <div class="font-medium">${record.user_name}</div>
                                                    <div class="text-sm text-gray-600">
                                                        In: ${formatTime(record.time_in)}
                                                        ${record.time_out ? ` • Out: ${formatTime(record.time_out)}` : ''}
                                                    </div>
                                                </div>
                                                <span class="px-2 py-1 text-xs rounded-full ${
                                                    record.status === 'late' 
                                                    ? 'bg-yellow-100 text-yellow-800' 
                                                    : 'bg-green-100 text-green-800'
                                                }">
                                                    ${record.status === 'late' ? 'Late' : 'On Time'}
                                                </span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>`;
                    }
                } else {
                    content += `
                        <div class="p-3 bg-gray-50 rounded text-center">
                            <p class="text-gray-600">No attendance records for this date</p>
                            ${response.attendance && response.attendance.isToday ? 
                                '<p class="text-blue-600 mt-1">Attendance tracking in progress for today</p>' : ''}
                        </div>`;
                }
            }

            modalContent.html(content);
            modal.removeClass('hidden');
        },
        error: function(xhr, status, error) {
            console.error('Error fetching day details:', error);
            console.error('Server response:', xhr.responseText);
            const modal = $('#dayDetailsModal');
            const modalContent = $('#modalContent');
            
            let errorMessage = 'Error loading attendance data. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.error) {
                    errorMessage = response.error;
                }
            } catch (e) {
                console.error('Error parsing response:', e);
            }
            
            modalContent.html(`
                <div class="p-3 bg-red-100 text-red-700 rounded text-center">
                    ${errorMessage}
                </div>
            `);
            modal.removeClass('hidden');
        }
    });
}

function formatTime(timeString) {
    if (!timeString) return '';
    return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function changeMonth(offset) {
    const currentUrl = new URL(window.location.href);
    const urlParams = new URLSearchParams(currentUrl.search);
    
    let year = parseInt(urlParams.get('year')) || new Date().getFullYear();
    let month = parseInt(urlParams.get('month')) || new Date().getMonth() + 1;
    
    month += offset;
    
    if (month > 12) {
        month = 1;
        year++;
    } else if (month < 1) {
        month = 12;
        year--;
    }
    
    window.location.href = `?year=${year}&month=${month}`;
}

function closeModal() {
    $('#dayDetailsModal').addClass('hidden');
}

// Close modal when clicking outside
$(document).on('click', '#dayDetailsModal', function(e) {
    if ($(e.target).is('#dayDetailsModal')) {
        closeModal();
    }
});

// Handle escape key press
$(document).keydown(function(e) {
    if (e.key === "Escape") {
        closeModal();
    }
});