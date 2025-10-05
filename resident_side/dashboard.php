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

require '../rfid-api/db.php';

// Check if user is logged in
if (!isset($_SESSION['household_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?error=" . urlencode("Your session has expired. Please log in again."));
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

// Fetch announcements
$announcements_sql = "SELECT a.id, a.title, a.body, a.status, a.created_at, 
                             ad.first_name, ad.last_name 
                      FROM announcements a 
                      LEFT JOIN admin_accounts ad ON a.admin_id = ad.admin_id 
                      WHERE a.status = 'published' 
                      ORDER BY a.created_at DESC";
$announcements_result = $conn->query($announcements_sql);

// Fetch events from database
$events_sql = "SELECT e.id, e.title, e.body, e.status, e.event_date, e.created_at, 
                      ad.first_name, ad.last_name 
               FROM events e 
               LEFT JOIN admin_accounts ad ON e.admin_id = ad.admin_id 
               WHERE e.status = 'published' 
               ORDER BY e.event_date ASC, e.created_at DESC";

$events_result = $conn->query($events_sql);

// ✅ SIMPLIFIED BOOKING PAGINATION - Only for this household
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total records count for this homeowner
$totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings WHERE homeowner_id = ?";
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->bind_param("s", $household_id);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $limit);

// ✅ SIMPLIFIED BOOKING QUERY - Only fetch what we need for this homeowner
$booking_sql = "SELECT 
    ab.id,
    ab.reservation_code,
    ab.amenity,
    ab.user_type,
    ab.reservation_date,
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
WHERE ab.homeowner_id = ? 
ORDER BY ab.reservation_date DESC 
LIMIT ? OFFSET ?";

$bookings_stmt = $conn->prepare($booking_sql);
$bookings_stmt->bind_param("sii", $household_id, $limit, $offset);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow-x: hidden;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 76px;
            background-color: white;
        }

        .sidebar {
            width: 250px;
            height: calc(100vh - 76px);
            position: fixed;
            top: 76px;
            left: 0;
            background-color: #1F2937;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1020;
            transition: transform 0.3s ease;
        }

        main {
            margin-left: 250px;
            margin-top: 76px;
            min-height: calc(100vh - 76px);
            overflow-y: auto;
            transition: margin-left 0.3s ease;
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

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: #1F2937;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 3px;
        }

        .sidebar .logout {
            flex-shrink: 0;
            border-top: 1px solid #374151;
            padding-top: 12px;
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

        .mobile-menu-btn {
            display: none;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 76px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1019;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                top: 76px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            main {
                margin-left: 0;
            }

            header .logo-container {
                width: auto !important;
            }

            .mobile-menu-btn {
                display: inline-block;
            }

            header h1 {
                font-size: 1rem !important;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            .sidebar-overlay {
                top: 0;
            }
        }

        @media (max-width: 576px) {
            header {
                height: auto;
                padding: 0.75rem !important;
            }

            header h1 {
                font-size: 1rem !important;
            }

            main {
                margin-top: 76px;
                padding: 0.75rem !important;
            }

            .sidebar {
                top: 76px;
            }

            .sidebar-overlay {
                top: 0;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <button class="btn btn-success mobile-menu-btn me-2" id="mobileMenuBtn" type="button">
            <i class="bi bi-list"></i>
        </button>
        <div class="me-4 logo-container" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">HOMEOWNER DASHBOARD</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span class="d-none d-md-inline">Hello, <?php echo htmlspecialchars($username); ?></span>
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
                            href="resident_details/view_resident.php?id=<?php echo $household_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="nav d-flex flex-column gap-1 sidebar-nav p-3">
                <a href="dashboard.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="amenity_booking/amenity_booking.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <a href="report.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!--Main Content-->
        <main class="flex-grow-1 p-4">
            <!-- Announcements and Events -->
            <div class="row g-4 mb-3">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Announcements</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 400px;">
                            <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                                <?php while ($row = $announcements_result->fetch_assoc()): ?>
                                    <div class="card mb-3 shadow-sm announcement-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="announcement-title"
                                                    style="font-weight: 600;font-size: 1rem;margin-bottom: 6px;">
                                                    <?= htmlspecialchars($row['title']); ?>
                                                </div>
                                            </div>
                                            <div class="announcement-body mt-2">
                                                <?= nl2br(htmlspecialchars($row['body'])); ?>
                                            </div>
                                            <div class="announcement-meta mt-1 text-muted" style="font-size: 0.8rem;">
                                                Posted by <?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                                on <?= date("M d, Y h:i A", strtotime($row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-megaphone" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No announcements available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Events</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 400px;">
                            <?php if ($events_result && $events_result->num_rows > 0): ?>
                                <?php while ($event_row = $events_result->fetch_assoc()): ?>
                                    <div class="card mb-3 shadow-sm event-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="event-title"
                                                    style="font-weight: 600;font-size: 1rem;margin-bottom: 6px;">
                                                    <?= htmlspecialchars($event_row['title']); ?>
                                                </div>
                                                <?php if (!empty($event_row['event_date'])): ?>
                                                    <div class="event-date mb-2">
                                                        <small class="badge bg-primary">
                                                            <i class="bi bi-calendar-event me-1"></i>
                                                            <?= date("M d, Y", strtotime($event_row['event_date'])); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="event-body mt-2">
                                                <?= nl2br(htmlspecialchars($event_row['body'])); ?>
                                            </div>
                                            <div class="event-meta mt-1 text-muted" style="font-size: 0.8rem;">
                                                Posted by
                                                <?= htmlspecialchars($event_row['first_name'] . " " . $event_row['last_name']); ?>
                                                on <?= date("M d, Y h:i A", strtotime($event_row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-event" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No events available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Amenity Schedule -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-semibold">Amenity Schedule</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">List of Amenity Bookings</span>
                        <a href="amenity_booking/choose_booking.php" class="btn btn-primary btn-sm">+ Create New
                            Booking</a>
                    </div>
                    <hr>
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
                                if ($bookings_result && $bookings_result->num_rows > 0) {
                                    while ($row = $bookings_result->fetch_assoc()) {
                                        $fullName = trim(ucwords($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']));
                                        $amenity = htmlspecialchars($row['amenity']);
                                        $bookingDate = date('F d, Y', strtotime($row['reservation_date']));
                                        $resCode = htmlspecialchars($row['reservation_code']);
                                        // Status styling
                                        $status = ucfirst($row['status']);
                                        $statusClass = '';
                                        switch (strtolower($row['status'])) {
                                            case 'paid':
                                                $statusClass = 'badge bg-success text-white';
                                                break;
                                            case 'partial':
                                                $statusClass = 'badge bg-primary text-white';
                                                break;
                                            case 'pending':
                                                $statusClass = 'badge bg-secondary text-dark';
                                                break;
                                            default:
                                                $statusClass = 'badge bg-warning text-dark';
                                        }
                                        echo "<tr>
                                    <td>{$bookingDate}</td>
                                    <td>{$fullName}</td>
                                    <td>{$amenity}</td>
                                    <td>{$resCode}</td>
                                    <td class='text-center'><span class='{$statusClass} fw-bold d-inline-flex align-items-center justify-content-center' style='min-width: 70px;'>{$status}</span</td>
                                  </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted'>No bookings found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalRecords > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalRecords); ?>
                                of <?php echo $totalRecords; ?> entries
                            </span>
                            <?php if ($totalPages > 1): ?>
                                <nav>
                                    <ul class="pagination pagination-sm justify-content-center mb-0">
                                        <!-- Previous button -->
                                        <li class="page-item <?php if ($page <= 1)
                                            echo 'disabled'; ?>">
                                            <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                                        </li>
                                        <!-- Page numbers -->
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($totalPages, $page + 2);

                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php if ($page == $i)
                                                echo 'active'; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <!-- Next button -->
                                        <li class="page-item <?php if ($page >= $totalPages)
                                            echo 'disabled'; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../admin_side/javascripts/mobileSidebar.js"></script>
</body>

</html>