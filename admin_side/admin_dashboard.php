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

require '../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
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

// Fetch announcements from database
$sql = "SELECT a.id, a.title, a.body, a.status, a.created_at, 
               ad.first_name, ad.last_name 
        FROM announcements a 
        LEFT JOIN admin_accounts ad ON a.admin_id = ad.admin_id 
        WHERE a.status = 'published' 
        ORDER BY a.created_at DESC";

$result = $conn->query($sql);

// Fetch events from database
$events_sql = "SELECT e.id, e.title, e.body, e.status, e.event_date, e.created_at, 
                      ad.first_name, ad.last_name 
               FROM events e 
               LEFT JOIN admin_accounts ad ON e.admin_id = ad.admin_id 
               WHERE e.status = 'published' 
               ORDER BY e.event_date ASC, e.created_at DESC";

$events_result = $conn->query($events_sql);

// Fetch household count
$household_count = 0;
try {
    $household_stmt = $conn->prepare("SELECT COUNT(*) as total_households FROM household_accounts");
    $household_stmt->execute();
    $household_result = $household_stmt->get_result();
    $household_data = $household_result->fetch_assoc();
    $household_count = $household_data['total_households'];
    $household_stmt->close();
} catch (Exception $e) {
    $household_count = 0;
    error_log("Error fetching household count: " . $e->getMessage());
}

// Fetch household count
$violation_count = 0;
try {
    $violations_stmt = $conn->prepare("SELECT COUNT(*) as total_violations FROM violations");
    $violations_stmt->execute();
    $violations_result = $violations_stmt->get_result();
    $violations_data = $violations_result->fetch_assoc();
    $violation_count = $violations_data['total_violations'];
    $violations_stmt->close();
} catch (Exception $e) {
    $violation_count = 0;
    error_log("Error fetching violations count: " . $e->getMessage());
}

// 1. Entry Logs - By Type
$entry_type_query = "SELECT type, COUNT(*) as count FROM entry_logs GROUP BY type";
$entry_type_result = $conn->query($entry_type_query);
$entry_visitor = 0;
$entry_household = 0;
while ($row = $entry_type_result->fetch_assoc()) {
    if ($row['type'] == 'visitor') {
        $entry_visitor = intval($row['count']);
    } else {
        $entry_household = intval($row['count']);
    }
}

// 2. Exit Logs - By Type
$exit_type_query = "SELECT type, COUNT(*) as count FROM exit_logs GROUP BY type";
$exit_type_result = $conn->query($exit_type_query);
$exit_visitor = 0;
$exit_household = 0;
while ($row = $exit_type_result->fetch_assoc()) {
    if ($row['type'] == 'visitor') {
        $exit_visitor = intval($row['count']);
    } else {
        $exit_household = intval($row['count']);
    }
}

// 3. Entry/Exit Trend (Last 7 days)
$entry_trend_query = "SELECT DATE(date_created) as day, type, COUNT(*) as count FROM entry_logs WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(date_created), type ORDER BY day ASC";
$entry_trend_result = $conn->query($entry_trend_query);
$entry_days = [];
$entry_visitor_trend = [];
$entry_household_trend = [];
$temp_entry = [];
while ($row = $entry_trend_result->fetch_assoc()) {
    $day = date('M d', strtotime($row['day']));
    if (!in_array($day, $entry_days)) {
        $entry_days[] = $day;
    }
    $temp_entry[$day][$row['type']] = intval($row['count']);
}
foreach ($entry_days as $day) {
    $entry_visitor_trend[] = $temp_entry[$day]['visitor'] ?? 0;
    $entry_household_trend[] = $temp_entry[$day]['household'] ?? 0;
}

// 4. Payment Method Breakdown
$payment_method_query = "SELECT payment_method, COUNT(*) as count FROM payments GROUP BY payment_method";
$payment_method_result = $conn->query($payment_method_query);
$payment_methods = [];
$payment_counts = [];
while ($row = $payment_method_result->fetch_assoc()) {
    $payment_methods[] = ucfirst($row['payment_method']);
    $payment_counts[] = intval($row['count']);
}

// 5. Top 5 Amenities Booked
$top_amenities_query = "SELECT amenity, COUNT(*) as bookings FROM amenity_bookings GROUP BY amenity ORDER BY bookings DESC LIMIT 5";
$top_amenities_result = $conn->query($top_amenities_query);
$amenity_names = [];
$amenity_bookings = [];
while ($row = $top_amenities_result->fetch_assoc()) {
    $amenity_names[] = $row['amenity'];
    $amenity_bookings[] = intval($row['bookings']);
}

// 6. Amenity Booking Status
$amenity_status_query = "SELECT status, COUNT(*) as count FROM amenity_bookings GROUP BY status";
$amenity_status_result = $conn->query($amenity_status_query);
$amenity_pending = 0;
$amenity_partial = 0;
$amenity_paid = 0;
while ($row = $amenity_status_result->fetch_assoc()) {
    if ($row['status'] == 'pending')
        $amenity_pending = intval($row['count']);
    if ($row['status'] == 'partial')
        $amenity_partial = intval($row['count']);
    if ($row['status'] == 'paid')
        $amenity_paid = intval($row['count']);
}

// 7. Monthly Dues Status
$dues_status_query = "SELECT status, COUNT(*) as count FROM monthly_dues GROUP BY status";
$dues_status_result = $conn->query($dues_status_query);
$dues_pending = 0;
$dues_partial = 0;
$dues_paid = 0;
while ($row = $dues_status_result->fetch_assoc()) {
    if ($row['status'] == 'Pending')
        $dues_pending = intval($row['count']);
    if ($row['status'] == 'Partial')
        $dues_partial = intval($row['count']);
    if ($row['status'] == 'Paid')
        $dues_paid = intval($row['count']);
}

// 8. Revenue by Category
$revenue_category_query = "SELECT category, SUM(amount) as total FROM payments GROUP BY category";
$revenue_category_result = $conn->query($revenue_category_query);
$revenue_categories = [];
$revenue_amounts = [];
while ($row = $revenue_category_result->fetch_assoc()) {
    $revenue_categories[] = ucfirst(str_replace('_', ' ', $row['category']));
    $revenue_amounts[] = floatval($row['total']);
}

// 9. Key Metrics
$total_entries = $entry_visitor + $entry_household;
$total_exits = $exit_visitor + $exit_household;
$total_revenue_query = "SELECT SUM(amount) as total FROM payments";
$total_revenue = floatval($conn->query($total_revenue_query)->fetch_assoc()['total'] ?? 0);
$total_outstanding_query = "SELECT SUM(balance_remaining) as total FROM monthly_dues";
$total_outstanding = floatval($conn->query($total_outstanding_query)->fetch_assoc()['total'] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

        .chart-container {
            position: relative;
            height: 300px;
        }

        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 1rem;
        }

        .metric-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .metric-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .metric-card.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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

            .chart-container {
                height: 250px;
            }

            .metric-card {
                padding: 15px;
            }

            .metric-card h3 {
                font-size: 1.5rem;
            }

            .metric-card h6 {
                font-size: 0.9rem;
            }

            .card-header {
                font-size: 0.9rem;
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

            .chart-container {
                height: 200px;
            }

            .metric-card {
                padding: 12px;
            }

            .metric-card h3 {
                font-size: 1.25rem;
            }

            .metric-card h6 {
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="shadow-sm py-3 px-4 d-flex align-items-center">
        <button class="btn btn-success mobile-menu-btn me-2" id="mobileMenuBtn" type="button">
            <i class="bi bi-list"></i>
        </button>
        <div class="me-4 logo-container" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ADMIN DASHBOARD</h1>
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
                    <li><a class="dropdown-item" href="admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="nav flex-column gap-1 sidebar-nav p-3">
                <a href="admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
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
                            <li><a href="admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
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
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2">Phone Book</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payment</a></li>
                            <li><a href="billing.php" class="nav-link px-2">List of Billings</a></li>
                            <li><a href="invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-grow-1 p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 60px; height: 60px;">
                                <i class="bi bi-exclamation-octagon text-danger fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo number_format($violation_count); ?></h5>
                                <small class="text-muted">Violations</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 60px; height: 60px;">
                                <i class="bi bi-hourglass text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">12</h5>
                                <small class="text-muted">Bookings</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 60px; height: 60px;">
                                <i class="bi bi-house-check text-success fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo number_format($household_count); ?></h5>
                                <small class="text-muted">Households</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="metric-card">
                        <h6 class="mb-2"><i class="bi bi-arrow-bar-right me-2"></i>Total Entries</h6>
                        <h3 class="mb-0"><?php echo number_format($total_entries); ?></h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="metric-card green">
                        <h6 class="mb-2"><i class="bi bi-arrow-bar-left me-2"></i>Total Exits</h6>
                        <h3 class="mb-0"><?php echo number_format($total_exits); ?></h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="metric-card orange">
                        <h6 class="mb-2"><i class="bi bi-cash-stack me-2"></i>Total Revenue</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="metric-card blue">
                        <h6 class="mb-2"><i class="bi bi-exclamation-circle me-2"></i>Outstanding</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_outstanding, 2); ?></h3>
                    </div>
                </div>
            </div>
            <!-- Entry Trend -->
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div
                            class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                            <i class="bi bi-graph-up me-2"></i>Entry Traffic (Last 7 Days)
                            <button class="btn btn-sm btn-outline-light"
                                onclick="exportChartToPDF('entryTrendChart', 'Entry_Traffic_Report')">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="entryTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Amenity & Dues -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div
                            class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                            <i class="bi bi-trophy me-2"></i>Top 5 Amenities Booked
                            <button class="btn btn-sm btn-outline-light"
                                onclick="exportChartToPDF('topAmenitiesChart', 'Top_Amenities_Report')">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="topAmenitiesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div
                            class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                            <i class="bi bi-tags me-2"></i>Revenue by Category
                            <button class="btn btn-sm btn-outline-light"
                                onclick="exportChartToPDF('revenueCategoryChart', 'Revenue_Category_Report')">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueCategoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Status Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div
                            class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                            <i class="bi bi-calendar-check me-2"></i>Amenity Booking Status
                            <button class="btn btn-sm btn-outline-light"
                                onclick="exportChartToPDF('amenityStatusChart', 'Amenity_Status_Report')">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="amenityStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div
                            class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                            <i class="bi bi-cash-coin me-2"></i>Monthly Dues Status
                            <button class="btn btn-sm btn-outline-light"
                                onclick="exportChartToPDF('duesStatusChart', 'Monthly_Dues_Report')">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="duesStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Announcements and Events -->
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Announcements</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 300px;">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
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
                        <div class="card-footer bg-light text-end">
                            <a href="announcements.php" class="btn btn-success btn-sm">View Announcements</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Events</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 300px;">
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
                        <div class="card-footer bg-light text-end d-flex justify-content-end gap-2">
                            <a href="events.php" class="btn btn-success btn-sm">View Events</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/mobileSidebar.js"></script>
    <script>
        // Entry Trend Chart
        new Chart(document.getElementById('entryTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($entry_days); ?>,
                datasets: [{
                    label: 'Visitors',
                    data: <?php echo json_encode($entry_visitor_trend); ?>,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Households',
                    data: <?php echo json_encode($entry_household_trend); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });

        // Top Amenities Chart
        new Chart(document.getElementById('topAmenitiesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($amenity_names); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode($amenity_bookings); ?>,
                    backgroundColor: [
                        '#198754', // Clubhouse - Green
                        '#0d6efd', // Swimming Pool - Blue
                        '#ffc107', // Gazebo - Yellow
                        '#dc3545'  // Basketball - Red
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            generateLabels: function (chart) {
                                const data = chart.data;
                                return data.labels.map((label, i) => ({
                                    text: label,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    hidden: false,
                                    index: i
                                }));
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Revenue Category Chart
        new Chart(document.getElementById('revenueCategoryChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($revenue_categories); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($revenue_amounts); ?>,
                    backgroundColor: ['#198754', '#17a2b8'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => '₱' + c.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 }) } }
                },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Amenity Status Chart
        new Chart(document.getElementById('amenityStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Partial', 'Paid'],
                datasets: [{
                    data: [<?php echo $amenity_pending; ?>, <?php echo $amenity_partial; ?>, <?php echo $amenity_paid; ?>],
                    backgroundColor: ['#ffc107', '#17a2b8', '#198754']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Dues Status Chart
        new Chart(document.getElementById('duesStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Partial', 'Paid'],
                datasets: [{
                    data: [<?php echo $dues_pending; ?>, <?php echo $dues_partial; ?>, <?php echo $dues_paid; ?>],
                    backgroundColor: ['#ffc107', '#17a2b8', '#198754']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    </script>
    <script>
        async function exportChartToPDF(chartId, filename) {
            const { jsPDF } = window.jspdf;
            const canvas = document.getElementById(chartId);
            const chart = Chart.getChart(chartId);

            // Create PDF
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();

            // Add header
            pdf.setFontSize(18);
            pdf.setTextColor(25, 135, 84);
            pdf.text('NSSHAI HOA Management', pageWidth / 2, 20, { align: 'center' });

            pdf.setFontSize(14);
            pdf.setTextColor(0, 0, 0);
            pdf.text(filename.replace(/_/g, ' '), pageWidth / 2, 30, { align: 'center' });

            pdf.setFontSize(10);
            pdf.setTextColor(128, 128, 128);
            pdf.text('Generated on: ' + new Date().toLocaleString(), pageWidth / 2, 38, { align: 'center' });

            // Add chart image
            const imgData = canvas.toDataURL('image/png');
            pdf.addImage(imgData, 'PNG', 15, 45, pageWidth - 30, 100);

            // Add data table
            let yPosition = 155;
            pdf.setFontSize(12);
            pdf.setTextColor(0, 0, 0);
            pdf.setFont(undefined, 'bold');
            pdf.text('Chart Data:', 15, yPosition);
            yPosition += 10;

            pdf.setFontSize(10);
            pdf.setDrawColor(200, 200, 200);

            // Define column widths and positions
            const col1X = 20;
            const col2X = 100;
            const col3X = 140;
            const rowHeight = 8;
            const tableWidth = pageWidth - 30;

            // Table headers background
            pdf.setFillColor(25, 135, 84);
            pdf.rect(15, yPosition, tableWidth, rowHeight, 'F');

            // Table headers text
            pdf.setTextColor(255, 255, 255);
            pdf.setFont(undefined, 'bold');

            if (chart.config.type === 'line' || chart.config.type === 'bar') {
                pdf.text('Label', col1X, yPosition + 5.5);
                chart.data.datasets.forEach((dataset, index) => {
                    pdf.text(dataset.label, col2X + (index * 40), yPosition + 5.5);
                });
            } else if (chart.config.type === 'doughnut' || chart.config.type === 'pie') {
                pdf.text('Category', col1X, yPosition + 5.5);
                pdf.text('Value', col2X, yPosition + 5.5);
                pdf.text('Percentage', col3X, yPosition + 5.5);
            }

            yPosition += rowHeight + 2; // Add spacing after header

            // Table data
            pdf.setTextColor(0, 0, 0);
            pdf.setFont(undefined, 'normal');
            let rowColor = true;

            if (chart.config.type === 'line' || chart.config.type === 'bar') {
                chart.data.labels.forEach((label, index) => {
                    if (yPosition > pageHeight - 20) {
                        pdf.addPage();
                        yPosition = 20;
                    }

                    // Alternating row colors
                    if (rowColor) {
                        pdf.setFillColor(245, 245, 245);
                        pdf.rect(15, yPosition - 1, tableWidth, 7, 'F');
                    }
                    rowColor = !rowColor;

                    pdf.text(label, col1X, yPosition + 4);
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        pdf.text(String(dataset.data[index] || 0), col2X + (datasetIndex * 40), yPosition + 4);
                    });
                    yPosition += 7;
                });
            } else if (chart.config.type === 'doughnut' || chart.config.type === 'pie') {
                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                chart.data.labels.forEach((label, index) => {
                    if (yPosition > pageHeight - 20) {
                        pdf.addPage();
                        yPosition = 20;
                    }

                    // Alternating row colors
                    if (rowColor) {
                        pdf.setFillColor(245, 245, 245);
                        pdf.rect(15, yPosition - 1, tableWidth, 7, 'F');
                    }
                    rowColor = !rowColor;

                    const value = chart.data.datasets[0].data[index];
                    const percentage = ((value / total) * 100).toFixed(1);

                    pdf.text(label, col1X, yPosition + 4);
                    pdf.text(String(value), col2X, yPosition + 4);
                    pdf.text(percentage + '%', col3X, yPosition + 4);
                    yPosition += 7;
                });

                // Add separator space
                yPosition += 3;

                // Add total row background
                pdf.setFillColor(25, 135, 84);
                pdf.rect(15, yPosition - 1, tableWidth, rowHeight, 'F');

                // Add total row text
                pdf.setTextColor(255, 255, 255);
                pdf.setFont(undefined, 'bold');
                pdf.text('Total:', col1X, yPosition + 4.5);
                pdf.text(String(total), col2X, yPosition + 4.5);
                pdf.text('100%', col3X, yPosition + 4.5);
            }

            // Save PDF
            pdf.save(filename + '_' + new Date().toISOString().split('T')[0] + '.pdf');
        }
    </script>
</body>

</html>