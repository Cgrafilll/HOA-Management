<?php
// ✅ Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
session_start();

require 'rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_side/login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: admin_side/login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admin_accounts WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    echo "Admin not found.";
    exit;
}

// Initialize user details
$username = $admin['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSSHAI HOA Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="images/SitioSeville_Logo.png" type="image/x-icon">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 20;
            left: 0;
            background-color: #1F2937;
            overflow-y: auto;
            color: #fff;
            padding: 20px;
        }

        main {
            margin-left: 250px;
        }

        .sidebar a,
        .sidebar button {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar a:hover,
        .sidebar button:hover,
        .collapse ul li a:hover {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed) {
            background-color: #198754;
            border-radius: 0.375rem;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .log-entry {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rfid-input {
            font-size: 1.2rem;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
        }

        .rfid-input:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .scan-logs {
            min-height: 400px;
            max-height: 400px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .user-details {
            max-height: 300px;
            overflow-y: auto;
        }

        .scan-logs::-webkit-scrollbar {
            width: 8px;
        }

        .scan-logs::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .scan-logs::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 4px;
        }

        .scan-logs::-webkit-scrollbar-thumb:hover {
            background: #157347;
        }

        .logs-container {
            position: relative;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">RFID SYSTEM</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?php echo htmlspecialchars($username); ?></span>
                    <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-5"
                        style="height: 40px; width: 40px; color: #aaa;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>"
                                style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 32px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="admin_side/login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="index.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i>Entry Monitoring
                </a>
                <a href="exit.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-sign-turn-left me-2"></i>Exit Monitoring
                </a>
                <a href="amenity.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i>Amenity Booking
                </a>
                <a href="admin_side/login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
    </div>

    <!--Main Content-->
    <main class="flex-fill p-4">
        <!-- SCAN RFID AND LOGS -->
        <div class="row g-4 mb-3">
            <!-- LEFT COLUMN: RFID Input & Gate Status -->
            <div class="col-6">
                <div class="card shadow-sm h-100 d-flex flex-column">
                    <div class="card-header bg-success text-white fw-semibold">
                        <i class="bi bi-upc-scan me-2"></i>RFID Scanner
                    </div>
                    <div class="card-body flex-grow-1">
                        <!-- RFID Input Field -->
                        <div class="mb-4">
                            <label for="rfidInput" class="form-label fw-bold">Scan or Enter RFID:</label>
                            <input type="text" id="rfidInput" class="form-control rfid-input"
                                placeholder="Please scan your RFID card..." autocomplete="off" autofocus>
                            <div class="form-text">Position your RFID card near the scanner or type manually</div>
                        </div>

                        <!-- Gate Status -->
                        <div id="gateStatus" class="alert alert-secondary border mt-3" role="alert">
                            <i class="bi bi-door-closed me-2"></i>Gate 1 Status: <strong>CLOSED</strong>
                        </div>

                        <!-- Gate Status Info -->
                        <div class="alert alert-info border-0 mt-2">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Gate opens automatically for registered users and closes after the vehicle has
                                passed.</small>
                        </div>

                        <!-- Manual Gate Controls -->
                        <div class="mt-3">
                            <label class="form-label fw-bold mb-2">Manual Gate Control:</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success" id="manualOpenBtn"
                                    onclick="manualGateControl('open')">
                                    <i class="bi bi-door-open me-1"></i>Open Gate
                                </button>
                                <button type="button" class="btn btn-danger" id="manualCloseBtn"
                                    onclick="manualGateControl('close')">
                                    <i class="bi bi-door-closed me-1"></i>Close Gate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Scan Logs -->
            <div class="col-6">
                <div class="card shadow-sm h-100 d-flex flex-column">
                    <div
                        class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history me-2"></i>Recent Scan Logs</span>
                    </div>
                    <div class="card-body flex-grow-1 p-0 logs-container">
                        <div class="scan-logs p-3" id="scanLogsContainer">
                            <div id="scanEntries">
                                <div class="text-muted text-center py-4">
                                    <i class="bi bi-upc-scan" style="font-size: 2rem;"></i>
                                    <div>No scans yet. Scan an RFID card to begin.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details Section -->
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white fw-semibold">
                <i class="bi bi-person me-2"></i>User Details
            </div>
            <div class="card-body user-details" id="userDetailsSection">
                <div class="text-center text-muted py-5">
                    <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                    <div class="mt-3">Scan an RFID card to view user details</div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let logUpdateInterval = null;
        let scrollTimeout = null;
        let statusPollingInterval = null;

        // Focus on RFID input when page loads
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('rfidInput').focus();
            loadScanLogs();
            startAutoLogUpdate();
            setupScrollDetection();
            startStatusPolling();
        });

        // Handle RFID input
        document.getElementById('rfidInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const uid = this.value.trim();
                if (uid.length > 0) {
                    processRFIDScan(uid);
                    this.value = ''; // Clear input
                }
            }
        });

        // Keep focus on RFID input
        document.getElementById('rfidInput').addEventListener('blur', function () {
            setTimeout(() => this.focus(), 100);
        });

        function setupScrollDetection() {
            const scanLogsContainer = document.getElementById('scanLogsContainer');

            scanLogsContainer.addEventListener('scroll', function () {
                isUserScrolling = true;

                // Clear existing timeout
                if (scrollTimeout) {
                    clearTimeout(scrollTimeout);
                }

                // Reset user scrolling flag after 3 seconds of no scrolling
                scrollTimeout = setTimeout(() => {
                    isUserScrolling = false;
                }, 3000);
            });
        }

        function processRFIDScan(uid) {
            // Add to scan log immediately
            addToScanLog(uid, 'Checking...', 'text-secondary');

            // Check UID against database
            fetch('rfid-api/check_uid.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'uid=' + encodeURIComponent(uid)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const statusText = data.type === 'household' ? 'Household Member' : 'Visitor';
                        const statusClass = data.type === 'household' ? 'text-success' : 'text-primary';

                        updateScanLog(uid, statusText, statusClass, data.full_name);
                        displayUserDetails(data);
                        triggerGate('open');
                        // Arduino handles auto-closing - no resetAutoClose needed
                    } else {
                        updateScanLog(uid, 'Not Registered', 'text-danger', 'Unknown Card');
                        clearUserDetails();
                        triggerGate('close');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    updateScanLog(uid, 'Connection Error', 'text-warning', 'Unknown Card');
                    clearUserDetails();
                    triggerGate('close');
                });
        }

        function addToScanLog(uid, status, statusClass) {
            const scanEntries = document.getElementById('scanEntries');

            // Remove "no scans" message if it exists
            const noScansMessage = scanEntries.querySelector('.text-muted.text-center');
            if (noScansMessage) {
                scanEntries.innerHTML = '';
            }

            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry border-bottom pb-2 mb-2';
            logEntry.dataset.uid = uid;
            logEntry.dataset.timestamp = Date.now();

            logEntry.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold">UID: ${uid}</div>
                    <div class="scan-name text-muted">Name: Checking...</div>
                </div>
                <div class="text-end">
                    <div><span class="badge scan-status ${statusClass.replace('text-', 'bg-')}">${status}</span></div>
                    <div class="text-muted small mt-1">${new Date().toLocaleTimeString()}</div>
                </div>
            </div>
        `;

            // Add new entry at the top
            scanEntries.insertBefore(logEntry, scanEntries.firstChild);

            // Keep only last 15 entries (increased from 10 for better history)
            while (scanEntries.children.length > 15) {
                scanEntries.removeChild(scanEntries.lastChild);
            }

            // Auto-scroll to show new entry
            setTimeout(() => {
                autoScrollToBottom();
            }, 100);
        }

        function updateScanLog(uid, status, statusClass, fullName) {
            const logEntry = document.querySelector(`[data-uid="${uid}"]`);
            if (logEntry) {
                const statusElement = logEntry.querySelector('.scan-status');
                const nameElement = logEntry.querySelector('.scan-name');

                if (statusElement) {
                    statusElement.textContent = status;
                    statusElement.className = `badge ${statusClass.replace('text-', 'bg-')}`;
                }

                if (nameElement) {
                    nameElement.textContent = `Name: ${fullName}`;
                    nameElement.className = 'text-dark';
                }
            }
        }

        function displayUserDetails(data) {
            const userDetailsSection = document.getElementById('userDetailsSection');
            const roleText = data.type === 'household' ? 'Household Member' : 'Visitor';
            const roleClass = data.type === 'household' ? 'text-success' : 'text-primary';

            // Check if profile picture exists
            let profileImageHtml = '';
            if (data.profile_picture && data.profile_picture.trim() !== '') {
                profileImageHtml = `
                <img src="${data.profile_picture}" 
                     class="border border-2 rounded mb-3" 
                     style="width: 150px; height: 150px; object-fit: cover;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                     alt="Profile Picture">
                <div class="d-none justify-content-center align-items-center border border-2 rounded mb-3" 
                     style="width: 150px; height: 150px; margin: 0 auto;">
                    <i class="bi bi-person" style="font-size: 4rem; color: #ccc;"></i>
                </div>
            `;
            } else {
                profileImageHtml = `
                <div class="d-flex justify-content-center align-items-center border border-2 rounded mb-3" 
                     style="width: 150px; height: 150px; margin: 0 auto;">
                    <i class="bi bi-person" style="font-size: 4rem; color: #ccc;"></i>
                </div>
            `;
            }

            userDetailsSection.innerHTML = `
            <div class="row">
                <div class="col-md-3 d-flex flex-column align-items-center justify-content-center">
                    ${profileImageHtml}
                    <span class="badge ${roleClass.replace('text-', 'bg-')} fs-6">${roleText}</span>
                </div>
                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Full Name:</label>
                            <div class="form-control-plaintext">${data.full_name}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">RFID:</label>
                            <div class="form-control-plaintext">${data.rfid}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">First Name:</label>
                            <div class="form-control-plaintext">${data.first_name}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Middle Name:</label>
                            <div class="form-control-plaintext">${data.middle_name || 'N/A'}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Last Name:</label>
                            <div class="form-control-plaintext">${data.last_name}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Type:</label>
                            <div class="form-control-plaintext">${roleText}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        }

        function clearUserDetails() {
            document.getElementById('userDetailsSection').innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                <div class="mt-3">User not found in database</div>
            </div>
        `;
        }

        function triggerGate(action) {
            console.log("Sending command:", action, "for gate 1"); // Debug line

            fetch('rfid-api/open_gate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + encodeURIComponent(action) + '&gate=1' // Gate 1 for Entry
            })
                .then(res => res.json())
                .then(data => {
                    console.log("Arduino response:", data.arduino_response); // Debug line
                    updateGateStatusFromArduino(data);
                })
                .catch(err => {
                    console.error('Gate trigger error:', err);
                    updateGateDisplay('ERROR');
                });
        }

        function updateGateStatusFromArduino(data) {
            if (data.status === 'success') {
                // Parse Arduino response for actual gate state
                const response = data.arduino_response || '';
                let currentStatus = 'UNKNOWN';

                if (response.includes('Gate1 opened') || response.includes('SUCCESS: Gate1 opened')) {
                    currentStatus = 'OPEN';
                } else if (response.includes('Gate1 closed') || response.includes('SUCCESS: Gate1 closed')) {
                    currentStatus = 'CLOSED';
                } else if (response.includes('already open')) {
                    currentStatus = 'OPEN';
                } else if (response.includes('already closed')) {
                    currentStatus = 'CLOSED';
                } else if (data.gate && data.gate !== 'UNKNOWN') {
                    currentStatus = data.gate;
                }

                updateGateDisplay(currentStatus);
            } else {
                updateGateDisplay('ERROR');
            }
        }

        function updateGateDisplay(status) {
            const gateStatus = document.getElementById('gateStatus');

            let icon, alertClass;

            switch (status) {
                case 'OPEN':
                    icon = 'bi-door-open';
                    alertClass = 'alert-success';
                    break;
                case 'CLOSED':
                    icon = 'bi-door-closed';
                    alertClass = 'alert-secondary';
                    break;
                case 'ERROR':
                    icon = 'bi-exclamation-triangle';
                    alertClass = 'alert-danger';
                    break;
                default:
                    icon = 'bi-question-circle';
                    alertClass = 'alert-warning';
                    status = 'UNKNOWN';
            }

            gateStatus.innerHTML = `<i class="bi ${icon} me-2"></i>Gate 1 Status: <strong>${status}</strong>`;
            gateStatus.className = `alert ${alertClass} border mt-3`;
        }

        function startStatusPolling() {
            // Poll Arduino status every 2 seconds
            statusPollingInterval = setInterval(() => {
                pollArduinoStatus();
            }, 2000);
        }

        function pollArduinoStatus() {
            fetch('rfid-api/get_gate_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'gate=1' // Check Gate 1 status
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateGateStatusFromArduino(data);
                    }
                })
                .catch(err => {
                    console.error('Status polling error:', err);
                });
        }

        function loadScanLogs() {
            // Load recent scan logs from database
            fetch('rfid-api/get_recent_logs.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.logs.length > 0) {
                        const scanEntries = document.getElementById('scanEntries');
                        scanEntries.innerHTML = '';

                        data.logs.forEach((log, index) => {
                            const statusClass = log.type === 'household' ? 'bg-success' : 'bg-primary';
                            const statusText = log.type === 'household' ? 'Household Member' : 'Visitor';

                            const logEntry = document.createElement('div');
                            logEntry.className = 'border-bottom pb-2 mb-2';
                            logEntry.dataset.uid = log.uid;
                            logEntry.dataset.timestamp = new Date(log.date_created).getTime();

                            logEntry.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">UID: ${log.uid}</div>
                                    <div class="text-dark">Name: ${log.full_name}</div>
                                </div>
                                <div>
                                    <span class="badge ${statusClass}">${statusText}</span>
                                    <div class="text-muted small mt-1">${new Date(log.date_created).toLocaleTimeString()}</div>
                                </div>
                            </div>
                        `;

                            scanEntries.appendChild(logEntry);
                        });

                        // Auto-scroll to bottom after loading
                        setTimeout(() => {
                            autoScrollToBottom();
                        }, 300);
                    }
                })
                .catch(err => console.error('Error loading logs:', err));
        }

        function startAutoLogUpdate() {
            // Check for new logs every 5 seconds
            logUpdateInterval = setInterval(() => {
                checkForNewLogs();
            }, 5000);
        }

        function checkForNewLogs() {
            const lastLogTimestamp = getLastLogTimestamp();

            fetch(`rfid-api/get_recent_logs.php?after=${lastLogTimestamp}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.logs.length > 0) {
                        appendNewLogs(data.logs);
                    }
                })
                .catch(err => console.error('Error checking for new logs:', err));
        }

        function getLastLogTimestamp() {
            const scanEntries = document.getElementById('scanEntries');
            const logs = scanEntries.querySelectorAll('[data-timestamp]');

            if (logs.length === 0) return 0;

            let latestTimestamp = 0;
            logs.forEach(log => {
                const timestamp = parseInt(log.dataset.timestamp);
                if (timestamp > latestTimestamp) {
                    latestTimestamp = timestamp;
                }
            });

            return latestTimestamp;
        }

        function appendNewLogs(logs) {
            logs.forEach(log => {
                const logTimestamp = new Date(log.date_created).getTime();

                // Check if log already exists
                const existingLog = document.querySelector(`[data-uid="${log.uid}"][data-timestamp="${logTimestamp}"]`);
                if (!existingLog) {
                    const statusClass = log.type === 'household' ? 'bg-success' : 'bg-primary';
                    const statusText = log.type === 'household' ? 'Household Member' : 'Visitor';

                    addNewLogEntry(log.uid, statusText, statusClass.replace('bg-', 'text-'), log.full_name, new Date(log.date_created));
                }
            });
        }

        function addNewLogEntry(uid, status, statusClass, fullName, timestamp) {
            const scanEntries = document.getElementById('scanEntries');

            // Remove "no scans" message if it exists
            const noScansMessage = scanEntries.querySelector('.text-muted.text-center');
            if (noScansMessage) {
                scanEntries.innerHTML = '';
            }

            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry border-bottom pb-2 mb-2';
            logEntry.dataset.uid = uid;
            logEntry.dataset.timestamp = timestamp.getTime();

            const badgeClass = statusClass.replace('text-', 'bg-');

            logEntry.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold">UID: ${uid}</div>
                    <div class="text-dark">Name: ${fullName}</div>
                </div>
                <div class="text-end">
                    <div><span class="badge ${badgeClass}">${status}</span></div>
                    <div class="text-muted small mt-1">${timestamp.toLocaleTimeString()}</div>
                </div>
            </div>
        `;

            // Add new entry at the top
            scanEntries.insertBefore(logEntry, scanEntries.firstChild);

            // Keep only last 15 entries
            while (scanEntries.children.length > 15) {
                scanEntries.removeChild(scanEntries.lastChild);
            }

            // Auto-scroll to show new entry
            setTimeout(() => {
                autoScrollToBottom();
            }, 100);
        }

        function autoScrollToBottom() {
            const scanLogsContainer = document.getElementById('scanLogsContainer');
            if (!isUserScrolling) {
                scanLogsContainer.scrollTop = scanLogsContainer.scrollHeight;
            }
        }

        // Manual gate control function
        function manualGateControl(action) {
            console.log("Manual gate control:", action, "for gate 1");

            // Disable buttons temporarily to prevent rapid clicking
            document.getElementById('manualOpenBtn').disabled = true;
            document.getElementById('manualCloseBtn').disabled = true;

            fetch('rfid-api/open_gate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + encodeURIComponent(action) + '&gate=1' // Gate 1 for Entry
            })
                .then(res => res.json())
                .then(data => {
                    console.log("Manual control Arduino response:", data.arduino_response); // Debug line
                    updateGateStatusFromArduino(data);
                })
                .catch(err => {
                    console.error('Manual gate control error:', err);
                    updateGateDisplay('ERROR');
                })
                .finally(() => {
                    // Re-enable buttons after 2 seconds
                    setTimeout(() => {
                        document.getElementById('manualOpenBtn').disabled = false;
                        document.getElementById('manualCloseBtn').disabled = false;
                    }, 2000);
                });
        }

        // Cleanup intervals when page is closed
        window.addEventListener('beforeunload', function () {
            if (logUpdateInterval) {
                clearInterval(logUpdateInterval);
            }
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            if (statusPollingInterval) {
                clearInterval(statusPollingInterval);
            }
        });

    </script>

</body>

</html>