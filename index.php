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
            max-height: 400px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .user-details {
            max-height: 300px;
            overflow-y: auto;
        }

        /* Enhanced scrollbar styling */
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

        /* Auto-scroll indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(25, 135, 84, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .scroll-indicator.show {
            opacity: 1;
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
        </div>
    </header>

    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="index.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i>Home / Scanner
                </a>
                <a href="amenity.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i>Amenity Booking
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
                            <i class="bi bi-door-closed me-2"></i>Gate Status: <strong>CLOSED</strong>
                        </div>

                        <!-- Gate Status Info -->
                        <div class="alert alert-info border-0 mt-2">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Gate opens automatically for registered users and closes after the vehicle has
                                passed.</small>
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
                        <small class="opacity-75">Auto-scroll enabled</small>
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
                        <div class="scroll-indicator" id="scrollIndicator">
                            <i class="bi bi-arrow-down me-1"></i>Auto-scrolling
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

    <script>
        let autoCloseTimer = null;
        let logUpdateInterval = null;
        let isUserScrolling = false;
        let scrollTimeout = null;

        // Focus on RFID input when page loads
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('rfidInput').focus();
            loadScanLogs();
            startAutoLogUpdate();
            setupScrollDetection();
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

        function autoScrollToBottom() {
            const scanLogsContainer = document.getElementById('scanLogsContainer');
            const scrollIndicator = document.getElementById('scrollIndicator');

            // Only auto-scroll if user isn't manually scrolling
            if (!isUserScrolling) {
                // Show scroll indicator
                scrollIndicator.classList.add('show');

                // Smooth scroll to bottom
                scanLogsContainer.scrollTo({
                    top: scanLogsContainer.scrollHeight,
                    behavior: 'smooth'
                });

                // Hide scroll indicator after animation
                setTimeout(() => {
                    scrollIndicator.classList.remove('show');
                }, 1000);
            }
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
                        resetAutoClose();
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
                    <div class="col-md-3 text-center">
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
            fetch('rfid-api/open_gate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + encodeURIComponent(action)
            })
                .then(res => res.json())
                .then(data => {
                    const gateStatus = document.getElementById('gateStatus');
                    if (data.status === 'success') {
                        const isOpen = data.gate === 'OPEN';
                        const icon = isOpen ? 'bi-door-open' : 'bi-door-closed';
                        const alertClass = isOpen ? 'alert-success' : 'alert-secondary';

                        gateStatus.innerHTML = `<i class="bi ${icon} me-2"></i>Gate Status: <strong>${data.gate}</strong>`;
                        gateStatus.className = `alert ${alertClass} border mt-3`;
                    } else {
                        gateStatus.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>Gate Status: <strong>Error</strong>`;
                        gateStatus.className = 'alert alert-danger border mt-3';
                    }
                })
                .catch(err => {
                    console.error('Gate trigger error:', err);
                    updateGateDisplay(action === 'open' ? 'OPEN' : 'CLOSED');
                });
        }

        function updateGateDisplay(status) {
            const gateStatus = document.getElementById('gateStatus');
            const isOpen = status === 'OPEN';
            const icon = isOpen ? 'bi-door-open' : 'bi-door-closed';
            const alertClass = isOpen ? 'alert-success' : 'alert-secondary';

            gateStatus.innerHTML = `<i class="bi ${icon} me-2"></i>Gate Status: <strong>${status}</strong>`;
            gateStatus.className = `alert ${alertClass} border mt-3`;
        }

        function resetAutoClose() {
            if (autoCloseTimer) clearTimeout(autoCloseTimer);
            autoCloseTimer = setTimeout(() => {
                triggerGate('close');
            }, 5000); // 5 seconds
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

        // Cleanup intervals when page is closed
        window.addEventListener('beforeunload', function () {
            if (logUpdateInterval) {
                clearInterval(logUpdateInterval);
            }
            if (autoCloseTimer) {
                clearTimeout(autoCloseTimer);
            }
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
        });
    </script>

</body>

</html>