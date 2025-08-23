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

// Initialize amenity details (make sure case & spacing match keys)
$amenity = isset($_GET['reserve']) ? urldecode($_GET['reserve']) : null;

// Define rates
$amenityRates = [
    "Swimming Pool" => [
        "homeowner" => [
            "day" => "₱100.00 / per person",
            "night" => "₱200.00 / per person"
        ],
        "visitor" => [
            "day" => "₱200.00 / per person",
            "night" => "₱300.00 / per person"
        ]
    ],
    "Clubhouse" => [
        "homeowner" => [
            "day" => "₱12,000.00",
            "night" => "₱12,000.00"
        ],
        "visitor" => [
            "day" => "₱15,000.00",
            "night" => "₱15,000.00"
        ]
    ],
    "Basketball Court" => [
        "homeowner" => [
            "day" => "₱200.00 / per person",
            "night" => "₱300.00 / per person"
        ],
        "visitor" => [
            "day" => "₱300.00 / per person",
            "night" => "₱400.00 / per person"
        ]
    ],
    "Gazebo" => [
        "homeowner" => [
            "day" => "₱1,000.00",
            "night" => "₱2,000.00"
        ],
        "visitor" => [
            "day" => "₱2,000.00",
            "night" => "₱3,000.00"
        ]
    ]
];

// Get default rates (homeowner)
$currentRates = ($amenity && isset($amenityRates[$amenity]))
    ? $amenityRates[$amenity]['homeowner']
    : null;

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

        .custom-radio-container {
            border: 2px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
            background-color: white;
        }

        .custom-radio-option {
            padding: 16px 20px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .custom-radio-option:not(:last-child) {
            border-bottom: 1px solid #dee2e6;
        }

        .custom-radio-option:hover {
            background-color: #f8f9fa;
        }

        .custom-radio-option.selected {
            background-color: #f0f9ff;
            border-color: #198754;
        }

        .custom-radio-circle {
            width: 20px;
            height: 20px;
            border: 2px solid #6c757d;
            border-radius: 50%;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.2s ease;
        }

        .custom-radio-circle.selected {
            border-color: #198754;
            background-color: #198754;
        }

        .custom-radio-circle.selected::after {
            content: '';
            width: 8px;
            height: 8px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
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
                <a href="../admin_dashboard.php"
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
                            <li><a href="../announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="../events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="../phonebook.php" class="nav-link px-2">Phone Book</a></li>
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
                        enctype="multipart/form-data" id="reservationForm">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <!-- User Type -->
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="userType" name="userType" required>
                                        <option value="homeowner">Homeowner/Resident</option>
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
                                <?php if ($amenity !== "Gazebo" && $amenity !== "Clubhouse"): ?>
                                    <!-- Guests -->
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="guests" name="guests" min="0">
                                        <label for="guests">Guests<small class="fw-bold text-danger">*</small></label>
                                    </div>
                                <?php endif; ?>
                                <!-- Rates -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rates<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div id="ratesContainer" class="custom-radio-container">
                                        <?php if ($currentRates): ?>
                                            <div class="custom-radio-option selected" data-value="day"
                                                onclick="selectRate(this, 'day')">
                                                <span id="dayRate">Day • <?= $currentRates['day'] ?></span>
                                                <div class="custom-radio-circle selected"></div>
                                            </div>
                                            <div class="custom-radio-option <?= $amenity === 'Clubhouse' ? 'disabled d-none' : '' ?>"
                                                data-value="night" onclick="selectRate(this, 'night')">
                                                <span id="nightRate">Night • <?= $currentRates['night'] ?></span>
                                                <div class="custom-radio-circle"></div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-danger">Rates not available for this amenity.</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="rate" id="selectedRate" value="day" required>
                                </div>
                                <!-- Payment -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Payment<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div class="custom-radio-container">
                                        <div class="custom-radio-option selected" data-value="bank"
                                            onclick="selectPayment(this, 'bank')">
                                            <span>Bank Deposit</span>
                                            <div class="custom-radio-circle selected"></div>
                                        </div>
                                        <div class="custom-radio-option" data-value="cash"
                                            onclick="selectPayment(this, 'cash')">
                                            <span>Cash</span>
                                            <div class="custom-radio-circle"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="payment" id="selectedPayment" value="bank" required>
                                </div>
                                <!-- Exclusive Booking -->
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="exclusiveBooking" name="exclusiveBooking" required>
                                        <option value="no" selected>No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                    <label for="exclusiveBooking">Is this an exclusive booking?<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>
                                <!-- Add-Ons -->
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="chairs" name="chairs" min="0"
                                                value="0">
                                            <label for="chairs">Chairs <small>(₱12.00/pc)</small></label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="tables" name="tables" min="0"
                                                value="0">
                                            <label for="tables">Tables <small>(₱20.00/pc)</small></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <!-- Payment Information -->
                                <div id="paymentInfo" class="payment-info p-3 rounded mb-4">
                                    <!-- Default Bank Info -->
                                    <div id="bankInfo">
                                        <h6 class="fw-bold mb-3">Payment Account</h6>
                                        <div class="mb-2"><small>EastWest Bank</small><br></div>
                                        <div class="mb-2"><small>Neopolitan Sitio Seville</small></div>
                                        <div class="mb-2"><small>Account Number: 20049887271</small></div>
                                        <div class="small fw-bold">
                                            Please settle payment as soon as possible to secure your slot. We strictly
                                            enforce payment first before we begin with your schedule/session.
                                            Failure to do so will result in cancellation of your reservation.
                                        </div>
                                    </div>

                                    <!-- Cash Info -->
                                    <div id="cashInfo" class="d-none">
                                        <h6 class="fw-bold mb-3">Payment Method: Cash</h6>
                                        <div class="small fw-bold">
                                            Please proceed to the clubhouse office at Neopolitan Sitio Seville to pay in
                                            cash.
                                            Make sure to settle your payment as soon as possible to confirm your
                                            booking.
                                        </div>
                                    </div>
                                </div>
                                <!-- Reference Number -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="referenceNumber" name="referenceNumber"
                                        placeholder="Reference Number">
                                    <label for="referenceNumber">Reference Number<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>
                                <!-- Total Amount -->
                                <div class="mb-3">
                                    <label for="total" class="form-label">Total<span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="text" class="form-control" id="total" name="total"
                                            placeholder="0.00" readonly>
                                    </div>
                                </div>
                                <!-- Amount Paid -->
                                <div class="mb-3">
                                    <label for="amountPaid" class="form-label">Amount Paid<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="amountPaid" name="amountPaid"
                                            placeholder="0.00" min="0" step="0.25" required>
                                    </div>
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
                                        I agree to <a href="#" class="text-success" data-bs-toggle="modal"
                                            data-bs-target="#termsModal">Terms and Conditions</a>
                                    </label>
                                </div>
                                <!-- Terms and Conditions Modal -->
                                <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title" id="termsModalLabel">TERMS AND CONDITIONS FOR
                                                    AMENITY BOOKING</h5>
                                                <button type="button" class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Neopolitan Sitio Seville Homeowners' Association, Inc.
                                                        (NSSHAI)</strong></p>
                                                <p><em>Effective Date: April 2025</em></p>
                                                <p>By booking any amenity through the NSSHAI HOA Management System, you
                                                    agree to the following terms and conditions:</p>
                                                <h6><strong>1. Reservation and Payment</strong></h6>
                                                <ul>
                                                    <li>A <strong>minimum of 50% down payment</strong> is required for
                                                        all reservations.
                                                        This payment is <strong>non-refundable</strong> but may be
                                                        rescheduled upon request.</li>
                                                    <li>Reservations must be made through the official HOA system and
                                                        are considered valid only once payment is received and
                                                        confirmed.</li>
                                                    <li>All payments must be made via:
                                                        <ul>
                                                            <li><strong>EastWest Bank</strong><br>Account Name:
                                                                Neopolitan Sitio Seville<br>Account Number: 20049887271
                                                            </li>
                                                            <li><strong>Or in person</strong> at the HOA Administrative
                                                                Office</li>
                                                        </ul>
                                                    </li>
                                                </ul>
                                                <h6><strong>2. Payment Confirmation</strong></h6>
                                                <ul>
                                                    <li>Proof of payment (e.g., deposit slip or screenshot) must be
                                                        uploaded through the online form or submitted to the office to
                                                        confirm the booking.</li>
                                                    <li>Incomplete or unverified reservations may be canceled without
                                                        notice.</li>
                                                </ul>
                                                <h6><strong>3. Rescheduling Policy</strong></h6>
                                                <ul>
                                                    <li><strong>Rescheduling is allowed</strong> but must be requested
                                                        <strong>at least 24 hours</strong>
                                                        before the reserved date.
                                                    </li>
                                                    <li>New schedule is subject to <strong>availability and HOA
                                                            approval.</strong></li>
                                                    <li>Only <strong>one (1) rescheduling</strong> per booking is
                                                        permitted. Further
                                                        changes may require a new reservation and payment.</li>
                                                </ul>
                                                <h6><strong>4. Exclusive Use and Special Requests</strong></h6>
                                                <ul>
                                                    <li>Requests for <strong>exclusive use</strong> of amenities (e.g.,
                                                        swimming pool)
                                                        require a <strong>minimum of 10 guests</strong>, higher rates,
                                                        and prior
                                                        approval.</li>
                                                    <li>Special bookings are dependent on HOA availability and
                                                        administrative discretion.</li>
                                                </ul>
                                                <h6><strong>5. Overtime Usage</strong></h6>
                                                <ul>
                                                    <li>Use of the <strong>Basketball Court beyond the booked
                                                            session</strong> (Day or
                                                        Night) will incur <strong>an additional charge of ₱1,000.00 per
                                                            hour.</strong></li>
                                                    <li>This applies only to <strong>excess hours beyond the reserved
                                                            time.</strong></li>
                                                    <li>Overtime use is subject to <strong>HOA approval and
                                                            monitoring.</strong></li>
                                                </ul>
                                                <h6><strong>6. Policy Enforcement</strong></h6>
                                                <ul>
                                                    <li>The HOA reserves the right to cancel or deny any booking due to
                                                        safety issues, maintenance, or failure to comply with policies.
                                                    </li>
                                                    <li>Improper use of the system or false information may lead to
                                                        suspension of booking privileges.</li>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-success"
                                                    data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Privacy Policy Checkbox -->
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="privacyPolicy"
                                        name="privacyPolicy" required>
                                    <label class="form-check-label" for="privacyPolicy">
                                        I agree to <a href="#" class="text-success" data-bs-toggle="modal"
                                            data-bs-target="#privacyPolicyModal">Privacy Policy</a>
                                    </label>
                                </div>
                                <!-- Privacy Policy Modal -->
                                <div class="modal fade" id="privacyPolicyModal" tabindex="-1"
                                    aria-labelledby="privacyPolicyModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title fw-bold" id="privacyPolicyModalLabel">PRIVACY
                                                    POLICY FOR AMENITY BOOKING</h5>
                                                <button type="button" class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Neopolitan Sitio Seville Homeowners' Association, Inc.
                                                        (NSSHAI)</strong></p>
                                                <p><em>Effective Date: April 2025</em></p>
                                                <p>NSSHAl values your privacy and is committed to protecting the
                                                    personal information you provide when using our Amenity Booking
                                                    feature through our HOA Management System.</p>

                                                <h6><strong>1. Information We Collect</strong></h6>
                                                <span>When you access and use the Amenity Booking feature, we may
                                                    collect
                                                    the following personal and transactional information:</span>
                                                <ul>
                                                    <li>Full Name (First, Middle, Last)</li>
                                                    <li>Email Address</li>
                                                    <li>Date and time of booking</li>
                                                    <li>Number of guests</li>
                                                    <li>Amenity type and time slot selected</li>
                                                    <li>Payment details (amount paid, mode of payment, reference number)
                                                    </li>
                                                    <li>Uploaded files (e.g., proof of payment)</li>
                                                </ul>
                                                <h6><strong>2. Purpose of Data Collection</strong></h6>
                                                <span>We use the collected information to:</span>
                                                <ul>
                                                    <li>Manage and confirm amenity reservations</li>
                                                    <li>Process and verify payments</li>
                                                    <li>Maintain organized HOA records for amenities usage</li>
                                                    <li>Communicate updates regarding bookings, schedule changes, or
                                                        policy updates</li>
                                                    <li>Ensure security, usage tracking, and compliance with HOA
                                                        regulations</li>
                                                </ul>
                                                <h6><strong>3. Data Storage and Protection</strong></h6>
                                                <span>Your personal information is stored securely within the HOA
                                                    Management System and protected through:</span>
                                                <ul>
                                                    <li>User authentication and administrative access controls</li>
                                                    <li>Secure encrypted file and data storage</li>
                                                    <li>Internal system logs and audit trails</li>
                                                    <li>Routine backups and restricted access to authorized personnel
                                                        only</li>
                                                </ul>
                                                <h6><strong>4. Data Sharing</strong></h6>
                                                <span>We do not sell or share personal information to third parties. All
                                                    access is governed by a need-to-know basis. Data
                                                    is accessed ony by:</span>
                                                <ul>
                                                    <li>HOA administrative staff</li>
                                                    <li>Authorized clubhouse personnel</li>
                                                    <li>Finance and accounting officers for verification and reporting
                                                    </li>
                                                </ul>
                                                <h6><strong>5. Retention of Records</strong></h6>
                                                <span>Personal and booking data is retained for as long as necessary to:
                                                </span>
                                                <ul>
                                                    <li>Manage amenity usage history</li>
                                                    <li>Maintain accounting and audit records</li>
                                                    <li>Comply with legal or regulatory obligations</li>
                                                    <li>Records are periodically reviewed and securely deleted when no
                                                        longer required.</li>
                                                </ul>
                                                <h6><strong>6. Your Data Privacy Rights</strong></h6>
                                                <span>You have the right to:</span>
                                                <ul class="mb-0">
                                                    <li>Request access to your personal booking and payment information
                                                    </li>
                                                    <li>Request correction of any inaccuracies</li>
                                                    <li>Request deletion of your personal data, subject to HOA
                                                        guidelines</li>
                                                    <li>Withdraw consent for data processing where applicable</li>
                                                </ul>
                                                <p>To exercise any of these rights, you may contact our HOA Admin Office
                                                    at:
                                                    8-2457647</p>
                                                <h6><strong>7. Policy Updates</strong></h6>
                                                <span>We reserve the right to update this Privacy Policy. Updates will
                                                    be
                                                    reflected on our official system and communicated to residents as
                                                    necessary.</span>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-success"
                                                    data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
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

        // Store rates from PHP into JS
        const amenityRates = <?php echo json_encode($amenityRates); ?>;
        const currentAmenity = "<?php echo $amenity; ?>";

        // Listen for userType change
        document.getElementById('userType').addEventListener('change', function () {
            const userType = this.value;

            // ✅ Keep previously selected rate (default to 'day' if none)
            let prevSelectedRate = document.getElementById('selectedRate').value || 'day';

            // ✅ For Clubhouse, always default to 'day' since night option won't exist
            if (currentAmenity === "Clubhouse") {
                prevSelectedRate = 'day';
            }

            if (amenityRates[currentAmenity] && amenityRates[currentAmenity][userType]) {
                const rates = amenityRates[currentAmenity][userType];

                // Update labels
                document.getElementById('dayRate').textContent = `Day • ${rates.day}`;

                // Only update night rate if it exists in the DOM
                const nightRateElement = document.getElementById('nightRate');
                if (nightRateElement) {
                    nightRateElement.textContent = `Night • ${rates.night}`;
                }

                // ✅ Restore the same rate (day/night) as before, but ensure it exists
                const container = document.getElementById('ratesContainer');
                const selectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);

                if (selectedOption) {
                    document.getElementById('selectedRate').value = prevSelectedRate;
                } else {
                    // If the previously selected rate doesn't exist (e.g., night for Clubhouse), default to day
                    document.getElementById('selectedRate').value = 'day';
                    prevSelectedRate = 'day';
                }

                // Reset UI
                container.querySelectorAll('.custom-radio-option').forEach(el => {
                    el.classList.remove('selected');
                    el.querySelector('.custom-radio-circle').classList.remove('selected');
                });

                const finalSelectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);
                if (finalSelectedOption) {
                    finalSelectedOption.classList.add('selected');
                    finalSelectedOption.querySelector('.custom-radio-circle').classList.add('selected');
                }
            } else {
                console.warn("No rates found for:", currentAmenity, userType);
            }

            calculateTotal();
        });

        function selectRate(option, value) {
            const container = document.getElementById('ratesContainer');
            container.querySelectorAll('.custom-radio-option').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.custom-radio-circle').classList.remove('selected');
            });
            option.classList.add('selected');
            option.querySelector('.custom-radio-circle').classList.add('selected');
            document.getElementById('selectedRate').value = value;

            calculateTotal();
        }

        function selectPayment(option, value) {
            const container = option.closest('.custom-radio-container');
            container.querySelectorAll('.custom-radio-option').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.custom-radio-circle').classList.remove('selected');
            });

            // Apply selected styling
            option.classList.add('selected');
            option.querySelector('.custom-radio-circle').classList.add('selected');

            // Save selected value
            document.getElementById('selectedPayment').value = value;

            // Toggle Payment Info + Reference + File Upload
            const bankInfo = document.getElementById("bankInfo");
            const cashInfo = document.getElementById("cashInfo");
            const referenceNumber = document.getElementById("referenceNumber").closest(".form-floating");
            const fileDropArea = document.getElementById("fileDropArea");

            if (value === "cash") {
                cashInfo.classList.remove("d-none");
                bankInfo.classList.add("d-none");

                // Hide reference number & file upload
                referenceNumber.classList.add("d-none");
                fileDropArea.classList.add("d-none");
            } else {
                bankInfo.classList.remove("d-none");
                cashInfo.classList.add("d-none");

                // Show reference number & file upload
                referenceNumber.classList.remove("d-none");
                fileDropArea.classList.remove("d-none");
            }
        }
        // Prices for add-ons
        const chairPrice = 12;
        const tablePrice = 20;

        function extractPrice(priceStr) {
            // Extract numbers from something like "₱100.00 / per person"
            const match = priceStr.replace(/,/g, '').match(/[\d.]+/);
            return match ? parseFloat(match[0]) : 0;
        }

        function calculateTotal() {
            const userType = document.getElementById("userType").value;
            const rateType = document.getElementById("selectedRate").value;

            let guests = parseInt(document.getElementById("guests")?.value || 0);
            let chairs = parseInt(document.getElementById("chairs").value || 0);
            let tables = parseInt(document.getElementById("tables").value || 0);

            // Get amenity rate from PHP rates
            let rateStr = amenityRates[currentAmenity]?.[userType]?.[rateType] || "₱0";
            let rateValue = extractPrice(rateStr);

            let total = 0;

            // Some amenities are per person (e.g., Swimming Pool, Basketball Court)
            if (rateStr.includes("per person")) {
                total += rateValue * guests;
            } else {
                total += rateValue;
            }

            // Add chairs and tables
            total += chairs * chairPrice;
            total += tables * tablePrice;

            // ✅ Format with commas and 2 decimals
            const formattedTotal = total.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Display in Total field
            document.getElementById("total").value = formattedTotal;
        }

        // Recalculate total when fields change
        ["guests", "chairs", "tables", "userType"].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener("input", calculateTotal);
                el.addEventListener("change", calculateTotal);
            }
        });

        // Run on load
        document.addEventListener("DOMContentLoaded", calculateTotal);
        // Recalculate total when fields change
        ["guests", "chairs", "tables", "userType"].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener("input", calculateTotal);
                el.addEventListener("change", calculateTotal);
            }
        });

        // Run on load
        document.addEventListener("DOMContentLoaded", calculateTotal);

        // Set minimum date to today
        document.addEventListener("DOMContentLoaded", function () {
            const today = new Date().toISOString().split("T")[0];
            const dateInput = document.getElementById("reservationDate");
            dateInput.min = today; // disables all past options
        });    
    </script>
</body>

</html>