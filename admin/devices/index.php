<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

// Get current device mode
$deviceModeData = $db->single("SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'");
$deviceMode = $deviceModeData['setting_value'] ?? 'scan';
$isRegistering = ($deviceMode === 'register');

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left Side: Registration Controls -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">RFID Card Registration</h2>
            
            <div class="mb-4">
                <div class="p-4 border rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium">Device Status:</span>
                        <span id="deviceMode" class="px-2 py-1 rounded text-sm <?= $isRegistering ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($deviceMode) ?> Mode
                        </span>
                    </div>
                    <p id="statusMessage" class="text-center text-gray-600">
                        <?= $isRegistering ? 'Ready to scan new RFID card' : 'Click Start Registration when ready' ?>
                    </p>
                </div>
            </div>

            <button onclick="toggleRegistration()" 
                    id="registrationButton"
                    class="w-full <?= $isRegistering ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-500 hover:bg-blue-600' ?> text-white px-4 py-2 rounded">
                <?= $isRegistering ? 'Cancel Registration' : 'Start Registration' ?>
            </button>
        </div>

        <!-- Right Side: Real-Time RFID Display -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Scanned RFID Tags</h2>
            <div id="rfidContainer" class="h-64 overflow-auto border rounded-lg p-4">
                <ul id="rfidList" class="space-y-2">
                    <li class="text-gray-500 italic">No RFID scanned yet...</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let registrationMode = <?= json_encode($isRegistering) ?>;
const button = document.getElementById('registrationButton');
const statusMessage = document.getElementById('statusMessage');
const deviceModeSpan = document.getElementById('deviceMode');
const rfidList = document.getElementById('rfidList');
let eventSource = null;

function updateDeviceMode(mode) {
    deviceModeSpan.textContent = `${mode.charAt(0).toUpperCase() + mode.slice(1)} Mode`;
    deviceModeSpan.className = `px-2 py-1 rounded text-sm ${
        mode === 'register' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'
    }`;
}

function toggleRegistration() {
    if (registrationMode) {
        stopRegistration();
    } else {
        startRegistration();
    }
}

function startRegistration() {
    button.disabled = true;
    statusMessage.textContent = 'Starting registration mode...';
    
    fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'register' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            registrationMode = true;
            updateDeviceMode('register');
            button.textContent = 'Cancel Registration';
            button.className = 'w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600';
            button.disabled = false;
            statusMessage.textContent = 'Ready to scan new RFID card';
            startEventListener();
        } else {
            throw new Error(data.message || 'Failed to start registration');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusMessage.textContent = `Error: ${error.message}`;
        button.disabled = false;
    });
}

function stopRegistration() {
    button.disabled = true;
    statusMessage.textContent = 'Stopping registration mode...';
    
    fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'scan' })
    })
    .then(response => response.json())
    .then(data => {
        registrationMode = false;
        updateDeviceMode('scan');
        button.textContent = 'Start Registration';
        button.className = 'w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600';
        button.disabled = false;
        statusMessage.textContent = 'Click Start Registration when ready';
        
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusMessage.textContent = `Error: ${error.message}`;
        button.disabled = false;
    });
}

function startEventListener() {
    if (eventSource) {
        eventSource.close();
    }

    eventSource = new EventSource('<?= BASE_URL ?>/device_events.php');
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);

        if (data.rfid_status === 'success') {
            addRFIDToList(data.rfid_value);
        } else if (data.rfid_status === 'error') {
            statusMessage.textContent = data.rfid_message || 'Registration failed';
        }
    };

    eventSource.onerror = function() {
        console.error('EventSource failed. Retrying...');
        eventSource.close();
        setTimeout(startEventListener, 5000);
    };
}

function addRFIDToList(rfid) {
    const listItem = document.createElement('li');
    listItem.textContent = `Scanned RFID: ${rfid}`;
    listItem.className = "bg-blue-100 text-blue-800 p-2 rounded";
    
    rfidList.appendChild(listItem);
    rfidList.scrollTop = rfidList.scrollHeight; // Auto-scroll
}

if (registrationMode) {
    startEventListener();
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
