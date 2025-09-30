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

// Check if resident is logged in
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

// Initialize resident details
$residentname = $resident['first_name']; // <- Set residentname directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($resident['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($resident['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
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

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .file-drop-area:hover {
            border-color: #6b7280;
            background-color: #f3f4f6;
        }

        .file-drop-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .cloud-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 16px;
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
        <!-- Sidebar -->
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
                <!-- Accounting -->
                <div>
                    <button
                        class="btn btn-toggle collapsed px-3 rounded py-2 d-flex align-items-center justify-content-start actuve"
                        data-bs-toggle="collapse" data-bs-target="#acctCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="payment.php" class="nav-link px-2 actived">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
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
            <div class="bg-white shadow rounded p-4">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Payments</h5>
                </div>

                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Payment Management</span>
                    </div>
                    <hr class="mb-3 mt-0">

                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-8">
                            <!-- Payment Method Selection -->
                            <div class="d-flex gap-3 mb-3">
                                <div class="card method-card flex-fill text-center p-3 border active" id="bankTransfer">
                                    <div><i class="bi bi-bank" style="font-size: 2rem;"></i></div>
                                    <h6 class="mt-2">EastWest Bank Transfer</h6>
                                </div>
                                <div class="card method-card flex-fill text-center p-3 border" id="inOffice">
                                    <div><i class="bi bi-building" style="font-size: 2rem;"></i></div>
                                    <h6 class="mt-2">In-Office Payment</h6>
                                </div>
                            </div>

                            <!-- Payment Form -->
                            <form id="paymentForm">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">User Type<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userTypeSelect" required>
                                            <option value="">Select User Type</option>
                                            <option value="Homeowner/Resident">Homeowner/Resident</option>
                                            <option value="Visitor">Visitor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" id="idLabel">Select ID<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userIdSelect" disabled required>
                                            <option value="">First select user type</option>
                                        </select>
                                        <div class="loading d-none" id="loadingIndicator">
                                            <i class="bi bi-arrow-clockwise"></i> Loading available IDs...
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Category<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="categorySelect" required>
                                            <option value="">Select Category</option>
                                            <option value="Monthly Dues">Monthly Dues</option>
                                            <option value="Amenity Fee">Amenity Fee</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Invoice Number<small
                                                class="fw-bold text-danger">*</small></label>
                                        <input type="text" class="form-control" id="invoiceInput"
                                            placeholder="Enter Invoice Number" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Amount Paid<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="amountPaid" placeholder="0.00"
                                            min="0" step="0.01" required>
                                    </div>
                                </div>

                                <div class="mb-3" id="referenceNumberGroup" style="display: none;">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="referenceNumber"
                                        placeholder="Bank transfer reference number">
                                </div>

                                <!-- Summary Display -->
                                <div class="bg-light rounded p-3 mb-3">
                                    <p class="mb-1"><strong>Reference No.:</strong> <span id="refNo"></span></p>
                                    <p class="mb-1"><strong>Name:</strong> <span id="residentName"></span></p>
                                    <p class="mb-1"><strong>Issue Date:</strong> <span id="issueDate"></span></p>
                                    <p class="mb-1"><strong>Payment Method:</strong> <span id="selectedMethod">Bank
                                            Transfer</span></p>
                                </div>

                                <!-- Invoice Details Table -->
                                <table class="table table-bordered">
                                    <thead class="table-success">
                                        <tr>
                                            <th>Category</th>
                                            <th>Item</th>
                                            <th>Rate</th>
                                            <th>Qty</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoiceTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No invoice data loaded</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="d-flex justify-content-end mb-3">
                                    <div>
                                        <p class="mb-1"><strong>Subtotal:</strong> <span id="subtotal">₱0.00</span></p>
                                        <p class="mb-1"><strong>Previously Paid:</strong> <span
                                                id="previouslyPaid">₱0.00</span></p>
                                        <p class="fw-bold text-success">Balance Due: <span id="balanceDue">₱0.00</span>
                                        </p>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Make Payment</button>
                            </form>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-4">
                            <!-- Payment Methods Info -->
                            <div class="card border mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold">PAYMENT METHODS</h6>
                                    <p class="mb-1"><strong>Bank Transfer Details</strong></p>
                                    <ul class="mb-3">
                                        <li><strong>Bank:</strong> EastWest Bank</li>
                                        <li><strong>Account Name:</strong> Neopolitan Sitio Seville</li>
                                        <li><strong>Account Number:</strong> 200049887271</li>
                                    </ul>
                                    <p class="mb-1"><strong>In-Office Payment</strong></p>
                                    <ul>
                                        <li><strong>Address:</strong> NSSHAI Clubhouse Narra St., Quezon City</li>
                                        <li><strong>Office Hours:</strong> Mon–Fri, 8AM–5PM</li>
                                        <li><strong>Accepted:</strong> Cash</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Upload Section -->
                            <div class="border rounded p-3 text-center">
                                <h6 class="fw-bold">Upload Proof of Payment</h6>
                                <div class="file-drop-area" id="fileDropArea" style="height: 250px;">
                                    <div class="cloud-icon">
                                        <i class="bi bi-cloud-upload"></i>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Supported formats: JPEG, PNG, GIF, PDF
                                    </div>
                                    <input type="file" id="fileInput" name="evidence" class="d-none"
                                        accept="image/jpeg,image/png,image/gif,application/pdf">
                                </div>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="confirmPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Confirm Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Are you sure?</b></p>
                    <p class="mb-3">Do you want to process this payment?</p>
                    <div class="bg-light rounded p-3 mb-3 text-start">
                        <div class="row">
                            <div class="col-6"><strong>Name:</strong></div>
                            <div class="col-6" id="confirmName"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Category:</strong></div>
                            <div class="col-6" id="confirmCategory"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Invoice:</strong></div>
                            <div class="col-6" id="confirmInvoice"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Amount:</strong></div>
                            <div class="col-6" id="confirmAmount"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Method:</strong></div>
                            <div class="col-6" id="confirmMethod"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn">Process Payment</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Success Modal -->
    <div class="modal fade" id="successPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Payment Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Processed Successfully!</b></p>
                    <p class="mb-3">The payment has been recorded in the system.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Error Modal -->
    <div class="modal fade" id="errorPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Payment Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Error!</b></p>
                    <p class="mb-3" id="errorMessage">An error occurred while processing your payment.</p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Error Modal -->
    <div class="modal fade" id="errorPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Payment Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Error!</b></p>
                    <p class="mb-3" id="errorMessage">An error occurred while processing your payment.</p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/payment.js"></script>
</body>

</html>
