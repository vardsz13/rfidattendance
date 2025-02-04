<?php
require_once 'config/constants.php';
require_once 'includes/functions.php';
require_once 'includes/auth_functions.php';
require_once 'includes/components/Calendar.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = getDatabase();

// Enable calendar
$useCalendar = true;

require_once 'includes/header.php';

// Get calendar parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$userId = $isAdmin ? null : ($_SESSION['user_id'] ?? null);

// Initialize calendar
$calendar = new Calendar($db, $year, $month, $isAdmin, $userId);
?>

<div class="space-y-6">
    <!-- Verification Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Attendance Verification</h2>
        
        <div id="verificationStatus" class="mb-4 hidden">
            <!-- Status messages will be inserted here -->
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- RFID Section -->
            <div>
                <h3 class="text-lg font-semibold mb-2">Step 1: Scan RFID</h3>
                <div class="p-4 border rounded-lg">
                    <p id="rfidMessage" class="text-center text-gray-600">
                        Please scan your RFID card
                    </p>
                </div>
            </div>

            <!-- Fingerprint Section -->
            <div>
                <h3 class="text-lg font-semibold mb-2">Step 2: Scan Fingerprint</h3>
                <div class="p-4 border rounded-lg">
                    <p id="fingerprintMessage" class="text-center text-gray-600">
                        Please place your finger on the scanner
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <div id="lastVerification" class="text-center text-gray-600">
                No recent verifications
            </div>
        </div>
    </div>

    <!-- Stats Cards for Admin -->
    <?php if ($isAdmin): ?>
        <?= $calendar->getStatsCards() ?>
    <?php endif; ?>

    <!-- Calendar Component -->
    <div id="calendar-container">
        <?= $calendar->render() ?>
    </div>
</div>

<!-- Real-time Updates Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const verificationStatus = document.getElementById('verificationStatus');
    const rfidMessage = document.getElementById('rfidMessage');
    const fingerprintMessage = document.getElementById('fingerprintMessage');
    const lastVerification = document.getElementById('lastVerification');

    function updateVerificationStatus(data) {
        if (data.rfid_status) {
            rfidMessage.textContent = data.rfid_message;
            rfidMessage.className = `text-center ${data.rfid_status === 'success' ? 'text-green-600' : 'text-gray-600'}`;
        }

        if (data.fingerprint_status) {
            fingerprintMessage.textContent = data.fingerprint_message;
            fingerprintMessage.className = `text-center ${data.fingerprint_status === 'success' ? 'text-green-600' : 'text-gray-600'}`;
        }

        if (data.verification_message) {
            verificationStatus.innerHTML = `
                <div class="p-4 rounded ${data.verification_status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                    ${data.verification_message}
                </div>
            `;
            verificationStatus.classList.remove('hidden');

            if (data.verification_status === 'success') {
                lastVerification.innerHTML = `
                    <div class="text-green-600">
                        <strong>${data.user_name}</strong> - ${data.log_type === 'in' ? 'Checked In' : 'Checked Out'} at ${new Date().toLocaleTimeString()}
                    </div>
                `;

                // Refresh calendar after successful verification
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }

            setTimeout(() => {
                verificationStatus.classList.add('hidden');
            }, 5000);
        }
    }

    function initializeRealTimeUpdates() {
        if (typeof EventSource !== 'undefined') {
            const eventSource = new EventSource('<?= BASE_URL ?>/ajax/device_events.php');

            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                updateVerificationStatus(data);
            };

            eventSource.onerror = function() {
                console.error('EventSource failed. Retrying in 5 seconds...');
                eventSource.close();
                setTimeout(initializeRealTimeUpdates, 5000);
            };
        } else {
            setInterval(pollVerificationStatus, 2000);
        }
    }

    function pollVerificationStatus() {
        fetch('<?= BASE_URL ?>/ajax/device_status.php')
            .then(response => response.json())
            .then(data => updateVerificationStatus(data))
            .catch(error => console.error('Error polling verification status:', error));
    }

    // Initialize real-time updates
    initializeRealTimeUpdates();
});
</script>

<?php require_once 'includes/footer.php'; ?>