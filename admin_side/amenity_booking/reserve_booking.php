<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = '';// Initialize user details

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE email_address = ?");
    $stmt->bind_param("s", $email_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];

        // Only set $photo if profile_pic exists and is not null
        if (!empty($user['profile_picture'])) {
            $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
        } else {
            $photo = ''; // Explicitly empty if no image is saved
        }
    } else {
        $error_message = "Failed to fetch user details.";
    }

} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}

//Initialize amenity details
$amenity = isset($_GET['reserve']) ? urldecode($_GET['reserve']) : null;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap');

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
        .collapse ul li a:hover,
        .collapse ul li a.actived {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed),
        .sidebar .btn-toggle.active {
            background-color: #198754;
            border-radius: 0.375rem;
        }

        .sidebar .btn-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            color: #ffffff;
            background: none;
            border: none;
        }

        .sidebar .btn-toggle i {
            margin-right: 8px;
        }

        .sidebar .btn-toggle::after {
            content: "▼";
            font-size: 10px;
            transition: transform 0.3s;
            margin-left: auto;
        }

        .sidebar .btn-toggle.collapsed::after {
            transform: rotate(0deg);
        }

        .sidebar .btn-toggle:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .file-drop-area:hover {
            border-color: #6b7280;
            background-color: #f3f4f6;
        }

        .file-drop-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .cloud-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 16px;
        }

        .payment-info {
            background-color: #f8f9fa;
            border-left: 4px solid #198754;
        }

        .form-floating>.form-select {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }

        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">RECORD KEEPING</h1>
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary">Hello, <?php echo htmlspecialchars($username); ?></span>
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
        </div>
    </header>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav flex-column gap-1">
                <a href="admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Accounts -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse show" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../amenity_booking.php" class="nav-link px-2 actived">Amenity Booking</a></li>
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="../entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Communication -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="#" class="nav-link px-2">Announcements</a></li>
                            <li><a href="#" class="nav-link px-2">Events</a></li>
                            <li><a href="#" class="nav-link px-2">Phone Book</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Accounting -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="#" class="nav-link px-2">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold w-100">Amenity Booking Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0"><?php echo htmlspecialchars($amenity); ?></span>
                    <a href="add_booking.php?amenity=<?php echo htmlspecialchars($amenity); ?>"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="d-flex justify-content-center align-items-center my-3">
                    <span class="text-uppercase text-center fw-medium"
                        style="font-family: 'Libre Baskervill', serif; font-size: 36px; letter-spacing: 10px;"><?php echo htmlspecialchars($amenity); ?>
                        RESERVATION</span>
                </div>
                <div class="p-3">
                    <form action="reserve_booking.php?reserve=<?php echo htmlspecialchars($amenity); ?>" method="POST"
                        id="householdForm" enctype="multipart/form-data" id="reservationForm">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <!-- User Type -->
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="userType" name="userType" required>
                                        <option value="homeowner" selected>Homeowner/Resident</option>
                                        <option value="visitor">Visitor</option>
                                    </select>
                                    <label for="userType">User Type<small class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- First Name -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="firstName" name="firstName"
                                        placeholder="First Name" required>
                                    <label for="firstName">First Name<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- Middle Name -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="middleName" name="middleName"
                                        placeholder="Middle Name">
                                    <label for="middleName">Middle Name<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- Last Name -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="lastName" name="lastName"
                                        placeholder="Last Name" required>
                                    <label for="lastName">Last Name<small class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- Email Address -->
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="emailAddress" name="emailAddress"
                                        placeholder="name@example.com" required>
                                    <label for="emailAddress">Email Address<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- Date -->
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="reservationDate" name="reservationDate"
                                        required>
                                    <label for="reservationDate">Date<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>

                                <!-- Guests -->
                                <div class="mb-3 position-relative">
                                    <label class="form-label">Guests<small class="fw-bold text-danger">*</small></label>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                            id="guestsButton">
                                            <span id="guestsDisplay">1</span>
                                        </button>
                                        <div class="dropdown-menu w-100 p-3" id="guestsDropdown">
                                            <!-- Household Members -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Household Members</span>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm rounded-circle p-1"
                                                        onclick="changeCount('household', -1)"
                                                        style="width: 30px; height: 30px;">
                                                        <i class="bi bi-dash"></i>
                                                    </button>
                                                    <span id="householdCount" class="fw-bold">1</span>
                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm rounded-circle p-1"
                                                        onclick="changeCount('household', 1)"
                                                        style="width: 30px; height: 30px;">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Guests -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Guests</span>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm rounded-circle p-1"
                                                        onclick="changeCount('guests', -1)"
                                                        style="width: 30px; height: 30px;">
                                                        <i class="bi bi-dash"></i>
                                                    </button>
                                                    <span id="guestCount" class="fw-bold">0</span>
                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm rounded-circle p-1"
                                                        onclick="changeCount('guests', 1)"
                                                        style="width: 30px; height: 30px;">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="householdMembers" id="householdMembers" value="1">
                                    <input type="hidden" name="guestMembers" id="guestMembers" value="0">
                                </div>

                                <!-- Rates -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rates</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rate" id="dayRate"
                                            value="day" required>
                                        <label class="form-check-label" for="dayRate">
                                            Day - ₱500.00 local
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rate" id="nightRate"
                                            value="night">
                                        <label class="form-check-label" for="nightRate">
                                            Night - ₱500.00 total
                                        </label>
                                    </div>
                                </div>

                                <!-- Payment -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Payment</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment" id="cash"
                                            value="cash" required>
                                        <label class="form-check-label" for="cash">
                                            Cash
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment" id="bankDeposit"
                                            value="bank">
                                        <label class="form-check-label" for="bankDeposit">
                                            Bank Deposit
                                        </label>
                                    </div>
                                </div>

                                <!-- Exclusive Booking -->
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="exclusiveBooking" name="exclusiveBooking" required>
                                        <option value="no" selected>No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                    <label for="exclusiveBooking">Is this an exclusive booking?</label>
                                </div>

                                <!-- Add-Ons -->
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="chairs" name="chairs" min="0"
                                                value="0">
                                            <label for="chairs">Chairs</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="tables" name="tables" min="0"
                                                value="0">
                                            <label for="tables">Tables</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <!-- Payment Information -->
                                <div class="payment-info p-3 rounded mb-4">
                                    <h6 class="fw-bold mb-3">Payment Account</h6>
                                    <div class="mb-2">
                                        <strong>EastWest Bank</strong><br>
                                        <small class="text-muted">Nesaphan Sitio Seville</small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Account Number: 200056977f</small>
                                    </div>
                                    <div class="small text-muted">
                                        Please settle payment as soon as possible to secure your slot. We strictly
                                        enforce payment first before we begin with your schedule/session.
                                        Failure to do so will result in cancellation of your reservation.
                                    </div>
                                </div>

                                <!-- Reference Number -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="referenceNumber" name="referenceNumber"
                                        placeholder="Reference Number">
                                    <label for="referenceNumber">Reference Number</label>
                                </div>

                                <!-- Amount Paid -->
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="amountPaid" name="amountPaid"
                                        placeholder="Amount Paid" step="0.01">
                                    <label for="amountPaid">Amount Paid</label>
                                </div>

                                <!-- File Upload -->
                                <div class="mb-4">
                                    <div class="file-drop-area" id="fileDropArea">
                                        <div class="cloud-icon">
                                            <i class="bi bi-cloud-upload"></i>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                        </div>
                                        <div class="small text-muted">
                                            Supported formats: JPEG, PNG, GIF, PDF, TXT, XLS, AI, Word, PPT
                                        </div>
                                        <input type="file" id="fileInput" name="proofOfPayment" class="d-none"
                                            accept=".jpeg,.jpg,.png,.gif,.pdf,.txt,.xls,.xlsx,.ai,.doc,.docx,.ppt,.pptx">
                                    </div>
                                    <div id="filePreview" class="mt-2"></div>
                                </div>

                                <!-- Terms and Conditions -->
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="termsConditions"
                                        name="termsConditions" required>
                                    <label class="form-check-label" for="termsConditions">
                                        I agree to <a href="#" class="text-success">Terms and Conditions</a>
                                    </label>
                                </div>

                                <!-- Privacy Policy -->
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="privacyPolicy"
                                        name="privacyPolicy" required>
                                    <label class="form-check-label" for="privacyPolicy">
                                        I agree to <a href="#" class="text-success">Privacy Policy</a>
                                    </label>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">Reserve</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload functionality
        const fileDropArea = document.getElementById('fileDropArea');
        const fileInput = document.getElementById('fileInput');
        const browseLink = document.getElementById('browseLink');
        const filePreview = document.getElementById('filePreview');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, unhighlight, false);
        });

        // Handle dropped files
        fileDropArea.addEventListener('drop', handleDrop, false);

        // Handle browse link click
        browseLink.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.click();
        });

        // Handle file input change
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            fileDropArea.classList.add('dragover');
        }

        function unhighlight(e) {
            fileDropArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        }

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                filePreview.innerHTML = `
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-file-earmark-check me-2"></i>
                        <div>
                            <strong>${file.name}</strong><br>
                            <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                        <button type="button" class="btn-close ms-auto" onclick="clearFile()"></button>
                    </div>
                `;
            }
        }

        function clearFile() {
            fileInput.value = '';
            filePreview.innerHTML = '';
        }

        // Form submission
        document.getElementById('reservationForm').addEventListener('submit', function (e) {
            e.preventDefault();

            // Basic form validation
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            // Show success message
            alert('Reservation submitted successfully!');

            // Reset form
            this.reset();
            filePreview.innerHTML = '';
            this.classList.remove('was-validated');
        });

        // Guest counter functionality
        let householdCount = 1;
        let guestCount = 0;

        function changeCount(type, change) {
            if (type === 'household') {
                householdCount = Math.max(1, householdCount + change);
                document.getElementById('householdCount').textContent = householdCount;
                document.getElementById('householdMembers').value = householdCount;
            } else if (type === 'guests') {
                guestCount = Math.max(0, guestCount + change);
                document.getElementById('guestCount').textContent = guestCount;
                document.getElementById('guestMembers').value = guestCount;
            }

            updateGuestsDisplay();
        }

        function updateGuestsDisplay() {
            const total = householdCount + guestCount;
            const displayText = total === 1 ? '1 guest' : `${total} guests`;
            document.getElementById('guestsDisplay').textContent = displayText;
        }

        // Prevent dropdown from closing when clicking inside
        document.getElementById('guestsDropdown').addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Set minimum date to today
        document.getElementById('reservationDate').setAttribute('min', new Date().toISOString().split('T')[0]);
    </script>
</body>

</html>