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

require '../../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
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
$username = $admin['first_name'];
$photo = '';
// Only set $photo if profile_pic exists and is not null
if (!empty($admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']);
} else {
    $photo = '';
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet">
    <link rel="icon" href="../../images/SitioSeville_Logo.png" type="image/x-icon">
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

        .field-hidden {
            display: none;
        }

        .bulk-option {
            background-color: #e8f5e8;
            border: 1px solid #198754;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        /* Select2 custom styling */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }

        /* Searchable Dropdown Styles */
        .search-select-wrapper {
            position: relative;
        }

        .search-select-wrapper input[type="text"] {
            padding-right: 35px;
        }

        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            display: none;
            z-index: 3;
        }

        .search-clear.show {
            display: block;
        }

        .search-clear:hover {
            color: #dc3545;
        }

        .search-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .search-select-dropdown.show {
            display: block;
        }

        .search-select-option {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .search-select-option:last-child {
            border-bottom: none;
        }

        .search-select-option:hover {
            background-color: #f8f9fa;
        }

        .search-select-option.selected {
            background-color: #e9f2ff;
            font-weight: 500;
        }

        .search-select-option.no-results {
            color: #6c757d;
            text-align: center;
            cursor: default;
        }

        .search-select-option.no-results:hover {
            background-color: white;
        }

        .search-select-dropdown::-webkit-scrollbar {
            width: 8px;
        }

        .search-select-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .search-select-dropdown::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .search-select-dropdown::-webkit-scrollbar-thumb:hover {
            background: #555;
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

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            .sidebar-overlay {
                top: 0;
            }

            .search-select-dropdown {
                max-height: 200px;
                font-size: 0.85rem;
            }

            .search-select-option {
                padding: 8px 12px;
            }

            .search-clear {
                font-size: 14px;
                right: 8px;
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

            .search-select-dropdown {
                max-height: 180px;
                font-size: 0.8rem;
            }

            .search-select-option {
                padding: 7px 10px;
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
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
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
                    <li><a class="dropdown-item" href="../admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../login/logout.php"><i
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
                <a href="../admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Accounts -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
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
                            <li><a href="../amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="../violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="../entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
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
                            <li><a href="../announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="../events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="../phonebook.php" class="nav-link px-2">Phone Book</a></li>
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
                            <li><a href="../payment.php" class="nav-link px-2">Payment</a></li>
                            <li><a href="../billing.php" class="nav-link px-2 actived">List of Billings</a></li>
                            <li><a href="../invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-grow-1 p-4">
            <div class="bg-white shadow rounded p-3">
                <!-- Header -->
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">New Invoice</h5>
                </div>
                <!-- Back Button -->
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">Fill out the form below to issue a new invoice</span>
                    <a href="../billing.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <!-- Form -->
                <form id="invoiceForm" class="p-4">
                    <div class="row g-3">
                        <!-- Category Selection -->
                        <div class="col-md-6">
                            <label for="category" class="form-label mb-2 fw-semibold">Billing Category <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="" selected disabled>Select Category</option>
                                <option value="monthly_dues">Monthly Dues</option>
                                <option value="penalty_fees">Penalty Fees</option>
                                <option value="other_fees">Other Fees</option>
                            </select>
                            <small class="text-muted mt-2">Choose the type of fee to bill</small>
                        </div>
                        <!-- Bulk Selection Option (Only for Monthly Dues) -->
                        <div class="col-md-6 field-hidden" id="bulkOptionContainer">
                            <div class="bulk-option">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bulkInvoice" name="bulkInvoice">
                                    <label class="form-check-label fw-semibold" for="bulkInvoice">
                                        <i class="bi bi-people-fill me-2"></i>Create invoices for all households
                                    </label>
                                    <div class="small text-muted mt-1">Check this to automatically generate monthly dues
                                        invoices for all registered households</div>
                                </div>
                            </div>
                        </div>
                        <!-- Household ID (Hidden when bulk is selected) -->
                        <div class="col-md-6" id="householdContainer">
                            <label for="household_id" class="form-label mb-2 fw-semibold">Household <span
                                    class="text-danger">*</span></label>
                            <div class="search-select-wrapper">
                                <input type="text" class="form-control" id="householdSearch"
                                    placeholder="Search household..." disabled>
                                <input type="hidden" id="household_id" name="household_id">
                                <button type="button" class="search-clear" id="householdSearchClear">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                                <div class="search-select-dropdown" id="householdDropdown"></div>
                            </div>
                            <small class="text-muted mt-2">Select the household to bill</small>
                        </div>
                        <!-- Billing Month (Only for Monthly Dues) -->
                        <div class="col-md-6 field-hidden" id="billingMonthContainer">
                            <label for="billing_month" class="form-label mb-2 fw-semibold">Billing Month <span
                                    class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="billing_month" name="billing_month">
                            <small class="text-muted mt-2">Month for which dues are being charged</small>
                        </div>
                        <!-- Description (Only for Penalty and Other Fees) -->
                        <div class="col-md-12 field-hidden" id="descriptionContainer">
                            <label for="description" class="form-label mb-2 fw-semibold">Description <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Enter details about the fee..."></textarea>
                            <small class="text-muted mt-2">Provide specific details about this charge</small>
                        </div>
                        <!-- Balance Remaining -->
                        <div class="col-md-6">
                            <label for="balance_remaining" class="form-label mb-2 fw-semibold">Amount to be Paid <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="balance_remaining"
                                    name="balance_remaining" min="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <!-- Due Date -->
                        <div class="col-md-6">
                            <label for="due_date" class="form-label mb-2 fw-semibold">Due Date <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                            <small class="text-muted mt-2" id="dueDateHint">Will be set automatically for monthly dues
                                (28th
                                of billing month)</small>
                        </div>
                    </div>
                    <!-- Submit -->
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="bi bi-save me-1"></i> <span id="submitBtnText">Save Invoice</span>
                        </button>
                    </div>
                </form>
                <!-- Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold">Confirm Invoice Creation</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                <p class="mb-2"><b>Are you sure?</b></p>
                                <p class="mb-3" id="confirmMessage">Do you really want to create this invoice?</p>
                                <button type="button" class="btn btn-success" id="confirmPublish">Yes, Create
                                    Invoice</button>
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Success Modal -->
                <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold">Success!</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                <p class="mt-3 mb-2"><b>Invoice(s) created successfully!</b></p>
                                <p class="mb-3" id="successMessage">The invoice has been created and email notification
                                    sent.</p>
                                <button type="button" class="btn btn-success" id="okButton">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Invoice Error Modal -->
                <div class="modal fade" id="errorInvoiceModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Invoice Error</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                                <p class="mt-3 mb-2"><b>Cannot Create Invoice!</b></p>
                                <p class="mb-3" id="invoiceErrorMessage">An error occurred while creating the invoice.
                                </p>
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- General Error Modal -->
                <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title fw-bold">Error</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-x-circle-fill text-danger" style="font-size: 64px;"></i>
                                <p class="mt-3 mb-2"><b>Something went wrong.</b></p>
                                <p id="errorMessage">Please try again later.</p>
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../javascripts/mobileSidebar.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById('invoiceForm');
            const categorySelect = document.getElementById('category');
            const bulkCheckbox = document.getElementById('bulkInvoice');
            const householdHiddenInput = document.getElementById('household_id');
            const billingMonthInput = document.getElementById('billing_month');
            const descriptionInput = document.getElementById('description');
            const dueDateInput = document.getElementById('due_date');

            // Searchable household elements
            const householdSearch = document.getElementById('householdSearch');
            const householdDropdown = document.getElementById('householdDropdown');
            const householdSearchClear = document.getElementById('householdSearchClear');
            let householdOptions = [];
            let selectedHouseholdId = null;

            // Containers
            const bulkOptionContainer = document.getElementById('bulkOptionContainer');
            const householdContainer = document.getElementById('householdContainer');
            const billingMonthContainer = document.getElementById('billingMonthContainer');
            const descriptionContainer = document.getElementById('descriptionContainer');

            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const errorInvoiceModal = new bootstrap.Modal(document.getElementById('errorInvoiceModal'));
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            const confirmPublishBtn = document.getElementById('confirmPublish');
            const submitBtn = document.getElementById('submitBtn');
            const submitBtnText = document.getElementById('submitBtnText');
            const confirmMessage = document.getElementById('confirmMessage');
            const dueDateHint = document.getElementById('dueDateHint');

            // ============================================
            // LOAD HOUSEHOLDS FOR SEARCHABLE DROPDOWN
            // ============================================
            function loadHouseholds() {
                householdOptions = [];
                <?php
                $households = $conn->query("SELECT household_id, CONCAT(first_name, ' ', last_name) AS name FROM household_accounts ORDER BY household_id");
                echo "householdOptions = [";
                $first = true;
                while ($row = $households->fetch_assoc()) {
                    if (!$first)
                        echo ",";
                    echo "{id: '{$row['household_id']}', name: '" . addslashes($row['name']) . "'}";
                    $first = false;
                }
                echo "];";
                ?>

                if (householdOptions.length > 0) {
                    householdSearch.disabled = false;
                    householdSearch.placeholder = 'Search household...';
                }
            }

            // ============================================
            // SEARCHABLE HOUSEHOLD DROPDOWN FUNCTIONALITY
            // ============================================
            function initSearchableHousehold() {
                householdSearch.addEventListener('focus', function () {
                    if (householdOptions.length > 0) {
                        renderHouseholdOptions();
                        householdDropdown.classList.add('show');
                    }
                });

                householdSearch.addEventListener('input', function () {
                    const searchTerm = this.value;
                    if (searchTerm) {
                        householdSearchClear.classList.add('show');
                    } else {
                        householdSearchClear.classList.remove('show');
                    }
                    renderHouseholdOptions(searchTerm);
                    householdDropdown.classList.add('show');
                });

                householdSearchClear.addEventListener('click', function () {
                    householdSearch.value = '';
                    householdHiddenInput.value = '';
                    selectedHouseholdId = null;
                    householdSearchClear.classList.remove('show');
                    householdSearch.classList.remove('border-danger', 'is-invalid');
                    householdSearch.style.borderColor = '';
                    householdSearch.style.boxShadow = '';
                    renderHouseholdOptions();
                    householdSearch.focus();
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!householdSearch.contains(e.target) && !householdDropdown.contains(e.target)) {
                        householdDropdown.classList.remove('show');
                    }
                });
            }

            function renderHouseholdOptions(searchTerm = '') {
                householdDropdown.innerHTML = '';

                const filteredOptions = householdOptions.filter(option => {
                    const searchText = `${option.id} ${option.name}`.toLowerCase();
                    return searchText.includes(searchTerm.toLowerCase());
                });

                if (filteredOptions.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'search-select-option no-results';
                    noResults.innerHTML = '<i class="bi bi-search"></i> No households found';
                    householdDropdown.appendChild(noResults);
                    return;
                }

                filteredOptions.forEach(option => {
                    const optionDiv = document.createElement('div');
                    optionDiv.className = 'search-select-option';
                    optionDiv.textContent = `${option.id} - ${option.name}`;
                    optionDiv.dataset.value = option.id;

                    if (option.id === selectedHouseholdId) {
                        optionDiv.classList.add('selected');
                    }

                    optionDiv.addEventListener('click', () => selectHousehold(option));
                    householdDropdown.appendChild(optionDiv);
                });
            }

            function selectHousehold(option) {
                selectedHouseholdId = option.id;
                householdSearch.value = `${option.id} - ${option.name}`;
                householdHiddenInput.value = option.id;
                householdSearchClear.classList.add('show');
                householdDropdown.classList.remove('show');

                // Remove error styling
                householdSearch.classList.remove('border-danger', 'is-invalid');
                householdSearch.style.borderColor = '#198754';
                householdSearch.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
            }

            function resetHouseholdSearch() {
                householdSearch.value = '';
                householdHiddenInput.value = '';
                selectedHouseholdId = null;
                householdSearchClear.classList.remove('show');
                householdSearch.classList.remove('border-danger', 'is-invalid');
                householdSearch.style.borderColor = '';
                householdSearch.style.boxShadow = '';
                householdDropdown.innerHTML = '';
            }

            // Initialize on page load
            loadHouseholds();
            initSearchableHousehold();

            // ============================================
            // CATEGORY CHANGE HANDLER
            // ============================================
            categorySelect.addEventListener('change', function () {
                const category = this.value;

                // Reset all fields
                bulkCheckbox.checked = false;
                resetHouseholdSearch();
                billingMonthInput.value = '';
                descriptionInput.value = '';
                dueDateInput.value = '';

                // Hide all conditional fields
                bulkOptionContainer.classList.add('field-hidden');
                billingMonthContainer.classList.add('field-hidden');
                descriptionContainer.classList.add('field-hidden');
                householdContainer.classList.remove('field-hidden');

                // Enable household search
                householdSearch.disabled = false;

                if (category === 'monthly_dues') {
                    bulkOptionContainer.classList.remove('field-hidden');
                    billingMonthContainer.classList.remove('field-hidden');
                    dueDateInput.setAttribute('readonly', 'readonly');
                    dueDateInput.style.backgroundColor = '#e9ecef';
                    dueDateInput.style.cursor = 'not-allowed';
                    dueDateHint.style.display = 'block';
                    updatePaymentDate();
                    submitBtnText.textContent = 'Save Invoice';

                } else if (category === 'penalty_fees' || category === 'other_fees') {
                    descriptionContainer.classList.remove('field-hidden');
                    dueDateInput.removeAttribute('readonly');
                    dueDateInput.style.backgroundColor = '';
                    dueDateInput.style.cursor = '';
                    dueDateHint.style.display = 'none';
                    submitBtnText.textContent = 'Save Invoice';
                }
            });

            // ============================================
            // BULK CHECKBOX HANDLER
            // ============================================
            bulkCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    householdContainer.classList.add('field-hidden');
                    resetHouseholdSearch();
                    householdSearch.disabled = true;
                    submitBtnText.textContent = 'Create Bulk Invoices';
                } else {
                    householdContainer.classList.remove('field-hidden');
                    householdSearch.disabled = false;
                    submitBtnText.textContent = 'Save Invoice';
                }
            });

            // ============================================
            // FORM SUBMISSION HANDLER
            // ============================================
            form.addEventListener("submit", function (e) {
                e.preventDefault();

                const category = categorySelect.value;
                const isBulk = bulkCheckbox.checked;

                // Validate based on category
                let valid = true;
                let errorMsg = '';

                if (!category) {
                    errorMsg = 'Please select a billing category.';
                    valid = false;
                } else if (category === 'monthly_dues') {
                    if (!isBulk && !householdHiddenInput.value) {
                        errorMsg = 'Please select a household.';
                        householdSearch.classList.add('border-danger', 'is-invalid');
                        valid = false;
                    }
                    if (!billingMonthInput.value) {
                        errorMsg = 'Please select a billing month.';
                        valid = false;
                    }
                } else {
                    if (!householdHiddenInput.value) {
                        errorMsg = 'Please select a household.';
                        householdSearch.classList.add('border-danger', 'is-invalid');
                        valid = false;
                    }
                    if (!descriptionInput.value.trim()) {
                        errorMsg = 'Please provide a description.';
                        valid = false;
                    }
                }

                if (!dueDateInput.value) {
                    errorMsg = 'Please select a due date.';
                    valid = false;
                }

                if (!valid) {
                    alert(errorMsg);
                    return;
                }

                // Set confirmation message
                if (isBulk) {
                    confirmMessage.textContent = 'This will create invoices for ALL households. Continue?';
                } else {
                    confirmMessage.textContent = 'Do you really want to create this invoice?';
                }

                confirmModal.show();
            });

            // ============================================
            // HANDLE CONFIRMATION - SUBMIT VIA AJAX
            // ============================================
            confirmPublishBtn.addEventListener("click", function () {
                confirmModal.hide();

                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Creating...';
                submitBtn.disabled = true;

                const formData = new FormData(form);

                fetch('process_add_billing.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> <span id="submitBtnText">Save Invoice</span>';
                        submitBtn.disabled = false;

                        if (data.success) {
                            document.getElementById('successMessage').innerHTML = data.message || 'Invoice created successfully!';
                            successModal.show();
                        } else {
                            if (data.error_type === 'validation') {
                                document.getElementById('invoiceErrorMessage').textContent = data.error;
                                errorInvoiceModal.show();
                            } else {
                                document.getElementById('errorMessage').textContent = data.error || 'An unexpected error occurred.';
                                errorModal.show();
                            }
                        }
                    })
                    .catch(error => {
                        submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> <span id="submitBtnText">Save Invoice</span>';
                        submitBtn.disabled = false;

                        console.error('Error:', error);
                        document.getElementById('errorMessage').textContent = 'Network error: ' + error.message;
                        errorModal.show();
                    });
            });

            // Redirect to invoice list on success
            document.getElementById('okButton').addEventListener('click', function () {
                window.location.href = '../billing.php';
            });

            // ============================================
            // BILLING MONTH AND DUE DATE LOGIC
            // ============================================
            function updatePaymentDate() {
                if (billingMonthInput.value && categorySelect.value === 'monthly_dues') {
                    const [year, month] = billingMonthInput.value.split('-');
                    let dueDate = new Date(year, month - 1, 28);

                    const year_str = dueDate.getFullYear();
                    const month_str = String(dueDate.getMonth() + 1).padStart(2, '0');
                    const day_str = String(dueDate.getDate()).padStart(2, '0');
                    const dueDateStr = `${year_str}-${month_str}-${day_str}`;

                    dueDateInput.value = dueDateStr;
                }
            }

            // Set minimum date to current month
            const today = new Date();
            const currentYear = today.getFullYear();
            const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
            const minMonth = `${currentYear}-${currentMonth}`;
            billingMonthInput.setAttribute('min', minMonth);

            billingMonthInput.addEventListener('change', updatePaymentDate);

            // Set minimum due date to today
            const todayStr = today.toISOString().split('T')[0];
            dueDateInput.setAttribute('min', todayStr);
        });
    </script>

</body>

</html>