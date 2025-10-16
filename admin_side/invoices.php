<?php
// ✅ Set session configuration BEFORE session_start()
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

if (!isset($_SESSION['admin_id'])) {
    header("Location: login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header("Location: login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

$_SESSION['last_activity'] = time();
$admin_id = $_SESSION['admin_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];
        if (!empty($user['profile_picture'])) {
            $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
        } else {
            $photo = '';
        }
    } else {
        $error_message = "Failed to fetch user details.";
    }

    // Get filter parameters - REMOVED STATUS FILTER
    $categoryFilter = $_GET['category'] ?? 'all';
    $searchQuery = $_GET['search'] ?? '';
    $invoices = [];

    try {
        // Build the WHERE clause based on filters
        $whereConditions = [];
        $params = [];
        $paramTypes = '';

        // Always show only paid/completed invoices (no status filter dropdown)
        $whereConditions[] = "LOWER(md.status) IN ('paid', 'completed')";

        // Category filter
        if ($categoryFilter !== 'all') {
            $whereConditions[] = "md.category = ?";
            $params[] = $categoryFilter;
            $paramTypes .= 's';
        }

        // Search filter
        if (!empty($searchQuery)) {
            $whereConditions[] = "(md.invoice_number LIKE ? OR md.household_id LIKE ? OR CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) LIKE ?)";
            $searchParam = '%' . $searchQuery . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $paramTypes .= 'sss';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Fetch monthly dues invoices with all categories
        $sql = "
            SELECT md.id, md.invoice_number, md.household_id, md.billing_month, md.amount_paid,
                   md.balance_remaining, md.due_date, md.status, md.category, md.description,
                   CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) AS full_name,
                   (md.amount_paid + md.balance_remaining) AS total_amount,
                   md.created_at, md.due_date AS sort_date
            FROM monthly_dues md
            LEFT JOIN household_accounts ha ON md.household_id = ha.household_id
            WHERE $whereClause
            ORDER BY md.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyDuesInvoices = $result->fetch_all(MYSQLI_ASSOC);

        // Fetch amenity bookings if category filter allows
        if ($categoryFilter === 'all' || $categoryFilter === 'amenity') {
            $amenityWhereConditions = [];
            $amenityParams = [];
            $amenityParamTypes = '';

            // Always show only paid amenity bookings
            $amenityWhereConditions[] = "LOWER(ab.status) IN ('paid', 'completed')";

            // Search filter for amenity
            if (!empty($searchQuery)) {
                $amenityWhereConditions[] = "(ab.invoice_number LIKE ? OR ab.reservation_code LIKE ? OR 
                    CASE 
                        WHEN ab.user_type = 'homeowner' THEN CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name)
                        WHEN ab.user_type = 'visitor' THEN CONCAT(v.first_name, ' ', v.middle_name, ' ', v.last_name)
                    END LIKE ?)";
                $searchParam = '%' . $searchQuery . '%';
                $amenityParams[] = $searchParam;
                $amenityParams[] = $searchParam;
                $amenityParams[] = $searchParam;
                $amenityParamTypes .= 'sss';
            }

            $amenityWhereClause = implode(' AND ', $amenityWhereConditions);

            $amenityStmt = $conn->prepare("
                SELECT ab.invoice_number, ab.reservation_code, ab.reservation_date, ab.created_at,
                       ab.total_amount, ab.amount_paid, (ab.total_amount - ab.amount_paid) AS balance_remaining,
                       ab.payment_method, ab.reference_number, ab.status, ab.amenity, ab.chairs, ab.tables,
                       ab.rate, ab.user_type, ab.guests,
                       CASE 
                           WHEN ab.user_type = 'homeowner' 
                               THEN CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name)
                           WHEN ab.user_type = 'visitor' 
                               THEN CONCAT(v.first_name, ' ', v.middle_name, ' ', v.last_name)
                           ELSE 'Unknown'
                       END AS full_name,
                       'amenity' AS category, ab.created_at AS sort_date
                FROM amenity_bookings ab
                LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id
                LEFT JOIN visitor_details v ON ab.visitor_id = v.visitor_id
                WHERE $amenityWhereClause
                ORDER BY ab.created_at DESC
            ");

            if (!empty($amenityParams)) {
                $amenityStmt->bind_param($amenityParamTypes, ...$amenityParams);
            }
            $amenityStmt->execute();
            $result = $amenityStmt->get_result();
            $amenityInvoices = $result->fetch_all(MYSQLI_ASSOC);
            $monthlyDuesInvoices = array_merge($monthlyDuesInvoices, $amenityInvoices);
        }

        $invoices = $monthlyDuesInvoices;
        usort($invoices, function ($a, $b) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        });

    } catch (Exception $e) {
        $invoices = [];
        $error_message = "Error fetching invoices: " . $e->getMessage();
    }

    $selectedInvoice = null;
    $activeInvoiceNumber = null;

    if (isset($_GET['invoice']) && !empty($_GET['invoice'])) {
        $activeInvoiceNumber = $_GET['invoice'];
        foreach ($invoices as $inv) {
            if ($inv['invoice_number'] === $_GET['invoice']) {
                $selectedInvoice = $inv;
                break;
            }
        }
    } elseif (!empty($invoices)) {
        $selectedInvoice = $invoices[0];
        $activeInvoiceNumber = $invoices[0]['invoice_number'];
    }

} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}

$amenityRates = [
    "Swimming Pool" => [
        "homeowner" => ["day" => "₱100.00 / per person", "night" => "₱200.00 / per person"],
        "visitor" => ["day" => "₱200.00 / per person", "night" => "₱300.00 / per person"]
    ],
    "Clubhouse" => [
        "homeowner" => ["day" => "₱12,000.00", "night" => "₱12,000.00"],
        "visitor" => ["day" => "₱15,000.00", "night" => "₱15,000.00"]
    ],
    "Basketball Court" => [
        "homeowner" => ["day" => "₱200.00 / per person", "night" => "₱300.00 / per person"],
        "visitor" => ["day" => "₱300.00 / per person", "night" => "₱400.00 / per person"]
    ],
    "Gazebo" => [
        "homeowner" => ["day" => "₱1,000.00", "night" => "₱2,000.00"],
        "visitor" => ["day" => "₱2,000.00", "night" => "₱3,000.00"]
    ]
];

function getNumericAmount($amountStr)
{
    return floatval(preg_replace('/[^\d.]/', '', $amountStr));
}

function getCategoryDisplayName($category)
{
    $names = [
        'monthly_dues' => 'Monthly Dues',
        'penalty_fees' => 'Penalty Fees',
        'other_fees' => 'Other Fees',
        'amenity' => 'Amenity Fees'
    ];
    return $names[$category] ?? ucwords(str_replace('_', ' ', $category));
}

function getCategoryIcon($category)
{
    $icons = [
        'monthly_dues' => '🏠',
        'penalty_fees' => '⚠️',
        'other_fees' => '📝',
        'amenity' => '🎯'
    ];
    return $icons[$category] ?? '📄';
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

        .invoice {
            transition: all 0.3s ease-in-out;
            cursor: pointer;
        }

        .invoice.active {
            border-color: #198754 !important;
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
            border-left: 4px solid #198754 !important;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2) !important;
            transform: translateX(2px);
        }

        .invoice:not(.active):hover {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            transform: translateX(1px);
        }

        .invoice.active h6 {
            color: #0f5132 !important;
            font-weight: 700 !important;
        }

        .invoice.active small {
            font-weight: 600 !important;
        }

        /* Category Badges */
        .category-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-monthly-dues {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .badge-penalty-fees {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-other-fees {
            background-color: #cff4fc;
            color: #055160;
        }

        .badge-amenity {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Print Styles */
        @media print {

            .sidebar,
            header,
            .btn,
            .invoice-list-container,
            form {
                display: none !important;
            }

            main {
                margin-left: 0 !important;
                margin-top: 0 !important;
            }

            .col-md-4 {
                display: none !important;
            }

            .col-md-8 {
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        /* Tablet Styles */
        @media (max-width: 992px) {
            .invoice-list-container {
                max-height: 400px;
            }

            .table-responsive {
                font-size: 0.9rem;
            }

            .category-badge {
                font-size: 0.65rem;
            }
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

            .sidebar-overlay {
                top: 0;
            }

            /* Stack columns on mobile */
            .col-md-4,
            .col-md-8 {
                margin-bottom: 1rem;
            }

            .invoice-list-container {
                max-height: 300px;
            }

            .invoice h6 {
                font-size: 0.9rem;
            }

            .invoice .small {
                font-size: 0.75rem;
            }

            .category-badge {
                font-size: 0.6rem;
                padding: 0.15rem 0.35rem;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 0.5rem;
            }

            /* Form controls on mobile */
            .form-control,
            .form-select {
                font-size: 0.875rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            /* Invoice details section */
            .fw-bold.mb-1 {
                font-size: 0.9rem;
            }

            .text-muted.mb-3 {
                font-size: 0.75rem;
            }

            /* Make invoice detail sections stack */
            .row.mb-3 .col-8,
            .row.mb-3 .col-4 {
                width: 100% !important;
                max-width: 100% !important;
            }

            .row.mb-3 .col-4 {
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            header {
                height: auto;
                padding: 0.75rem !important;
            }

            header h1 {
                font-size: 0.9rem !important;
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

            .bg-white.shadow.rounded {
                padding: 0.5rem !important;
            }

            .bg-success.text-white.rounded-top {
                padding: 0.75rem !important;
            }

            .bg-success.text-white.rounded-top h5 {
                font-size: 1rem;
            }

            /* Even smaller invoice cards */
            .invoice-list-container {
                max-height: 250px;
            }

            .invoice {
                padding: 0.5rem !important;
            }

            .invoice h6 {
                font-size: 0.85rem;
            }

            .invoice .small {
                font-size: 0.7rem;
            }

            .category-badge {
                font-size: 0.55rem;
                padding: 0.1rem 0.3rem;
            }

            /* Table adjustments */
            .table-responsive {
                font-size: 0.75rem;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 0.35rem;
            }

            /* Filter section */
            .row.g-2 {
                gap: 0.5rem !important;
            }

            .col-md-6,
            .col-md-4,
            .col-md-2 {
                width: 100% !important;
                max-width: 100% !important;
                margin-bottom: 0.5rem;
            }

            /* Invoice header company info */
            .fw-bold.mb-1 {
                font-size: 0.85rem;
            }

            .text-muted.mb-3,
            .small {
                font-size: 0.7rem;
                line-height: 1.3;
            }

            /* Summary section */
            .d-flex.justify-content-end>div {
                min-width: 100% !important;
            }

            /* Alert boxes */
            .alert {
                font-size: 0.8rem;
                padding: 0.75rem;
            }

            .alert h6 {
                font-size: 0.85rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 375px) {
            header h1 {
                font-size: 0.85rem !important;
            }

            .invoice h6 {
                font-size: 0.8rem;
            }

            .table-responsive {
                font-size: 0.7rem;
            }

            .category-badge {
                font-size: 0.5rem;
            }
        }

        /* Landscape mobile adjustments */
        @media (max-width: 768px) and (orientation: landscape) {
            .invoice-list-container {
                max-height: 200px;
            }

            main {
                padding: 0.5rem !important;
            }
        }
    </style>
</head>

<body class="bg-light">
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <button class="btn btn-success mobile-menu-btn me-2" id="mobileMenuBtn" type="button">
            <i class="bi bi-list"></i>
        </button>
        <div class="me-4 logo-container" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ACCOUNTING</h1>
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
        <aside class="sidebar">
            <nav class="nav flex-column gap-1 sidebar-nav p-3">
                <a href="admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center"><i class="bi bi-person-lines-fill me-2"></i>
                            Accounts</span>
                    </button>
                    <div class="collapse" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center"><i class="bi bi-book me-2"></i> Record Keeping</span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
                        </ul>
                    </div>
                </div>
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center"><i class="bi bi-chat-left-text me-2"></i>
                            Communication</span>
                    </button>
                    <div class="collapse" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2">Phone Book</a></li>
                        </ul>
                    </div>
                </div>
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../payment.php" class="nav-link px-2">Payment</a></li>
                            <li><a href="../billing.php" class="nav-link px-2">List of Billings</a></li>
                            <li><a href="../invoices.php" class="nav-link px-2 actived">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <main class="flex-grow-1 p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Invoices</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-semibold">List of Paid Invoices</div>
                    </div>
                    <!-- FILTERS: SEARCH BAR + CATEGORY ONLY -->
                    <form method="get" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Search by invoice#, household, or name..."
                                        value="<?= htmlspecialchars($searchQuery) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="category" id="category" class="form-select form-select-sm"
                                    onchange="this.form.submit()">
                                    <option value="all" <?= $categoryFilter == 'all' ? 'selected' : '' ?>>All Categories
                                    </option>
                                    <option value="monthly_dues" <?= $categoryFilter == 'monthly_dues' ? 'selected' : '' ?>>Monthly Dues</option>
                                    <option value="penalty_fees" <?= $categoryFilter == 'penalty_fees' ? 'selected' : '' ?>>Penalty Fees</option>
                                    <option value="other_fees" <?= $categoryFilter == 'other_fees' ? 'selected' : '' ?>>
                                        Other Fees</option>
                                    <option value="amenity" <?= $categoryFilter == 'amenity' ? 'selected' : '' ?>>Amenity
                                        Fees</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-success w-100">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3" style="max-height: 600px;">
                                <div class="list-group list-group-flush">
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $inv): ?>
                                            <?php
                                            $queryParams = [
                                                'category' => $categoryFilter,
                                                'search' => $searchQuery,
                                                'invoice' => $inv['invoice_number']
                                            ];
                                            $queryString = http_build_query($queryParams);
                                            ?>
                                            <a href="?<?= $queryString ?>"
                                                class="list-group-item list-group-item-action invoice <?= ($inv['invoice_number'] === $activeInvoiceNumber) ? 'active' : '' ?>">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <?= getCategoryIcon($inv['category']) ?>
                                                            #<?= htmlspecialchars($inv['invoice_number']); ?>
                                                        </h6>
                                                        <p class="mb-1 small">
                                                            <?= htmlspecialchars($inv['full_name'] ?? 'No Name'); ?>
                                                        </p>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <span class="category-badge badge-<?= $inv['category'] ?>">
                                                                <?= getCategoryDisplayName($inv['category']) ?>
                                                            </span>
                                                            <small class="fw-bold text-success">
                                                                <?= ucfirst($inv['status']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php
                                                        if (!empty($inv['due_date'])) {
                                                            echo date('M d, Y', strtotime($inv['due_date']));
                                                        } elseif (!empty($inv['created_at'])) {
                                                            echo date('M d, Y', strtotime($inv['created_at']));
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-5 text-muted medium">No paid invoices found.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <!-- Invoice details section remains the same as previous response -->
                            <div class="border rounded-3">
                                <?php if ($selectedInvoice): ?>
                                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                                        <div class="fw-bold text-uppercase small">
                                            STATUS: <span
                                                class="text-success"><?= strtoupper($selectedInvoice['status']); ?></span>
                                        </div>
                                        <button class="btn btn-primary btn-sm" onclick="window.print()">
                                            <i class="bi bi-download me-1"></i>Export
                                        </button>
                                    </div>
                                    <div class="p-3">
                                        <?php
                                        $category = $selectedInvoice['category'];

                                        // MONTHLY DUES
                                        if ($category === 'monthly_dues'): ?>
                                            <!-- Monthly Dues Template (same as before) -->
                                            <div class="row mb-3">
                                                <div class="col-8">
                                                    <div class="fw-bold mb-1">NEOPOLITAN SITIO SEVILLE HOMEOWNERS INC.</div>
                                                    <div class="small text-muted mb-3">
                                                        NON VAT REG. TIN: 404-587-404-0000<br>
                                                        NSSHAI Clubhouse Narra St. Neopolitan Sitio Seville<br>
                                                        North Fairview III-B Quezon City NCR, Second District Philippines
                                                    </div>
                                                    <div class="small">
                                                        <div class="mb-1"><span class="fw-semibold">Name:</span>
                                                            <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Household ID:</span>
                                                            <?= htmlspecialchars($selectedInvoice['household_id']); ?></div>
                                                        <div><span class="fw-semibold">Billing Period:</span>
                                                            <?php if (!empty($selectedInvoice['billing_month'])): ?>
                                                                <?= date('F Y', strtotime($selectedInvoice['billing_month'])); ?>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-end small">
                                                        <div class="fw-bold mb-2 fs-6">Invoice No.
                                                            <?= htmlspecialchars($selectedInvoice['invoice_number']); ?>
                                                        </div>
                                                        <div class="mb-1"><span class="fw-semibold">Due Date:</span>
                                                            <?= date('M d, Y', strtotime($selectedInvoice['due_date'])); ?>
                                                        </div>
                                                        <div><span class="fw-semibold">Invoice Type:</span> Monthly Dues</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-3">
                                                    <thead class="table-success">
                                                        <tr class="small">
                                                            <th>Description</th>
                                                            <th>Period</th>
                                                            <th class="text-end">Amount Due</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="small">
                                                        <tr>
                                                            <td>HOA Monthly Dues</td>
                                                            <td>
                                                                <?php if (!empty($selectedInvoice['billing_month'])): ?>
                                                                    <?= date('F Y', strtotime($selectedInvoice['billing_month'])); ?>
                                                                <?php else: ?>
                                                                    N/A
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end">₱
                                                                <?= number_format($selectedInvoice['total_amount'], 2); ?>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <div class="text-end small" style="min-width: 200px;">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Total Amount</span>
                                                        <span>₱
                                                            <?= number_format($selectedInvoice['total_amount'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Amount Paid</span>
                                                        <span>₱ <?= number_format($selectedInvoice['amount_paid'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold border-top pt-1">
                                                        <span>Balance Due</span>
                                                        <span>₱
                                                            <?= number_format($selectedInvoice['balance_remaining'], 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php
                                            // PENALTY FEES OR OTHER FEES
                                        elseif ($category === 'penalty_fees' || $category === 'other_fees'): ?>
                                            <div class="row mb-3">
                                                <div class="col-8">
                                                    <div class="fw-bold mb-1">NEOPOLITAN SITIO SEVILLE HOMEOWNERS INC.</div>
                                                    <div class="small text-muted mb-3">
                                                        NON VAT REG. TIN: 404-587-404-0000<br>
                                                        NSSHAI Clubhouse Narra St. Neopolitan Sitio Seville<br>
                                                        North Fairview III-B Quezon City NCR, Second District Philippines
                                                    </div>
                                                    <div class="small">
                                                        <div class="mb-1"><span class="fw-semibold">Name:</span>
                                                            <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Household ID:</span>
                                                            <?= htmlspecialchars($selectedInvoice['household_id']); ?></div>
                                                        <div><span class="fw-semibold">Invoice Date:</span>
                                                            <?= date('M d, Y', strtotime($selectedInvoice['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-end small">
                                                        <div class="fw-bold mb-2 fs-6">Invoice No.
                                                            <?= htmlspecialchars($selectedInvoice['invoice_number']); ?>
                                                        </div>
                                                        <div class="mb-1"><span class="fw-semibold">Due Date:</span>
                                                            <?= date('M d, Y', strtotime($selectedInvoice['due_date'])); ?>
                                                        </div>
                                                        <div><span class="fw-semibold">Category:</span>
                                                            <?= getCategoryDisplayName($category); ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Description Section -->
                                            <div class="alert alert-info mb-3">
                                                <h6 class="alert-heading mb-2">
                                                    <i class="bi bi-info-circle me-2"></i>Fee Description
                                                </h6>
                                                <p class="mb-0 small">
                                                    <?= nl2br(htmlspecialchars($selectedInvoice['description'] ?? 'No description available')); ?>
                                                </p>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-3">
                                                    <thead class="table-success">
                                                        <tr class="small">
                                                            <th>Description</th>
                                                            <th class="text-end">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="small">
                                                        <tr>
                                                            <td><?= getCategoryDisplayName($category); ?></td>
                                                            <td class="text-end">₱
                                                                <?= number_format($selectedInvoice['total_amount'], 2); ?>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <div class="text-end small" style="min-width: 200px;">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Total Amount</span>
                                                        <span>₱
                                                            <?= number_format($selectedInvoice['total_amount'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Amount Paid</span>
                                                        <span>₱ <?= number_format($selectedInvoice['amount_paid'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold border-top pt-1">
                                                        <span>Balance Due</span>
                                                        <span>₱
                                                            <?= number_format($selectedInvoice['balance_remaining'], 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php
                                            // AMENITY BOOKING
                                        elseif ($category === 'amenity'): ?>
                                            <div class="row mb-3">
                                                <div class="col-8">
                                                    <div class="fw-bold mb-1">NEOPOLITAN SITIO SEVILLE HOMEOWNERS INC.</div>
                                                    <div class="small text-muted mb-3">
                                                        NON VAT REG. TIN: 404-587-404-0000<br>
                                                        NSSHAI Clubhouse Narra St. Neopolitan Sitio Seville<br>
                                                        North Fairview III-B Quezon City NCR, Second District Philippines
                                                    </div>
                                                    <div class="small">
                                                        <div class="mb-1"><span class="fw-semibold">Name:</span>
                                                            <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Reservation Date:</span>
                                                            <?= htmlspecialchars($selectedInvoice['reservation_date']); ?></div>
                                                        <div><span class="fw-semibold">Reservation Code:</span>
                                                            <?= htmlspecialchars($selectedInvoice['reservation_code']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-end small">
                                                        <div class="fw-bold mb-2 fs-6">Invoice No.
                                                            <?= htmlspecialchars($selectedInvoice['invoice_number']); ?>
                                                        </div>
                                                        <div class="mb-1"><span class="fw-semibold">Payment Method:</span>
                                                            <?= htmlspecialchars(ucfirst($selectedInvoice['payment_method'])); ?>
                                                        </div>
                                                        <?php if (strtolower($selectedInvoice['payment_method']) !== 'cash'): ?>
                                                            <div><span class="fw-semibold">Reference Number:</span>
                                                                <?= htmlspecialchars($selectedInvoice['reference_number']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-3">
                                                    <thead class="table-success">
                                                        <tr class="small">
                                                            <th>Category</th>
                                                            <th>Item</th>
                                                            <th class="text-end">Rate</th>
                                                            <th class="text-center">Qty</th>
                                                            <th class="text-end">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="small">
                                                        <?php
                                                        $amenity = $selectedInvoice['amenity'];
                                                        $userType = $selectedInvoice['user_type'];
                                                        $dayOrNight = $selectedInvoice['rate'];
                                                        $numGuests = $selectedInvoice['guests'] ?? 1;
                                                        $rateStr = $amenityRates[$amenity][$userType][$dayOrNight] ?? "₱0.00";
                                                        $numericRate = getNumericAmount($rateStr);
                                                        if (in_array($amenity, ['Swimming Pool', 'Basketball Court'])) {
                                                            $qty = $numGuests;
                                                            $totalAmount = $numericRate * $qty;
                                                        } else {
                                                            $qty = 1;
                                                            $totalAmount = $numericRate;
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td>Amenity</td>
                                                            <td><?= htmlspecialchars($amenity); ?></td>
                                                            <td class="text-end"><?= $rateStr; ?></td>
                                                            <td class="text-center"><?= $qty; ?></td>
                                                            <td class="text-end">₱ <?= number_format($totalAmount, 2); ?></td>
                                                        </tr>
                                                        <?php if ($selectedInvoice['chairs'] > 0): ?>
                                                            <tr>
                                                                <td>Add-On</td>
                                                                <td>Chairs</td>
                                                                <td class="text-end">₱ 12.00</td>
                                                                <td class="text-center"><?= $selectedInvoice['chairs']; ?></td>
                                                                <td class="text-end">₱
                                                                    <?= number_format($selectedInvoice['chairs'] * 12, 2); ?>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                        <?php if ($selectedInvoice['tables'] > 0): ?>
                                                            <tr>
                                                                <td>Add-On</td>
                                                                <td>Tables</td>
                                                                <td class="text-end">₱ 20.00</td>
                                                                <td class="text-center"><?= $selectedInvoice['tables']; ?></td>
                                                                <td class="text-end">₱
                                                                    <?= number_format($selectedInvoice['tables'] * 20, 2); ?>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <div class="text-end small" style="min-width: 200px;">
                                                    <?php
                                                    $chairsTotal = $selectedInvoice['chairs'] * 12;
                                                    $tablesTotal = $selectedInvoice['tables'] * 20;
                                                    $subtotal = $totalAmount + $chairsTotal + $tablesTotal;
                                                    $amountPaid = $selectedInvoice['amount_paid'];
                                                    $balanceRemaining = $subtotal - $amountPaid;
                                                    ?>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Subtotal</span>
                                                        <span>₱ <?= number_format($subtotal, 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Previously Paid</span>
                                                        <span>₱ <?= number_format($amountPaid, 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold border-top pt-1">
                                                        <span>Balance Due</span>
                                                        <span>₱ <?= number_format($balanceRemaining, 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-5 text-center text-muted">No paid invoices available to display.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/mobileSidebar.js"></script>
</body>

</html>