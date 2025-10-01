<?php
// ✅ FIX: Set session configuration BEFORE session_start()
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

require '../../rfid-api/db.php';

// Check if user is logged in
if (!isset($_SESSION['household_id'])) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$household_id = $_SESSION['household_id'];
$sql = "SELECT * FROM household_accounts WHERE household_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $household_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();

if (!$resident) {
    echo "Resident not found.";
    exit;
}

// Initialize user details
$username = $resident['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($resident['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($resident['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

// How many records per page
$limit = 10;
// Current page number (default 1 if not set)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
// Calculate offset for SQL query
$offset = ($page - 1) * $limit;
// Get total number of records from amenity_bookings for this homeowner
if ($household_id) {
    $totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings WHERE homeowner_id = ?";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->bind_param("s", $household_id);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
} else {
    // If no household_id, get all records
    $totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings";
    $totalResult = $conn->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
}
// Calculate total pages
$totalPages = ceil($totalRecords / $limit);
// ✅ Fetch only the records for THIS PAGE (table)
if ($household_id) {
    $booking_sql = "SELECT 
        ab.id,
        ab.reservation_code,
        ab.amenity,
        ab.user_type,
        ab.reservation_date,
        ab.rate,
        ab.total_amount,
        ab.amount_paid,
        ab.status,
        ab.created_at,
        ab.homeowner_id,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.first_name
            WHEN ab.user_type = 'visitor' THEN vd.first_name
            ELSE NULL
        END as first_name,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.middle_name
            WHEN ab.user_type = 'visitor' THEN vd.middle_name
            ELSE NULL
        END as middle_name,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.last_name
            WHEN ab.user_type = 'visitor' THEN vd.last_name
            ELSE NULL
        END as last_name
    FROM amenity_bookings ab
    LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id AND ab.user_type = 'homeowner'
    LEFT JOIN visitor_details vd ON ab.visitor_id = vd.visitor_id AND ab.user_type = 'visitor'
    WHERE ab.homeowner_id = ? ORDER BY ab.reservation_date ASC LIMIT ? OFFSET ?";
    $bookings_stmt = $conn->prepare($booking_sql);
    $bookings_stmt->bind_param("sii", $household_id, $limit, $offset);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
} else {
    // If no homeowner_id, get all records for this page
    $booking_sql = "SELECT 
        ab.id,
        ab.reservation_code,
        ab.amenity,
        ab.user_type,
        ab.reservation_date,
        ab.rate,
        ab.total_amount,
        ab.amount_paid,
        ab.status,
        ab.created_at,
        ab.homeowner_id,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.first_name
            WHEN ab.user_type = 'visitor' THEN vd.first_name
            ELSE NULL
        END as first_name,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.middle_name
            WHEN ab.user_type = 'visitor' THEN vd.middle_name
            ELSE NULL
        END as middle_name,
        CASE 
            WHEN ab.user_type = 'homeowner' THEN ha.last_name
            WHEN ab.user_type = 'visitor' THEN vd.last_name
            ELSE NULL
        END as last_name
    FROM amenity_bookings ab
    LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id AND ab.user_type = 'homeowner'
    LEFT JOIN visitor_details vd ON ab.visitor_id = vd.visitor_id AND ab.user_type = 'visitor'
    ORDER BY ab.reservation_date ASC LIMIT ? OFFSET ?";
    $bookings_stmt = $conn->prepare($booking_sql);
    $bookings_stmt->bind_param("ii", $limit, $offset);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
}
// ✅ Fetch ALL records (for calendar JSON) - if you need this for calendar
$sql = "SELECT 
    ab.id,
    ab.reservation_code,
    ab.amenity,
    ab.user_type,
    ab.reservation_date,
    ab.rate,
    ab.total_amount,
    ab.amount_paid,
    ab.status,
    ab.created_at,
    ab.homeowner_id,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.first_name
        WHEN ab.user_type = 'visitor' THEN vd.first_name
        ELSE NULL
    END as first_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.middle_name
        WHEN ab.user_type = 'visitor' THEN vd.middle_name
        ELSE NULL
    END as middle_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.last_name
        WHEN ab.user_type = 'visitor' THEN vd.last_name
        ELSE NULL
    END as last_name
FROM amenity_bookings ab
LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id AND ab.user_type = 'homeowner'
LEFT JOIN visitor_details vd ON ab.visitor_id = vd.visitor_id AND ab.user_type = 'visitor'
ORDER BY ab.reservation_date ASC";
$result = $conn->query($sql);
$bookings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Determine time slot based on 'rate'
        $timeSlot = "N/A";
        if (isset($row['rate'])) {
            if ($row['rate'] === "day") {
                $timeSlot = "9:00 AM - 5:00 PM";
            } elseif ($row['rate'] === "night") {
                $timeSlot = "5:00 PM - 10:00 PM";
            }
        }
        $bookings[] = [
            "id" => $row['id'],
            "date" => $row['reservation_date'],
            "fullName" => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
            "amenity" => $row['amenity'],
            "reservationCode" => $row['reservation_code'],
            "paymentStatus" => ucfirst($row['status']), // pending → Pending
            "amount" => "₱" . number_format($row['amount_paid'], 2) .
                ($row['status'] === 'partial' ? " / ₱" . number_format($row['total_amount'], 2) : ""),
            "time" => $timeSlot,
            "homeownerId" => $row['homeowner_id']
        ];
    }
}

// Query for reschedule requests for this homeowner
$reschedule_sql = "SELECT 
    ab.id,
    ab.reservation_code,
    ab.amenity,
    ab.user_type,
    ab.reservation_date,
    ab.rate,
    ab.status,
    ab.requested_date,
    ab.requested_rate,
    ab.reschedule_reason,
    ab.reschedule_status,
    ab.reschedule_requested_at,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.first_name
        WHEN ab.user_type = 'visitor' THEN vd.first_name
        ELSE NULL
    END as first_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.middle_name
        WHEN ab.user_type = 'visitor' THEN vd.middle_name
        ELSE NULL
    END as middle_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.last_name
        WHEN ab.user_type = 'visitor' THEN vd.last_name
        ELSE NULL
    END as last_name
FROM amenity_bookings ab
LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id AND ab.user_type = 'homeowner'
LEFT JOIN visitor_details vd ON ab.visitor_id = vd.visitor_id AND ab.user_type = 'visitor'
WHERE ab.homeowner_id = ? AND ab.reschedule_status IN ('pending', 'approved', 'rejected')
ORDER BY ab.reschedule_requested_at DESC";

$reschedule_stmt = $conn->prepare($reschedule_sql);
$reschedule_stmt->bind_param("s", $household_id);
$reschedule_stmt->execute();
$reschedule_result = $reschedule_stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            min-height: 100vh;
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
        .collapse ul li a:hover {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed),
        .sidebar .logout:hover {
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

        .calendar-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-nav button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .calendar-nav button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar-nav button.active {
            background: rgba(255, 255, 255, 0.4);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-left: 1px solid #dee2e6;
            border-top: 1px solid #dee2e6;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 0.75rem 0.5rem;
            font-weight: 600;
            text-align: center;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.875rem;
        }

        .calendar-day {
            min-height: 120px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 0.5rem;
            position: relative;
            background: white;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }

        .calendar-day.today {
            background: #e3f2fd;
        }

        .legend {
            display: flex;
            gap: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 2px;
        }

        .booking-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .booking-detail:last-child {
            border-bottom: none;
        }

        /* Reschedule Calendar - following reserve_booking.php style */
        .calendar-grid-reschedule {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            max-width: 100%;
            overflow: hidden;
        }

        .calendar-grid-reschedule .calendar-day-header {
            text-align: center;
            font-weight: 600;
            font-size: 12px;
            padding: 8px;
            color: #6c757d;
        }

        .calendar-grid-reschedule .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            background: white;
            position: relative;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        .calendar-grid-reschedule .calendar-day:hover:not(.disabled):not(.booked) {
            background: #e7f5ea;
            border-color: #198754;
        }

        .calendar-grid-reschedule .calendar-day.today {
            border: 2px solid #198754;
            font-weight: 600;
        }

        .calendar-grid-reschedule .calendar-day.booked {
            background: #f8d7da;
            color: #721c24;
            cursor: not-allowed;
        }

        .calendar-grid-reschedule .calendar-day.booked::after {
            content: "●";
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: 8px;
            color: #dc3545;
        }

        .calendar-grid-reschedule .calendar-day.selected {
            background: #198754 !important;
            color: white !important;
            border-color: #198754 !important;
        }

        .calendar-grid-reschedule .calendar-day.disabled {
            background: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
        }

        .calendar-grid-reschedule .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }

        .calendar-grid-reschedule .calendar-day.partial-booked {
            background-color: #fff3cd;
            border-color: #ffc107;
            cursor: pointer;
        }

        .calendar-grid-reschedule .calendar-day.partial-booked:hover {
            background-color: #ffeaa7;
            border-color: #ffb300;
        }

        .calendar-grid-reschedule .partial-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 10px;
            color: #856404;
            pointer-events: none;
        }

        #dateMessageReschedule {
            margin-top: 10px;
        }

        .rate-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .rate-option {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rate-option:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }

        .rate-option.selected {
            border-color: #0d6efd;
            background: #cfe2ff;
        }

        .rate-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #e9ecef;
        }

        .rate-option i {
            font-size: 24px;
            color: #0d6efd;
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
            <h1 class="h5 mb-0 fw-bold">AMENITY BOOKING</h1>
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
                    <li><a class="dropdown-item"
                            href="../resident_details/view_resident.php?id=<?php echo $household_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="../dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="amenity_booking.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <a href="../report.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-exclamation-triangle me-2"></i> Report Violation
                </a>
                <!-- Accounting -->
                <div>
                    <button
                        class="btn btn-toggle collapsed px-3 rounded py-2 d-flex align-items-center justify-content-start"
                        data-bs-toggle="collapse" data-bs-target="#acctCollapse" aria-expanded="false">
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
                <a href="../logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-left me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Amenity Booking Management</h5>
                </div>
                <!-- Tabs -->
                <ul class="nav nav-tabs my-3" id="dashboardTabs">
                    <li class="nav-item">
                        <a class="nav-link active link-dark" id="bookings-tab" data-bs-toggle="tab" href="#bookings"
                            role="tab">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-secondary" id="calendar-tab" data-bs-toggle="tab" href="#calendar"
                            role="tab">Calendar View</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-secondary" id="reschedule-tab" data-bs-toggle="tab" href="#reschedule"
                            role="tab">Reschedule Requests</a>
                    </li>
                </ul>
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Bookings Table -->
                    <div class="tab-pane fade show active" id="bookings" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small">List of Amenity Bookings</span>
                            <a href="choose_booking.php" class="btn btn-primary btn-sm">+ Create New
                                Booking</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-success text-white small">
                                    <tr>
                                        <th>Booking Date</th>
                                        <th>Full Name</th>
                                        <th>Amenity</th>
                                        <th>Reservation Code</th>
                                        <th>Payment Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="small align-middle">
                                    <?php
                                    if ($bookings_result->num_rows > 0) {
                                        while ($row = $bookings_result->fetch_assoc()) {
                                            // Verify this booking belongs to the logged-in household
                                            if ($row['homeowner_id'] == $household_id) {
                                                $id = $row['id'];
                                                $fullName = ucwords(trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']));
                                                $amenity = $row['amenity'];
                                                $bookingDate = date('F d, Y', strtotime($row['reservation_date']));
                                                $resCode = $row['reservation_code'];
                                                $statusClass = $row['status'] === 'paid'
                                                    ? 'badge bg-success text-white'
                                                    : ($row['status'] === 'partial'
                                                        ? 'badge bg-primary text-white'
                                                        : 'badge bg-warning text-dark');

                                                echo "<tr>
                                                    <td>{$bookingDate}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$amenity}</td>
                                                    <td>{$resCode}</td>
                                                    <td class='text-center'>
                                                        <span class='" . $statusClass . " fw-bold d-inline-flex align-items-center justify-content-center' style='min-width: 70px;'>
                                                            " . ucfirst($row['status']) . "
                                                        </span>
                                                    </td>
                                                    <td class='text-center'>
                                                        <button class='btn btn-sm btn-outline-success' title='View Details' 
                                                            onclick='showBookingDetailsFromTable({
                                                                fullName: \"" . addslashes($fullName) . "\",
                                                                amenity: \"" . addslashes($amenity) . "\",
                                                                date: \"" . $bookingDate . "\",
                                                                reservationCode: \"" . $resCode . "\",
                                                                paymentStatus: \"" . ucfirst($row['status']) . "\",
                                                                amount: \"₱" . number_format($row['amount_paid'], 2) . ($row['status'] === 'partial' ? " / ₱" . number_format($row['total_amount'], 2) : "") . "\",
                                                                time: \"" . ($row['rate'] === 'day' ? '9:00 AM - 5:00 PM' : ($row['rate'] === 'night' ? '5:00 PM - 10:00 PM' : 'N/A')) . "\"
                                                            })'>
                                                            <i class='bi bi-eye'></i>
                                                        </button>
                                                        <!-- Reschedule button -->
                                                        <button class='btn btn-sm btn-outline-primary me-1' title='Reschedule' style='padding: 2px 6px; font-size: 0.9rem;'
                                                            onclick='openRescheduleModal({
                                                                id: \"" . $id . "\",
                                                                fullName: \"" . addslashes($fullName) . "\",
                                                                amenity: \"" . addslashes($amenity) . "\",
                                                                date: \"" . $bookingDate . "\",
                                                                time: \"" . ($row['rate'] === 'day' ? '9:00 AM - 5:00 PM' : ($row['rate'] === 'night' ? '5:00 PM - 10:00 PM' : 'N/A')) . "\",
                                                                rate: \"" . $row['rate'] . "\"
                                                            })'>
                                                            <i class='bi bi-calendar2-week'></i>
                                                        </button>
                                                    </td>
                                                </tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center text-muted'>No bookings found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small">Showing 1 to <?php echo $bookings_result->num_rows; ?> entries</span>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php if ($page <= 1)
                                        echo 'disabled'; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                    <!-- Page numbers -->
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php if ($page == $i)
                                            echo 'active'; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <!-- Next button -->
                                    <li class="page-item <?php if ($page >= $totalPages)
                                        echo 'disabled'; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <!-- Calendar View -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <!-- Calendar Controls -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4">
                                <div class="legend">
                                    <div class="legend-item">
                                        <div class="legend-color paid bg-success"></div>
                                        <span>Paid</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color partial bg-warning"></div>
                                        <span>Partial Payment</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color pending bg-secondary"></div>
                                        <span>Pending Payment</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8 text-end">
                                <button class="btn btn-primary btn-sm" onclick="goToToday()">
                                    <i class="bi bi-calendar-date me-1"></i>Today
                                </button>
                            </div>
                        </div>
                        <!-- Calendar -->
                        <div class="calendar-container shadow-sm">
                            <div
                                class="calendar-header bg-success text-white p-3 d-flex justify-content-between align-items-center">
                                <div class="calendar-nav d-flex gap-2">
                                    <button onclick="previousMonth()">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <button onclick="nextMonth()">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                                <h4 class="mb-0" id="monthYear">August 2025</h4>
                                <div class="calendar-nav d-flex gap-2">
                                    <button id="monthBtn" class="active" onclick="setView('month')">Month</button>
                                </div>
                            </div>
                            <div class="calendar-grid" id="calendarGrid">
                                <!-- Calendar will be generated here -->
                            </div>
                        </div>
                        <!-- Booking Details Modal -->
                        <div class="modal fade booking-modal" id="bookingModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Booking Details</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" id="modalContent">
                                        <!-- Booking details will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Reschedule Requests -->
                    <div class="tab-pane fade" id="reschedule" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small">List of Your Reschedule Requests</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-success text-white small">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Amenity</th>
                                        <th>Reservation Code</th>
                                        <th>Current Date</th>
                                        <th>Requested Date</th>
                                        <th>Reason</th>
                                        <th>Requested At</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="small align-middle">
                                    <?php
                                    if ($reschedule_result->num_rows > 0) {
                                        while ($row = $reschedule_result->fetch_assoc()) {
                                            $fullName = ucwords(trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']));
                                            $amenity = $row['amenity'];
                                            $currentDate = date('M d, Y', strtotime($row['reservation_date']));
                                            $requestedDate = date('M d, Y', strtotime($row['requested_date']));
                                            $currentTime = $row['rate'] === 'day' ? '9:00 AM - 5:00 PM' : '5:00 PM - 10:00 PM';
                                            $requestedTime = $row['requested_rate'] === 'day' ? '9:00 AM - 5:00 PM' : '5:00 PM - 10:00 PM';
                                            $reason = htmlspecialchars($row['reschedule_reason']);
                                            $statusClass = $row['reschedule_status'] === 'approved'
                                                ? 'badge bg-success text-white'
                                                : ($row['reschedule_status'] === 'rejected'
                                                    ? 'badge bg-danger text-white'
                                                    : 'badge bg-warning text-dark');
                                            $requestedAt = date('M d, Y h:i A', strtotime($row['reschedule_requested_at']));

                                            echo "<tr>
                                                <td>{$fullName}</td>
                                                <td><span class='badge bg-secondary'>{$amenity}</span></td>
                                                <td>{$row['reservation_code']}</td>
                                                <td>{$currentDate}<br><small class='text-muted'>{$currentTime}</small></td>
                                                <td><span class='text-primary fw-bold'>{$requestedDate}</span><br><small class='text-muted'>{$requestedTime}</small></td>
                                                <td>{$reason}</td>
                                                <td><small>{$requestedAt}</small></td>
                                                <td class='text-center'>
                                                    <span class='" . $statusClass . " fw-bold d-inline-flex align-items-center justify-content-center' style='min-width: 80px;'>
                                                        " . ucfirst($row['reschedule_status']) . "
                                                    </span>
                                                </td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center text-muted'>No reschedule requests found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <!-- Booking Details Modal -->
        <div class="modal fade booking-modal" id="bookingModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Booking Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="modalContent">
                        <!-- Booking details will be populated here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Reschedule Modal -->
        <div class="modal fade" id="rescheduleModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Reschedule Booking</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="rescheduleForm" method="POST" action="amenity_booking/reschedule_booking.php">
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <input type="hidden" id="reschedule_booking_id" name="booking_id">
                            <input type="hidden" id="reschedule_amenity" name="amenity">
                            <!-- Current Booking Info -->
                            <div class="alert alert-info">
                                <strong>Current Booking:</strong>
                                <div id="currentBookingInfo"></div>
                            </div>
                            <!-- Calendar for New Date -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select New Date<span
                                        class="text-danger">*</span></label>
                                <div class="bg-light border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="prevMonthReschedule">
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <div class="fw-bold" id="currentMonthReschedule">Loading...</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="nextMonthReschedule">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div class="calendar-grid-reschedule mb-2" id="calendarGridReschedule">
                                        <!-- Calendar will be generated by JavaScript -->
                                    </div>
                                    <div class="d-flex gap-3 mt-2 justify-content-center">
                                        <small class="d-flex align-items-center">
                                            <span class="badge bg-success me-1">●</span> Available
                                        </small>
                                        <small class="d-flex align-items-center">
                                            <span class="badge bg-warning me-1">◐</span> Partial
                                        </small>
                                        <small class="d-flex align-items-center">
                                            <span class="badge bg-danger me-1">●</span> Booked
                                        </small>
                                        <small class="d-flex align-items-center">
                                            <span class="badge bg-secondary me-1">●</span> Past
                                        </small>
                                    </div>
                                    <div id="dateMessageReschedule" class="mt-2"></div>
                                </div>
                                <!-- Visible Date Input Below Calendar -->
                                <input type="text" class="form-control" id="selected_date_display" readonly
                                    placeholder="Select a date from calendar above" onfocus="this.blur()">
                                <!-- Hidden input for form submission -->
                                <input type="hidden" id="new_date" name="new_date" required>
                            </div>
                            <!-- New Time Slot -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select New Time Slot<span
                                        class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="border rounded p-2 h-100 rate-option" data-value="day"
                                            onclick="selectRescheduleRate(this, 'day')" style="cursor: pointer;">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-sun fs-4 text-primary"></i>
                                                <div>
                                                    <strong class="d-block small">Day Rate</strong>
                                                    <small class="text-muted">9:00 AM - 5:00 PM</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 h-100 rate-option" data-value="night"
                                            onclick="selectRescheduleRate(this, 'night')" style="cursor: pointer;">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-moon-stars fs-4 text-primary"></i>
                                                <div>
                                                    <strong class="d-block small">Night Rate</strong>
                                                    <small class="text-muted">5:00 PM - 10:00 PM</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="new_rate" name="new_rate" required>
                            </div>
                            <!-- Reason -->
                            <div class="mb-3">
                                <label for="reschedule_reason" class="form-label fw-bold">Reason for Rescheduling<span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="reschedule_reason" name="reason" rows="3" required
                                    placeholder="Please provide a reason for rescheduling..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit Reschedule Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Confirm Reschedule Action Modal -->
        <div class="modal fade" id="confirmRescheduleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" id="confirmModalHeader">
                        <h5 class="modal-title" id="confirmModalTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmModalMessage"></p>
                        <div class="alert alert-info">
                            <strong>Guest:</strong> <span id="confirmGuestName"></span><br>
                            <strong>Amenity:</strong> <span id="confirmAmenity"></span><br>
                            <strong>New Date:</strong> <span id="confirmDate"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-cancel"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn" id="confirmActionBtn"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const bookings = <?= json_encode($bookings) ?>;
        const loggedInHouseholdId = <?= json_encode($household_id) ?>;
        let currentDate = new Date();
        let currentView = 'month';

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthYear = document.getElementById('monthYear');

            grid.innerHTML = '';

            // Update header
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            monthYear.textContent = `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

            // Day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });

            // Get first day of month and number of days
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());

            // Create calendar days
            const today = new Date();
            for (let i = 0; i < 35; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                // Add classes for styling
                if (date.getMonth() !== currentDate.getMonth()) {
                    dayElement.classList.add('other-month');
                }
                if (date.toDateString() === today.toDateString()) {
                    dayElement.classList.add('today');
                }
                // Day number
                const dayNumber = document.createElement('div');
                dayNumber.className = 'day-number fw-medium';
                dayNumber.style.fontSize = `0.875rem`;
                dayNumber.textContent = date.getDate();
                dayElement.appendChild(dayNumber);
                // Add bookings for this date
                const dateStr = date.toLocaleDateString('en-CA');
                const dayBookings = bookings.filter(booking => booking.date === dateStr);
                dayBookings.forEach(booking => {
                    const bookingContainer = document.createElement('div');
                    bookingContainer.className = 'position-relative';
                    bookingContainer.style.display = 'inline-block';
                    bookingContainer.style.width = '100%';
                    bookingContainer.style.marginBottom = '4px';

                    const bookingElement = document.createElement('div');

                    // Determine background class based on payment
                    let bgClass = 'bg-secondary text-white';
                    if (booking.paymentStatus === 'Paid') {
                        bgClass = 'bg-success text-white';
                    } else if (booking.paymentStatus === 'Partial') {
                        bgClass = 'bg-warning text-dark';
                    }

                    if (booking.homeownerId == loggedInHouseholdId) {
                        // ✅ Your household
                        bookingElement.className = `booking-item ${bgClass} text-white overflow-hidden text-nowrap rounded-2`;
                        bookingElement.style.fontSize = '0.75rem';
                        bookingElement.style.padding = '0.25rem 0.5rem';
                        bookingElement.style.cursor = 'pointer';
                        bookingElement.textContent = booking.fullName;

                        // Badge (always shown for your bookings)
                        const amenityBadge = createAmenityBadge(booking);
                        bookingContainer.appendChild(bookingElement);
                        bookingContainer.appendChild(amenityBadge);

                        bookingContainer.onclick = () => showBookingDetails(booking);

                    } else {
                        // ✅ Other households
                        bookingElement.className = `booking-item ${bgClass} overflow-hidden text-nowrap rounded-2`;
                        bookingElement.style.fontSize = '0.75rem';
                        bookingElement.style.padding = '0.25rem 0.5rem';
                        bookingElement.style.cursor = 'not-allowed';
                        bookingElement.textContent = `Booked - ${booking.time}`;

                        // Badge only if payment is Paid or Partial
                        if (booking.paymentStatus === 'Paid' || booking.paymentStatus === 'Partial') {
                            const amenityBadge = createAmenityBadge(booking);
                            bookingContainer.appendChild(bookingElement);
                            bookingContainer.appendChild(amenityBadge);
                        }
                    }

                    dayElement.appendChild(bookingContainer);
                });

                grid.appendChild(dayElement);
            }
        }

        function createAmenityBadge(booking) {
            const amenityBadge = document.createElement('span');
            const amenityLower = booking.amenity.toLowerCase();

            if (amenityLower === 'clubhouse') {
                amenityBadge.style.backgroundColor = '#dc3545'; // danger
            } else if (amenityLower === 'swimming pool') {
                amenityBadge.style.backgroundColor = '#0d6efd'; // primary
            } else if (amenityLower === 'gazebo') {
                amenityBadge.style.backgroundColor = '#ffc107'; // warning
            } else if (amenityLower === 'basketball court') {
                amenityBadge.style.backgroundColor = '#0dcaf0'; // info
            } else {
                amenityBadge.style.backgroundColor = '#6c757d'; // secondary
            }

            let badgeInitials = '';
            if (amenityLower === 'clubhouse') badgeInitials = 'C';
            else if (amenityLower === 'swimming pool') badgeInitials = 'SP';
            else if (amenityLower === 'gazebo') badgeInitials = 'G';
            else if (amenityLower === 'basketball court') badgeInitials = 'BC';
            else badgeInitials = booking.amenity.charAt(0);

            amenityBadge.className = 'position-absolute d-flex align-items-center justify-content-center text-white fw-bold';
            amenityBadge.style.top = '-8px';
            amenityBadge.style.right = '-8px';
            amenityBadge.style.width = '24px';
            amenityBadge.style.height = '20px';
            amenityBadge.style.borderRadius = '10px';
            amenityBadge.style.fontSize = '0.6rem';
            amenityBadge.style.lineHeight = '1';
            amenityBadge.style.zIndex = '10';
            amenityBadge.style.border = '2px solid white';
            amenityBadge.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';
            amenityBadge.style.padding = '0 4px';
            amenityBadge.style.whiteSpace = 'nowrap';
            amenityBadge.style.overflow = 'hidden';
            amenityBadge.style.transition = 'all 0.3s ease';
            amenityBadge.style.cursor = 'pointer';
            amenityBadge.textContent = badgeInitials;

            const fullAmenityText = booking.amenity;
            amenityBadge.addEventListener('mouseenter', function () {
                this.style.width = 'max-content';
                this.style.minWidth = '60px';
                this.style.padding = '0 8px';
                this.textContent = fullAmenityText;
            });
            amenityBadge.addEventListener('mouseleave', function () {
                this.style.width = '24px';
                this.style.minWidth = 'auto';
                this.style.padding = '0 4px';
                this.textContent = badgeInitials;
            });

            return amenityBadge;
        }

        function showBookingDetails(booking) {
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div class="booking-detail">
                    <strong>Guest Name:</strong>
                    <span>${booking.fullName}</span>
                </div>
                <div class="booking-detail">
                    <strong>Amenity:</strong>
                    <span class="badge bg-${booking.amenity.toLowerCase() === 'clubhouse' ? 'danger' :
                    booking.amenity.toLowerCase() === 'swimming pool' ? 'primary' :
                        booking.amenity.toLowerCase() === 'gazebo' ? 'warning' : booking.amenity.toLowerCase() === 'basketball court' ? 'info' : 'secondary'}">${booking.amenity}</span>
                </div>
                <div class="booking-detail">
                    <strong>Date:</strong>
                    <span>${new Date(booking.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div class="booking-detail">
                    <strong>Time:</strong>
                    <span>${booking.time}</span>
                </div>
                <div class="booking-detail">
                    <strong>Reservation Code:</strong>
                    <span>${booking.reservationCode}</span>
                </div>
                <div class="booking-detail">
                    <strong>Payment Status:</strong>
                    <span class="badge bg-${booking.paymentStatus === 'Paid' ? 'success' : booking.paymentStatus === 'Partial' ? 'warning' : 'secondary'}">${booking.paymentStatus}</span>
                </div>
                <div class="booking-detail">
                    <strong>Amount:</strong>
                    <span>${booking.amount}</span>
                </div>
                <div class="booking-detail d-flex justify-content-end mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('bookingModal')).show();
        }

        // Function to show booking details from table view
        function showBookingDetailsFromTable(booking) {
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div class="booking-detail">
                    <strong>Guest Name:</strong>
                    <span>${booking.fullName}</span>
                </div>
                <div class="booking-detail">
                    <strong>Amenity:</strong>
                    <span class="badge bg-${booking.amenity.toLowerCase() === 'clubhouse' ? 'danger' :
                    booking.amenity.toLowerCase() === 'swimming pool' ? 'primary' :
                        booking.amenity.toLowerCase() === 'gazebo' ? 'warning text-dark' : booking.amenity.toLowerCase() === 'basketball court' ? 'info' : 'secondary'}">${booking.amenity}</span>
                </div>
                <div class="booking-detail">
                    <strong>Date:</strong>
                    <span>${new Date(booking.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div class="booking-detail">
                    <strong>Time:</strong>
                    <span>${booking.time}</span>
                </div>
                <div class="booking-detail">
                    <strong>Reservation Code:</strong>
                    <span>${booking.reservationCode}</span>
                </div>
                <div class="booking-detail">
                    <strong>Payment Status:</strong>
                    <span class="badge bg-${booking.paymentStatus === 'Paid' ? 'success' : booking.paymentStatus === 'Partial' ? 'warning text-dark' : 'secondary'}">${booking.paymentStatus}</span>
                </div>
                <div class="booking-detail">
                    <strong>Amount:</strong>
                    <span>${booking.amount}</span>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('bookingModal')).show();
        }

        // Function to open reschedule modal
        function openRescheduleModal(booking) {
            // Set booking ID
            document.getElementById('reschedule_booking_id').value = booking.id;

            // Display current booking info
            document.getElementById('currentBookingInfo').innerHTML = `
                <div><strong>Guest:</strong> ${booking.fullName}</div>
                <div><strong>Amenity:</strong> ${booking.amenity}</div>
                <div><strong>Current Date:</strong> ${new Date(booking.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                <div><strong>Current Time:</strong> ${booking.time}</div>
            `;

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('new_date').setAttribute('min', today);

            // Clear form
            document.getElementById('new_date').value = '';
            document.getElementById('new_rate').value = '';
            document.getElementById('reschedule_reason').value = '';

            // Show modal
            new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }

        function goToToday() {
            currentDate = new Date();
            renderCalendar();
        }

        function setView(view) {
            currentView = view;
            document.getElementById('monthBtn').classList.toggle('active', view === 'month');
            document.getElementById('weekBtn').classList.toggle('active', view === 'week');

            if (view === 'week') {
                // You can implement week view here
                alert('Week view feature coming soon!');
            } else {
                renderCalendar();
            }
        }

        // Initialize calendar
        renderCalendar();

        // Reschedule Modal Variables
        let rescheduleBookedDates = {};
        let rescheduleCurrentDate = new Date();
        let rescheduleSelectedDate = null;
        let rescheduleAmenity = null;

        // Function to open reschedule modal
        function openRescheduleModal(booking) {
            // Set booking details
            document.getElementById('reschedule_booking_id').value = booking.id;
            document.getElementById('reschedule_amenity').value = booking.amenity;
            rescheduleAmenity = booking.amenity;

            // Display current booking info
            document.getElementById('currentBookingInfo').innerHTML = `
                <div><strong>Guest:</strong> ${booking.fullName}</div>
                <div><strong>Amenity:</strong> ${booking.amenity}</div>
                <div><strong>Current Date:</strong> ${new Date(booking.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                <div><strong>Current Time:</strong> ${booking.time}</div>
            `;

            // Reset selections
            rescheduleSelectedDate = null;
            document.getElementById('new_date').value = '';
            document.getElementById('new_rate').value = '';
            document.getElementById('reschedule_reason').value = '';
            document.querySelectorAll('.rate-option').forEach(el => el.classList.remove('selected'));

            // Fetch booked dates and render calendar
            fetchRescheduleBookedDates();

            // Show modal
            new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
        }

        async function fetchRescheduleBookedDates() {
            if (!rescheduleAmenity) {
                console.error('Amenity is not defined!');
                return;
            }

            try {
                const url = `amenity_booking.php?action=get_booked_dates&amenity=${encodeURIComponent(rescheduleAmenity)}`;
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    rescheduleBookedDates = {};
                    (data.bookings || []).forEach(booking => {
                        const dateKey = booking.date;
                        const rate = booking.rate;

                        if (!rescheduleBookedDates[dateKey]) {
                            rescheduleBookedDates[dateKey] = { day: false, night: false };
                        }
                        rescheduleBookedDates[dateKey][rate] = true;
                    });

                    renderRescheduleCalendar();
                } else {
                    console.error('API returned error:', data.error);
                    renderRescheduleCalendar();
                }
            } catch (error) {
                console.error('Error fetching booked dates:', error);
                renderRescheduleCalendar();
            }
        }

        function renderRescheduleCalendar() {
            const grid = document.getElementById('calendarGridReschedule');
            const monthDisplay = document.getElementById('currentMonthReschedule');

            if (!grid || !monthDisplay) {
                console.error('Calendar elements not found');
                return;
            }

            const year = rescheduleCurrentDate.getFullYear();
            const month = rescheduleCurrentDate.getMonth();

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
                const dateString = formatDateReschedule(cellDate);

                // Check if past date
                if (cellDate < today) {
                    dayElement.classList.add('disabled');
                    dayElement.title = 'Past date';
                } else {
                    // Check booking status
                    const booking = rescheduleBookedDates[dateString];

                    if (booking) {
                        const dayBooked = booking.day;
                        const nightBooked = booking.night;

                        // Both rates booked - fully booked
                        if (dayBooked && nightBooked) {
                            dayElement.classList.add('booked');
                            dayElement.title = 'Fully booked (Day & Night)';
                        }
                        // Partially booked - still selectable
                        else if (dayBooked || nightBooked) {
                            dayElement.classList.add('partial-booked');
                            const available = dayBooked ? 'Night' : 'Day';
                            dayElement.title = `Partially booked - ${available} available`;

                            // Add a small indicator
                            const indicator = document.createElement('span');
                            indicator.className = 'partial-indicator';
                            indicator.textContent = '◐';
                            dayElement.appendChild(indicator);
                        }
                    }

                    // Check if today
                    if (cellDate.getTime() === today.getTime()) {
                        dayElement.classList.add('today');
                    }
                }

                // Check if selected
                if (rescheduleSelectedDate && rescheduleSelectedDate === dateString) {
                    dayElement.classList.add('selected');
                }

                // Click handler - allow clicking on partially booked dates
                if (!dayElement.classList.contains('disabled') && !dayElement.classList.contains('booked')) {
                    dayElement.addEventListener('click', () => selectRescheduleDate(dateString, dayElement));
                }

                grid.appendChild(dayElement);
            }
        }

        function formatDateReschedule(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function selectRescheduleDate(dateString, element) {
            console.log('Date selected:', dateString);

            const booking = rescheduleBookedDates[dateString];

            // No validation here - just select the date
            rescheduleSelectedDate = dateString;
            document.getElementById('new_date').value = dateString;

            // Update the visible date input with formatted date
            const formattedDate = new Date(dateString + 'T00:00:00').toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('selected_date_display').value = formattedDate;

            // Remove previous selection
            document.querySelectorAll('.calendar-grid-reschedule .calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });

            // Add selection to clicked element
            element.classList.add('selected');

            // Update rate options based on availability
            updateRescheduleRateOptions(booking);

            // If date is partially booked, show which rate is available
            if (booking && (booking.day || booking.night)) {
                const availableRate = booking.day ? 'night' : 'day';
                showRescheduleRateMessage(dateString, availableRate);
            } else {
                document.getElementById('dateMessageReschedule').innerHTML = '';
            }
        }

        function selectRescheduleRate(element, rate) {
            // Check if disabled
            if (element.classList.contains('bg-secondary') || element.style.opacity === '0.5') return;

            // Remove selection from all rate options
            document.querySelectorAll('.rate-option').forEach(el => {
                el.classList.remove('border-primary', 'border-2', 'bg-primary', 'bg-opacity-10');
                el.style.borderWidth = '1px';
            });

            // Add selection to clicked option
            element.classList.add('border-primary', 'border-2', 'bg-primary', 'bg-opacity-10');
            element.style.borderWidth = '2px';

            document.getElementById('new_rate').value = rate;
        }

        function updateRescheduleRateOptions(booking) {
            const dayOption = document.querySelector('.rate-option[data-value="day"]');
            const nightOption = document.querySelector('.rate-option[data-value="night"]');

            // Reset both options
            [dayOption, nightOption].forEach(option => {
                option.classList.remove('bg-secondary', 'bg-opacity-10', 'border-primary', 'border-2', 'bg-primary');
                option.style.opacity = '1';
                option.style.cursor = 'pointer';
                option.style.borderWidth = '1px';
            });

            if (booking) {
                if (booking.day) {
                    dayOption.classList.add('bg-secondary', 'bg-opacity-10');
                    dayOption.style.opacity = '0.5';
                    dayOption.style.cursor = 'not-allowed';
                }
                if (booking.night) {
                    nightOption.classList.add('bg-secondary', 'bg-opacity-10');
                    nightOption.style.opacity = '0.5';
                    nightOption.style.cursor = 'not-allowed';
                }
            }

            // Clear selection
            document.getElementById('new_rate').value = '';
        }

        function showRescheduleRateMessage(date, availableRate) {
            const messageDiv = document.getElementById('dateMessageReschedule');
            if (messageDiv) {
                messageDiv.innerHTML = `<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Note: For ${date}, only <strong>${availableRate}</strong> rate is available.</div>`;

                setTimeout(() => {
                    messageDiv.innerHTML = '';
                }, 5000);
            }
        }

        // Calendar navigation for reschedule
        document.addEventListener('DOMContentLoaded', function () {
            const prevBtn = document.getElementById('prevMonthReschedule');
            const nextBtn = document.getElementById('nextMonthReschedule');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    rescheduleCurrentDate.setMonth(rescheduleCurrentDate.getMonth() - 1);
                    renderRescheduleCalendar();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    rescheduleCurrentDate.setMonth(rescheduleCurrentDate.getMonth() + 1);
                    renderRescheduleCalendar();
                });
            }
        });

        // Handle reschedule confirmation modal
        document.addEventListener('DOMContentLoaded', function () {
            const confirmModal = document.getElementById('confirmRescheduleModal');

            if (confirmModal) {
                confirmModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const action = button.getAttribute('data-action');
                    const bookingId = button.getAttribute('data-id');
                    const guestName = button.getAttribute('data-name');
                    const amenity = button.getAttribute('data-amenity');
                    const date = button.getAttribute('data-date');

                    const modalHeader = document.getElementById('confirmModalHeader');
                    const modalTitle = document.getElementById('confirmModalTitle');
                    const modalMessage = document.getElementById('confirmModalMessage');
                    const confirmBtn = document.getElementById('confirmActionBtn');

                    // Set modal content based on action
                    if (action === 'approve') {
                        modalHeader.className = 'modal-header bg-success text-white';
                        modalTitle.textContent = 'Approve Reschedule Request';
                        modalMessage.textContent = 'Are you sure you want to approve this reschedule request?';
                        confirmBtn.className = 'btn btn-success';
                        confirmBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Approve';
                        confirmBtn.onclick = function () {
                            window.location.href = `amenity_booking/process_reschedule.php?id=${bookingId}&action=approve`;
                        };
                    } else if (action === 'reject') {
                        modalHeader.className = 'modal-header bg-danger text-white';
                        modalTitle.textContent = 'Reject Reschedule Request';
                        modalMessage.textContent = 'Are you sure you want to reject this reschedule request?';
                        confirmBtn.className = 'btn btn-danger';
                        confirmBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Reject';
                        confirmBtn.onclick = function () {
                            window.location.href = `amenity_booking/process_reschedule.php?id=${bookingId}&action=reject`;
                        };
                    }

                    // Set booking details
                    document.getElementById('confirmGuestName').textContent = guestName;
                    document.getElementById('confirmAmenity').textContent = amenity;
                    document.getElementById('confirmDate').textContent = date;
                });
            }
        });

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert-dismissible');

            alerts.forEach(function (alert) {
                setTimeout(function () {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Handle tab switching and link color changes
        document.addEventListener('DOMContentLoaded', function () {
            const tabLinks = document.querySelectorAll('#dashboardTabs .nav-link');
            const tabPanes = document.querySelectorAll('.tab-pane');

            // Add click event listener to each tab link
            tabLinks.forEach(function (tabLink) {
                tabLink.addEventListener('click', function (event) {
                    event.preventDefault();

                    // Get the target tab pane
                    const targetId = this.getAttribute('href').substring(1);
                    const targetPane = document.getElementById(targetId);

                    // Remove active classes from all tabs and panes
                    tabLinks.forEach(function (link) {
                        link.classList.remove('active', 'link-dark');
                        link.classList.add('link-secondary');
                    });

                    tabPanes.forEach(function (pane) {
                        pane.classList.remove('show', 'active');
                    });

                    // Add active classes to current tab and pane
                    this.classList.add('active');
                    this.classList.remove('link-secondary');
                    this.classList.add('link-dark');

                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                });
            });
        });
    </script>
</body>

</html>