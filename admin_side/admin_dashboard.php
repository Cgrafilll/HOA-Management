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
    header("Location: auth/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: auth/login.php?error=" . urlencode("Your session has expired. Please log in again."));
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

// Fetch analytics data
// 1. Monthly Dues Status Breakdown
$status_query = "SELECT status, COUNT(*) as count, SUM(balance_remaining) as total_amount FROM monthly_dues GROUP BY status";
$status_result = $conn->query($status_query);
$status_data = ['Pending' => 0, 'Partial' => 0, 'Paid' => 0];
$status_amounts = ['Pending' => 0, 'Partial' => 0, 'Paid' => 0];
while ($row = $status_result->fetch_assoc()) {
    $status_data[$row['status']] = $row['count'];
    $status_amounts[$row['status']] = $row['total_amount'];
}

// 2. Total Revenue by Month (Last 6 months)
$revenue_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC";
$revenue_result = $conn->query($revenue_query);
$revenue_months = [];
$revenue_amounts = [];
while ($row = $revenue_result->fetch_assoc()) {
    $revenue_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $revenue_amounts[] = floatval($row['total']);
}

// 3. Payment Category Breakdown
$category_query = "SELECT category, SUM(amount) as total FROM payments GROUP BY category";
$category_result = $conn->query($category_query);
$category_data = [];
$category_labels = [];
while ($row = $category_result->fetch_assoc()) {
    $category_labels[] = ucfirst(str_replace('_', ' ', $row['category']));
    $category_data[] = floatval($row['total']);
}

// 4. Collection Rate (Paid vs Outstanding)
$collection_query = "SELECT SUM(amount_paid) as total_paid, SUM(balance_remaining) as total_outstanding FROM monthly_dues";
$collection_result = $conn->query($collection_query);
$collection = $collection_result->fetch_assoc();
$total_paid = floatval($collection['total_paid']);
$total_outstanding = floatval($collection['total_outstanding']);

// 5. Key Metrics
$total_revenue_query = "SELECT SUM(amount) as total FROM payments";
$total_revenue_result = $conn->query($total_revenue_query);
$total_revenue = floatval($total_revenue_result->fetch_assoc()['total']);

$pending_count = $status_data['Pending'];
$paid_count = $status_data['Paid'];

// 6. Monthly Dues Trend (Last 6 months)
$dues_trend_query = "SELECT DATE_FORMAT(billing_month, '%Y-%m') as month, SUM(amount_paid) as paid, SUM(balance_remaining) as remaining FROM monthly_dues WHERE billing_month >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(billing_month, '%Y-%m') ORDER BY month ASC";
$dues_trend_result = $conn->query($dues_trend_query);
$dues_months = [];
$dues_paid = [];
$dues_remaining = [];
while ($row = $dues_trend_result->fetch_assoc()) {
    $dues_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $dues_paid[] = floatval($row['paid']);
    $dues_remaining[] = floatval($row['remaining']);
}

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
        .collapse ul li a:hover {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed) {
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
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ADMIN DASHBOARD</h1>
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
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav flex-column gap-1">
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="invoice.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
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
                            <div class="bg-danger bg-opacity-10 rounded-5 p-3">
                                <span class="text-danger fs-4">⚠️</span>
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
                            <div class="bg-primary bg-opacity-10 rounded-5 p-3">
                                <span class="text-primary fs-4">⏳</span>
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
                            <div class="bg-success bg-opacity-10 rounded-5 p-3">
                                <span class="text-success fs-4">🏠</span>
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
                <div class="col-md-3">
                    <div class="metric-card">
                        <h6 class="mb-2"><i class="bi bi-cash-stack me-2"></i>Total Revenue</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card green">
                        <h6 class="mb-2"><i class="bi bi-check-circle me-2"></i>Paid Invoices</h6>
                        <h3 class="mb-0"><?php echo $paid_count; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card orange">
                        <h6 class="mb-2"><i class="bi bi-clock-history me-2"></i>Pending</h6>
                        <h3 class="mb-0"><?php echo $pending_count; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card blue">
                        <h6 class="mb-2"><i class="bi bi-exclamation-circle me-2"></i>Outstanding</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_outstanding, 2); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-graph-up me-2"></i>Revenue Trend (Last 6 Months)
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-pie-chart me-2"></i>Invoice Status
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-bar-chart me-2"></i>Monthly Dues Collection
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="duesCollectionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-pie-chart-fill me-2"></i>Collection Rate
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="collectionRateChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <?php if (!empty($category_data)): ?>
                <div class="row g-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white fw-semibold">
                                <i class="bi bi-tags me-2"></i>Payment Category Breakdown
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Announcements and Events -->
            <div class="row g-4">
                <div class="col-6">
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
                <div class="col-md-6">
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
    <script>
        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenue_months); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($revenue_amounts); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => '₱' + context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₱' + value.toLocaleString('en-PH')
                        }
                    }
                }
            }
        });

        // Status Pie Chart
        const statusPieCtx = document.getElementById('statusPieChart').getContext('2d');
        new Chart(statusPieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Partial', 'Paid'],
                datasets: [{
                    data: [<?php echo $status_data['Pending']; ?>, <?php echo $status_data['Partial']; ?>, <?php echo $status_data['Paid']; ?>],
                    backgroundColor: ['#ffc107', '#17a2b8', '#198754'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Dues Collection Chart
        const duesCollectionCtx = document.getElementById('duesCollectionChart').getContext('2d');
        new Chart(duesCollectionCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dues_months); ?>,
                datasets: [
                    {
                        label: 'Paid',
                        data: <?php echo json_encode($dues_paid); ?>,
                        backgroundColor: '#198754',
                        borderRadius: 6
                    },
                    {
                        label: 'Outstanding',
                        data: <?php echo json_encode($dues_remaining); ?>,
                        backgroundColor: '#dc3545',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ₱' + context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₱' + value.toLocaleString('en-PH')
                        }
                    }
                }
            }
        });

        // Collection Rate Chart
        const collectionRateCtx = document.getElementById('collectionRateChart').getContext('2d');
        new Chart(collectionRateCtx, {
            type: 'doughnut',
            data: {
                labels: ['Collected', 'Outstanding'],
                datasets: [{
                    data: [<?php echo $total_paid; ?>, <?php echo $total_outstanding; ?>],
                    backgroundColor: ['#198754', '#dc3545'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.label + ': ₱' + context.parsed.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                        }
                    }
                }
            }
        });

        <?php if (!empty($category_data)): ?>
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($category_labels); ?>,
                    datasets: [{
                        label: 'Total Amount',
                        data: <?php echo json_encode($category_data); ?>,
                        backgroundColor: ['#198754', '#17a2b8'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => '₱' + context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => '₱' + value.toLocaleString('en-PH')
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>