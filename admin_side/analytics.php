<?php
// Session configuration
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
require '../rfid-api/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header("Location: auth/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

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

$username = $admin['first_name'];
$photo = '';
if (!empty($admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']);
}

// === ANALYTICS DATA ===

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
    if ($row['status'] == 'pending') $amenity_pending = intval($row['count']);
    if ($row['status'] == 'partial') $amenity_partial = intval($row['count']);
    if ($row['status'] == 'paid') $amenity_paid = intval($row['count']);
}

// 7. Monthly Dues Status
$dues_status_query = "SELECT status, COUNT(*) as count FROM monthly_dues GROUP BY status";
$dues_status_result = $conn->query($dues_status_query);
$dues_pending = 0;
$dues_partial = 0;
$dues_paid = 0;
while ($row = $dues_status_result->fetch_assoc()) {
    if ($row['status'] == 'Pending') $dues_pending = intval($row['count']);
    if ($row['status'] == 'Partial') $dues_partial = intval($row['count']);
    if ($row['status'] == 'Paid') $dues_paid = intval($row['count']);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - NSSHAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');
        * { font-family: "Montserrat", sans-serif; }
        header { position: sticky; top: 0; z-index: 1030; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #1F2937; overflow-y: auto; }
        main { margin-left: 250px; }
        .sidebar a, .sidebar button { color: #ffffff; text-decoration: none; display: flex; align-items: center; }
        .sidebar a:hover, .sidebar button:hover { color: #80ed99; }
        .sidebar .nav-link.active { background-color: #198754; border-radius: 0.375rem; }
        .chart-container { position: relative; height: 300px; }
        .metric-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 20px; }
        .metric-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .metric-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .metric-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    </style>
</head>
<body class="bg-light">
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">COMPREHENSIVE ANALYTICS</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown">
                    <span>Hello, <?php echo htmlspecialchars($username); ?></span>
                    <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-circle" style="height: 40px; width: 40px;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>" style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 32px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="admin/view_admin.php?id=<?php echo $admin_id; ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="login/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="d-flex">
        <aside class="sidebar p-3">
            <nav class="nav flex-column gap-1">
                <a href="admin_dashboard.php" class="nav-link px-3 py-2 rounded"><i class="bi bi-house me-2"></i>Home</a>
                <a href="analytics.php" class="nav-link px-3 py-2 rounded active"><i class="bi bi-graph-up me-2"></i>Analytics</a>
                <a href="login/logout.php" class="nav-link px-3 py-2 rounded" style="position: fixed; bottom: 0; width: 220px;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </aside>

        <main class="flex-grow-1 p-4">
            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="metric-card">
                        <h6 class="mb-2"><i class="bi bi-arrow-bar-right me-2"></i>Total Entries</h6>
                        <h3 class="mb-0"><?php echo number_format($total_entries); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card green">
                        <h6 class="mb-2"><i class="bi bi-arrow-bar-left me-2"></i>Total Exits</h6>
                        <h3 class="mb-0"><?php echo number_format($total_exits); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card orange">
                        <h6 class="mb-2"><i class="bi bi-cash-stack me-2"></i>Total Revenue</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card blue">
                        <h6 class="mb-2"><i class="bi bi-exclamation-circle me-2"></i>Outstanding</h6>
                        <h3 class="mb-0">₱<?php echo number_format($total_outstanding, 2); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Entry/Exit Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-arrow-bar-right me-2"></i>Entry Logs by Type
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="entryTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-arrow-bar-left me-2"></i>Exit Logs by Type
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="exitTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-credit-card me-2"></i>Payment Methods
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entry Trend -->
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-graph-up me-2"></i>Entry Traffic (Last 7 Days)
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
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-trophy me-2"></i>Top 5 Amenities Booked
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
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-tags me-2"></i>Revenue by Category
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
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-calendar-check me-2"></i>Amenity Booking Status
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
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-cash-coin me-2"></i>Monthly Dues Status
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="duesStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Entry Type Chart
        new Chart(document.getElementById('entryTypeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Visitors', 'Households'],
                datasets: [{
                    data: [<?php echo $entry_visitor; ?>, <?php echo $entry_household; ?>],
                    backgroundColor: ['#ffc107', '#198754']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Exit Type Chart
        new Chart(document.getElementById('exitTypeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Visitors', 'Households'],
                datasets: [{
                    data: [<?php echo $exit_visitor; ?>, <?php echo $exit_household; ?>],
                    backgroundColor: ['#dc3545', '#17a2b8']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

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

        // Payment Method Chart
        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($payment_methods); ?>,
                datasets: [{
                    data: <?php echo json_encode($payment_counts); ?>,
                    backgroundColor: ['#198754', '#17a2b8', '#ffc107']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Top Amenities Chart
        new Chart(document.getElementById('topAmenitiesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($amenity_names); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode($amenity_bookings); ?>,
                    backgroundColor: '#198754',
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
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
                    tooltip: { callbacks: { label: (c) => '₱' + c.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2}) } }
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
</body>
</html>