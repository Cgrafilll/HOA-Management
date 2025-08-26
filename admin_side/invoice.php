<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = ''; // Initialize user details

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE email_address = ?");
    $stmt->bind_param("s", $email_address);
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
    }// Fetch invoices from amenity_bookings
    try {
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
                END AS full_name
            FROM amenity_bookings ab
            LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id
            LEFT JOIN visitor_details v ON ab.visitor_id = v.visitor_id
            ORDER BY ab.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $invoices = [];
        $error_message = "Error fetching invoices: " . $e->getMessage();
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
function getNumericAmount($amountStr) {
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

        .announcement-card {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            max-width: 100%;
        }

        .announcement-body {
            font-size: 0.95rem;
            margin: 0;
            margin-bottom: 8px;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        main {
            margin-left: 250px;
            padding-bottom: 100px;
            /* ✅ give breathing room at bottom */
        }

        .card-body p,
        .card-body h6 {
            word-wrap: break-word;
            /* Old support */
            overflow-wrap: break-word;
            /* Modern support */
            white-space: pre-wrap;
            /* Keeps newlines */
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

        /* Make Cancel button slightly darker on hover */
        #confirmModal .btn-cancel:hover {
            background-color: #d6d8db;
            /* slightly darker gray */
            color: #000;
        }

        .form-control.border-danger {
            border: 2px solid #dc3545 !important;
            /* force red */
        }

        textarea {
            min-height: 100px;
            resize: none;
            /* optional: prevent manual drag */
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
            <h1 class="h5 mb-0 fw-bold">COMMUNICATION</h1>
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary">Hello, <?php echo htmlspecialchars($username); ?></span>
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
                            <li><a href="entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
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
                            <li><a href="announcements.php" class="nav-link px-2 actived">Announcements</a></li>
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
                    <i class="bi bi-box-arrow-left me-2"></i> Logout
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
                        <button class="btn btn-primary btn-sm d-flex align-items-center">
                            <i class="bi bi-plus-lg me-1"></i> New Invoice
                        </button>
                    </div>
                    <div class="row g-3">
                        <!-- LEFT: List of invoices -->
                        <div class="col-md-4">
                            <div class="border rounded-3">
                                <div class="list-group list-group-flush">
                                    <?php if (!empty($invoices)): ?>
                                        <?php foreach ($invoices as $index => $inv): ?>
                                            <a href="?invoice=<?= urlencode($inv['invoice_number']); ?>" 
                                            class="list-group-item list-group-item-action border-0 <?= (isset($_GET['invoice']) && $_GET['invoice'] == $inv['invoice_number']) ? 'bg-light' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($inv['full_name']); ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($inv['invoice_number']); ?> | <?= htmlspecialchars($inv['reservation_date']); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-semibold">₱ <?= number_format($inv['total_amount'], 2); ?></div>
                                                        <small class="text-success fw-semibold"><?= strtoupper($inv['status']); ?></small>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="list-group-item text-center text-muted">No invoices found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- RIGHT: Invoice detail -->
                        <?php
                        $selectedInvoice = null;
                        if (isset($_GET['invoice'])) {
                            foreach ($invoices as $inv) {
                                if ($inv['invoice_number'] === $_GET['invoice']) {
                                    $selectedInvoice = $inv;
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="col-md-8">
                            <div class="border rounded-3">
                                <?php if ($selectedInvoice): ?>
                                    <!-- Status and Export header -->
                                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                                        <div class="fw-bold text-uppercase small">
                                            STATUS: <span class="text-success"><?= strtoupper($selectedInvoice['status']); ?></span>
                                        </div>
                                        <button class="btn btn-primary btn-sm">Export</button>
                                    </div>

                                    <div class="p-3">
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
                                                    <div class="mb-1"><span class="fw-semibold">Name:</span> <?= htmlspecialchars($selectedInvoice['full_name']); ?></div>
                                                    <div class="mb-1"><span class="fw-semibold">Reservation Date:</span> <?= htmlspecialchars($selectedInvoice['reservation_date']); ?></div>
                                                    <div><span class="fw-semibold">Reservation Code:</span> <?= htmlspecialchars($selectedInvoice['reservation_code']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-end small">
                                                    <div class="fw-bold mb-2 fs-6">Invoice No. <?= htmlspecialchars($selectedInvoice['invoice_number']); ?></div>
                                                    <div class="mb-1"><span class="fw-semibold">Payment Method:</span> <?= htmlspecialchars(ucfirst($selectedInvoice['payment_method'])); ?></div>
                                                    <div><span class="fw-semibold">Reference Number:</span> <?= htmlspecialchars($selectedInvoice['reference_number']); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Items table -->
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
                                                        <td class="text-end">₱ <?= number_format($selectedInvoice['chairs'] * 12, 2); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($selectedInvoice['tables'] > 0): ?>
                                                    <tr>
                                                        <td>Add-On</td>
                                                        <td>Tables</td>
                                                        <td class="text-end">₱ 15.00</td>
                                                        <td class="text-center"><?= $selectedInvoice['tables']; ?></td>
                                                        <td class="text-end">₱ <?= number_format($selectedInvoice['tables'] * 15, 2); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Summary -->
                                        <div class="d-flex justify-content-end">
                                            <div class="text-end small" style="min-width: 200px;">
                                                <?php
                                                    $chairsTotal = $selectedInvoice['chairs'] * 12;
                                                    $tablesTotal = $selectedInvoice['tables'] * 15;
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
                                    </div>
                                <?php else: ?>
                                    <div class="p-5 text-center text-muted">Select an invoice from the left to view details.</div>
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