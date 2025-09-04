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

// Handle AJAX requests for dynamic dropdowns
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_households') {
        try {
            $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name FROM household_accounts WHERE status = 'active' ORDER BY household_id");
            $stmt->execute();
            $result = $stmt->get_result();

            $households = [];
            while ($row = $result->fetch_assoc()) {
                $households[] = [
                    'household_id' => $row['household_id'],
                    'name' => $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'],
                ];
            }

            echo json_encode(['success' => true, 'data' => $households]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'get_visitors') {
        try {
            $stmt = $conn->prepare("SELECT visitor_id, first_name, middle_name, last_name FROM visitor_details WHERE status = 'active' ORDER BY visitor_id");
            $stmt->execute();
            $result = $stmt->get_result();

            $visitors = [];
            while ($row = $result->fetch_assoc()) {
                $visitors[] = [
                    'visitor_id' => $row['visitor_id'],
                    'name' => $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'],
                ];
            }

            echo json_encode(['success' => true, 'data' => $visitors]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'get_amenity_booking_by_invoice') {
        if (
            !isset($_GET['invoice_number']) || empty($_GET['invoice_number']) ||
            !isset($_GET['user_id']) || empty($_GET['user_id']) ||
            !isset($_GET['user_type']) || empty($_GET['user_type'])
        ) {
            echo json_encode(['success' => false, 'error' => 'Invoice number, User ID and User Type are required']);
            exit;
        }

        $invoice_number = $_GET['invoice_number'];
        $user_id = $_GET['user_id'];
        $user_type = $_GET['user_type'];

        try {
            $user_data = null;
            $booking = null;

            if ($user_type === 'Homeowner/Resident') {
                $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM household_accounts WHERE household_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();

                $stmt = $conn->prepare("
                SELECT reference_number, created_at 
                FROM amenity_bookings 
                WHERE homeowner_id = ? AND invoice_number = ?
                LIMIT 1
            ");
                $stmt->bind_param("ss", $user_id, $invoice_number);
                $stmt->execute();
                $result = $stmt->get_result();
                $booking = $result->fetch_assoc();

            } else if ($user_type === 'Visitor') {
                $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM visitor_details WHERE visitor_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();

                $stmt = $conn->prepare("
                SELECT reference_number, created_at 
                FROM amenity_bookings 
                WHERE visitor_id = ? AND invoice_number = ?
                LIMIT 1
            ");
                $stmt->bind_param("ss", $user_id, $invoice_number);
                $stmt->execute();
                $result = $stmt->get_result();
                $booking = $result->fetch_assoc();
            }

            if ($booking && $user_data) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'reference_number' => $booking['reference_number'],
                        'first_name' => $user_data['first_name'],
                        'middle_name' => $user_data['middle_name'],
                        'last_name' => $user_data['last_name'],
                        'created_at' => $booking['created_at']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No amenity booking found with that invoice']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        exit;
    }
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

        .method-card {
            cursor: pointer;
            transition: 0.3s;
        }

        .method-card.active {
            border: 2px solid #007bff;
            background-color: #e9f2ff;
        }

        .method-card:hover {
            border-color: #007bff;
        }

        /* Loading indicator styles */
        .loading {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-select:disabled {
            background-color: #f8f9fa;
            opacity: 0.8;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                            <li><a href="announcements.php" class="nav-link px-2 ">Announcements</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2 actived">Payments</a></li>
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
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-4">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Payments</h4>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Payment Management</span>
                    </div>
                    <hr class="mb-3 mt-0">
                    <div class="row">
                        <!-- Left Side -->
                        <div class="col-md-8">
                            <!-- Payment Method Toggle -->
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
                            <!-- Form -->
                            <form>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">User Type<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userTypeSelect">
                                            <option value="" selected disabled>Select User Type</option>
                                            <option value="Homeowner/Resident">Homeowner/Resident</option>
                                            <option value="Visitor">Visitor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" id="idLabel">Select ID<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userIdSelect" disabled>
                                            <option value="" selected disabled>First select user type</option>
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
                                        <select class="form-select" id="categorySelect">
                                            <option value="" selected disabled>Select Category</option>
                                            <option value="Monthly Dues">Monthly Dues</option>
                                            <option value="Amenity Fee">Amenity Fee</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Invoice Number<small
                                                class="fw-bold text-danger">*</small></label>
                                        <input type="text" class="form-control" placeholder="Enter Invoice Number">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount Paid<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="amountPaid" name="amountPaid"
                                            placeholder="0.00" min="0" step="0.25" required>
                                    </div>
                                </div>
                                <div class="bg-light rounded p-3 mb-3">
                                    <p class="mb-1"><strong>Reference No.:</strong> <span id="refNo"></span></p>
                                    <p class="mb-1"><strong>Name:</strong> <span id="residentName"></span></p>
                                    <p class="mb-1"><strong>Issue Date:</strong> <span id="issueDate"></span></p>
                                    <p class="mb-1"><strong>Payment Method:</strong> <span id="selectedMethod">Bank
                                            Transfer</span></p>
                                </div>
                                <!-- Table -->
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
                                    <tbody>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="d-flex justify-content-end">
                                    <div>
                                        <p class="mb-1"><strong>Subtotal:</strong> </p>
                                        <p class="mb-1"><strong>Previously Paid:</strong></p>
                                        <p class="fw-bold text-success">Balance Due:</p>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Make Payment</button>
                            </form>
                        </div>
                        <!-- Right Side -->
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
                            <!-- Upload Proof -->
                            <div class="border rounded p-3 text-center">
                                <h6 class="fw-bold">Upload Proof of Payment</h6>
                                <!-- File Upload -->
                                <div class="file-drop-area" id="fileDropArea" style="height: 250px;">
                                    <div class="cloud-icon">
                                        <i class="bi bi-cloud-upload"></i>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Supported formats: JPEG, PNG, GIF, PDF, TXT, XLS, AI, Word, PPT
                                    </div>
                                    <input type="file" id="fileInput" name="evidence" class="d-none"
                                        accept="JPEG, PNG, GIF, MP4, PDF, DOC, DOCX" required>
                                </div>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Payment Method
        const bankTransfer = document.getElementById('bankTransfer');
        const inOffice = document.getElementById('inOffice');
        const selectedMethod = document.getElementById('selectedMethod');

        bankTransfer.addEventListener('click', () => {
            bankTransfer.classList.add('active');
            inOffice.classList.remove('active');
            selectedMethod.textContent = "Bank Transfer";
        });

        inOffice.addEventListener('click', () => {
            inOffice.classList.add('active');
            bankTransfer.classList.remove('active');
            selectedMethod.textContent = "In-Office Payment";
        });

        // Dynamic Dropdown Functionality
        const userTypeSelect = document.getElementById('userTypeSelect');
        const userIdSelect = document.getElementById('userIdSelect');
        const idLabel = document.getElementById('idLabel');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const categorySelect = document.getElementById('categorySelect');
        const monthlyOption = [...categorySelect.options].find(opt => opt.value === "Monthly Dues");

        // Elements for displaying details
        const refNo = document.getElementById('refNo');
        const residentName = document.getElementById('residentName');
        const issueDate = document.getElementById('issueDate');

        const invoiceInput = document.querySelector('input[placeholder="Enter Invoice Number"]');

        invoiceInput.addEventListener('blur', async function () {
            const invoiceNumber = this.value.trim();
            const selectedCategory = categorySelect.value;
            const userId = userIdSelect.value;
            const userType = userTypeSelect.value;


            // Reset fields
            refNo.textContent = "";
            residentName.textContent = "";
            issueDate.textContent = "";

            // Only fetch details if category is Amenity Fee and all required fields are filled
            if (selectedCategory === "Amenity Fee" && invoiceNumber && userId && userType) {
                try {
                    const response = await fetch(`?action=get_amenity_booking_by_invoice&invoice_number=${encodeURIComponent(invoiceNumber)}&user_id=${userId}&user_type=${encodeURIComponent(userType)}`);
                    const result = await response.json();

                    if (result.success) {
                        const data = result.data;
                        refNo.textContent = data.reference_number;
                        residentName.textContent = `${data.first_name} ${data.middle_name} ${data.last_name}`;
                        issueDate.textContent = new Date(data.created_at).toLocaleDateString();

                        // Optionally show success feedback
                        this.style.borderColor = '#198754';
                        this.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
                    } else {
                        console.error(result.error);
                        // Show error feedback
                        refNo.textContent = "Invoice not found";
                        residentName.textContent = "";
                        issueDate.textContent = "";

                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                    }
                } catch (error) {
                    console.error('Error fetching amenity booking:', error);
                    refNo.textContent = "Error loading";
                    residentName.textContent = "";
                    issueDate.textContent = "";

                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                }
            }
        });
        invoiceInput.addEventListener('input', function () {
            this.style.borderColor = '#dee2e6';
            this.style.boxShadow = 'none';
        });

        userTypeSelect.addEventListener('change', async function () {
            const selectedType = this.value;

            // Hide/Show Monthly Dues option
            if (selectedType === 'Visitor') {
                monthlyOption.style.display = "none";
                if (categorySelect.value === "Monthly Dues") {
                    categorySelect.value = ""; // reset to default
                }
            } else if (selectedType === 'Homeowner/Resident') {
                monthlyOption.style.display = "block";
            } else {
                categorySelect.value = ""; // reset if no user type
            }

            // Reset the ID dropdown
            userIdSelect.innerHTML = '<option value="">Loading...</option>';
            userIdSelect.disabled = true;
            loadingIndicator.classList.remove('d-none');

            // Fetch data based on user type
            if (selectedType === 'Homeowner/Resident') {
                idLabel.innerHTML = 'Resident ID<span class="text-danger">*</span>';
                try {
                    const response = await fetch(`?action=get_households`);
                    const result = await response.json();
                    if (result.success) {
                        userIdSelect.innerHTML = '<option value="" selected disabled>Select Resident ID</option>';
                        result.data.forEach(household => {
                            const option = document.createElement('option');
                            option.value = household.household_id;
                            option.textContent = `${household.household_id} - ${household.name}`;
                            userIdSelect.appendChild(option);
                        });
                    } else {
                        userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                        console.error('Error:', result.error);
                    }
                } catch (error) {
                    console.error('Error fetching household data:', error);
                    userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                }
            } else if (selectedType === 'Visitor') {
                idLabel.innerHTML = 'Visitor ID<span class="text-danger">*</span>';
                try {
                    const response = await fetch(`?action=get_visitors`);
                    const result = await response.json();
                    if (result.success) {
                        userIdSelect.innerHTML = '<option value="" selected disabled>Select Visitor ID</option>';
                        result.data.forEach(visitor => {
                            const option = document.createElement('option');
                            option.value = visitor.visitor_id;
                            option.textContent = `${visitor.visitor_id} - ${visitor.name}`;
                            userIdSelect.appendChild(option);
                        });
                    } else {
                        userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                        console.error('Error:', result.error);
                    }
                } catch (error) {
                    console.error('Error fetching visitor data:', error);
                    userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                }
            } else {
                idLabel.innerHTML = 'Select ID<span class="text-danger">*</span>';
                userIdSelect.innerHTML = '<option value="">First select user type</option>';
            }

            loadingIndicator.classList.add('d-none');
            userIdSelect.disabled = false;
            userIdSelect.classList.add('fade-in');
            setTimeout(() => {
                userIdSelect.classList.remove('fade-in');
            }, 300);
        });

        // Add visual feedback for ID selection
        userIdSelect.addEventListener('change', function () {
            if (this.value) {
                this.style.borderColor = '#198754';
                this.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
            } else {
                this.style.borderColor = '#dee2e6';
                this.style.boxShadow = 'none';
            }
        });

        // Clear all form fields
        function clearFormFields() {
            // Reset dropdowns
            document.getElementById('userTypeSelect').value = "";
            document.getElementById('userIdSelect').innerHTML = '<option value="" selected disabled>First select user type</option>';
            document.getElementById('userIdSelect').disabled = true;
            document.getElementById('categorySelect').value = "";

            // Reset text/number inputs
            document.querySelector('input[placeholder="Enter Invoice Number"]').value = "";
            document.getElementById('amountPaid').value = "";

            // Reset display fields
            document.getElementById('refNo').textContent = "";
            document.getElementById('residentName').textContent = "";
            document.getElementById('issueDate').textContent = "";

            // Reset input borders
            const invoiceInput = document.querySelector('input[placeholder="Enter Invoice Number"]');
            invoiceInput.style.borderColor = '#dee2e6';
            invoiceInput.style.boxShadow = 'none';

            const userIdSelect = document.getElementById('userIdSelect');
            userIdSelect.style.borderColor = '#dee2e6';
            userIdSelect.style.boxShadow = 'none';

            // ✅ Reset file upload input and preview
            const proofInput = document.getElementById('proofOfPayment');
            const previewArea = document.getElementById('imagePreview');
            if (proofInput) proofInput.value = ""; // clears file input
            if (previewArea) previewArea.innerHTML = ""; // clears preview
        }

        // Toggle Payment Method
        bankTransfer.addEventListener('click', () => {
            bankTransfer.classList.add('active');
            inOffice.classList.remove('active');
            selectedMethod.textContent = "Bank Transfer";
            clearFormFields(); // ✅ Now resets
        });

        inOffice.addEventListener('click', () => {
            inOffice.classList.add('active');
            bankTransfer.classList.remove('active');
            selectedMethod.textContent = "In-Office Payment";
            clearFormFields(); // ✅ Now resets
        });


    </script>

</body>

</html>