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

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
    $stmt->bind_param("s", $admin_id);
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
    // Fetch invoices from amenity_bookings OR monthly_dues based on filter
    $filter = $_GET['filter'] ?? '--';

    try {
        if ($filter === '--') {
            // ✅ Fetch ALL invoices from both tables when no filter is selected
            $invoices = [];

            // Fetch from monthly_dues
            $stmt = $conn->prepare("
            SELECT 
                md.id,
                md.invoice_number,
                md.household_id,
                md.billing_month,
                md.amount_paid,
                md.balance_remaining,
                md.due_date,
                md.status,
                CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) AS full_name,
                (md.amount_paid + md.balance_remaining) AS total_amount,
                'monthly_dues' AS source_table,
                md.due_date AS sort_date
            FROM monthly_dues md
            LEFT JOIN household_accounts ha ON md.household_id = ha.household_id
        ");
            $stmt->execute();
            $result = $stmt->get_result();
            $monthlyDuesInvoices = $result->fetch_all(MYSQLI_ASSOC);

            // Fetch from amenity_bookings
            $stmt = $conn->prepare("
            SELECT 
                ab.invoice_number,
                ab.reservation_code,
                ab.reservation_date,
                ab.created_at,
                ab.total_amount,
                ab.amount_paid,
                (ab.total_amount - ab.amount_paid) AS balance_remaining,
                ab.payment_method,
                ab.reference_number,
                ab.status,
                ab.amenity,
                ab.chairs,
                ab.tables,
                ab.rate,
                ab.user_type,
                ab.guests,
                CASE 
                    WHEN ab.user_type = 'homeowner' 
                        THEN CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name)
                    WHEN ab.user_type = 'visitor' 
                        THEN CONCAT(v.first_name, ' ', v.middle_name, ' ', v.last_name)
                    ELSE 'Unknown'
                END AS full_name,
                'amenity_bookings' AS source_table,
                ab.created_at AS sort_date
            FROM amenity_bookings ab
            LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id
            LEFT JOIN visitor_details v ON ab.visitor_id = v.visitor_id
        ");
            $stmt->execute();
            $result = $stmt->get_result();
            $amenityInvoices = $result->fetch_all(MYSQLI_ASSOC);

            // Combine both arrays
            $invoices = array_merge($monthlyDuesInvoices, $amenityInvoices);

            // Sort by date (most recent first)
            usort($invoices, function ($a, $b) {
                return strtotime($b['sort_date']) - strtotime($a['sort_date']);
            });

        } elseif ($filter === 'monthly_dues') {
            // Fetch from monthly_dues
            $stmt = $conn->prepare("
            SELECT 
                md.id,
                md.invoice_number,
                md.household_id,
                md.billing_month,
                md.amount_paid,
                md.balance_remaining,
                md.due_date,
                md.status,
                CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) AS full_name,
                (md.amount_paid + md.balance_remaining) AS total_amount,
                'monthly_dues' AS source_table
            FROM monthly_dues md
            LEFT JOIN household_accounts ha ON md.household_id = ha.household_id
            ORDER BY md.due_date DESC
        ");
            $stmt->execute();
            $result = $stmt->get_result();
            $invoices = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            // Fetch from amenity_bookings
            $stmt = $conn->prepare("
            SELECT 
                ab.invoice_number,
                ab.reservation_code,
                ab.reservation_date,
                ab.created_at,
                ab.total_amount,
                ab.amount_paid,
                (ab.total_amount - ab.amount_paid) AS balance_remaining,
                ab.payment_method,
                ab.reference_number,
                ab.status,
                ab.amenity,
                ab.chairs,
                ab.tables,
                ab.rate,
                ab.user_type,
                ab.guests,
                CASE 
                    WHEN ab.user_type = 'homeowner' 
                        THEN CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name)
                    WHEN ab.user_type = 'visitor' 
                        THEN CONCAT(v.first_name, ' ', v.middle_name, ' ', v.last_name)
                    ELSE 'Unknown'
                END AS full_name,
                'amenity_bookings' AS source_table
            FROM amenity_bookings ab
            LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id
            LEFT JOIN visitor_details v ON ab.visitor_id = v.visitor_id
            ORDER BY ab.created_at DESC
        ");
            $stmt->execute();
            $result = $stmt->get_result();
            $invoices = $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        $invoices = [];
        $error_message = "Error fetching invoices: " . $e->getMessage();
    }

    // ✅ Auto-select first invoice if none is specifically requested
    $selectedInvoice = null;
    $activeInvoiceNumber = null;

    if (isset($_GET['invoice']) && !empty($_GET['invoice'])) {
        // Specific invoice requested
        $activeInvoiceNumber = $_GET['invoice'];
        foreach ($invoices as $inv) {
            if ($inv['invoice_number'] === $_GET['invoice']) {
                $selectedInvoice = $inv;
                break;
            }
        }
    } elseif (!empty($invoices)) {
        // No specific invoice requested, auto-select first one
        $selectedInvoice = $invoices[0];
        $activeInvoiceNumber = $invoices[0]['invoice_number'];
    }

} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}
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

// Get numeric amount from formatted string (removes ₱ and commas)
function getNumericAmount($amountStr)
{
    $amountStr = preg_replace('/[^\d.]/', '', $amountStr);
    return floatval($amountStr);
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

        .invoice.active {
            border-color: #198754 !important;
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
            border-left: 4px solid #198754 !important;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2) !important;
            transform: translateX(2px);
            transition: all 0.2s ease-in-out;
        }

        .invoice:not(.active):hover {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            transform: translateX(1px);
            transition: all 0.2s ease-in-out;
        }

        .invoice.active h6 {
            color: #0f5132 !important;
            font-weight: 700 !important;
        }

        .invoice.active small {
            font-weight: 600 !important;
        }

        .invoice {
            transition: all 0.3s ease-in-out;
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
            <h1 class="h5 mb-0 fw-bold">ACCOUNTING</h1>
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
                        data-bs-target="#commCollapse" aria-expanded="true">
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
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="invoice.php" class="nav-link px-2 actived">Invoices</a></li>
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
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <!-- Top bar -->
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Payments</h5>
                </div>
                <div class="p-3">
                    <!-- Button row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-semibold">List of Invoices</div>
                        <a href="invoice/add_invoice.php" class="btn btn-primary btn-sm d-flex align-items-center">
                            <i class="bi bi-plus-lg me-1"></i> New Invoice
                        </a>
                    </div>
                    <!-- Filter Dropdown -->
                    <form method="get" class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <label for="filter" class="fw-semibold">Filter by:</label>
                            <select name="filter" id="filter" class="form-select form-select-sm w-auto"
                                onchange="this.form.submit()">
                                <option value="--" <?= (!isset($_GET['filter']) || $_GET['filter'] == '--') ? 'selected' : '' ?>>All</option>
                                <option value="amenities" <?= (isset($_GET['filter']) && $_GET['filter'] == 'amenities') ? 'selected' : '' ?>>
                                    Amenities
                                </option>
                                <option value="monthly_dues" <?= (isset($_GET['filter']) && $_GET['filter'] == 'monthly_dues') ? 'selected' : '' ?>>
                                    Monthly Dues
                                </option>
                            </select>
                        </div>
                    </form>
                    <div class="row g-3">
                        <!-- LEFT: List of invoices -->
                        <div class="col-md-4">
                            <div class="border rounded-3">
                                <div class="list-group list-group-flush">
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $index => $inv): ?>
                                            <a href="?filter=<?= htmlspecialchars($filter) ?>&invoice=<?= htmlspecialchars($inv['invoice_number']); ?>"
                                                class="list-group-item list-group-item-action invoice <?= ($inv['invoice_number'] === $activeInvoiceNumber) ? 'active' : '' ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">Invoice #<?= htmlspecialchars($inv['invoice_number']); ?>
                                                    </h6>
                                                    <!-- Show different date info based on source -->
                                                    <?php if (isset($inv['source_table'])): ?>
                                                        <?php if ($inv['source_table'] === 'monthly_dues' && !empty($inv['due_date'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['due_date'])); ?></small>
                                                        <?php elseif ($inv['source_table'] === 'amenity_bookings' && !empty($inv['created_at'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- Fallback for filtered results -->
                                                        <?php if ($filter === 'monthly_dues' && !empty($inv['due_date'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['due_date'])); ?></small>
                                                        <?php elseif ($filter === 'amenities' && !empty($inv['created_at'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mb-1 small">
                                                    <?= htmlspecialchars($inv['full_name'] ?? 'No Name'); ?>
                                                </p>
                                                <small class="fw-bold <?php
                                                $status = strtolower($inv['status']);
                                                if ($status === 'pending') {
                                                    echo 'text-warning';
                                                } elseif ($status === 'partial') {
                                                    echo 'text-info';
                                                } else {
                                                    echo 'text-success';
                                                }
                                                ?>">
                                                    <?= htmlspecialchars(ucfirst($inv['status'])); ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-5 text-muted medium">No invoices found.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="border rounded-3">
                                <?php if ($selectedInvoice): ?>
                                    <!-- Status and Export header -->
                                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                                        <div class="fw-bold text-uppercase small">
                                            STATUS: <span class="<?php
                                            $status = strtolower($selectedInvoice['status']);
                                            $statusColor = 'text-success'; // Default for 'Paid'
                                            if ($status === 'pending') {
                                                $statusColor = 'text-warning';
                                            } elseif ($status === 'partial') {
                                                $statusColor = 'text-info';
                                            }
                                            echo $statusColor;
                                            ?>"><?= strtoupper($selectedInvoice['status']); ?></span>
                                        </div>
                                        <button class="btn btn-primary btn-sm">Export</button>
                                    </div>
                                    <div class="p-3">
                                        <?php
                                        // Determine if this is a monthly dues invoice
                                        $isMonthlyDues = false;
                                        if (isset($selectedInvoice['source_table'])) {
                                            $isMonthlyDues = ($selectedInvoice['source_table'] === 'monthly_dues');
                                        } else {
                                            // Fallback: check if it has monthly dues specific fields
                                            $isMonthlyDues = isset($selectedInvoice['billing_month']) && isset($selectedInvoice['household_id']);
                                        }

                                        if ($isMonthlyDues): ?>
                                            <!-- Monthly Dues Invoice Detail -->
                                            <!-- Company Header -->
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
                                                            <?= date('F Y', strtotime($selectedInvoice['billing_month'])); ?>
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
                                                        <div><span class="fw-semibold">Invoice Type:</span>
                                                            Monthly Dues</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Monthly Dues Items table -->
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
                                                            <td><?= date('F Y', strtotime($selectedInvoice['billing_month'])); ?>
                                                            </td>
                                                            <td class="text-end">₱
                                                                <?= number_format($selectedInvoice['total_amount'], 2); ?>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <!-- Monthly Dues Summary -->
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
                                        <?php else: ?>
                                            <!-- Amenity Booking Invoice Detail -->
                                            <!-- Company Header -->
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

                                            <!-- Amenity Items table -->
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
                                                        $dayOrNight = $selectedInvoice['rate']; // "day" or "night"
                                                        $numGuests = $selectedInvoice['guests'] ?? 1; // fallback 1 just in case
                                                
                                                        $rateStr = $amenityRates[$amenity][$userType][$dayOrNight] ?? "₱0.00";
                                                        $numericRate = getNumericAmount($rateStr);

                                                        // Determine total based on amenity type
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
                                            <!-- Amenity Summary -->
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
                                    <div class="p-5 text-center text-muted">No invoices available to display.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>