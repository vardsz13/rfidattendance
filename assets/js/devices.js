// assets/js/devices.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables if they exist
    if ($.fn.DataTable) {
        $('#unregisteredTable').DataTable({
            order: [[1, 'desc']], // Sort by scan time descending
            pageLength: 10
        });

        $('#registeredDevicesTable').DataTable({
            order: [[0, 'asc']], // Sort by user name
            pageLength: 10
        });
    }

    // Refresh unassigned RFIDs periodically
    setInterval(refreshUnassignedRFIDs, 30000); // Every 30 seconds
});

// Function to refresh unassigned RFIDs
function refreshUnassignedRFIDs() {
    fetch(`${BASE_URL}/ajax/devices.php?action=get_unassigned`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUnassignedTable(data.data);
            }
        })
        .catch(error => console.error('Error fetching unassigned RFIDs:', error));
}

// Update unassigned RFIDs table
function updateUnassignedTable(rfids) {
    const table = $('#unregisteredTable').DataTable();
    table.clear();

    rfids.forEach(rfid => {
        table.row.add([
            rfid.rfid_uid,
            new Date(rfid.verification_time).toLocaleString(),
            `<button onclick="assignRFID('${rfid.rfid_uid}')"
                     class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded">
                 Assign to User
             </button>`
        ]);
    });

    table.draw();
}

// Open assignment modal
function assignRFID(rfidUid) {
    document.getElementById('rfid_uid').value = rfidUid;
    document.getElementById('assignmentModal').classList.remove('hidden');
}

// Close assignment modal
function closeModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    document.getElementById('assignmentForm').reset();
}

// Handle RFID assignment
document.getElementById('assignmentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'assign_rfid');

    fetch(`${BASE_URL}/ajax/devices.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('RFID assigned successfully', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showAlert(data.error || 'Failed to assign RFID', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred', 'error');
    });
});

// Deactivate RFID
function deactivateRFID(verificationId) {
    if (!confirm('Are you sure you want to deactivate this RFID?')) {
        return;
    }

    const formData = new FormData();
    formData.append('verification_id', verificationId);
    formData.append('action', 'deactivate_rfid');

    fetch(`${BASE_URL}/ajax/devices.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('RFID deactivated successfully', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showAlert(data.error || 'Failed to deactivate RFID', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred', 'error');
    });
}

// Show alert message
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 p-4 rounded shadow-lg ${
        type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
    }`;
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

// Close modal when clicking outside
document.getElementById('assignmentModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});