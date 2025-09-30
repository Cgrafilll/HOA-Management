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

// Handle AJAX request to get booked dates for an amenity
if (isset($_GET['action']) && $_GET['action'] === 'get_booked_dates') {
    header('Content-Type: application/json');
    try {
        $amenity = $_GET['amenity'] ?? '';

        if (empty($amenity)) {
            echo json_encode(['success' => false, 'error' => 'Amenity required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT reservation_date FROM amenity_bookings WHERE amenity = ? AND status IN ('pending', 'partial', 'paid')");
        $stmt->bind_param("s", $amenity);
        $stmt->execute();
        $result = $stmt->get_result();

        $booked_dates = [];
        while ($row = $result->fetch_assoc()) {
            $booked_dates[] = date('Y-m-d', strtotime($row['reservation_date']));
        }

        echo json_encode(['success' => true, 'dates' => $booked_dates]);
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
                            <img src="<?php echo htmlspecialchars($photo); ?>"
                                style="width: 40px; height: 40px; object-fit: cover;">
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
                            <li><a href="../invoice.php" class="nav-link px-2">Invoices</a></li>
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
                        style="font-family: 'Libre Baskerville', serif; font-size: 36px; letter-spacing: 10px;"><?php echo htmlspecialchars($amenity); ?>
                        RESERVATION</span>
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
                                            <select class="form-select" id="userId" name="userId" disabled required>
                                                <option value="" selected disabled>First select user type</option>
                                            </select>
                                            <label for="userId" id="userIdLabel">Select ID<small
                                                    class="fw-bold text-danger">*</small></label>
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
                                                placeholder="First Name" required>
                                            <label for="firstName">First Name<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <!-- Middle Name -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="middleName" name="middleName"
                                                placeholder="Middle Name">
                                            <label for="middleName">Middle Name<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <!-- Last Name -->
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="lastName" name="lastName"
                                                placeholder="Last Name" required>
                                            <label for="lastName">Last Name<small
                                                    class="fw-bold text-danger">*</small></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Contact Number -->
                                <div class="form-floating mb-3">
                                    <input type="tel" name="cellphone_number" class="form-control" pattern="[0-9]+"
                                        maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        placeholder="e.g., 09171234567" readonly />
                                    <label class="form-label ">Cellphone Number</label>
                                </div>
                                <!-- Email Address -->
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="emailAddress" name="emailAddress"
                                        placeholder="name@example.com" required>
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
                                        <div class="d-flex gap-3 mt-2 justify-content-center">
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-success me-1">●</span> Available
                                            </small>
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-danger me-1">●</span> Booked
                                            </small>
                                            <small class="d-flex align-items-center">
                                                <span class="badge bg-secondary me-1">●</span> Past
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Hidden Date Input -->
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="reservationDate"
                                            name="reservationDate" readonly required>
                                        <label for="reservationDate">Selected Date<small
                                                class="fw-bold text-danger">*</small></label>
                                    </div>
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
                                <!-- Vehicle -->
                                <div class="row">
                                    <label class="form-label fw-bold">Vehicle</label>
                                    <div class="col-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="cars" name="cars" min="0"
                                                value="0">
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
                                                separate plate numbers by comma (e.g., ABC-1234, XYZ-5678)</small>
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
                            <i class="bi bi-x-circle text-danger" style="font-size: 64px;"></i>
                            <p class="mb-2"><b>Oops! Something went wrong</b></p>
                            <p class="mb-3">There was an error processing your reservation. Please try again.</p>
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
        let bookedDates = [];
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
                const url = `reservation_form.php?action=get_booked_dates&amenity=${encodeURIComponent(amenity)}`;
                console.log('Fetch URL:', url);

                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Fetched data:', data);

                if (data.success) {
                    bookedDates = data.dates || [];
                    console.log('Booked dates array:', bookedDates);
                    renderCalendar();
                } else {
                    console.error('API returned error:', data.error);
                    renderCalendar(); // Still render calendar even if fetch fails
                }
            } catch (error) {
                console.error('Error fetching booked dates:', error);
                renderCalendar(); // Still render calendar even if fetch fails
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

            // Set month display
            monthDisplay.textContent = new Date(year, month).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });

            // Clear grid
            grid.innerHTML = '';

            // Day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });

            // Get first day of month and total days
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Empty cells before first day
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                grid.appendChild(emptyDay);
            }

            // Days of month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.textContent = day;

                const cellDate = new Date(year, month, day);
                cellDate.setHours(0, 0, 0, 0);

                // Use timezone-safe date formatting
                const dateString = formatDate(cellDate);

                // Debug: Log date checking for first few days
                if (day <= 3) {
                    console.log(`Day ${day}: ${dateString}, Is booked:`, bookedDates.includes(dateString));
                }

                // Check if past date
                if (cellDate < today) {
                    dayElement.classList.add('disabled');
                    dayElement.title = 'Past date';
                }
                // Check if booked
                else if (bookedDates.includes(dateString)) {
                    dayElement.classList.add('booked');
                    dayElement.title = 'This date is already booked';
                    console.log(`Marking ${dateString} as booked`);
                }
                // Check if today
                else if (cellDate.getTime() === today.getTime()) {
                    dayElement.classList.add('today');
                    dayElement.title = 'Today';
                }

                // Check if selected
                if (selectedDate && selectedDate === dateString) {
                    dayElement.classList.add('selected');
                }

                // Click handler
                if (!dayElement.classList.contains('disabled') && !dayElement.classList.contains('booked')) {
                    dayElement.addEventListener('click', () => selectDate(dateString, dayElement));
                }

                grid.appendChild(dayElement);
            }
        }

        function selectDate(dateString, element) {
            selectedDate = dateString;
            const dateInput = document.getElementById('reservationDate');
            if (dateInput) {
                dateInput.value = dateString;
            }

            // Remove previous selection
            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });

            // Add selection to clicked element
            element.classList.add('selected');
        }

        // Calendar navigation
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
            }
        }

        function clearFile() {
            if (fileInput) fileInput.value = '';
            if (filePreview) filePreview.innerHTML = '';
        }

        // ============================================
        // RATES & USER TYPE FUNCTIONALITY
        // ============================================
        const amenityRates = <?php echo json_encode($amenityRates); ?>;
        const currentAmenity = "<?php echo $amenity; ?>";
        let userData = {};

        // Chair and table prices
        const chairPrice = 12;
        const tablePrice = 20;

        // Listen for userType change
        const userTypeSelect = document.getElementById('userType');
        if (userTypeSelect) {
            userTypeSelect.addEventListener('change', async function () {
                const userType = this.value;

                // Keep previously selected rate (default to 'day' if none)
                let prevSelectedRate = document.getElementById('selectedRate')?.value || 'day';

                // For Clubhouse, always default to 'day' since night option won't exist
                if (currentAmenity === "Clubhouse") {
                    prevSelectedRate = 'day';
                }

                // Dynamic Dropdown Functionality
                const userIdSelect = document.getElementById('userId');
                const idLabel = document.getElementById('userIdLabel');
                const loadingIndicator = document.getElementById('loadingIndicator');

                // Reset the ID dropdown and user data
                userData = {};
                clearUserFields();

                if (userIdSelect) {
                    userIdSelect.innerHTML = '<option value="">Loading...</option>';
                    userIdSelect.disabled = true;
                    if (loadingIndicator) {
                        loadingIndicator.classList.remove('d-none');
                    }

                    if (userType === 'homeowner') {
                        if (idLabel) {
                            idLabel.innerHTML = 'Resident ID<span class="text-danger">*</span>';
                        }

                        try {
                            const response = await fetch(`?action=get_households`);
                            const result = await response.json();

                            if (result.success) {
                                userIdSelect.innerHTML = '<option value="" selected disabled>Select Resident ID</option>';
                                result.data.forEach(household => {
                                    userData[household.household_id] = {
                                        first_name: household.first_name,
                                        middle_name: household.middle_name,
                                        last_name: household.last_name,
                                        email: household.email,
                                        cellphone_number: household.cellphone_number || ''
                                    };

                                    const option = document.createElement('option');
                                    option.value = household.household_id;
                                    option.textContent = `${household.household_id} - ${household.name}`;
                                    option.setAttribute('data-address', household.address);
                                    userIdSelect.appendChild(option);
                                });
                            } else {
                                userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                                console.error('Error:', result.error);
                            }
                        } catch (error) {
                            console.error('Error fetching household data:', error);
                            userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                        }
                    } else if (userType === 'visitor') {
                        if (idLabel) {
                            idLabel.innerHTML = 'Visitor ID<span class="text-danger">*</span>';
                        }

                        try {
                            const response = await fetch(`?action=get_visitors`);
                            const result = await response.json();

                            if (result.success) {
                                userIdSelect.innerHTML = '<option value="" selected disabled>Select Visitor ID</option>';
                                result.data.forEach(visitor => {
                                    userData[visitor.visitor_id] = {
                                        first_name: visitor.first_name,
                                        middle_name: visitor.middle_name,
                                        last_name: visitor.last_name,
                                        email: visitor.email,
                                        cellphone_number: visitor.cellphone_number || ''
                                    };

                                    const option = document.createElement('option');
                                    option.value = visitor.visitor_id;
                                    option.textContent = `${visitor.visitor_id} - ${visitor.name}`;
                                    option.setAttribute('data-purpose', visitor.purpose);
                                    userIdSelect.appendChild(option);
                                });
                            } else {
                                userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                                console.error('Error:', result.error);
                            }
                        } catch (error) {
                            console.error('Error fetching visitor data:', error);
                            userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                        }
                    }

                    if (loadingIndicator) {
                        loadingIndicator.classList.add('d-none');
                    }
                    userIdSelect.disabled = false;
                    userIdSelect.classList.add('fade-in');

                    setTimeout(() => {
                        userIdSelect.classList.remove('fade-in');
                    }, 300);
                }

                // Update rates display
                if (amenityRates[currentAmenity] && amenityRates[currentAmenity][userType]) {
                    const rates = amenityRates[currentAmenity][userType];

                    const dayRateElement = document.getElementById('dayRate');
                    if (dayRateElement) {
                        dayRateElement.textContent = `Day • ${rates.day}`;
                    }

                    const nightRateElement = document.getElementById('nightRate');
                    if (nightRateElement) {
                        nightRateElement.textContent = `Night • ${rates.night}`;
                    }

                    // Restore the same rate (day/night) as before
                    const container = document.getElementById('ratesContainer');
                    if (container) {
                        const selectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);

                        const selectedRateInput = document.getElementById('selectedRate');
                        if (selectedRateInput) {
                            if (selectedOption) {
                                selectedRateInput.value = prevSelectedRate;
                            } else {
                                selectedRateInput.value = 'day';
                                prevSelectedRate = 'day';
                            }
                        }

                        // Reset UI
                        container.querySelectorAll('.custom-radio-option').forEach(el => {
                            el.classList.remove('selected');
                            const circle = el.querySelector('.custom-radio-circle');
                            if (circle) circle.classList.remove('selected');
                        });

                        const finalSelectedOption = container.querySelector(`[data-value="${prevSelectedRate}"]`);
                        if (finalSelectedOption) {
                            finalSelectedOption.classList.add('selected');
                            const circle = finalSelectedOption.querySelector('.custom-radio-circle');
                            if (circle) circle.classList.add('selected');
                        }
                    }
                }

                calculateTotal();
            });
        }

        // User ID selection handler
        const userIdSelectElement = document.getElementById('userId');
        if (userIdSelectElement) {
            userIdSelectElement.addEventListener('change', function () {
                const selectedUserId = this.value;

                if (selectedUserId && userData[selectedUserId]) {
                    populateUserFields(userData[selectedUserId]);
                    this.style.borderColor = '#198754';
                    this.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
                } else {
                    clearUserFields();
                    this.style.borderColor = '#dee2e6';
                    this.style.boxShadow = 'none';
                }
            });
        }

        function populateUserFields(user) {
            const fields = [
                { id: 'firstName', value: user.first_name || '' },
                { id: 'middleName', value: user.middle_name || '' },
                { id: 'lastName', value: user.last_name || '' },
                { id: 'emailAddress', value: user.email || '' },
                { id: 'cellphone_number', value: user.cellphone_number || '' }
            ];

            fields.forEach(field => {
                const element = document.querySelector(`[name="${field.id}"]`) || document.getElementById(field.id);
                if (element) {
                    element.value = field.value;
                    element.readOnly = true;
                    element.style.backgroundColor = '#f8f9fa';
                    element.style.opacity = '0.8';
                }
            });
        }

        function clearUserFields() {
            const fieldNames = ['firstName', 'middleName', 'lastName', 'emailAddress', 'cellphone_number'];

            fieldNames.forEach(fieldName => {
                const element = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(fieldName);
                if (element) {
                    element.value = '';
                    element.readOnly = false;
                    element.style.backgroundColor = '';
                    element.style.opacity = '';
                }
            });
        }

        // ============================================
        // RATE & PAYMENT SELECTION
        // ============================================
        function selectRate(option, value) {
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

            // Toggle Payment Info
            const bankInfo = document.getElementById("bankInfo");
            const cashInfo = document.getElementById("cashInfo");
            const referenceNumber = document.getElementById("referenceNumber");
            const fileInputElement = document.getElementById("fileInput");

            if (value === "cash") {
                if (cashInfo) cashInfo.classList.remove("d-none");
                if (bankInfo) bankInfo.classList.add("d-none");

                if (referenceNumber) {
                    referenceNumber.closest(".form-floating")?.classList.add("d-none");
                    referenceNumber.removeAttribute("required");
                }
                if (fileInputElement) {
                    fileInputElement.closest("#fileDropArea")?.classList.add("d-none");
                    fileInputElement.removeAttribute("required");
                }
            } else {
                if (bankInfo) bankInfo.classList.remove("d-none");
                if (cashInfo) cashInfo.classList.add("d-none");

                if (referenceNumber) {
                    referenceNumber.closest(".form-floating")?.classList.remove("d-none");
                    referenceNumber.setAttribute("required", "required");
                }
                if (fileInputElement) {
                    fileInputElement.closest("#fileDropArea")?.classList.remove("d-none");
                    fileInputElement.setAttribute("required", "required");
                }
            }
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

            let guests = parseInt(document.getElementById("guests")?.value || 0);
            let chairs = parseInt(document.getElementById("chairs")?.value || 0);
            let tables = parseInt(document.getElementById("tables")?.value || 0);

            // Get amenity rate
            let rateStr = amenityRates[currentAmenity]?.[userType]?.[rateType] || "₱0";
            let rateValue = extractPrice(rateStr);

            let total = 0;

            // Check if rate is per person
            if (rateStr.includes("per person")) {
                total += rateValue * guests;
            } else {
                total += rateValue;
            }

            // Add chairs and tables
            total += chairs * chairPrice;
            total += tables * tablePrice;

            // Format with commas and 2 decimals
            const formattedTotal = total.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const totalElement = document.getElementById("total");
            if (totalElement) {
                totalElement.value = formattedTotal;
            }
        }

        // Recalculate total when fields change
        ["guests", "chairs", "tables", "userType"].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener("input", calculateTotal);
                el.addEventListener("change", calculateTotal);
            }
        });

        // ============================================
        // AMOUNT PAID VALIDATION
        // ============================================
        function validateAmountPaid() {
            const totalField = document.getElementById("total");
            const amountPaidField = document.getElementById("amountPaid");

            if (!totalField || !amountPaidField) return true;

            const totalValue = parseFloat(totalField.value.replace(/,/g, '')) || 0;
            const amountPaidValue = parseFloat(amountPaidField.value) || 0;

            // Remove existing validation
            amountPaidField.classList.remove('border-danger', 'is-invalid');
            const existingFeedback = amountPaidField.parentNode.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }

            // Check if amount paid exceeds total
            if (amountPaidValue > totalValue && totalValue > 0) {
                amountPaidField.classList.add('border-danger', 'is-invalid');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Amount paid cannot exceed total amount (₱${totalValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;

                amountPaidField.parentNode.parentNode.insertAdjacentElement('beforeend', errorDiv);
                return false;
            }

            return true;
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
        // FORM SUBMISSION
        // ============================================
        function initializeFormSubmission() {
            const form = document.getElementById('reservationForm');
            const submitButton = form?.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.addEventListener('click', function (e) {
                    e.preventDefault();

                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return;
                    }

                    if (!validateAmountPaid()) {
                        const amountPaidField = document.getElementById("amountPaid");
                        if (amountPaidField) amountPaidField.focus();
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

            // Fetch booked dates for calendar
            fetchBookedDates();

            // Initialize amount paid validation
            const amountPaidField = document.getElementById("amountPaid");
            if (amountPaidField) {
                amountPaidField.addEventListener('input', validateAmountPaid);
                amountPaidField.addEventListener('blur', validateAmountPaid);
            }

            // Initialize form submission
            initializeFormSubmission();

            // Initialize vehicle field
            initializeVehicleField();

            // Initialize modals
            initializeModals();

            // Set minimum date to today
            const today = new Date().toISOString().split("T")[0];
            const dateInput = document.getElementById("reservationDate");
            if (dateInput) {
                dateInput.min = today;
            }

            // Initial calculation
            calculateTotal();

            console.log('Initialization complete');
        });
    </script>

</body>

</html>