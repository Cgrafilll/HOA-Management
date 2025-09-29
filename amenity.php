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

// How many records per page
$limit = 10;

// Current page number (default 1 if not set)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

// Calculate offset for SQL query
$offset = ($page - 1) * $limit;

// Get total number of records from amenity_bookings (filtered for partial/paid only)
$totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings WHERE status IN ('partial', 'paid')";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];

// Calculate total pages
$totalPages = ceil($totalRecords / $limit);

// ✅ Updated query with JOINs for table data (filtered for partial/paid only)
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
WHERE ab.status IN ('partial', 'paid')
ORDER BY ab.reservation_date DESC 
LIMIT $limit OFFSET $offset";

$bookings_result = $conn->query($booking_sql);

// ✅ Updated query for calendar data with JOINs (filtered for partial/paid only)
$calendar_sql = "SELECT 
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
WHERE ab.status IN ('partial', 'paid')
ORDER BY ab.reservation_date ASC";

$calendar_result = $conn->query($calendar_sql);

$bookings = [];
while ($row = $calendar_result->fetch_assoc()) {
    // Determine time slot based on 'rate'
    $timeSlot = "N/A";
    if (isset($row['rate'])) {
        if ($row['rate'] === "day") {
            $timeSlot = "9:00 AM - 5:00 PM";
        } elseif ($row['rate'] === "night") {
            $timeSlot = "5:00 PM - 10:00 PM";
        }
    }

    // Construct full name from the joined data
    $fullName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);

    $bookings[] = [
        "id" => $row['id'],
        "date" => $row['reservation_date'],
        "fullName" => $fullName,
        "amenity" => $row['amenity'],
        "reservationCode" => $row['reservation_code'],
        "paymentStatus" => ucfirst($row['status']), // partial → Partial, paid → Paid
        "amount" => "₱" . number_format($row['amount_paid'], 2) .
            ($row['status'] === 'partial' ? " / ₱" . number_format($row['total_amount'], 2) : ""),
        "time" => $timeSlot,
        "userType" => $row['user_type']
    ];
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
                <a href="index.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i>Entry Monitoring
                </a>
                <a href="exit.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-sign-turn-left me-2"></i>Exit Monitoring
                </a>
                <a href="amenity.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
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
            </ul>
            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Bookings Table -->
                <div class="tab-pane fade show active" id="bookings" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">List of Amenity Bookings (Partial & Paid Only)</span>
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
                                </tr>
                            </thead>
                            <tbody class="small align-middle">
                                <?php
                                if ($bookings_result->num_rows > 0) {
                                    while ($row = $bookings_result->fetch_assoc()) {
                                        $id = $row['id'];

                                        // Construct full name from joined data
                                        $fullName = ucwords(trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']));

                                        $amenity = $row['amenity'];
                                        $bookingDate = $row['reservation_date'];
                                        $resCode = $row['reservation_code'];
                                        $statusClass = $row['status'] === 'paid' ? 'text-success' : 'text-warning'; // Only partial or paid will show
                                
                                        echo "<tr>
                                                    <td>{$bookingDate}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$amenity}</td>
                                                    <td>{$resCode}</td>
                                                    <td class='{$statusClass} fw-bold'>" . ucfirst($row['status']) . "</td>
                                                </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted'>No bookings found with partial or paid status.</td></tr>";
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
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        const bookings = <?php echo json_encode($bookings); ?>;
        let currentDate = new Date(); // Current date
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
                    // Create container for booking with notification badge
                    const bookingContainer = document.createElement('div');
                    bookingContainer.className = 'position-relative';
                    bookingContainer.style.display = 'inline-block';
                    bookingContainer.style.width = '100%';
                    bookingContainer.style.marginBottom = '4px';

                    const bookingElement = document.createElement('div');
                    let bgClass = 'bg-secondary text-white'; // default (though pending is filtered out)
                    if (booking.paymentStatus === 'Paid') {
                        bgClass = 'bg-success text-white';
                    } else if (booking.paymentStatus === 'Partial') {
                        bgClass = 'bg-warning text-dark';
                    }
                    bookingElement.className = `booking-item ${bgClass} overflow-hidden text-nowrap rounded-2`;
                    bookingElement.style.fontSize = '0.75rem';
                    bookingElement.style.padding = '0.25rem 0.5rem';
                    bookingElement.style.cursor = 'pointer';
                    bookingElement.textContent = booking.fullName;

                    // Create notification badge with initials
                    const amenityBadge = document.createElement('span');
                    let amenityBgClass = 'bg-secondary'; // default
                    const amenityLower = booking.amenity.toLowerCase();
                    if (amenityLower === 'clubhouse') {
                        amenityBadge.style.backgroundColor = '#dc3545'; // Bootstrap danger red
                    } else if (amenityLower === 'swimming pool') {
                        amenityBadge.style.backgroundColor = '#0d6efd'; // Bootstrap primary blue
                    } else if (amenityLower === 'gazebo') {
                        amenityBadge.style.backgroundColor = '#ffc107'; // Bootstrap warning yellow
                    } else if (amenityLower === 'basketball court') {
                        amenityBadge.style.backgroundColor = '#0dcaf0'; // Bootstrap info cyan
                    } else {
                        amenityBadge.style.backgroundColor = '#6c757d'; // Bootstrap secondary gray
                    }

                    // Get initials for badge
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

                    // Store full text for hover effect
                    const fullAmenityText = booking.amenity;

                    // Add hover events
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

                    bookingContainer.appendChild(bookingElement);
                    bookingContainer.appendChild(amenityBadge);

                    // Add click handler to the container
                    bookingContainer.onclick = () => showBookingDetails(booking);

                    dayElement.appendChild(bookingContainer);
                });
                grid.appendChild(dayElement);
            }
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

            if (view === 'week') {
                // You can implement week view here
                alert('Week view feature coming soon!');
            } else {
                renderCalendar();
            }
        }

        // Initialize calendar
        renderCalendar();
    </script>

</body>

</html>