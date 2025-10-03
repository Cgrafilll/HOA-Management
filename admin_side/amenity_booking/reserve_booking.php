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

require '../../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
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

if (isset($_GET['action']) && $_GET['action'] === 'get_households') {
    try {
        // Include cellphone_number in the query
        $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name, email_address, cellphone_number FROM household_accounts WHERE status = 'active' ORDER BY household_id");
        $stmt->execute();
        $result = $stmt->get_result();

        $households = [];
        while ($row = $result->fetch_assoc()) {
            $households[] = [
                'household_id' => $row['household_id'],
                'name' => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
                'email' => $row['email_address'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'cellphone_number' => $row['cellphone_number']
            ];
        }

        echo json_encode(['success' => true, 'data' => $households]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_visitors') {
    try {
        // Include cellphone_number in the query
        $stmt = $conn->prepare("SELECT visitor_id, first_name, middle_name, last_name, email_address, cellphone_number FROM visitor_details WHERE status = 'active' ORDER BY visitor_id");
        $stmt->execute();
        $result = $stmt->get_result();

        $visitors = [];
        while ($row = $result->fetch_assoc()) {
            $visitors[] = [
                'visitor_id' => $row['visitor_id'],
                'name' => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
                'email' => $row['email_address'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'cellphone_number' => $row['cellphone_number']
            ];
        }

        echo json_encode(['success' => true, 'data' => $visitors]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request to get booked dates with rates for an amenity
if (isset($_GET['action']) && $_GET['action'] === 'get_booked_dates') {
    header('Content-Type: application/json');

    try {
        $amenity = $_GET['amenity'] ?? '';

        if (empty($amenity)) {
            echo json_encode(['success' => false, 'error' => 'Amenity required']);
            exit;
        }

        // Query to get both date and rate for each booking
        $stmt = $conn->prepare("
            SELECT reservation_date, rate 
            FROM amenity_bookings 
            WHERE amenity = ? 
            AND (status = 'pending' OR status = 'partial' OR status = 'paid')
        ");
        $stmt->bind_param("s", $amenity);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = [
                'date' => date('Y-m-d', strtotime($row['reservation_date'])),
                'rate' => $row['rate']
            ];
        }

        echo json_encode(['success' => true, 'bookings' => $bookings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Initialize amenity details (make sure case & spacing match keys)
$amenity = isset($_GET['reserve']) ? urldecode($_GET['reserve']) : null;

// Define rates
$amenityRates = [
    "Swimming Pool" => [
        "homeowner" => [
            "day" => "₱100.00 / per person",
            "night" => "₱200.00 / per person",
            "whole" => "₱300.00 / per person"
        ],
        "visitor" => [
            "day" => "₱200.00 / per person",
            "night" => "₱300.00 / per person",
            "whole" => "₱500.00 / per person"
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
            "night" => "₱300.00 / per person",
            "whole" => "₱500.00 / per person"
        ],
        "visitor" => [
            "day" => "₱300.00 / per person",
            "night" => "₱400.00 / per person",
            "whole" => "₱700.00 / per person"
        ]
    ],
    "Gazebo" => [
        "homeowner" => [
            "day" => "₱1,000.00",
            "night" => "₱1,500.00",
            "whole" => "₱2,500.00"
        ],
        "visitor" => [
            "day" => "₱2,000.00",
            "night" => "₱3,000.00",
            "whole" => "₱5,000.00"
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

        .file-drop-area.required-highlight {
            border-color: #dc3545;
            background-color: #fff5f5;
        }

        .file-drop-area.required-highlight .cloud-icon {
            color: #dc3545;
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
            padding: 12px 20px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 60px;
            display: flex;
            align-items: center;
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

        .loading {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-select:disabled {
            background-color: #f8f9fa;
            opacity: 0.8;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .calendar-view {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-nav-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 5px 10px;
            color: #198754;
        }

        .calendar-nav-btn:hover {
            background: #e9ecef;
            border-radius: 4px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            font-size: 12px;
            padding: 8px;
            color: #6c757d;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            background: white;
        }

        .calendar-day:hover:not(.disabled):not(.booked) {
            background: #e7f5ea;
            border-color: #198754;
        }

        .calendar-day.today {
            border: 2px solid #198754;
            font-weight: 600;
        }

        .calendar-day.booked {
            background: #f8d7da;
            color: #721c24;
            cursor: not-allowed;
            position: relative;
        }

        .calendar-day.booked::after {
            content: "●";
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: 8px;
            color: #dc3545;
        }

        .calendar-day.selected {
            background: #198754;
            color: white;
            border-color: #198754;
        }

        .calendar-day.disabled {
            background: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
        }

        .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }

        .calendar-day.partial-booked {
            background-color: #fff3cd;
            border-color: #ffc107;
            cursor: pointer;
            position: relative;
        }

        .calendar-day.partial-booked:hover {
            background-color: #ffeaa7;
            border-color: #ffb300;
        }

        .partial-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 10px;
            color: #856404;
        }

        #dateMessage {
            margin-top: 10px;
        }

        .search-select-input {
            width: 100%;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .search-select-input:focus {
            border-color: #198754;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        .search-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }

        .search-select-dropdown.show {
            display: block;
        }

        .search-select-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }

        .search-select-option:hover {
            background-color: #f8f9fa;
        }

        .search-select-option.selected {
            background-color: #e7f5ea;
            color: #198754;
            font-weight: 500;
        }

        .search-select-option.no-results {
            padding: 1rem;
            text-align: center;
            color: #6c757d;
            cursor: default;
        }

        .search-select-option.no-results:hover {
            background-color: transparent;
        }

        .search-select-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            display: none;
        }

        .search-select-clear.show {
            display: block;
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
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?php echo htmlspecialchars($username); ?></span>
                    <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-5"
                        style="height: 40px; width: 40px; color: #aaa;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>" style="width: 40px; height: 40px;
                        object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 32px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="../admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
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
                            <li><a href="../violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="../entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
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
                            <li><a href="../payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="../billing.php" class="nav-link px-2">Billing</a></li>
                            <li><a href="../invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold w-100">Amenity Booking Management</h5>
                </div>
                <div class=" p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">
                        <?php echo htmlspecialchars($amenity); ?>
                    </span>
                    <a href="add_booking.php?amenity=<?php echo htmlspecialchars($amenity); ?>"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="d-flex justify-content-center align-items-center my-3">
                    <span class="text-uppercase text-center fw-medium"
                        style="font-family: 'Libre Baskerville', serif; font-size: 36px; letter-spacing: 10px;">
                        <?php echo htmlspecialchars($amenity); ?>
                        RESERVATION
                    </span>
                </div>
                <div class="p-3">
                    <form action="process_booking.php?reserve=<?php echo htmlspecialchars($amenity); ?>" method="POST"
                        enctype="multipart/form-data" id="reservationForm">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <div class="row">
                                    <div class="col-6">
                                        <!-- User Type -->
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="userType" name="userType" required>
                                                <option value="" selected disabled>Select User Type</option>
                                                <option value="homeowner">Homeowner/Resident</option>
                                                <option value="visitor">Visitor</option>
                                            </select>
                                            <label for="userType">User Type<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <!-- User ID -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control search-select-input"
                                                id="userIdSearch" name="search" disabled autocomplete="off">
                                            <label for="search">Search User</label>
                                            <i class="bi bi-x-circle search-select-clear" id="searchClear"></i>
                                            <div class="search-select-dropdown" id="userIdDropdown"></div>
                                            <input type="hidden" id="userId" name="userId" required>
                                            <div class="loading d-none" id="loadingIndicator">
                                                <i class="bi bi-arrow-clockwise"></i> Loading available IDs...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <!-- First Name -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="firstName" name="firstName"
                                                placeholder="First Name" required disabled>
                                            <label for="firstName">First Name<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <!-- Middle Name -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="middleName" name="middleName"
                                                placeholder="Middle Name" disabled>
                                            <label for="middleName">Middle Name</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <!-- Last Name -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="lastName" name="lastName"
                                                placeholder="Last Name" required disabled>
                                            <label for="lastName">Last Name<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Contact Number -->
                                <div class="form-floating mb-3">
                                    <input type="tel" name="cellphone_number" class="form-control" pattern="[0-9]+"
                                        maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        placeholder="e.g., 09171234567" required disabled />
                                    <label class="form-label">Cellphone Number<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>
                                <!-- Email Address -->
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="emailAddress" name="emailAddress"
                                        placeholder="name@example.com" required disabled>
                                    <label for="emailAddress">Email Address<small
                                            class="fw-bold text-danger">*</small></label>
                                </div>
                                <!-- Date -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Select Date<small
                                            class="fw-bold text-danger">*</small></label>
                                    <!-- Calendar View -->
                                    <div class="calendar-view">
                                        <div class="calendar-header">
                                            <button type="button" class="calendar-nav-btn" id="prevMonth">
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <div class="fw-bold" id="currentMonth">Loading...</div>
                                            <button type="button" class="calendar-nav-btn" id="nextMonth">
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                        <div class="calendar-grid" id="calendarGrid">
                                            <!-- Calendar will be generated by JavaScript -->
                                        </div>
                                        <div class="d-flex gap-3 mt-2 justify-content-center flex-wrap">
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-success me-1">●</span> Available
                                            </small>
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-warning me-1">●</span> Partial
                                            </small>
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-danger me-1">●</span> Fully Booked
                                            </small>
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-secondary me-1">●</span> Past
                                            </small>
                                        </div>
                                        <!-- Add this div after your calendar for showing availability messages -->
                                        <div id="dateMessage"></div>
                                    </div>
                                    <!-- Hidden Date Input -->
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="reservationDate"
                                            name="reservationDate" readonly required>
                                        <label for="reservationDate">Selected Date<small class="fw-bold
                                                text-danger">*</small></label>
                                    </div>
                                </div>
                                <?php if ($amenity !== "Gazebo" && $amenity !== "Clubhouse"): ?>
                                    <!-- Guests -->
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="guests" name="guests" min="0">
                                        <label for="guests">Guests<small class="fw-bold text-danger">*</small></label>
                                    </div>
                                <?php endif; ?>
                                <?php if ($amenity === "Swimming Pool"): ?>
                                    <!-- Exclusive Booking -->
                                    <div class="form-floating">
                                        <select class="form-select" id="exclusiveBooking" name="exclusiveBooking" required>
                                            <option value="no" selected>No</option>
                                            <option value="yes">Yes</option>
                                        </select>
                                        <label for="exclusiveBooking">Is this an exclusive booking?<small
                                                class="fw-bold text-danger">*</small></label>
                                    </div>
                                    <div class="form-text text-muted mb-3">
                                        <small><i class="bi bi-info-circle me-1"></i>Exclusive booking adds ₱100.00 per
                                            head</small>
                                    </div>
                                <?php endif; ?>
                                <!-- Add-Ons -->
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="chairs" name="chairs" min="0"
                                                value="0" max="40">
                                            <label for="chairs">Chairs <small>(₱12.00/pc)</small></label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="tables" name="tables" min="0"
                                                value="0" max="15">
                                            <label for="tables">Tables <small>(₱20.00/pc)</small></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Vehicle -->
                                <div class="row">
                                    <label class="form-label fw-bold">Vehicle</label>
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="cars" name="cars" min="0"
                                                value="0" max="3">
                                            <label for="cars">No. of Vehicle/s</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="plates" name="plates">
                                            <label for="plates">Vehicle Plate Number/s</label>
                                        </div>
                                        <div class="form-text text-muted">
                                            <small><i class="bi bi-info-circle me-1"></i>If more than 1 vehicle,
                                                separate plate numbers by comma (e.g., ABC-1234,
                                                XYZ-5678)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <!-- Rates -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rates<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div id="ratesContainer" class="custom-radio-container">
                                        <?php if ($currentRates): ?>
                                            <div class="custom-radio-option selected" data-value="day"
                                                onclick="selectRate(this, 'day')">
                                                <div>
                                                    <div><strong id="dayRate">Day • <?= $currentRates['day'] ?></strong>
                                                    </div>
                                                    <small class="text-muted">9:00 AM - 5:00 PM</small>
                                                </div>
                                                <div class="custom-radio-circle selected"></div>
                                            </div>
                                            <div class="custom-radio-option <?= $amenity === 'Clubhouse' ? 'd-none' : '' ?>"
                                                data-value="night" onclick="selectRate(this, 'night')">
                                                <div>
                                                    <div><strong id="nightRate">Night •
                                                            <?= $currentRates['night'] ?></strong></div>
                                                    <small class="text-muted">5:00 PM - 10:00 PM</small>
                                                </div>
                                                <div class="custom-radio-circle"></div>
                                            </div>
                                            <div class="custom-radio-option <?= $amenity === 'Clubhouse' ? 'd-none' : '' ?>"
                                                data-value="whole" onclick="selectRate(this, 'whole')">
                                                <div>
                                                    <div><strong id="wholeRate">Whole Day •
                                                            <?= $currentRates['whole'] ?></strong></div>
                                                    <small class="text-muted">9:00 AM - 10:00 PM</small>
                                                </div>
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
                                <!-- Payment Information -->
                                <div id="paymentInfo" class="payment-info p-3 rounded mb-4">
                                    <!-- Default Bank Info -->
                                    <div id="bankInfo">
                                        <h6 class="fw-bold mb-3">Payment Account</h6>
                                        <div class="mb-2"><small>EastWest Bank</small><br></div>
                                        <div class="mb-2"><small>Neopolitan Sitio Seville</small></div>
                                        <div class="mb-2"><small>Account Number: 20049887271</small></div>
                                        <div class="small fw-bold">
                                            Please settle payment as soon as possible to secure your slot. We
                                            strictly
                                            enforce payment first before we begin with your schedule/session.
                                            Failure to do so will result in cancellation of your reservation.
                                        </div>
                                    </div>
                                    <!-- Cash Info -->
                                    <div id="cashInfo" class="d-none">
                                        <h6 class="fw-bold mb-3">Payment Method: Cash</h6>
                                        <div class="small fw-bold">
                                            Please proceed to the clubhouse office at Neopolitan Sitio Seville to
                                            pay in
                                            cash.
                                            Make sure to settle your payment as soon as possible to confirm your
                                            booking.
                                        </div>
                                    </div>
                                </div>
                                <!-- Reference Number -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="referenceNumber" name="referenceNumber"
                                        placeholder="Reference Number" required>
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
                                <!-- Change (Only for Cash Payment) -->
                                <div class="mb-3 d-none" id="changeContainer">
                                    <label for="change" class="form-label">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="text" class="form-control" id="change" name="change"
                                            placeholder="0.00" readonly>
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
                                            Supported formats: JPEG, PNG, GIF, PDF
                                        </div>
                                        <input type="file" id="fileInput" name="proofOfPayment" class="d-none"
                                            accept=".jpeg,.jpg,.png,.gif,.pdf" required>
                                    </div>
                                    <div id="filePreview" class="mt-2"></div>
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
            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content text-center">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold">Confirm Reservation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                            <p class="mb-2"><b>Are you sure?</b></p>
                            <p class="mb-3">Please confirm your amenity reservation details before proceeding.</p>
                            <div class="alert alert-info text-start mb-3">
                                <div class="row">
                                    <div class="col-4"><strong>Amenity:</strong></div>
                                    <div class="col-8" id="confirmAmenity">-</div>
                                </div>
                                <div class="row">
                                    <div class="col-4"><strong>Date:</strong></div>
                                    <div class="col-8" id="confirmDate">-</div>
                                </div>
                                <div class="row">
                                    <div class="col-4"><strong>Name:</strong></div>
                                    <div class="col-8" id="confirmName">-</div>
                                </div>
                                <div class="row">
                                    <div class="col-4"><strong>Total:</strong></div>
                                    <div class="col-8" id="confirmTotal">-</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-primary" id="confirmProceed">Confirm &
                                    Submit</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Success Modal -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content text-center">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fw-bold">Booking Confirmation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <i class="bi bi-check2-circle text-success" style="font-size: 64px;"></i>
                            <p class="mb-2"><b>Success!</b></p>
                            <p class="mb-1">Your reservation has been successfully submitted.</p>
                            <p class="mb-3"><strong>Reservation Code: <span class="text-primary"
                                        id="reservationCode"></span></strong></p>
                            <div class="alert alert-info text-start">
                                <small>
                                    <i class="bi bi-info-circle me-2"></i>
                                    Please keep your reservation code for future reference. You will receive a
                                    confirmation email shortly.
                                </small>
                            </div>
                            <button type="button" class="btn btn-success" id="doneButton">Done</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Error Modal -->
            <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content text-center">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Booking Error</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger text-start">
                                <small id="errorMessage">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Please check your information and try again.
                                </small>
                            </div>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================
        // CALENDAR & BOOKING DATES FUNCTIONALITY
        // ============================================
        let bookedDates = {}; // Changed to object: { "2025-08-30": { day: true, night: false }, ... }
        let currentDate = new Date();
        let selectedDate = null;
        const amenity = "<?php echo htmlspecialchars($amenity); ?>";

        // Helper function to format date without timezone issues
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        async function fetchBookedDates() {
            console.log('Fetching booked dates for amenity:', amenity);

            if (!amenity) {
                console.error('Amenity is not defined!');
                return;
            }

            try {
                const currentPage = window.location.pathname.split('/').pop() || 'reserve_booking.php';
                const url = `${currentPage}?action=get_booked_dates&amenity=${encodeURIComponent(amenity)}`;
                console.log('Fetch URL:', url);

                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Fetched data:', data);

                if (data.success) {
                    bookedDates = {};
                    (data.bookings || []).forEach(booking => {
                        const dateKey = booking.date;
                        const rate = booking.rate;

                        if (!bookedDates[dateKey]) {
                            bookedDates[dateKey] = { day: false, night: false };
                        }
                        bookedDates[dateKey][rate] = true;
                    });

                    console.log('Processed booked dates:', bookedDates);
                    renderCalendar();
                } else {
                    console.error('API returned error:', data.error);
                    renderCalendar();
                }
            } catch (error) {
                console.error('Error fetching booked dates:', error);
                renderCalendar();
            }
        }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthDisplay = document.getElementById('currentMonth');

            if (!grid || !monthDisplay) {
                console.error('Calendar elements not found');
                return;
            }

            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            monthDisplay.textContent = new Date(year, month).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });

            grid.innerHTML = '';

            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                grid.appendChild(emptyDay);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.textContent = day;

                const cellDate = new Date(year, month, day);
                cellDate.setHours(0, 0, 0, 0);
                const dateString = formatDate(cellDate);

                if (cellDate < today) {
                    dayElement.classList.add('disabled');
                    dayElement.title = 'Past date';
                } else {
                    const booking = bookedDates[dateString];

                    if (booking) {
                        const dayBooked = booking.day;
                        const nightBooked = booking.night;

                        if (dayBooked && nightBooked) {
                            dayElement.classList.add('booked');
                            dayElement.title = 'Fully booked (Day & Night)';
                        } else if (dayBooked || nightBooked) {
                            dayElement.classList.add('partial-booked');
                            const available = dayBooked ? 'Night' : 'Day';
                            dayElement.title = `Partially booked - ${available} available`;

                            const indicator = document.createElement('span');
                            indicator.className = 'partial-indicator';
                            indicator.textContent = '◐';
                            dayElement.appendChild(indicator);
                        }
                    }

                    if (cellDate.getTime() === today.getTime()) {
                        dayElement.classList.add('today');
                    }
                }

                if (selectedDate && selectedDate === dateString) {
                    dayElement.classList.add('selected');
                }

                if (!dayElement.classList.contains('disabled') && !dayElement.classList.contains('booked')) {
                    dayElement.addEventListener('click', () => selectDate(dateString, dayElement));
                }

                grid.appendChild(dayElement);
            }
        }

        function showErrorModal(message) {
            const errorMessageElement = document.getElementById('errorMessage');
            const errorModalElement = document.getElementById('errorModal');

            if (errorMessageElement && errorModalElement) {
                errorMessageElement.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${message}`;
                const errorModal = new bootstrap.Modal(errorModalElement);
                errorModal.show();
            }
        }

        function selectDate(dateString, element) {
            const selectedRateType = document.getElementById('selectedRate')?.value || 'day';
            const booking = bookedDates[dateString];

            if (booking && booking[selectedRateType]) {
                showErrorModal(`This date is already booked for <strong>${selectedRateType}</strong>. Please select the other rate or choose a different date.`);
                return;
            }

            selectedDate = dateString;
            const dateInput = document.getElementById('reservationDate');
            if (dateInput) {
                dateInput.value = dateString;
                dateInput.classList.remove('border-danger', 'is-invalid');
                const existingError = dateInput.parentNode.querySelector('.invalid-feedback');
                if (existingError) {
                    existingError.remove();
                }
            }

            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });

            element.classList.add('selected');

            if (booking && (booking.day || booking.night)) {
                const availableRate = booking.day ? 'night' : 'day';
                showRateAvailabilityMessage(dateString, availableRate);
            }
        }

        function showRateAvailabilityMessage(date, availableRate) {
            const messageDiv = document.getElementById('dateMessage');
            if (messageDiv) {
                messageDiv.innerHTML = `<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Note: For ${date}, only <strong>${availableRate}</strong> rate is available.</div>`;

                const rateOption = document.querySelector(`[data-value="${availableRate}"]`);
                if (rateOption) {
                    selectRate(rateOption, availableRate);
                }

                setTimeout(() => {
                    messageDiv.innerHTML = '';
                }, 5000);
            }
        }

        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');

        if (prevMonthBtn) {
            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });
        }

        if (nextMonthBtn) {
            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });
        }

        // ============================================
        // FILE UPLOAD FUNCTIONALITY
        // ============================================
        const fileDropArea = document.getElementById('fileDropArea');
        const fileInput = document.getElementById('fileInput');
        const browseLink = document.getElementById('browseLink');
        const filePreview = document.getElementById('filePreview');

        if (fileDropArea && fileInput && browseLink && filePreview) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileDropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                fileDropArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                fileDropArea.addEventListener(eventName, unhighlight, false);
            });

            fileDropArea.addEventListener('drop', handleDrop, false);

            browseLink.addEventListener('click', (e) => {
                e.preventDefault();
                fileInput.click();
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });
        }

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

                const fileDropArea = document.getElementById('fileDropArea');
                if (fileDropArea) {
                    fileDropArea.classList.remove('required-highlight');
                }
            }
        }

        function clearFile() {
            if (fileInput) fileInput.value = '';
            if (filePreview) filePreview.innerHTML = '';
            validateFileUpload();
        }

        // ============================================
        // SEARCHABLE DROPDOWN FUNCTIONALITY
        // ============================================
        let userOptions = [];
        let selectedUserId = null;

        function initSearchableDropdown() {
            const searchInput = document.getElementById('userIdSearch');
            const dropdown = document.getElementById('userIdDropdown');
            const clearBtn = document.getElementById('searchClear');
            const hiddenInput = document.getElementById('userId');

            if (!searchInput || !dropdown || !clearBtn || !hiddenInput) return;

            searchInput.addEventListener('focus', function () {
                if (userOptions.length > 0) {
                    renderDropdownOptions();
                    dropdown.classList.add('show');
                }
            });

            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();

                if (searchTerm) {
                    clearBtn.classList.add('show');
                } else {
                    clearBtn.classList.remove('show');
                }

                renderDropdownOptions(searchTerm);
                dropdown.classList.add('show');
            });

            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                hiddenInput.value = '';
                selectedUserId = null;
                clearBtn.classList.remove('show');
                clearUserFields();
                renderDropdownOptions();
                searchInput.focus();
            });

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }

        function renderDropdownOptions(searchTerm = '') {
            const dropdown = document.getElementById('userIdDropdown');
            if (!dropdown) return;

            dropdown.innerHTML = '';

            const filteredOptions = userOptions.filter(option => {
                const searchText = `${option.id} ${option.name}`.toLowerCase();
                return searchText.includes(searchTerm.toLowerCase());
            });

            if (filteredOptions.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'search-select-option no-results';
                noResults.innerHTML = '<i class="bi bi-search"></i> No results found';
                dropdown.appendChild(noResults);
                return;
            }

            filteredOptions.forEach(option => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'search-select-option';
                optionDiv.textContent = `${option.id} - ${option.name}`;
                optionDiv.dataset.value = option.id;

                if (option.id === selectedUserId) {
                    optionDiv.classList.add('selected');
                }

                optionDiv.addEventListener('click', function () {
                    selectUser(option);
                });

                dropdown.appendChild(optionDiv);
            });
        }

        function selectUser(option) {
            const searchInput = document.getElementById('userIdSearch');
            const dropdown = document.getElementById('userIdDropdown');
            const hiddenInput = document.getElementById('userId');
            const clearBtn = document.getElementById('searchClear');

            if (!searchInput || !dropdown || !hiddenInput) return;

            selectedUserId = option.id;
            searchInput.value = `${option.id} - ${option.name}`;
            hiddenInput.value = option.id;
            clearBtn.classList.add('show');
            dropdown.classList.remove('show');

            // Remove error styling when user is selected
            searchInput.classList.remove('border-danger', 'is-invalid');
            const existingError = searchInput.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }

            if (userData[option.id]) {
                populateUserFields(userData[option.id]);
                searchInput.style.borderColor = '#198754';
                searchInput.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
            }
        }

        function resetSearchableDropdown() {
            const searchInput = document.getElementById('userIdSearch');
            const dropdown = document.getElementById('userIdDropdown');
            const hiddenInput = document.getElementById('userId');
            const clearBtn = document.getElementById('searchClear');

            if (searchInput) {
                searchInput.value = '';
                searchInput.style.borderColor = '';
                searchInput.style.boxShadow = '';
            }
            if (dropdown) dropdown.innerHTML = '';
            if (hiddenInput) hiddenInput.value = '';
            if (clearBtn) clearBtn.classList.remove('show');

            userOptions = [];
            selectedUserId = null;
        }

        // ============================================
        // RATES & USER TYPE FUNCTIONALITY
        // ============================================
        const amenityRates = <?php echo json_encode($amenityRates); ?>;
        const currentAmenity = "<?php echo $amenity; ?>";
        let userData = {};

        const chairPrice = 12;
        const tablePrice = 20;

        const userTypeSelect = document.getElementById('userType');
        if (userTypeSelect) {
            userTypeSelect.addEventListener('change', async function () {
                const userType = this.value;

                let prevSelectedRate = document.getElementById('selectedRate')?.value || 'day';

                if (currentAmenity === "Clubhouse") {
                    prevSelectedRate = 'day';
                }

                const searchInput = document.getElementById('userIdSearch');
                const loadingIndicator = document.getElementById('loadingIndicator');

                userData = {};
                clearUserFields();
                resetSearchableDropdown();

                if (searchInput) {
                    searchInput.disabled = true;
                    if (loadingIndicator) {
                        loadingIndicator.classList.remove('d-none');
                    }

                    if (userType === 'homeowner') {
                        try {
                            const response = await fetch(`?action=get_households`);
                            const result = await response.json();

                            if (result.success) {
                                userOptions = result.data.map(household => ({
                                    id: household.household_id,
                                    name: household.name
                                }));

                                result.data.forEach(household => {
                                    userData[household.household_id] = {
                                        first_name: household.first_name,
                                        middle_name: household.middle_name,
                                        last_name: household.last_name,
                                        email: household.email,
                                        cellphone_number: household.cellphone_number || ''
                                    };
                                });

                                searchInput.placeholder = 'Type to search residents...';
                            } else {
                                console.error('Error:', result.error);
                                searchInput.placeholder = 'Error loading data';
                            }
                        } catch (error) {
                            console.error('Error fetching household data:', error);
                            searchInput.placeholder = 'Error loading data';
                        }
                    } else if (userType === 'visitor') {
                        try {
                            const response = await fetch(`?action=get_visitors`);
                            const result = await response.json();

                            if (result.success) {
                                userOptions = result.data.map(visitor => ({
                                    id: visitor.visitor_id,
                                    name: visitor.name
                                }));

                                result.data.forEach(visitor => {
                                    userData[visitor.visitor_id] = {
                                        first_name: visitor.first_name,
                                        middle_name: visitor.middle_name,
                                        last_name: visitor.last_name,
                                        email: visitor.email,
                                        cellphone_number: visitor.cellphone_number || ''
                                    };
                                });

                                searchInput.placeholder = 'Type to search visitors...';
                            } else {
                                console.error('Error:', result.error);
                                searchInput.placeholder = 'Error loading data';
                            }
                        } catch (error) {
                            console.error('Error fetching visitor data:', error);
                            searchInput.placeholder = 'Error loading data';
                        }
                    }

                    if (loadingIndicator) {
                        loadingIndicator.classList.add('d-none');
                    }
                    searchInput.disabled = false;
                    searchInput.classList.add('fade-in');

                    setTimeout(() => {
                        searchInput.classList.remove('fade-in');
                    }, 300);
                }

                if (amenityRates[currentAmenity] && amenityRates[currentAmenity][userType]) {
                    const rates = amenityRates[currentAmenity][userType];

                    const dayRateElement = document.getElementById('dayRate');
                    if (dayRateElement) {
                        dayRateElement.innerHTML = `Day • ${rates.day}`;
                    }

                    const nightRateElement = document.getElementById('nightRate');
                    if (nightRateElement) {
                        nightRateElement.innerHTML = `Night • ${rates.night}`;
                    }

                    const wholeRateElement = document.getElementById('wholeRate');
                    if (wholeRateElement) {
                        wholeRateElement.innerHTML = `Whole Day • ${rates.whole}`;
                    }

                    const container = document.getElementById('ratesContainer');
                    if (container) {
                        const selectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);

                        const selectedRateInput = document.getElementById('selectedRate');
                        if (selectedRateInput) {
                            if (selectedOption && !selectedOption.classList.contains('d-none')) {
                                selectedRateInput.value = prevSelectedRate;
                            } else {
                                selectedRateInput.value = 'day';
                                prevSelectedRate = 'day';
                            }
                        }

                        container.querySelectorAll('.custom-radio-option').forEach(el => {
                            el.classList.remove('selected');
                            const circle = el.querySelector('.custom-radio-circle');
                            if (circle) circle.classList.remove('selected');
                        });

                        const finalSelectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);
                        if (finalSelectedOption && !finalSelectedOption.classList.contains('d-none')) {
                            finalSelectedOption.classList.add('selected');
                            const circle = finalSelectedOption.querySelector('.custom-radio-circle');
                            if (circle) circle.classList.add('selected');
                        }
                    }
                }

                calculateTotal();
            });
        }

        function populateUserFields(user) {
            const fields = [
                { id: 'firstName', value: user.first_name || '' },
                { id: 'middleName', value: user.middle_name || '' },
                { id: 'lastName', value: user.last_name || '' },
                { id: 'emailAddress', value: user.email || '' },
                { name: 'cellphone_number', value: user.cellphone_number || '' }
            ];

            fields.forEach(field => {
                const element = field.id ? document.getElementById(field.id) : document.querySelector(`[name="${field.name}"]`);
                if (element) {
                    element.disabled = false;
                    element.value = field.value;
                    element.readOnly = true;
                    element.setAttribute('readonly', 'readonly');
                }
            });
        }

        function clearUserFields() {
            const fieldIds = ['firstName', 'middleName', 'lastName', 'emailAddress'];
            const fieldNames = ['cellphone_number'];

            fieldIds.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.value = '';
                    element.readOnly = false;
                    element.removeAttribute('readonly');
                    element.disabled = true;
                }
            });

            fieldNames.forEach(fieldName => {
                const element = document.querySelector(`[name="${fieldName}"]`);
                if (element) {
                    element.value = '';
                    element.readOnly = false;
                    element.removeAttribute('readonly');
                    element.disabled = true;
                }
            });
        }

        // ============================================
        // RATE & PAYMENT SELECTION
        // ============================================
        function selectRate(option, value) {
            if (selectedDate && bookedDates[selectedDate] && bookedDates[selectedDate][value]) {
                showErrorModal(`The <strong>${value}</strong> rate is already booked for <strong>${selectedDate}</strong>. Please select the other rate or choose a different date.`);
                return;
            }

            const container = document.getElementById('ratesContainer');
            if (container) {
                container.querySelectorAll('.custom-radio-option').forEach(el => {
                    el.classList.remove('selected');
                    const circle = el.querySelector('.custom-radio-circle');
                    if (circle) circle.classList.remove('selected');
                });
            }

            option.classList.add('selected');
            const circle = option.querySelector('.custom-radio-circle');
            if (circle) circle.classList.add('selected');

            const selectedRateInput = document.getElementById('selectedRate');
            if (selectedRateInput) {
                selectedRateInput.value = value;
            }

            calculateTotal();
        }

        function selectPayment(option, value) {
            const container = option.closest('.custom-radio-container');
            if (container) {
                container.querySelectorAll('.custom-radio-option').forEach(el => {
                    el.classList.remove('selected');
                    const circle = el.querySelector('.custom-radio-circle');
                    if (circle) circle.classList.remove('selected');
                });
            }

            option.classList.add('selected');
            const circle = option.querySelector('.custom-radio-circle');
            if (circle) circle.classList.add('selected');

            const selectedPaymentInput = document.getElementById('selectedPayment');
            if (selectedPaymentInput) {
                selectedPaymentInput.value = value;
            }

            const bankInfo = document.getElementById("bankInfo");
            const cashInfo = document.getElementById("cashInfo");
            const referenceNumber = document.getElementById("referenceNumber");
            const fileInputElement = document.getElementById("fileInput");
            const changeContainer = document.getElementById("changeContainer");

            if (value === "cash") {
                if (cashInfo) cashInfo.classList.remove("d-none");
                if (bankInfo) bankInfo.classList.add("d-none");
                if (changeContainer) changeContainer.classList.remove("d-none");

                if (referenceNumber) {
                    referenceNumber.closest(".form-floating")?.classList.add("d-none");
                    referenceNumber.removeAttribute("required");
                }
                if (fileInputElement) {
                    fileInputElement.closest("#fileDropArea")?.classList.add("d-none");
                    fileInputElement.removeAttribute("required");
                }

                calculateChange();
            } else {
                if (bankInfo) bankInfo.classList.remove("d-none");
                if (cashInfo) cashInfo.classList.add("d-none");
                if (changeContainer) changeContainer.classList.add("d-none");

                if (referenceNumber) {
                    referenceNumber.closest(".form-floating")?.classList.remove("d-none");
                    referenceNumber.setAttribute("required", "required");
                }
                if (fileInputElement) {
                    fileInputElement.closest("#fileDropArea")?.classList.remove("d-none");
                    fileInputElement.setAttribute("required", "required");
                }
            }

            validateFileUpload();
        }

        // ============================================
        // TOTAL CALCULATION
        // ============================================
        function extractPrice(priceStr) {
            const match = priceStr.replace(/,/g, '').match(/[\d.]+/);
            return match ? parseFloat(match[0]) : 0;
        }

        function calculateTotal() {
            const userType = document.getElementById("userType")?.value;
            const rateType = document.getElementById("selectedRate")?.value;
            const exclusiveBooking = document.getElementById("exclusiveBooking")?.value;

            let guests = parseInt(document.getElementById("guests")?.value || 0);
            let chairs = parseInt(document.getElementById("chairs")?.value || 0);
            let tables = parseInt(document.getElementById("tables")?.value || 0);

            let rateStr = amenityRates[currentAmenity]?.[userType]?.[rateType] || "₱0";
            let rateValue = extractPrice(rateStr);

            let rateTotal = 0;

            // Calculate base rate amount
            if (rateStr.includes("per person")) {
                // Apply exclusive booking surcharge (₱100 per guest) ONLY for Swimming Pool
                if (exclusiveBooking === "yes" && currentAmenity === "Swimming Pool") {
                    rateTotal = (rateValue + 100) * guests; // Add ₱100 per guest
                } else {
                    rateTotal = rateValue * guests;
                }
            } else {
                rateTotal = rateValue;
            }

            // Calculate add-ons (NOT subject to exclusive booking fee)
            let addOnsTotal = (chairs * chairPrice) + (tables * tablePrice);

            // Final total = rate (with exclusive fee if applicable) + add-ons (no exclusive fee)
            let total = rateTotal + addOnsTotal;

            const formattedTotal = total.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const totalElement = document.getElementById("total");
            if (totalElement) {
                totalElement.value = formattedTotal;
            }

            // Recalculate change if payment method is cash
            calculateChange();
        }

        function calculateChange() {
            const totalField = document.getElementById("total");
            const amountPaidField = document.getElementById("amountPaid");
            const changeField = document.getElementById("change");
            const paymentMethod = document.getElementById("selectedPayment")?.value;

            if (!totalField || !amountPaidField || !changeField) return;

            if (paymentMethod !== 'cash') {
                changeField.value = '';
                return;
            }

            const totalValue = parseFloat(totalField.value.replace(/,/g, '')) || 0;
            const amountPaidValue = parseFloat(amountPaidField.value) || 0;

            const change = amountPaidValue - totalValue;

            if (change > 0) {
                changeField.value = change.toLocaleString("en-US", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } else if (change === 0) {
                changeField.value = '0.00';
            } else {
                changeField.value = '';
            }
        }

        // ============================================
        // AMOUNT PAID VALIDATION
        // ============================================
        function validateAmountPaid() {
            const totalField = document.getElementById("total");
            const amountPaidField = document.getElementById("amountPaid");
            const paymentMethod = document.getElementById("selectedPayment")?.value;

            if (!totalField || !amountPaidField) return true;

            const totalValue = parseFloat(totalField.value.replace(/,/g, '')) || 0;
            const amountPaidValue = parseFloat(amountPaidField.value) || 0;
            const minimumPayment = totalValue * 0.5;

            amountPaidField.classList.remove('border-danger', 'is-invalid');
            const existingFeedback = amountPaidField.parentNode.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }

            if (totalValue > 0 && amountPaidValue < minimumPayment) {
                amountPaidField.classList.add('border-danger', 'is-invalid');
                amountPaidField.setCustomValidity('Amount must be at least 50% of total');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Amount paid must be at least 50% of total (₱${minimumPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;

                amountPaidField.parentNode.parentNode.insertAdjacentElement('beforeend', errorDiv);
                return false;
            }

            if (paymentMethod === 'bank' && amountPaidValue > totalValue && totalValue > 0) {
                amountPaidField.classList.add('border-danger', 'is-invalid');
                amountPaidField.setCustomValidity('Amount cannot exceed total');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Amount paid cannot exceed total amount (₱${totalValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;

                amountPaidField.parentNode.parentNode.insertAdjacentElement('beforeend', errorDiv);
                return false;
            }

            amountPaidField.setCustomValidity('');
            return true;
        }

        function validateFileUpload() {
            const fileInput = document.getElementById('fileInput');
            const fileDropArea = document.getElementById('fileDropArea');
            const paymentMethod = document.getElementById('selectedPayment')?.value;

            if (!fileInput || !fileDropArea) return true;

            if (paymentMethod === 'bank') {
                if (!fileInput.files || fileInput.files.length === 0) {
                    fileDropArea.classList.add('required-highlight');
                    return false;
                } else {
                    fileDropArea.classList.remove('required-highlight');
                    return true;
                }
            } else {
                fileDropArea.classList.remove('required-highlight');
                return true;
            }
        }

        // ============================================
        // VEHICLE & PLATE NUMBER FUNCTIONALITY
        // ============================================
        function initializeVehicleField() {
            const carsField = document.getElementById('cars');
            const platesField = document.getElementById('plates');

            if (carsField && platesField) {
                function togglePlateField() {
                    const numberOfCars = parseInt(carsField.value) || 0;

                    if (numberOfCars === 0) {
                        platesField.disabled = true;
                        platesField.value = '';
                        platesField.style.backgroundColor = '#f8f9fa';
                        platesField.style.opacity = '0.6';
                        platesField.removeAttribute('required');

                        const instructionDiv = platesField.parentNode.nextElementSibling;
                        if (instructionDiv && instructionDiv.classList.contains('form-text')) {
                            instructionDiv.innerHTML = '<small class="text-muted"><i class="bi bi-info-circle me-1"></i>Enter number of vehicles first to enable plate number field</small>';
                        }
                    } else {
                        platesField.disabled = false;
                        platesField.style.backgroundColor = '';
                        platesField.style.opacity = '';
                        platesField.setAttribute('required', 'required');

                        const instructionDiv = platesField.parentNode.nextElementSibling;
                        if (instructionDiv && instructionDiv.classList.contains('form-text')) {
                            if (numberOfCars === 1) {
                                instructionDiv.innerHTML = '<small><i class="bi bi-info-circle me-1"></i>Enter the vehicle plate number</small>';
                            } else {
                                instructionDiv.innerHTML = '<small><i class="bi bi-info-circle me-1"></i>If more than 1 vehicle, separate plate numbers by comma (e.g., ABC-1234, XYZ-5678)</small>';
                            }
                        }
                    }
                }

                togglePlateField();
                carsField.addEventListener('input', togglePlateField);
                carsField.addEventListener('change', togglePlateField);
            }
        }

        // ============================================
        // INPUT MAX VALUE CONSTRAINTS
        // ============================================
        function initializeMaxValueConstraints() {
            const constraints = [
                { id: 'chairs', max: 40, name: 'Chairs' },
                { id: 'tables', max: 15, name: 'Tables' },
                { id: 'cars', max: 3, name: 'Vehicles' }
            ];

            constraints.forEach(constraint => {
                const field = document.getElementById(constraint.id);
                if (field) {
                    field.setAttribute('max', constraint.max);

                    field.addEventListener('input', function () {
                        const value = parseInt(this.value);

                        if (value > constraint.max) {
                            this.value = constraint.max;
                            showMaxValueNotification(constraint.name, constraint.max);
                        }

                        if (value < 0) {
                            this.value = 0;
                        }
                    });

                    field.addEventListener('change', function () {
                        const value = parseInt(this.value);

                        if (value > constraint.max) {
                            this.value = constraint.max;
                        }

                        if (value < 0 || isNaN(value)) {
                            this.value = 0;
                        }
                    });
                }
            });
        }

        function showMaxValueNotification(itemName, maxValue) {
            const existingNotification = document.querySelector('.max-value-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'alert alert-warning alert-dismissible fade show max-value-notification';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Maximum Limit:</strong> ${itemName} cannot exceed ${maxValue}.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 150);
                }
            }, 3000);
        }

        // ============================================
        // FORM SUBMISSION
        // ============================================
        // ============================================
        // FORM SUBMISSION
        // ============================================
        function initializeFormSubmission() {
            const form = document.getElementById('reservationForm');
            const submitButton = form?.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.addEventListener('click', function (e) {
                    e.preventDefault();

                    // Validate user selection FIRST
                    const userIdInput = document.getElementById('userId');
                    const userIdSearch = document.getElementById('userIdSearch');

                    if (!userIdInput || !userIdInput.value) {
                        // Add invalid styling to search input
                        if (userIdSearch) {
                            userIdSearch.classList.add('border-danger', 'is-invalid');

                            // Remove existing error if any
                            const existingError = userIdSearch.parentNode.querySelector('.invalid-feedback');
                            if (existingError) {
                                existingError.remove();
                            }

                            // Add compact error message
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            errorDiv.style.display = 'block';
                            errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Please select a user.';
                            userIdSearch.parentNode.appendChild(errorDiv);

                            // Scroll to field
                            userIdSearch.closest('.mb-3').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    // Validate date
                    const dateInput = document.getElementById('reservationDate');
                    if (!dateInput || !dateInput.value) {
                        dateInput.classList.add('border-danger', 'is-invalid');

                        const existingError = dateInput.parentNode.querySelector('.invalid-feedback');
                        if (existingError) {
                            existingError.remove();
                        }

                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.style.display = 'block';
                        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Please select a reservation date from the calendar.';
                        dateInput.parentNode.appendChild(errorDiv);

                        dateInput.closest('.mb-3').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }

                    // Validate amount paid
                    const amountValid = validateAmountPaid();
                    const fileValid = validateFileUpload();

                    if (!amountValid) {
                        const amountPaidField = document.getElementById("amountPaid");
                        if (amountPaidField) {
                            amountPaidField.focus();
                            amountPaidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return;
                    }

                    if (!fileValid) {
                        const fileDropArea = document.getElementById('fileDropArea');
                        if (fileDropArea) {
                            fileDropArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    showConfirmationModal();
                });
            }
        }

        function showConfirmationModal() {
            const date = document.getElementById('reservationDate')?.value;
            const firstName = document.getElementById('firstName')?.value;
            const lastName = document.getElementById('lastName')?.value;
            const total = document.getElementById('total')?.value;

            const confirmAmenity = document.getElementById('confirmAmenity');
            const confirmDate = document.getElementById('confirmDate');
            const confirmName = document.getElementById('confirmName');
            const confirmTotal = document.getElementById('confirmTotal');

            if (confirmAmenity) confirmAmenity.textContent = amenity;
            if (confirmDate) confirmDate.textContent = date || '-';
            if (confirmName) confirmName.textContent = (firstName + ' ' + lastName).trim() || '-';
            if (confirmTotal) confirmTotal.textContent = total ? '₱' + total : '-';

            const confirmModalElement = document.getElementById('confirmModal');
            if (confirmModalElement) {
                const confirmModal = new bootstrap.Modal(confirmModalElement);
                confirmModal.show();

                const confirmProceed = document.getElementById('confirmProceed');
                if (confirmProceed) {
                    confirmProceed.onclick = function () {
                        confirmModal.hide();
                        const form = document.getElementById('reservationForm');
                        if (form) form.submit();
                    };
                }
            }
        }

        // ============================================
        // MODAL HANDLERS
        // ============================================
        function initializeModals() {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('success') && urlParams.get('success') === '1') {
                const reservationCode = urlParams.get('code');
                const reservationCodeElement = document.getElementById('reservationCode');

                if (reservationCode && reservationCodeElement) {
                    reservationCodeElement.textContent = reservationCode;
                }

                const successModalElement = document.getElementById('successModal');
                if (successModalElement) {
                    const successModal = new bootstrap.Modal(successModalElement);
                    successModal.show();

                    const doneButton = document.getElementById('doneButton');
                    if (doneButton) {
                        doneButton.addEventListener('click', function () {
                            window.location.href = '../amenity_booking.php';
                        });
                    }

                    successModalElement.addEventListener('hidden.bs.modal', function () {
                        const amenityParam = urlParams.get('reserve');
                        window.history.replaceState({}, document.title, window.location.pathname + (amenityParam ? '?reserve=' + encodeURIComponent(amenityParam) : ''));
                    });
                }
            }

            if (urlParams.has('error') && urlParams.get('error') === '1') {
                const errorMessage = urlParams.get('message') || 'An unknown error occurred.';
                const errorMessageElement = document.getElementById('errorMessage');

                if (errorMessageElement) {
                    errorMessageElement.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + decodeURIComponent(errorMessage);
                }

                const errorModalElement = document.getElementById('errorModal');
                if (errorModalElement) {
                    const errorModal = new bootstrap.Modal(errorModalElement);
                    errorModal.show();

                    errorModalElement.addEventListener('hidden.bs.modal', function () {
                        const amenityParam = urlParams.get('reserve');
                        window.history.replaceState({}, document.title, window.location.pathname + (amenityParam ? '?reserve=' + encodeURIComponent(amenityParam) : ''));
                    });
                }
            }
        }

        // ============================================
        // INITIALIZATION
        // ============================================
        document.addEventListener("DOMContentLoaded", function () {
            console.log('Page loaded, initializing...');

            fetchBookedDates();
            initSearchableDropdown();

            const amountPaidField = document.getElementById("amountPaid");
            if (amountPaidField) {
                amountPaidField.addEventListener('input', function () {
                    calculateChange();
                    validateAmountPaid();
                });
                amountPaidField.addEventListener('change', function () {
                    calculateChange();
                    validateAmountPaid();
                });
                amountPaidField.addEventListener('blur', validateAmountPaid);
            }

            // Add event listeners for calculation fields
            ["guests", "chairs", "tables"].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener("input", calculateTotal);
                    el.addEventListener("change", calculateTotal);
                }
            });

            // Add event listener for exclusive booking
            const exclusiveBookingEl = document.getElementById("exclusiveBooking");
            if (exclusiveBookingEl) {
                exclusiveBookingEl.addEventListener("input", calculateTotal);
                exclusiveBookingEl.addEventListener("change", calculateTotal);
            }

            initializeFormSubmission();
            initializeVehicleField();
            initializeMaxValueConstraints();
            initializeModals();

            const today = new Date().toISOString().split("T")[0];
            const dateInput = document.getElementById("reservationDate");
            if (dateInput) {
                dateInput.min = today;
            }

            calculateTotal();

            console.log('Initialization complete');
        });
    </script>

</body>

</html>