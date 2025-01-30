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
            
            modalDate.text(new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }));

            let content = '';
            
            if (response.holiday) {
                content += `
                    <div class="p-3 bg-blue-50 rounded">
                        <h4 class="font-semibold text-blue-800">${response.holiday.title}</h4>
                        ${response.holiday.description ? 
                            `<p class="text-blue-600 mt-1">${response.holiday.description}</p>` : 
                            ''}
                    </div>`;
            }

            if (response.attendance) {
                const { total_users, present, on_time, late } = response.attendance;
                content += `
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="text-2xl font-bold text-gray-800">${present}</div>
                            <div class="text-sm text-gray-600">Present</div>
                        </div>
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="text-2xl font-bold text-gray-800">${total_users - present}</div>
                            <div class="text-sm text-gray-600">Absent</div>
                        </div>
                    </div>`;

                if (response.attendance.logs && response.attendance.logs.length > 0) {
                    content += `
                        <div class="mt-4">
                            <h4 class="font-semibold mb-2">Attendance Details</h4>
                            <div class="max-h-60 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Name</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Time</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Type</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${response.attendance.logs.map(log => `
                                            <tr>
                                                <td class="px-3 py-2 text-sm">${log.name}</td>
                                                <td class="px-3 py-2 text-sm">${log.time}</td>
                                                <td class="px-3 py-2 text-sm">${log.log_type}</td>
                                                <td class="px-3 py-2">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        ${log.is_late ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                                                        ${log.is_late ? 'Late' : 'On Time'}
                                                    </span>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>`;
                }
            }

            modalContent.html(content);
            modal.removeClass('hidden');
        },
        error: function(xhr, status, error) {
            console.error('Error fetching day details:', error);
        }
    });
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