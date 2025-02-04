<?php
// admin/users/register/fingerprint.php
require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once dirname(__DIR__, 3) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    header('Location: basic.php');
    exit();
}

// Get user details with verification data
$user = $db->single(
    "SELECT u.*, uvd.id as verification_id 
     FROM users u
     LEFT JOIN user_verification_data uvd ON u.id = uvd.user_id AND uvd.is_active = true
     WHERE u.id = ?", 
    [$userId]
);

if (!$user) {
    header('Location: basic.php');
    exit();
}

// Special users skip fingerprint registration
if ($user['user_type'] === 'special') {
    header("Location: complete.php?user_id=$userId");
    exit();
}

// Verify RFID has been assigned
if (!$user['verification_id']) {
    header("Location: rfid.php?user_id=$userId");
    exit();
}

require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumbs -->
    <div class="mb-8">
        <div class="flex items-center space-x-2 text-gray-500">
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">Basic Info</span>
            <span>→</span>
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">RFID Assignment</span>
            <span>→</span>
            <span class="bg-blue-500 text-white px-3 py-1 rounded-full">3</span>
            <span>Fingerprint</span>
            <span>→</span>
            <span class="text-gray-400">Complete</span>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6">Register Fingerprint</h2>
            
            <div class="mb-4">
                <p class="text-gray-600">Registering fingerprint for:</p>
                <p class="font-semibold"><?= htmlspecialchars($user['name']) ?></p>
                <p class="text-sm text-gray-500">ID: <?= htmlspecialchars($user['id_number']) ?></p>
            </div>

            <div id="registrationStatus" class="mb-6">
                <div class="p-4 border rounded-lg">
                    <p id="statusMessage" class="text-center text-gray-600">
                        Click Start Registration when ready
                    </p>
                </div>
            </div>

            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-semibold mb-2">Instructions:</h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li>Click Start Registration below</li>
                    <li>Wait for "Place finger" message on device LCD</li>
                    <li>Place your finger on the sensor</li>
                    <li>Follow the LCD instructions to complete registration</li>
                    <li>Click Complete when finished</li>
                </ol>
            </div>

            <div class="flex justify-between items-center">
                <button onclick="window.history.back()" 
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    ← Back
                </button>
                <div>
                    <button onclick="startRegistration()" id="startButton"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 mr-2">
                        Start Registration
                    </button>
                    <a href="complete.php?user_id=<?= $user['id'] ?>" 
                       class="inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Complete →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let isRegistering = false;

function startRegistration() {
    if (isRegistering) return;
    
    const button = document.getElementById('startButton');
    const statusMessage = document.getElementById('statusMessage');
    
    button.disabled = true;
    isRegistering = true;
    statusMessage.textContent = 'Initializing registration mode...';
    
    // Set device to registration mode
    fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            mode: 'register',
            user_id: <?= $userId ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            statusMessage.textContent = 'Please follow the instructions on the device LCD';
            button.textContent = 'Registration in Progress';
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusMessage.textContent = 'Error starting registration. Please try again.';
        button.disabled = false;
        isRegistering = false;
    });
}
</script>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>