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

// Authentication check - MODIFIED FOR RESIDENT SIDE
if (!isset($_SESSION['household_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

$_SESSION['last_activity'] = time();

// Get resident data - MODIFIED FOR RESIDENT SIDE
$household_id = $_SESSION['household_id'];
$stmt = $conn->prepare("SELECT * FROM household_accounts WHERE household_id = ?");
$stmt->bind_param("s", $household_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();

if (!$resident) {
    exit("Resident not found.");
}

// Initialize user details for display
$residentname = $resident['first_name'];
$photo = !empty($resident['profile_picture'])
    ? 'data:image/jpeg;base64,' . base64_encode($resident['profile_picture'])
    : '';

try {
    $filter = $_GET['filter'] ?? 'paid';
    $invoices = [];

    try {
        // Get monthly dues invoices for THIS resident only
        $stmt = $conn->prepare("
            SELECT md.id, md.invoice_number, md.household_id, md.billing_month, md.amount_paid,
                   md.balance_remaining, md.due_date, md.status, md.category,
                   CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) AS full_name,
                   (md.amount_paid + md.balance_remaining) AS total_amount,
                   'monthly_dues' AS source_table, md.due_date AS sort_date
            FROM monthly_dues md
            LEFT JOIN household_accounts ha ON md.household_id = ha.household_id
            WHERE md.household_id = ? AND LOWER(md.status) = ?
        ");
        $filterStatus = strtolower($filter);
        $stmt->bind_param("ss", $household_id, $filterStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyDuesInvoices = $result->fetch_all(MYSQLI_ASSOC);

        // Get amenity bookings for THIS resident only
        $stmt = $conn->prepare("
            SELECT ab.invoice_number, ab.reservation_code, ab.reservation_date, ab.created_at,
                   ab.total_amount, ab.amount_paid, (ab.total_amount - ab.amount_paid) AS balance_remaining,
                   ab.payment_method, ab.reference_number, ab.status, ab.amenity, ab.chairs, ab.tables,
                   ab.rate, ab.user_type, ab.guests,
                   CONCAT(ha.first_name, ' ', ha.middle_name, ' ', ha.last_name) AS full_name,
                   'amenity_bookings' AS source_table, ab.created_at AS sort_date
            FROM amenity_bookings ab
            LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id
            WHERE ab.homeowner_id = ? AND LOWER(ab.status) = ?
        ");
        $stmt->bind_param("ss", $household_id, $filterStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        $amenityInvoices = $result->fetch_all(MYSQLI_ASSOC);

        // Merge and sort invoices
        $invoices = array_merge($monthlyDuesInvoices, $amenityInvoices);
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
    $error_message = "Error fetching invoices: " . $e->getMessage();
}

$amenityRates = [
    "Swimming Pool" => ["homeowner" => ["day" => "₱100.00 / per person", "night" => "₱200.00 / per person"],
                        "visitor" => ["day" => "₱200.00 / per person", "night" => "₱300.00 / per person"]],
    "Clubhouse" => ["homeowner" => ["day" => "₱12,000.00", "night" => "₱12,000.00"],
                    "visitor" => ["day" => "₱15,000.00", "night" => "₱15,000.00"]],
    "Basketball Court" => ["homeowner" => ["day" => "₱200.00 / per person", "night" => "₱300.00 / per person"],
                           "visitor" => ["day" => "₱300.00 / per person", "night" => "₱400.00 / per person"]],
    "Gazebo" => ["homeowner" => ["day" => "₱1,000.00", "night" => "₱2,000.00"],
                 "visitor" => ["day" => "₱2,000.00", "night" => "₱3,000.00"]]
];

function getNumericAmount($amountStr) {
    return floatval(preg_replace('/[^\d.]/', '', $amountStr));
}

// Helper function to get category display name
function getCategoryDisplayName($dbCategory) {
    $categoryMap = [
        'monthly_dues' => 'Monthly Dues',
        'penalty_fees' => 'Penalty Fees',
        'other_fees' => 'Other Fees'
    ];
    return $categoryMap[$dbCategory] ?? 'Monthly Dues';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management - My Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');
        * { font-family: "Montserrat", sans-serif; }
        header { position: sticky; top: 0; z-index: 1030; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 20; left: 0; background-color: #1F2937; overflow-y: auto; }
        main { margin-left: 250px; }
        .sidebar a, .sidebar button { color: #ffffff; text-decoration: none; display: flex; align-items: center; justify-content: space-between; }
        .sidebar a:hover, .sidebar button:hover, .collapse ul li a:hover, .collapse ul li a.actived { color: #80ed99; }
        .sidebar .nav-link.active, .sidebar .btn-toggle:not(.collapsed), .sidebar .btn-toggle.active { background-color: #198754; border-radius: 0.375rem; }
        .sidebar .btn-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; color: #ffffff; background: none; border: none; }
        .sidebar .btn-toggle i { margin-right: 8px; }
        .sidebar .btn-toggle::after { content: "▼"; font-size: 10px; transition: transform 0.3s; margin-left: auto; }
        .sidebar .btn-toggle.collapsed::after { transform: rotate(0deg); }
        .sidebar .btn-toggle:not(.collapsed)::after { transform: rotate(180deg); }
        .invoice.active { border-color: #198754 !important; background-color: #d1e7dd !important; color: #0f5132 !important; border-left: 4px solid #198754 !important; box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2) !important; transform: translateX(2px); transition: all 0.2s ease-in-out; }
        .invoice:not(.active):hover { background-color: #f8f9fa; border-color: #dee2e6; transform: translateX(1px); transition: all 0.2s ease-in-out; }
        .invoice.active h6 { color: #0f5132 !important; font-weight: 700 !important; }
        .invoice.active small { font-weight: 600 !important; }
        .invoice { transition: all 0.3s ease-in-out; }
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
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="residentDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?php echo htmlspecialchars($residentname); ?></span>
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
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="residentDropdown">
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
    <div class="d-flex">
        <!-- Sidebar (Use your existing resident sidebar) -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="amenity_booking/amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="violations.php" class="nav-link px-2">Violations</a></li>
                        </ul>
                    </div>
                </div>
                <a href="report.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-exclamation-triangle me-2"></i> Report Violation
                </a>
                <!-- Accounting (Active) -->
                <div>
                    <button
                        class="btn btn-toggle collapsed px-3 rounded py-2 d-flex align-items-center justify-content-start active"
                        data-bs-toggle="collapse" data-bs-target="#acctCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2 active"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="invoices.php" class="nav-link px-2 actived">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">My Invoices</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-semibold">Invoice History</div>
                    </div>
                    <form method="get" class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <label for="filter" class="fw-semibold">Filter by Status:</label>
                            <select name="filter" id="filter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                <option value="paid" <?= $filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="partial" <?= $filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                            </select>
                        </div>
                    </form>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3">
                                <div class="list-group list-group-flush">
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $inv): ?>
                                            <a href="?filter=<?= htmlspecialchars($filter) ?>&invoice=<?= htmlspecialchars($inv['invoice_number']); ?>" class="list-group-item list-group-item-action invoice <?= ($inv['invoice_number'] === $activeInvoiceNumber) ? 'active' : '' ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">Invoice #<?= htmlspecialchars($inv['invoice_number']); ?></h6>
                                                    <?php if (isset($inv['source_table'])): ?>
                                                        <?php if ($inv['source_table'] === 'monthly_dues' && !empty($inv['due_date'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['due_date'])); ?></small>
                                                        <?php elseif ($inv['source_table'] === 'amenity_bookings' && !empty($inv['created_at'])): ?>
                                                            <small><?= date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mb-1 small">
                                                    <?php if ($inv['source_table'] === 'monthly_dues'): ?>
                                                        <?= getCategoryDisplayName($inv['category'] ?? 'monthly_dues'); ?>
                                                    <?php else: ?>
                                                        Amenity Booking
                                                    <?php endif; ?>
                                                </p>
                                                <small class="fw-bold <?= strtolower($inv['status']) === 'paid' || strtolower($inv['status']) === 'completed' ? 'text-success' : (strtolower($inv['status']) === 'partial' ? 'text-warning' : 'text-danger') ?>">
                                                    <?= ucfirst($inv['status']); ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-5 text-muted text-center">No invoices found.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="border rounded-3">
                                <?php if ($selectedInvoice): ?>
                                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                                        <div class="fw-bold text-uppercase small">
                                            STATUS: 
                                            <span class="<?= strtolower($selectedInvoice['status']) === 'paid' || strtolower($selectedInvoice['status']) === 'completed' ? 'text-success' : (strtolower($selectedInvoice['status']) === 'partial' ? 'text-warning' : 'text-danger') ?>">
                                                <?= strtoupper($selectedInvoice['status']); ?>
                                            </span>
                                        </div>
                                        <button class="btn btn-primary btn-sm" onclick="window.print()">
                                            <i class="bi bi-download me-1"></i>Export
                                        </button>
                                    </div>
                                    <div class="p-3">
                                        <?php
                                        $isMonthlyDues = isset($selectedInvoice['source_table']) ? 
                                            ($selectedInvoice['source_table'] === 'monthly_dues') : 
                                            (isset($selectedInvoice['billing_month']) && isset($selectedInvoice['household_id']));
                                        if ($isMonthlyDues): ?>
                                            <!-- Monthly Dues Invoice Template -->
                                            <div class="row mb-3">
                                                <div class="col-8">
                                                    <div class="fw-bold mb-1">NEOPOLITAN SITIO SEVILLE HOMEOWNERS INC.</div>
                                                    <div class="small text-muted mb-3">
                                                        NON VAT REG. TIN: 404-587-404-0000<br>
                                                        NSSHAI Clubhouse Narra St. Neopolitan Sitio Seville<br>
                                                        North Fairview III-B Quezon City NCR, Second District Philippines
                                                    </div>
                                                    <div class="small">
                                                        <div class="mb-1"><span class="fw-semibold">Name:</span> <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Household ID:</span> <?= htmlspecialchars($selectedInvoice['household_id']); ?></div>
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
                                                        <div class="fw-bold mb-2 fs-6">Invoice No. <?= htmlspecialchars($selectedInvoice['invoice_number']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Due Date:</span> <?= date('M d, Y', strtotime($selectedInvoice['due_date'])); ?></div>
                                                        <div><span class="fw-semibold">Invoice Type:</span> <?= getCategoryDisplayName($selectedInvoice['category'] ?? 'monthly_dues'); ?></div>
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
                                                            <td><?= getCategoryDisplayName($selectedInvoice['category'] ?? 'monthly_dues'); ?></td>
                                                            <td>
                                                                <?php if (!empty($selectedInvoice['billing_month'])): ?>
                                                                    <?= date('F Y', strtotime($selectedInvoice['billing_month'])); ?>
                                                                <?php else: ?>
                                                                    N/A
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end">₱ <?= number_format($selectedInvoice['total_amount'], 2); ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <div class="text-end small" style="min-width: 200px;">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Total Amount</span>
                                                        <span>₱ <?= number_format($selectedInvoice['total_amount'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-semibold">Amount Paid</span>
                                                        <span>₱ <?= number_format($selectedInvoice['amount_paid'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold border-top pt-1">
                                                        <span>Balance Due</span>
                                                        <span>₱ <?= number_format($selectedInvoice['balance_remaining'], 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Amenity Booking Invoice Template -->
                                            <div class="row mb-3">
                                                <div class="col-8">
                                                    <div class="fw-bold mb-1">NEOPOLITAN SITIO SEVILLE HOMEOWNERS INC.</div>
                                                    <div class="small text-muted mb-3">
                                                        NON VAT REG. TIN: 404-587-404-0000<br>
                                                        NSSHAI Clubhouse Narra St. Neopolitan Sitio Seville<br>
                                                        North Fairview III-B Quezon City NCR, Second District Philippines
                                                    </div>
                                                    <div class="small">
                                                        <div class="mb-1"><span class="fw-semibold">Name:</span> <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Reservation Date:</span> <?= htmlspecialchars($selectedInvoice['reservation_date']); ?></div>
                                                        <div><span class="fw-semibold">Reservation Code:</span> <?= htmlspecialchars($selectedInvoice['reservation_code']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-end small">
                                                        <div class="fw-bold mb-2 fs-6">Invoice No. <?= htmlspecialchars($selectedInvoice['invoice_number']); ?></div>
                                                        <div class="mb-1"><span class="fw-semibold">Payment Method:</span> <?= htmlspecialchars(ucfirst($selectedInvoice['payment_method'])); ?></div>
                                                        <?php if (strtolower($selectedInvoice['payment_method']) !== 'cash'): ?>
                                                            <div><span class="fw-semibold">Reference Number:</span> <?= htmlspecialchars($selectedInvoice['reference_number']); ?></div>
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
                                                                <td class="text-end">₱ <?= number_format($selectedInvoice['chairs'] * 12, 2); ?></td>
                                                            </tr>
                                                        <?php endif; ?>
                                                        <?php if ($selectedInvoice['tables'] > 0): ?>
                                                            <tr>
                                                                <td>Add-On</td>
                                                                <td>Tables</td>
                                                                <td class="text-end">₱ 20.00</td>
                                                                <td class="text-center"><?= $selectedInvoice['tables']; ?></td>
                                                                <td class="text-end">₱ <?= number_format($selectedInvoice['tables'] * 20, 2); ?></td>
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